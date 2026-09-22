<script setup lang="ts">
import { Check, Clock, Trophy } from '@lucide/vue';
import { computed } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import WeekPhaseBadge from '@/components/WeekPhaseBadge.vue';
import GameMatchup from '@/components/board/GameMatchup.vue';
import PickGrid from '@/components/board/PickGrid.vue';
import StandingsList from '@/components/board/StandingsList.vue';
import { kickoff, ordinal, points, relative } from '@/lib/format';
import type { WeekBoard } from '@/types';

const props = defineProps<{ board: WeekBoard }>();

const submittedCount = computed(
    () => props.board.participants.filter((p) => p.submitted).length,
);
const isClosed = computed(() => props.board.week.phase === 'closed');
const gridRows = computed(
    () => props.board.standings?.filter((row) => row.submitted) ?? [],
);
const podium = computed(() =>
    gridRows.value.filter(
        (row) => row.placement !== null && row.placement <= 3,
    ),
);
</script>

<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                >
                    {{ board.week.season_year }} season
                </p>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold sm:text-3xl">
                        Week {{ board.week.number }}
                    </h1>
                    <WeekPhaseBadge :phase="board.week.phase" />
                </div>
            </div>
            <slot name="actions" />
        </div>

        <slot name="notice" />

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">Players in</div>
                <div class="text-2xl font-bold">
                    {{ board.participants.length }}
                </div>
            </div>
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">Submitted</div>
                <div class="text-2xl font-bold">
                    {{ submittedCount }}/{{ board.participants.length }}
                </div>
            </div>
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">Games</div>
                <div class="text-2xl font-bold">{{ board.games.length }}</div>
            </div>
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <div class="text-xs text-muted-foreground">
                    {{ board.week.is_locked ? 'Locked' : 'Picks lock' }}
                </div>
                <div class="text-xs font-semibold sm:text-sm">
                    {{ kickoff(board.week.locks_at) }}
                </div>
                <div
                    v-if="!board.week.is_locked"
                    class="text-xs text-muted-foreground"
                >
                    {{ relative(board.week.locks_at) }}
                </div>
            </div>
        </div>

        <div
            v-if="isClosed && podium.length"
            class="rounded-lg border border-green-300 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/40"
        >
            <div
                class="mb-3 flex items-center gap-2 text-sm font-semibold text-green-800 dark:text-green-100"
            >
                <Trophy class="size-4" /> Podium
            </div>
            <div class="flex flex-wrap gap-x-8 gap-y-3">
                <div
                    v-for="row in podium"
                    :key="row.entry_id"
                    class="flex min-w-0 items-center gap-2"
                >
                    <span
                        class="w-8 shrink-0 text-sm font-bold text-green-800 tabular-nums dark:text-green-100"
                        >{{ ordinal(row.placement!) }}</span
                    >
                    <UserAvatar
                        v-bind="row.user"
                        size-class="size-10 text-xs"
                    />
                    <div class="min-w-0 leading-tight">
                        <div class="truncate font-semibold">
                            {{ row.user.name }}
                        </div>
                        <div class="text-sm text-muted-foreground">
                            {{ row.correct }} correct ·
                            {{ points(row.points) }} pts
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Before the lock: who's in, and the slate. No picks are shown. -->
        <template v-if="!board.standings">
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <h2 class="mb-1 font-semibold">Who's in</h2>
                <p class="mb-4 text-sm text-muted-foreground">
                    Everyone's picks appear here when the first game kicks off.
                </p>
                <div
                    v-if="board.participants.length"
                    class="grid grid-cols-[repeat(auto-fill,minmax(5.5rem,1fr))] gap-4"
                >
                    <div
                        v-for="p in board.participants"
                        :key="p.user.id"
                        class="flex flex-col items-center gap-1 text-center"
                    >
                        <div class="relative">
                            <UserAvatar
                                v-bind="p.user"
                                size-class="size-12 text-sm"
                                :class="{
                                    'opacity-40 grayscale': !p.submitted,
                                }"
                            />
                            <span
                                class="absolute -right-1 -bottom-1 flex size-5 items-center justify-center rounded-full border-2 border-card"
                                :class="
                                    p.submitted
                                        ? 'bg-green-500 text-white'
                                        : 'bg-gray-300 text-gray-700'
                                "
                            >
                                <Check v-if="p.submitted" class="size-3" />
                                <Clock v-else class="size-3" />
                            </span>
                        </div>
                        <span class="w-full truncate text-xs">{{
                            p.user.name
                        }}</span>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">Nobody yet.</p>
            </div>

            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <h2 class="mb-4 font-semibold">This week's games</h2>
                <div
                    class="grid grid-cols-[repeat(auto-fill,minmax(9rem,1fr))] gap-2 sm:gap-4"
                >
                    <div
                        v-for="game in board.games"
                        :key="game.id"
                        class="rounded-md border p-3"
                    >
                        <GameMatchup :game="game" />
                    </div>
                </div>
            </div>
        </template>

        <!-- After the lock: the master spreadsheet. -->
        <template v-else>
            <PickGrid
                v-if="gridRows.length"
                :games="board.games"
                :rows="gridRows"
            />
            <StandingsList
                :rows="board.standings"
                :tiebreaker-total="board.tiebreaker_total"
                :is-final="isClosed"
            />
        </template>
    </div>
</template>
