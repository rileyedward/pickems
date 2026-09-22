<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import UserAvatar from '@/components/UserAvatar.vue';
import { show } from '@/routes/users';

defineProps<{
    users: {
        id: number;
        name: string;
        photo: string | null;
        full_name: string;
        is_active: boolean;
        weeks_played: number;
    }[];
}>();
</script>

<template>
    <Head title="Players" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-4">
        <h1 class="text-3xl font-bold">Players</h1>

        <div class="grid grid-cols-[repeat(auto-fill,minmax(12rem,1fr))] gap-3">
            <Link
                v-for="user in users"
                :key="user.id"
                :href="show(user.id)"
                class="flex items-center gap-3 rounded-lg border bg-card p-3 hover:bg-muted/60"
                :class="{ 'opacity-60': !user.is_active }"
            >
                <UserAvatar
                    :id="user.id"
                    :name="user.name"
                    :photo="user.photo"
                    size-class="size-12 text-sm"
                />
                <div class="min-w-0 leading-tight">
                    <div class="truncate font-semibold">{{ user.name }}</div>
                    <div
                        v-if="user.name !== user.full_name"
                        class="truncate text-xs text-muted-foreground"
                    >
                        {{ user.full_name }}
                    </div>
                    <div class="text-xs text-muted-foreground">
                        {{ user.weeks_played }}
                        {{ user.weeks_played === 1 ? 'week' : 'weeks' }}
                        played
                    </div>
                </div>
            </Link>
        </div>
        <p
            v-if="users.length === 0"
            class="rounded-lg border bg-card px-4 py-8 text-center text-muted-foreground"
        >
            Nobody's playing yet.
        </p>
    </div>
</template>
