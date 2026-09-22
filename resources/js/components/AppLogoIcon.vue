<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- A football: body, seam and laces. -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        fill="none"
        :class="className"
        v-bind="$attrs"
    >
        <path
            fill="currentColor"
            d="M27.6 4.4c-4.9-1.3-12.3-.5-17.3 4.5S4.1 21.4 5.4 26.3c.1.5.6 1 1.1 1.1 1 .3 2.2.4 3.4.4 4.6 0 10.1-1.6 13.9-5.4 5-5 5.8-12.4 4.5-17.3-.2-.3-.5-.6-.7-.7Z"
        />
        <path
            stroke="var(--color-background, #fff)"
            stroke-width="1.6"
            stroke-linecap="round"
            d="M12.5 19.5l7-7M14 15.4l2.6 2.6M16.2 13.2l2.6 2.6M11.8 17.6l2.6 2.6"
        />
    </svg>
</template>
