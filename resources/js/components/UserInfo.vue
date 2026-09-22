<script setup lang="ts">
import UserAvatar from '@/components/UserAvatar.vue';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

withDefaults(defineProps<Props>(), {
    showEmail: false,
});
</script>

<template>
    <UserAvatar
        :id="user.id"
        :name="user.display_name"
        :photo="user.photo_url"
        size-class="size-8 text-[11px]"
    />

    <div class="grid flex-1 text-left text-sm leading-tight">
        <span class="truncate font-medium">{{ user.display_name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
