<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { KeyRound, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    destroy,
    index,
    password as setPassword,
    store,
    update,
} from '@/routes/admin/users';

type User = {
    id: number;
    name: string;
    nickname: string | null;
    email: string;
    display_name: string;
    photo: string | null;
    is_active: boolean;
    is_admin: boolean;
    entries_count: number;
    weeks_played: number;
};

const props = defineProps<{ users: User[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const viewerId = usePage().props.auth.user.id;

const search = ref('');
const filter = ref<'all' | 'active' | 'inactive'>('all');

const visibleUsers = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.users.filter(
        (u) =>
            (filter.value === 'all' ||
                (filter.value === 'active') === u.is_active) &&
            (!term ||
                [u.name, u.nickname ?? '', u.email].some((v) =>
                    v.toLowerCase().includes(term),
                )),
    );
});

const activeCount = computed(
    () => props.users.filter((u) => u.is_active).length,
);

function toggle(user: User, field: 'is_active' | 'is_admin', value: boolean) {
    router.post(
        update(user.id).url,
        { [field]: value },
        { preserveScroll: true },
    );
}

// Create / edit dialog
const editing = ref<User | null>(null);
const dialogOpen = ref(false);
const form = useForm({
    name: '',
    nickname: '' as string | null,
    email: '',
    password: '',
    password_confirmation: '',
    photo: null as File | null,
    remove_photo: false,
});

function openDialog(user: User | null) {
    editing.value = user;
    form.clearErrors();
    form.name = user?.name ?? '';
    form.nickname = user?.nickname ?? '';
    form.email = user?.email ?? '';
    form.password = '';
    form.password_confirmation = '';
    form.photo = null;
    form.remove_photo = false;
    dialogOpen.value = true;
}

function saveUser() {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => (dialogOpen.value = false),
    };

    if (editing.value) {
        form.transform(
            ({ password, password_confirmation, ...data }) => data,
        ).post(update(editing.value.id).url, options);
    } else {
        form.transform((data) => data).post(store().url, options);
    }
}

// Password reset dialog
const resetting = ref<User | null>(null);
const passwordForm = useForm({ password: '', password_confirmation: '' });

function openPasswordDialog(user: User) {
    resetting.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
}

function savePassword() {
    if (!resetting.value) {
        return;
    }

    passwordForm.put(setPassword(resetting.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (resetting.value = null),
    });
}

function removeUser(user: User) {
    if (confirm(`Delete ${user.name}'s account?`)) {
        router.delete(destroy(user.id).url, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Users" />

    <div class="space-y-6 p-3 sm:p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Users"
                :description="`${activeCount} active of ${users.length}. Active users are entered automatically when a week opens.`"
            />
            <Button @click="openDialog(null)"><Plus /> Create user</Button>
        </div>

        <div class="flex flex-wrap gap-2">
            <Input
                v-model="search"
                placeholder="Search name or email"
                class="max-w-xs"
            />
            <div class="flex rounded-md border p-0.5">
                <Button
                    v-for="option in ['all', 'active', 'inactive'] as const"
                    :key="option"
                    size="sm"
                    :variant="filter === option ? 'secondary' : 'ghost'"
                    class="capitalize"
                    @click="filter = option"
                    >{{ option }}</Button
                >
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead
                    class="bg-muted/60 text-left text-xs tracking-wider text-muted-foreground uppercase"
                >
                    <tr>
                        <th class="px-4 py-2">User</th>
                        <th class="px-4 py-2">Email</th>
                        <th class="px-4 py-2 text-center">Weeks played</th>
                        <th class="px-4 py-2 text-center">Active</th>
                        <th class="px-4 py-2 text-center">Admin</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="user in visibleUsers"
                        :key="user.id"
                        class="border-t"
                    >
                        <td class="px-4 py-2">
                            <div class="flex items-center gap-3">
                                <UserAvatar
                                    :id="user.id"
                                    :name="user.name"
                                    :photo="user.photo"
                                    size-class="size-9 text-xs"
                                />
                                <div class="leading-tight">
                                    <div class="font-medium">
                                        {{ user.name }}
                                        <span
                                            v-if="user.id === viewerId"
                                            class="text-xs font-normal text-muted-foreground"
                                            >(you)</span
                                        >
                                    </div>
                                    <div
                                        v-if="user.nickname"
                                        class="text-xs text-muted-foreground"
                                    >
                                        “{{ user.nickname }}”
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td
                            class="max-w-[10rem] truncate px-4 py-2 text-muted-foreground sm:max-w-none"
                            :title="user.email"
                        >
                            {{ user.email }}
                        </td>
                        <td class="px-4 py-2 text-center tabular-nums">
                            {{ user.weeks_played }}
                        </td>
                        <td class="px-4 py-2 text-center">
                            <Switch
                                :model-value="user.is_active"
                                :aria-label="`${user.name} active`"
                                @update:model-value="
                                    toggle(user, 'is_active', $event)
                                "
                            />
                        </td>
                        <td class="px-4 py-2 text-center">
                            <Switch
                                :model-value="user.is_admin"
                                :aria-label="`${user.name} admin`"
                                @update:model-value="
                                    toggle(user, 'is_admin', $event)
                                "
                            />
                        </td>
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Edit ${user.name}`"
                                title="Edit"
                                @click="openDialog(user)"
                                ><Pencil
                            /></Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                :aria-label="`Set a new password for ${user.name}`"
                                title="Set a new password"
                                @click="openPasswordDialog(user)"
                                ><KeyRound
                            /></Button>
                            <Button
                                v-if="
                                    user.entries_count === 0 &&
                                    user.id !== viewerId
                                "
                                variant="ghost"
                                size="icon"
                                :aria-label="`Delete ${user.name}`"
                                title="Delete"
                                @click="removeUser(user)"
                                ><Trash2
                            /></Button>
                        </td>
                    </tr>
                    <tr v-if="visibleUsers.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-10 text-center text-muted-foreground"
                        >
                            {{
                                users.length
                                    ? 'No users match.'
                                    : 'Nobody has registered yet.'
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog v-model:open="dialogOpen">
        <DialogContent class="max-h-[90dvh] overflow-y-auto">
            <form class="space-y-4" @submit.prevent="saveUser">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? `Edit ${editing.name}` : 'Create user'
                    }}</DialogTitle>
                    <DialogDescription>
                        {{
                            editing
                                ? 'The nickname, if set, is shown on the board instead of the name.'
                                : "For a friend who won't register themselves. The account starts active."
                        }}
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-1.5">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="nickname">Nickname</Label>
                    <Input
                        id="nickname"
                        v-model="form.nickname as string"
                        placeholder="Optional"
                    />
                    <InputError :message="form.errors.nickname" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <template v-if="!editing">
                    <div class="grid gap-1.5">
                        <Label for="password">Password</Label>
                        <PasswordInput
                            id="password"
                            v-model="form.password"
                            autocomplete="new-password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="password_confirmation"
                            >Confirm password</Label
                        >
                        <PasswordInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            autocomplete="new-password"
                        />
                    </div>
                </template>
                <div class="grid gap-1.5">
                    <Label for="photo">Photo</Label>
                    <div class="flex items-center gap-3">
                        <UserAvatar
                            v-if="editing"
                            :id="editing.id"
                            :name="editing.name"
                            :photo="form.remove_photo ? null : editing.photo"
                            size-class="size-10 text-xs"
                        />
                        <Input
                            id="photo"
                            type="file"
                            accept="image/*"
                            @input="
                                form.photo =
                                    ($event.target as HTMLInputElement)
                                        .files?.[0] ?? null
                            "
                        />
                    </div>
                    <label
                        v-if="editing?.photo"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <input v-model="form.remove_photo" type="checkbox" />
                        Remove current photo
                    </label>
                    <InputError :message="form.errors.photo" />
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="form.processing">
                        Save
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="resetting !== null"
        @update:open="(value) => !value && (resetting = null)"
    >
        <DialogContent v-if="resetting">
            <form class="space-y-4" @submit.prevent="savePassword">
                <DialogHeader>
                    <DialogTitle
                        >New password for {{ resetting.name }}</DialogTitle
                    >
                    <DialogDescription>
                        There's no email reset, so share the new password with
                        them directly. They can change it in their settings.
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-1.5">
                    <Label for="new_password">New password</Label>
                    <PasswordInput
                        id="new_password"
                        v-model="passwordForm.password"
                        autocomplete="new-password"
                    />
                    <InputError :message="passwordForm.errors.password" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="new_password_confirmation"
                        >Confirm password</Label
                    >
                    <PasswordInput
                        id="new_password_confirmation"
                        v-model="passwordForm.password_confirmation"
                        autocomplete="new-password"
                    />
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="passwordForm.processing">
                        Set password
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
