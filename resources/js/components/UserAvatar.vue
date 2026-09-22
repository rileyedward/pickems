<script setup lang="ts">
import { computed } from 'vue';
import { avatarColor, initials } from '@/lib/avatar';

const props = withDefaults(
    defineProps<{
        // Web path to the photo. Null/empty → initials fallback.
        photo?: string | null;
        // Name shown as the alt text and used for the initials fallback.
        name: string;
        // Stable id for the fallback color, so a user's color never changes.
        id?: number | null;
        // Tailwind size + text classes for the circle.
        sizeClass?: string;
    }>(),
    {
        photo: null,
        id: null,
        sizeClass: 'size-10 text-xs',
    },
);

const fallbackColor = computed(() => avatarColor(props.id));
const fallbackInitials = computed(() => initials(props.name));
</script>

<template>
    <img
        v-if="photo"
        :src="photo"
        :alt="name"
        :title="name"
        :class="sizeClass"
        class="shrink-0 rounded-full bg-muted object-cover"
    />
    <div
        v-else
        :title="name"
        :class="sizeClass"
        class="flex shrink-0 items-center justify-center rounded-full font-semibold text-white"
        :style="{ backgroundColor: fallbackColor }"
    >
        {{ fallbackInitials }}
    </div>
</template>
