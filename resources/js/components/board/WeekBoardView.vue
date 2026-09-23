<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Check, Clock, Trophy } from '@lucide/vue';
import { computed, ref } from 'vue';
import UserAvatar from '@/components/UserAvatar.vue';
import WeekPhaseBadge from '@/components/WeekPhaseBadge.vue';
import GameMatchup from '@/components/board/GameMatchup.vue';
import PickGrid from '@/components/board/PickGrid.vue';
import PicksList from '@/components/board/PicksList.vue';
import StandingsList from '@/components/board/StandingsList.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { kickoff, ordinal, points, relative } from '@/lib/format';
import type { WeekBoard } from '@/types';

type Participant = WeekBoard['participants'][number];

const props = defineProps<{ board: WeekBoard }>();

const page = usePage();
const myId = computed(() => page.props.auth.user?.id ?? null);

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

// Who's in → picks modal.
const viewing = ref<Participant | null>(null);

// Picks section: you first (even before you've submitted), then everyone
// else who has submitted.
const me = computed(
    () =>
        props.board.participants.find((p) => p.user.id === myId.value) ?? null,
);
const pickers = computed(() => [
    ...(me.value ? [me.value] : []),
    ...props.board.participants.filter(
        (p) => p.submitted && p.user.id !== myId.value,
    ),
]);
const selectedId = ref<number | null>(null);
const selected = computed(
    () =>
        pickers.value.find((p) => p.user.id === selectedId.value) ??
        pickers.value[0] ??
        null,
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

        <!-- Before the lock: who's in (tap a submitted player to see their picks), and the slate. -->
        <template v-if="!board.standings">
            <div class="rounded-lg border bg-card p-3 sm:p-4">
                <h2 class="mb-1 font-semibold">Who's in</h2>
                <p class="mb-4 text-sm text-muted-foreground">
                    Tap a player who's submitted to see their picks.
                </p>
                <div
                    v-if="board.participants.length"
                    class="grid grid-cols-[repeat(auto-fill,minmax(5.5rem,1fr))] gap-4"
                >
                    <component
                        :is="p.submitted ? 'button' : 'div'"
                        v-for="p in board.participants"
                        :key="p.user.id"
                        :type="p.submitted ? 'button' : undefined"
                        :title="
                            p.submitted
                                ? `View ${p.user.name}'s picks`
                                : undefined
                        "
                        class="flex flex-col items-center gap-1 rounded-md text-center"
                        :class="{
                            'cursor-pointer transition hover:opacity-80':
                                p.submitted,
                        }"
                        @click="p.submitted && (viewing = p)"
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
                    </component>
                </div>
                <p v-else class="text-sm text-muted-foreground">Nobody yet.</p>
            </div>

            <div
                v-if="pickers.length && selected"
                class="rounded-lg border bg-card p-3 sm:p-4"
            >
                <h2 class="mb-1 font-semibold">Picks</h2>
                <p class="mb-4 text-sm text-muted-foreground">
                    Picks can change until the lock.
                </p>
                <div
                    class="-mx-3 mb-4 flex gap-2 overflow-x-auto px-3 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0"
                    role="tablist"
                >
                    <button
                        v-for="p in pickers"
                        :key="p.user.id"
                        type="button"
                        role="tab"
                        :aria-selected="p.user.id === selected.user.id"
                        class="flex shrink-0 items-center gap-2 rounded-full border py-1 pr-3 pl-1 text-sm transition"
                        :class="
                            p.user.id === selected.user.id
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'hover:bg-muted/60'
                        "
                        @click="selectedId = p.user.id"
                    >
                        <UserAvatar
                            v-bind="p.user"
                            size-class="size-7 text-[10px]"
                        />
                        {{ p.user.id === myId ? 'You' : p.user.name }}
                    </button>
                </div>
                <template v-if="selected.submitted">
                    <p class="mb-2 text-sm text-muted-foreground">
                        Tie-breaker guess: {{ selected.tiebreaker_guess }} ·
                        submitted {{ kickoff(selected.submitted_at) }}
                    </p>
                    <PicksList
                        :games="board.games"
                        :picks="selected.picks"
                        class="sm:columns-2 sm:gap-8 lg:columns-3 [&>li]:break-inside-avoid"
                    />
                </template>
                <p v-else class="text-sm text-muted-foreground">
                    You haven't submitted your picks yet.
                </p>
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

        <!-- Picks viewer -->
        <Dialog
            :open="viewing !== null"
            @update:open="(value) => !value && (viewing = null)"
        >
            <DialogContent v-if="viewing" class="max-h-[90dvh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ viewing.user.name }}'s picks</DialogTitle>
                    <DialogDescription>
                        Tie-breaker guess: {{ viewing.tiebreaker_guess }} ·
                        submitted {{ kickoff(viewing.submitted_at) }}
                    </DialogDescription>
                </DialogHeader>
                <PicksList
                    :games="board.games"
                    :picks="viewing.picks"
                    class="max-h-[60vh] overflow-y-auto"
                />
            </DialogContent>
        </Dialog>
    </div>
</template>
