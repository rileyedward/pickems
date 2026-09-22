<script setup lang="ts">
import type { Team } from '@/types';

withDefaults(
    defineProps<{
        team: Team;
        sizeClass?: string;
    }>(),
    { sizeClass: 'size-8' },
);
</script>

<template>
    <img
        :src="team.logo"
        :alt="team.display_name"
        :title="team.display_name"
        :class="sizeClass"
        class="shrink-0 object-contain"
        loading="lazy"
    />
</template>
