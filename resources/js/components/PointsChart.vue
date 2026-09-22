<script setup lang="ts">
import { onClickOutside, useMediaQuery } from '@vueuse/core';
import { computed, ref, useTemplateRef } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { points } from '@/lib/format';
import type { LeaderboardRow } from '@/types';

/**
 * Cumulative season points, one line per user. Dependency-free inline SVG.
 *
 * Colors follow the user, not their rank: slots are handed out by user id in
 * a fixed order, so a line keeps its color as the standings move. There are
 * eight validated slots; anyone past that is drawn in neutral ink and is
 * still identifiable by hovering or through the legend.
 *
 * On touch screens a tap does what hovering does, and sticks until you tap
 * outside the chart.
 */
const props = defineProps<{
    weeks: number[];
    rows: LeaderboardRow[];
}>();

const SLOTS = 8;

// Narrow screens get a narrower canvas so the labels don't shrink to nothing.
const compact = useMediaQuery('(max-width: 640px)');
const width = computed(() => (compact.value ? 360 : 640));
const height = 260;
const pad = { top: 12, right: 16, bottom: 28, left: 36 };
const plotWidth = computed(() => width.value - pad.left - pad.right);
const plotHeight = height - pad.top - pad.bottom;

// Thin out the week labels so they never overlap.
const labelEvery = computed(() =>
    Math.max(1, Math.ceil(props.weeks.length / (compact.value ? 6 : 12))),
);

function showWeekLabel(index: number): boolean {
    return index % labelEvery.value === 0 || index === props.weeks.length - 1;
}

const hoveredUser = ref<number | null>(null);
const hoveredWeek = ref<number | null>(null);

const slotByUser = computed(() => {
    const ids = props.rows.map((row) => row.user.id).sort((a, b) => a - b);

    return new Map(ids.map((id, index) => [id, index]));
});

function seriesColor(userId: number): string {
    const slot = slotByUser.value.get(userId) ?? SLOTS;

    return slot < SLOTS ? `var(--series-${slot + 1})` : 'var(--series-other)';
}

const maxPoints = computed(() =>
    Math.max(1, ...props.rows.flatMap((row) => row.cumulative)),
);

// Round the top of the axis up to a tidy step so gridlines land on whole numbers.
const yTicks = computed(() => {
    const rough = maxPoints.value / 4;
    const magnitude = 10 ** Math.floor(Math.log10(rough));
    const step =
        [1, 2, 2.5, 5, 10].map((m) => m * magnitude).find((s) => s >= rough) ??
        rough;
    const top = Math.ceil(maxPoints.value / step) * step;

    return Array.from(
        { length: Math.round(top / step) + 1 },
        (_, i) => i * step,
    );
});

const yMax = computed(() => yTicks.value[yTicks.value.length - 1] || 1);

function x(index: number): number {
    return props.weeks.length <= 1
        ? pad.left + plotWidth.value / 2
        : pad.left + (index / (props.weeks.length - 1)) * plotWidth.value;
}

function y(value: number): number {
    return pad.top + plotHeight - (value / yMax.value) * plotHeight;
}

function path(row: LeaderboardRow): string {
    return row.cumulative
        .map((value, i) => `${i === 0 ? 'M' : 'L'}${x(i)},${y(value)}`)
        .join(' ');
}

// Draw the hovered line last so it sits on top.
const orderedRows = computed(() =>
    [...props.rows].sort(
        (a, b) =>
            Number(a.user.id === hoveredUser.value) -
            Number(b.user.id === hoveredUser.value),
    ),
);

function lineOpacity(userId: number): number {
    return hoveredUser.value === null || hoveredUser.value === userId
        ? 1
        : 0.15;
}

const tooltipRows = computed(() => {
    const index = hoveredWeek.value;

    if (index === null) {
        return [];
    }

    return props.rows
        .map((row) => ({ user: row.user, value: row.cumulative[index] ?? 0 }))
        .sort((a, b) => b.value - a.value);
});

function onPointerMove(event: PointerEvent) {
    const svg = event.currentTarget as SVGSVGElement;
    const rect = svg.getBoundingClientRect();
    const svgX = ((event.clientX - rect.left) / rect.width) * width.value;

    if (props.weeks.length <= 1) {
        hoveredWeek.value = 0;

        return;
    }

    const index = Math.round(
        ((svgX - pad.left) / plotWidth.value) * (props.weeks.length - 1),
    );

    hoveredWeek.value = Math.min(Math.max(index, 0), props.weeks.length - 1);
}

const tooltipLeft = computed(() =>
    hoveredWeek.value === null ? 0 : (x(hoveredWeek.value) / width.value) * 100,
);

// A finger "leaves" the moment it lifts, so only a mouse clears on leave.
function onPointerLeave(event: PointerEvent, clear: () => void) {
    if (event.pointerType === 'mouse') {
        clear();
    }
}

function toggleUser(userId: number) {
    hoveredUser.value = hoveredUser.value === userId ? null : userId;
}

onClickOutside(useTemplateRef('chart'), () => {
    hoveredWeek.value = null;
    hoveredUser.value = null;
});
</script>

<template>
    <div ref="chart" class="points-chart space-y-3">
        <div class="relative">
            <svg
                :viewBox="`0 0 ${width} ${height}`"
                class="h-auto w-full touch-pan-y select-none"
                role="img"
                aria-label="Cumulative season points by week"
                @pointerdown="onPointerMove"
                @pointermove="onPointerMove"
                @pointerleave="
                    onPointerLeave($event, () => (hoveredWeek = null))
                "
            >
                <g>
                    <template v-for="tick in yTicks" :key="tick">
                        <line
                            :x1="pad.left"
                            :x2="width - pad.right"
                            :y1="y(tick)"
                            :y2="y(tick)"
                            stroke="var(--chart-grid)"
                            stroke-width="1"
                        />
                        <text
                            :x="pad.left - 8"
                            :y="y(tick)"
                            text-anchor="end"
                            dominant-baseline="middle"
                            class="fill-muted-foreground text-[11px] tabular-nums"
                        >
                            {{ points(tick) }}
                        </text>
                    </template>
                    <template v-for="(week, i) in weeks" :key="week">
                        <text
                            v-if="showWeekLabel(i)"
                            :x="x(i)"
                            :y="height - 8"
                            text-anchor="middle"
                            class="fill-muted-foreground text-[11px]"
                        >
                            W{{ week }}
                        </text>
                    </template>
                </g>

                <line
                    v-if="hoveredWeek !== null"
                    :x1="x(hoveredWeek)"
                    :x2="x(hoveredWeek)"
                    :y1="pad.top"
                    :y2="pad.top + plotHeight"
                    stroke="var(--chart-crosshair)"
                    stroke-width="1"
                />

                <g
                    v-for="row in orderedRows"
                    :key="row.user.id"
                    :opacity="lineOpacity(row.user.id)"
                    class="transition-opacity"
                    @pointerenter="hoveredUser = row.user.id"
                    @pointerleave="
                        onPointerLeave($event, () => (hoveredUser = null))
                    "
                >
                    <!-- Wide invisible stroke: a hit target bigger than the line. -->
                    <path
                        :d="path(row)"
                        fill="none"
                        stroke="transparent"
                        stroke-width="12"
                    />
                    <path
                        :d="path(row)"
                        fill="none"
                        :stroke="seriesColor(row.user.id)"
                        stroke-width="2"
                        stroke-linejoin="round"
                        stroke-linecap="round"
                    />
                    <circle
                        v-for="(value, i) in row.cumulative"
                        :key="i"
                        :cx="x(i)"
                        :cy="y(value)"
                        :r="hoveredWeek === i ? 4.5 : 3"
                        :fill="seriesColor(row.user.id)"
                        stroke="var(--chart-surface)"
                        stroke-width="2"
                    />
                </g>
            </svg>

            <div
                v-if="hoveredWeek !== null && tooltipRows.length"
                class="pointer-events-none absolute top-2 z-10 w-44 rounded-md border bg-popover p-2 text-xs text-popover-foreground shadow-md"
                :style="{
                    left: `${tooltipLeft}%`,
                    transform:
                        tooltipLeft > 60
                            ? 'translateX(calc(-100% - 12px))'
                            : 'translateX(12px)',
                }"
            >
                <div class="mb-1 font-semibold">
                    After week {{ weeks[hoveredWeek] }}
                </div>
                <div
                    v-for="item in tooltipRows"
                    :key="item.user.id"
                    class="flex items-center gap-2 py-0.5"
                    :class="{
                        'font-semibold': item.user.id === hoveredUser,
                    }"
                >
                    <span
                        class="size-2 shrink-0 rounded-full"
                        :style="{ backgroundColor: seriesColor(item.user.id) }"
                    />
                    <span class="min-w-0 flex-1 truncate">{{
                        item.user.name
                    }}</span>
                    <span class="tabular-nums">{{ points(item.value) }}</span>
                </div>
            </div>
        </div>

        <ul class="flex flex-wrap gap-x-4 gap-y-2" aria-label="Legend">
            <li
                v-for="row in rows"
                :key="row.user.id"
                class="flex cursor-pointer items-center gap-1.5 py-1 text-xs text-muted-foreground transition-opacity"
                :style="{ opacity: lineOpacity(row.user.id) === 1 ? 1 : 0.5 }"
                @pointerenter="
                    $event.pointerType === 'mouse' &&
                    (hoveredUser = row.user.id)
                "
                @pointerleave="
                    onPointerLeave($event, () => (hoveredUser = null))
                "
                @click="toggleUser(row.user.id)"
            >
                <span
                    class="h-0.5 w-3 rounded-full"
                    :style="{ backgroundColor: seriesColor(row.user.id) }"
                />
                <UserAvatar v-bind="row.user" size-class="size-5 text-[8px]" />
                <span class="text-foreground">{{ row.user.name }}</span>
            </li>
        </ul>
    </div>
</template>

<style scoped>
.points-chart {
    --chart-surface: var(--card);
    --chart-grid: color-mix(in oklab, var(--foreground) 8%, transparent);
    --chart-crosshair: color-mix(in oklab, var(--foreground) 35%, transparent);
    --series-1: #2a78d6;
    --series-2: #eb6834;
    --series-3: #1baf7a;
    --series-4: #eda100;
    --series-5: #e87ba4;
    --series-6: #008300;
    --series-7: #4a3aa7;
    --series-8: #e34948;
    --series-other: #8a8985;
}

:global(.dark) .points-chart {
    --series-1: #3987e5;
    --series-2: #d95926;
    --series-3: #199e70;
    --series-4: #c98500;
    --series-5: #d55181;
    --series-6: #008300;
    --series-7: #9085e9;
    --series-8: #e66767;
    --series-other: #8a8985;
}
</style>
