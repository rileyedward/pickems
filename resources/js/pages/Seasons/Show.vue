<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import PointsChart from '@/components/PointsChart.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import WeekPhaseBadge from '@/components/WeekPhaseBadge.vue';
import { ordinal, points } from '@/lib/format';
import { show as seasonShow } from '@/routes/seasons';
import { show as userShow } from '@/routes/users';
import { show as weekShow } from '@/routes/weeks';
import type { LeaderboardRow, UserAvatar as Avatar, WeekPhase } from '@/types';

defineProps<{
    season: { id: number; year: number; points_table: number[] };
    seasons: number[];
    weeks: {
        number: number;
        phase: WeekPhase;
        entry_count: number;
        podium: (Avatar & {
            placement: number;
            points: number;
            correct: number;
        })[];
    }[];
    leaderboard: LeaderboardRow[];
    chartWeeks: number[];
}>();

const viewerId = usePage().props.auth.user.id;

function switchSeason(event: Event) {
    router.visit(seasonShow(Number((event.target as HTMLSelectElement).value)));
}
</script>

<template>
    <Head :title="`${season.year} season`" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-3 sm:p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                >
                    Season standings
                </p>
                <h1 class="text-2xl font-bold sm:text-3xl">
                    {{ season.year }} season
                </h1>
            </div>
            <select
                v-if="seasons.length > 1"
                :value="season.year"
                class="h-10 rounded-md border bg-background px-3 text-base sm:h-9 sm:text-sm"
                aria-label="Season"
                @change="switchSeason"
            >
                <option v-for="year in seasons" :key="year" :value="year">
                    {{ year }}
                </option>
            </select>
        </div>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
            <div class="rounded-lg border bg-card">
                <h2 class="border-b px-4 py-3 font-semibold">Leaderboard</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="text-xs tracking-wider text-muted-foreground uppercase"
                        >
                            <tr class="border-b">
                                <th
                                    class="w-8 px-2 py-2 text-right sm:w-10 sm:px-4"
                                >
                                    #
                                </th>
                                <th class="px-2 py-2 text-left">Player</th>
                                <th class="px-2 py-2 text-right">Pts</th>
                                <th
                                    class="px-2 py-2 text-right"
                                    title="Weeks won"
                                >
                                    W
                                </th>
                                <th
                                    class="px-3 py-2 text-right sm:px-4"
                                    title="Correct picks / weeks played"
                                >
                                    Correct
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in leaderboard"
                                :key="row.user.id"
                                class="border-b last:border-b-0"
                                :class="{
                                    'bg-primary/5': row.user.id === viewerId,
                                }"
                            >
                                <td
                                    class="px-2 py-2 text-right font-semibold text-muted-foreground tabular-nums sm:px-4"
                                >
                                    {{ row.rank }}
                                </td>
                                <td class="px-2 py-2">
                                    <Link
                                        :href="userShow(row.user.id)"
                                        class="flex min-w-0 items-center gap-2 hover:underline"
                                    >
                                        <UserAvatar
                                            v-bind="row.user"
                                            size-class="size-7 shrink-0 text-[10px]"
                                        />
                                        <span
                                            class="max-w-[8rem] truncate sm:max-w-none"
                                            >{{ row.user.name }}</span
                                        >
                                    </Link>
                                </td>
                                <td
                                    class="px-2 py-2 text-right font-bold tabular-nums"
                                >
                                    {{ points(row.total_points) }}
                                </td>
                                <td class="px-2 py-2 text-right tabular-nums">
                                    {{ row.weeks_won }}
                                </td>
                                <td
                                    class="px-3 py-2 text-right whitespace-nowrap text-muted-foreground tabular-nums sm:px-4"
                                >
                                    {{ row.total_correct }}/{{
                                        row.weeks_played
                                    }}w
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-if="leaderboard.length === 0"
                    class="px-4 py-6 text-center text-sm text-muted-foreground"
                >
                    Standings appear once a week is final.
                </p>
                <p class="border-t px-4 py-2 text-xs text-muted-foreground">
                    Points per week:
                    <span class="tabular-nums">{{
                        season.points_table
                            .map((p, i) => `${ordinal(i + 1)} ${p}`)
                            .join(' · ')
                    }}</span
                    >. Ties split the points.
                </p>
            </div>

            <div class="rounded-lg border bg-card">
                <h2 class="border-b px-4 py-3 font-semibold">
                    Points over the season
                </h2>
                <div class="p-3 sm:p-4">
                    <PointsChart
                        v-if="chartWeeks.length"
                        :weeks="chartWeeks"
                        :rows="leaderboard"
                    />
                    <p
                        v-else
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        The chart fills in as weeks close.
                    </p>
                </div>
            </div>
        </div>

        <div class="rounded-lg border bg-card">
            <h2 class="border-b px-4 py-3 font-semibold">Weeks</h2>
            <ul>
                <li
                    v-for="week in weeks"
                    :key="week.number"
                    class="border-b last:border-b-0"
                >
                    <Link
                        :href="
                            weekShow({
                                season: season.year,
                                week: week.number,
                            })
                        "
                        class="flex items-center gap-3 px-3 py-3 hover:bg-muted/60 sm:px-4"
                    >
                        <div
                            class="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:gap-3"
                        >
                            <div class="flex shrink-0 items-center gap-3">
                                <span class="w-20 font-semibold"
                                    >Week {{ week.number }}</span
                                >
                                <WeekPhaseBadge :phase="week.phase" />
                            </div>
                            <div
                                class="flex min-w-0 flex-1 flex-wrap items-center gap-x-4 gap-y-1"
                            >
                                <template v-if="week.podium.length">
                                    <div
                                        v-for="place in week.podium"
                                        :key="place.id"
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <span
                                            class="text-xs font-semibold text-muted-foreground"
                                            >{{
                                                ordinal(place.placement)
                                            }}</span
                                        >
                                        <UserAvatar
                                            v-bind="place"
                                            size-class="size-6 shrink-0 text-[9px]"
                                        />
                                        <span class="truncate text-sm">{{
                                            place.name
                                        }}</span>
                                        <span
                                            class="shrink-0 text-sm whitespace-nowrap text-muted-foreground"
                                            >{{
                                                points(place.points)
                                            }}
                                            pts</span
                                        >
                                    </div>
                                </template>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >{{ week.entry_count }} submitted</span
                                >
                            </div>
                        </div>
                        <ChevronRight
                            class="size-4 shrink-0 text-muted-foreground"
                        />
                    </Link>
                </li>
            </ul>
            <p
                v-if="weeks.length === 0"
                class="px-4 py-6 text-center text-sm text-muted-foreground"
            >
                No weeks played yet.
            </p>
        </div>
    </div>
</template>
