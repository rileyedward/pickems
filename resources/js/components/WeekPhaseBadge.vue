<script setup lang="ts">
import { computed } from 'vue';
import type { WeekPhase } from '@/types';

const props = defineProps<{ phase: WeekPhase }>();

const styles: Record<WeekPhase, { label: string; class: string }> = {
    draft: {
        label: 'Draft',
        class: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
    },
    open: {
        label: 'Picks open',
        class: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-100',
    },
    locked: {
        label: 'In play',
        class: 'bg-yellow-200 text-yellow-900 dark:bg-yellow-800 dark:text-yellow-100',
    },
    closed: {
        label: 'Final',
        class: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100',
    },
};

const style = computed(() => styles[props.phase]);
</script>

<template>
    <span
        :class="style.class"
        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wide whitespace-nowrap"
    >
        {{ style.label }}
    </span>
</template>
