<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import UserAvatar from '@/components/UserAvatar.vue';
import WeekPhaseBadge from '@/components/WeekPhaseBadge.vue';
import { ordinal, points } from '@/lib/format';
import { show } from '@/routes/users';
import { show as weekShow } from '@/routes/weeks';
import type { WeekPhase } from '@/types';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        photo: string | null;
        full_name: string;
        nickname: string | null;
        is_active: boolean;
    };
    season: number | null;
    seasons: number[];
    totals: {
        rank: number;
        total_points: number;
        weeks_won: number;
        total_correct: number;
        weeks_played: number;
        dnp_count: number;
    } | null;
    weeks: {
        number: number;
        phase: WeekPhase;
        submitted: boolean;
        placement: number | null;
        points: number | null;
        correct: number | null;
    }[];
}>();

function switchSeason(event: Event) {
    router.visit(
        show(props.user.id, {
            query: { season: (event.target as HTMLSelectElement).value },
        }),
    );
}
</script>

<template>
    <Head :title="user.name" />

    <div class="mx-auto w-full max-w-4xl space-y-6 p-3 sm:p-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                <UserAvatar
                    :id="user.id"
                    :name="user.name"
                    :photo="user.photo"
                    size-class="size-14 shrink-0 text-lg sm:size-20 sm:text-xl"
                />
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold break-words sm:text-3xl">
                        {{ user.full_name }}
                    </h1>
                    <p v-if="user.nickname" class="text-muted-foreground">
                        “{{ user.nickname }}”
                    </p>
                    <p
                        v-if="!user.is_active"
                        class="text-sm text-muted-foreground"
                    >
                        Not playing right now
                    </p>
                </div>
            </div>
            <select
                v-if="seasons.length > 1"
                :value="season ?? undefined"
                class="h-10 rounded-md border bg-background px-3 text-base sm:h-9 sm:text-sm"
                aria-label="Season"
                @change="switchSeason"
            >
                <option v-for="year in seasons" :key="year" :value="year">
                    {{ year }}
                </option>
            </select>
        </div>

        <div v-if="totals" class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">
                    {{ season }} rank
                </div>
                <div class="text-2xl font-bold">
                    {{ ordinal(totals.rank) }}
                </div>
            </div>
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">Points</div>
                <div class="text-2xl font-bold">
                    {{ points(totals.total_points) }}
                </div>
            </div>
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">Weeks won</div>
                <div class="text-2xl font-bold">{{ totals.weeks_won }}</div>
            </div>
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">Correct picks</div>
                <div class="text-2xl font-bold">
                    {{ totals.total_correct }}
                </div>
            </div>
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">Played</div>
                <div class="text-2xl font-bold">
                    {{ totals.weeks_played }}
                    <span
                        v-if="totals.dnp_count"
                        class="text-sm font-normal text-muted-foreground"
                        >· {{ totals.dnp_count }} DNP</span
                    >
                </div>
            </div>
        </div>

        <div class="rounded-lg border bg-card">
            <h2 class="border-b px-4 py-3 font-semibold">
                {{ season }} week by week
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead
                        class="text-xs tracking-wider text-muted-foreground uppercase"
                    >
                        <tr class="border-b">
                            <th class="px-3 py-2 text-left sm:px-4">Week</th>
                            <th class="px-2 py-2 text-right">Place</th>
                            <th class="px-2 py-2 text-right">Pts</th>
                            <th class="px-3 py-2 text-right sm:px-4">
                                Correct
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="week in weeks"
                            :key="week.number"
                            class="border-b last:border-b-0"
                        >
                            <td class="px-3 py-2 sm:px-4">
                                <Link
                                    v-if="season"
                                    :href="
                                        weekShow({
                                            season,
                                            week: week.number,
                                        })
                                    "
                                    class="flex flex-wrap items-center gap-x-3 gap-y-1 hover:underline"
                                >
                                    <span
                                        class="w-16 font-medium whitespace-nowrap"
                                        >Week {{ week.number }}</span
                                    >
                                    <WeekPhaseBadge :phase="week.phase" />
                                </Link>
                            </td>
                            <td class="px-2 py-2 text-right tabular-nums">
                                <template v-if="!week.submitted">
                                    <span
                                        v-if="week.phase === 'closed'"
                                        class="text-muted-foreground"
                                        >DNP</span
                                    >
                                    <span v-else class="text-muted-foreground"
                                        >—</span
                                    >
                                </template>
                                <template v-else>{{
                                    week.placement
                                        ? ordinal(week.placement)
                                        : '—'
                                }}</template>
                            </td>
                            <td
                                class="px-2 py-2 text-right font-semibold tabular-nums"
                            >
                                {{
                                    week.points === null
                                        ? '—'
                                        : points(week.points)
                                }}
                            </td>
                            <td
                                class="px-3 py-2 text-right tabular-nums sm:px-4"
                            >
                                {{ week.correct ?? '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="weeks.length === 0"
                class="px-4 py-6 text-center text-sm text-muted-foreground"
            >
                No weeks played this season.
            </p>
        </div>
    </div>
</template>
