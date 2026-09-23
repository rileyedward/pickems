<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Clock,
    ExternalLink,
    Eye,
    X,
    Lock,
    LockOpen,
    Pencil,
    Plus,
    RefreshCw,
    SquarePen,
    Trash2,
    Trophy,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TeamLogo from '@/components/TeamLogo.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import WeekPhaseBadge from '@/components/WeekPhaseBadge.vue';
import GameMatchup from '@/components/board/GameMatchup.vue';
import PicksForm from '@/components/picks/PicksForm.vue';
import type { PicksFormData } from '@/components/picks/PicksForm.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { kickoff, ordinal, points, relative, toLocalInput } from '@/lib/format';
import {
    destroy as entryDestroy,
    picks as entryPicks,
    store as entryStore,
} from '@/routes/admin/entries';
import {
    destroy as gameDestroy,
    store as gameStore,
    tiebreaker as gameTiebreaker,
    update as gameUpdate,
} from '@/routes/admin/games';
import {
    index as seasonsIndex,
    show as seasonShow,
} from '@/routes/admin/seasons';
import {
    close,
    lock,
    open,
    reopen,
    show as weekShow,
    sync,
} from '@/routes/admin/weeks';
import { show as publicWeek } from '@/routes/weeks';
import type {
    Game,
    GameStatus,
    UserAvatar as Avatar,
    Team,
    WeekSummary,
} from '@/types';

type Entry = {
    id: number;
    user: Avatar & { email: string };
    submitted_at: string | null;
    tiebreaker_guess: number | null;
    correct: number | null;
    placement: number | null;
    points: number | null;
    picks: Record<number, number>;
};

const props = defineProps<{
    week: WeekSummary & { season_id: number };
    games: (Game & { manual_override: boolean })[];
    entries: Entry[];
    summary: {
        all_games_final: boolean;
        tiebreaker_total: number | null;
    };
    availableUsers: { id: number; name: string; is_active: boolean }[];
    teams: Team[];
    adjacentWeeks: { previous: number | null; next: number | null };
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Seasons', href: seasonsIndex() },
        {
            title: String(props.week.season_year),
            href: seasonShow(props.week.season_id),
        },
        { title: `Week ${props.week.number}`, href: weekShow(props.week.id) },
    ],
});

const phase = computed(() => props.week.phase);
const submittedCount = computed(
    () => props.entries.filter((e) => e.submitted_at).length,
);
const hasTiebreaker = computed(() => props.games.some((g) => g.is_tiebreaker));

// Week lifecycle actions
const busy = ref<string | null>(null);

function act(name: string, url: string, confirmMessage?: string) {
    if (confirmMessage && !confirm(confirmMessage)) {
        return;
    }

    router.post(
        url,
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = name),
            onFinish: () => (busy.value = null),
        },
    );
}

// Entries
function removeEntry(entry: Entry) {
    if (confirm(`Remove ${entry.user.name} from this week?`)) {
        router.delete(entryDestroy(entry.id).url, { preserveScroll: true });
    }
}

const addUserId = ref<number | ''>('');

function addUser() {
    if (addUserId.value === '') {
        return;
    }

    router.post(
        entryStore(props.week.id).url,
        { user_id: addUserId.value },
        {
            preserveScroll: true,
            onSuccess: () => (addUserId.value = ''),
        },
    );
}

// Enter picks on someone's behalf (before the lock).
const enteringFor = ref<Entry | null>(null);
const picksForm = useForm<PicksFormData>({ picks: {}, tiebreaker_guess: '' });

function openPicksDialog(entry: Entry) {
    enteringFor.value = entry;
    picksForm.clearErrors();
    picksForm.picks = { ...entry.picks };
    picksForm.tiebreaker_guess = entry.tiebreaker_guess ?? '';
}

function savePicks() {
    if (!enteringFor.value) {
        return;
    }

    picksForm.put(entryPicks(enteringFor.value.id).url, {
        preserveScroll: true,
        onSuccess: () => (enteringFor.value = null),
    });
}

const viewingEntry = ref<Entry | null>(null);

function pickedTeam(game: Game, entry: Entry): Team | null {
    return (
        [game.home, game.away].find((t) => t.id === entry.picks[game.id]) ??
        null
    );
}

// Games
const gameDialogOpen = ref(false);
const editingGame = ref<Game | null>(null);
const gameForm = useForm({
    away_team_id: '' as number | '',
    home_team_id: '' as number | '',
    kickoff_at: '',
    away_score: null as number | null,
    home_score: null as number | null,
    status: 'scheduled' as GameStatus,
});

function openGameDialog(game: Game | null) {
    editingGame.value = game;
    gameForm.clearErrors();
    gameForm.away_team_id = game?.away.id ?? '';
    gameForm.home_team_id = game?.home.id ?? '';
    gameForm.kickoff_at = game ? toLocalInput(game.kickoff_at) : '';
    gameForm.away_score = game?.away_score ?? null;
    gameForm.home_score = game?.home_score ?? null;
    gameForm.status = game?.status ?? 'scheduled';
    gameDialogOpen.value = true;
}

function saveGame() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (gameDialogOpen.value = false),
    };

    if (editingGame.value) {
        gameForm.put(gameUpdate(editingGame.value.id).url, options);
    } else {
        gameForm.post(gameStore(props.week.id).url, options);
    }
}

function removeGame(game: Game) {
    if (
        confirm(`Remove ${game.away.abbreviation} @ ${game.home.abbreviation}?`)
    ) {
        router.delete(gameDestroy(game.id).url, { preserveScroll: true });
    }
}

function setTiebreaker(game: Game) {
    router.post(gameTiebreaker(game.id).url, {}, { preserveScroll: true });
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-base shadow-xs md:text-sm';
</script>

<template>
    <Head :title="`Week ${week.number} · ${week.season_year}`" />

    <div class="space-y-6 p-4">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <Button
                    variant="ghost"
                    size="icon"
                    :disabled="!adjacentWeeks.previous"
                    as-child
                >
                    <Link
                        v-if="adjacentWeeks.previous"
                        :href="weekShow(adjacentWeeks.previous)"
                        aria-label="Previous week"
                        ><ChevronLeft
                    /></Link>
                    <span v-else><ChevronLeft /></span>
                </Button>
                <div>
                    <p
                        class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                    >
                        {{ week.season_year }} season
                    </p>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold">
                            Week {{ week.number }}
                        </h1>
                        <WeekPhaseBadge :phase="phase" />
                    </div>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    :disabled="!adjacentWeeks.next"
                    as-child
                >
                    <Link
                        v-if="adjacentWeeks.next"
                        :href="weekShow(adjacentWeeks.next)"
                        aria-label="Next week"
                        ><ChevronRight
                    /></Link>
                    <span v-else><ChevronRight /></span>
                </Button>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button v-if="phase !== 'draft'" variant="ghost" as-child>
                    <a
                        :href="
                            publicWeek({
                                season: week.season_year,
                                week: week.number,
                            }).url
                        "
                        target="_blank"
                        ><ExternalLink /> Board</a
                    >
                </Button>

                <template v-if="phase === 'draft'">
                    <Button
                        variant="outline"
                        :disabled="!!busy"
                        @click="act('sync', sync(week.id).url)"
                    >
                        <RefreshCw
                            :class="{ 'animate-spin': busy === 'sync' }"
                        />
                        Sync games from ESPN
                    </Button>
                    <Button
                        :disabled="!!busy || games.length === 0"
                        @click="
                            act(
                                'open',
                                open(week.id).url,
                                'Open this week? Every active player gets an entry and picks lock at the first kickoff.',
                            )
                        "
                    >
                        <LockOpen /> Open week
                    </Button>
                </template>

                <template v-if="phase === 'open'">
                    <Button
                        variant="outline"
                        :disabled="!!busy"
                        @click="act('sync', sync(week.id).url)"
                    >
                        <RefreshCw
                            :class="{ 'animate-spin': busy === 'sync' }"
                        />
                        Sync from ESPN
                    </Button>
                    <Button
                        :disabled="!!busy"
                        @click="
                            act(
                                'lock',
                                lock(week.id).url,
                                `Lock picks now? Nobody can change their picks after this, and everyone's picks show on the board. ${entries.length - submittedCount} of ${entries.length} haven't submitted yet.`,
                            )
                        "
                    >
                        <Lock /> Lock picks now
                    </Button>
                </template>

                <template v-if="phase === 'locked'">
                    <Button
                        variant="outline"
                        :disabled="!!busy"
                        @click="act('sync', sync(week.id).url)"
                    >
                        <RefreshCw
                            :class="{ 'animate-spin': busy === 'sync' }"
                        />
                        Get latest results
                    </Button>
                    <Button
                        :disabled="!!busy || !summary.all_games_final"
                        :title="
                            summary.all_games_final
                                ? ''
                                : 'Every game needs a final score first'
                        "
                        @click="
                            act(
                                'close',
                                close(week.id).url,
                                'Close the week? Placements and season points are stored.',
                            )
                        "
                    >
                        <Trophy /> Close week
                    </Button>
                </template>

                <Button
                    v-if="phase === 'closed'"
                    variant="outline"
                    :disabled="!!busy"
                    @click="
                        act(
                            'reopen',
                            reopen(week.id).url,
                            'Reopen this week to make corrections? Its points are removed from the season until it is closed again.',
                        )
                    "
                >
                    <LockOpen /> Reopen week
                </Button>
            </div>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">Picks lock</div>
                <div class="text-sm font-semibold">
                    {{ kickoff(week.locks_at) }}
                </div>
                <div
                    v-if="week.locks_at && !week.is_locked"
                    class="text-xs text-muted-foreground"
                >
                    {{ relative(week.locks_at) }}
                </div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">Entered</div>
                <div class="text-xl font-bold">{{ entries.length }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">Submitted</div>
                <div class="text-xl font-bold">{{ submittedCount }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-muted-foreground">Games</div>
                <div class="text-xl font-bold">{{ games.length }}</div>
            </div>
        </div>

        <p
            v-if="phase === 'draft'"
            class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground"
        >
            Confirm the games below — sync from ESPN, then fix anything by hand.
            Opening the week enters every active user and sets the lock to the
            first kickoff. Players then make their picks from the home page.
        </p>

        <Tabs :default-value="phase === 'draft' ? 'games' : 'entries'">
            <TabsList>
                <TabsTrigger value="entries"
                    >Entries ({{ entries.length }})</TabsTrigger
                >
                <TabsTrigger value="games"
                    >Games ({{ games.length }})</TabsTrigger
                >
            </TabsList>

            <!-- Entries -->
            <TabsContent value="entries" class="space-y-3">
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/60 text-left text-xs tracking-wider text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-2">Player</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2 text-center">TB</th>
                                <th class="px-4 py-2 text-center">Correct</th>
                                <th class="px-4 py-2 text-center">
                                    {{
                                        phase === 'closed'
                                            ? 'Place · pts'
                                            : 'Projected'
                                    }}
                                </th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="entry in entries"
                                :key="entry.id"
                                class="border-t"
                            >
                                <td class="px-4 py-2">
                                    <div class="flex items-center gap-3">
                                        <UserAvatar
                                            v-bind="entry.user"
                                            size-class="size-8 text-[11px]"
                                        />
                                        <div class="leading-tight">
                                            <div class="font-medium">
                                                {{ entry.user.name }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ entry.user.email }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <span
                                        v-if="entry.submitted_at"
                                        class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800 dark:bg-green-900 dark:text-green-100"
                                        :title="kickoff(entry.submitted_at)"
                                        ><Check class="size-3" />
                                        Submitted</span
                                    >
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200"
                                        ><Clock class="size-3" />
                                        {{
                                            week.is_locked ? 'DNP' : 'Waiting'
                                        }}</span
                                    >
                                </td>
                                <td class="px-4 py-2 text-center tabular-nums">
                                    {{ entry.tiebreaker_guess ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-center tabular-nums">
                                    <span
                                        :class="{
                                            'font-bold text-green-700 dark:text-green-300':
                                                entry.placement === 1,
                                        }"
                                    >
                                        {{ entry.correct ?? '—' }}
                                    </span>
                                </td>
                                <td
                                    class="px-4 py-2 text-center whitespace-nowrap tabular-nums"
                                >
                                    <template v-if="entry.placement">
                                        {{ ordinal(entry.placement) }} ·
                                        <span class="font-semibold">{{
                                            points(entry.points)
                                        }}</span>
                                    </template>
                                    <template
                                        v-else-if="
                                            phase === 'closed' &&
                                            entry.points !== null
                                        "
                                        >DNP · 0</template
                                    >
                                    <template v-else>—</template>
                                </td>
                                <td
                                    class="px-4 py-2 text-right whitespace-nowrap"
                                >
                                    <Button
                                        v-if="entry.submitted_at"
                                        variant="ghost"
                                        size="icon"
                                        title="View picks"
                                        @click="viewingEntry = entry"
                                        ><Eye
                                    /></Button>
                                    <Button
                                        v-if="phase === 'open'"
                                        variant="ghost"
                                        size="icon"
                                        title="Enter picks"
                                        @click="openPicksDialog(entry)"
                                        ><SquarePen
                                    /></Button>
                                    <Button
                                        v-if="phase !== 'closed'"
                                        variant="ghost"
                                        size="icon"
                                        title="Remove from week"
                                        @click="removeEntry(entry)"
                                        ><Trash2
                                    /></Button>
                                </td>
                            </tr>
                            <tr v-if="entries.length === 0">
                                <td
                                    colspan="6"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {{
                                        phase === 'draft'
                                            ? 'Active users are entered when the week opens.'
                                            : 'No entries.'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="phase === 'open' || phase === 'locked'"
                    class="flex max-w-md gap-2"
                >
                    <select
                        v-model="addUserId"
                        :class="selectClass"
                        aria-label="Add a player"
                    >
                        <option value="">Add a player to this week…</option>
                        <option
                            v-for="p in availableUsers"
                            :key="p.id"
                            :value="p.id"
                        >
                            {{ p.name }}{{ p.is_active ? '' : ' (inactive)' }}
                        </option>
                    </select>
                    <Button
                        variant="outline"
                        :disabled="addUserId === ''"
                        @click="addUser"
                        ><Plus /> Add</Button
                    >
                </div>
            </TabsContent>

            <!-- Games -->
            <TabsContent value="games" class="space-y-3">
                <p
                    v-if="!hasTiebreaker && games.length"
                    class="text-sm text-orange-700 dark:text-orange-300"
                >
                    No tie-breaker game chosen yet — the last kickoff is used
                    when the week opens.
                </p>
                <div class="overflow-x-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/60 text-left text-xs tracking-wider text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-4 py-2">Matchup</th>
                                <th class="px-4 py-2">Kickoff</th>
                                <th class="px-4 py-2 text-center">
                                    Tie-breaker
                                </th>
                                <th class="px-4 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="game in games"
                                :key="game.id"
                                class="border-t"
                            >
                                <td class="w-56 px-4 py-2">
                                    <GameMatchup :game="game" />
                                </td>
                                <td class="px-4 py-2">
                                    <div>
                                        {{
                                            game.is_tbd_flex
                                                ? 'TBD (flex)'
                                                : kickoff(game.kickoff_at)
                                        }}
                                    </div>
                                    <div
                                        v-if="game.manual_override"
                                        class="text-xs text-muted-foreground"
                                        title="Edited by hand; ESPN syncs skip this game"
                                    >
                                        Edited by hand
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-center">
                                    <span
                                        v-if="game.is_tiebreaker"
                                        class="rounded bg-yellow-200 px-2 py-0.5 text-xs font-semibold text-yellow-900"
                                    >
                                        Tie-breaker{{
                                            summary.tiebreaker_total !== null
                                                ? ` · ${summary.tiebreaker_total} pts`
                                                : ''
                                        }}
                                    </span>
                                    <Button
                                        v-else-if="!week.is_locked"
                                        variant="ghost"
                                        size="sm"
                                        @click="setTiebreaker(game)"
                                        >Use</Button
                                    >
                                </td>
                                <td
                                    class="px-4 py-2 text-right whitespace-nowrap"
                                >
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        title="Edit game"
                                        @click="openGameDialog(game)"
                                        ><Pencil
                                    /></Button>
                                    <Button
                                        v-if="phase === 'draft'"
                                        variant="ghost"
                                        size="icon"
                                        title="Remove game"
                                        @click="removeGame(game)"
                                        ><Trash2
                                    /></Button>
                                </td>
                            </tr>
                            <tr v-if="games.length === 0">
                                <td
                                    colspan="4"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    No games yet. Sync from ESPN or add them by
                                    hand.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Button
                    v-if="phase === 'draft'"
                    variant="outline"
                    @click="openGameDialog(null)"
                    ><Plus /> Add game</Button
                >
            </TabsContent>
        </Tabs>
    </div>

    <!-- Game editor -->
    <Dialog v-model:open="gameDialogOpen">
        <DialogContent class="max-h-[90dvh] overflow-y-auto">
            <form class="space-y-4" @submit.prevent="saveGame">
                <DialogHeader>
                    <DialogTitle>{{
                        editingGame ? 'Edit game' : 'Add game'
                    }}</DialogTitle>
                    <DialogDescription>
                        Hand-edited games are skipped by future ESPN syncs.
                        <template v-if="week.is_locked">
                            Picks are locked, so only the score and status can
                            change.</template
                        >
                    </DialogDescription>
                </DialogHeader>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="away_team_id">Away</Label>
                        <select
                            id="away_team_id"
                            v-model="gameForm.away_team_id"
                            :class="selectClass"
                            :disabled="week.is_locked"
                        >
                            <option value="" disabled>Team…</option>
                            <option
                                v-for="team in teams"
                                :key="team.id"
                                :value="team.id"
                            >
                                {{ team.display_name }}
                            </option>
                        </select>
                        <InputError :message="gameForm.errors.away_team_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="home_team_id">Home</Label>
                        <select
                            id="home_team_id"
                            v-model="gameForm.home_team_id"
                            :class="selectClass"
                            :disabled="week.is_locked"
                        >
                            <option value="" disabled>Team…</option>
                            <option
                                v-for="team in teams"
                                :key="team.id"
                                :value="team.id"
                            >
                                {{ team.display_name }}
                            </option>
                        </select>
                        <InputError :message="gameForm.errors.home_team_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="away_score">Away score</Label>
                        <Input
                            id="away_score"
                            v-model.number="gameForm.away_score as number"
                            type="number"
                            min="0"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="home_score">Home score</Label>
                        <Input
                            id="home_score"
                            v-model.number="gameForm.home_score as number"
                            type="number"
                            min="0"
                        />
                        <InputError :message="gameForm.errors.home_score" />
                    </div>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="grid gap-1.5">
                        <Label for="kickoff_at">Kickoff (local time)</Label>
                        <Input
                            id="kickoff_at"
                            v-model="gameForm.kickoff_at"
                            type="datetime-local"
                            :disabled="week.is_locked"
                        />
                        <InputError :message="gameForm.errors.kickoff_at" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="status">Status</Label>
                        <select
                            id="status"
                            v-model="gameForm.status"
                            :class="selectClass"
                        >
                            <option value="scheduled">Scheduled</option>
                            <option value="in_progress">In progress</option>
                            <option value="final">Final</option>
                        </select>
                        <InputError :message="gameForm.errors.status" />
                    </div>
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="gameForm.processing"
                        >Save game</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Picks viewer -->
    <Dialog
        :open="viewingEntry !== null"
        @update:open="(value) => !value && (viewingEntry = null)"
    >
        <DialogContent
            v-if="viewingEntry"
            class="max-h-[90dvh] overflow-y-auto"
        >
            <DialogHeader>
                <DialogTitle>{{ viewingEntry.user.name }}'s picks</DialogTitle>
                <DialogDescription>
                    Tie-breaker guess: {{ viewingEntry.tiebreaker_guess }} ·
                    submitted {{ kickoff(viewingEntry.submitted_at) }}
                </DialogDescription>
            </DialogHeader>
            <ul class="max-h-[60vh] divide-y overflow-y-auto">
                <li
                    v-for="game in games"
                    :key="game.id"
                    class="flex items-center justify-between gap-3 py-2 text-sm"
                >
                    <span class="text-muted-foreground"
                        >{{ game.away.abbreviation }} @
                        {{ game.home.abbreviation }}</span
                    >
                    <span
                        v-if="pickedTeam(game, viewingEntry)"
                        class="flex items-center gap-2 font-medium"
                    >
                        <TeamLogo
                            :team="pickedTeam(game, viewingEntry)!"
                            size-class="size-6"
                        />
                        {{ pickedTeam(game, viewingEntry)!.abbreviation }}
                        <Check
                            v-if="
                                game.status === 'final' &&
                                game.winning_team_id ===
                                    viewingEntry.picks[game.id]
                            "
                            class="size-4 text-green-600"
                        />
                        <X
                            v-else-if="game.status === 'final'"
                            class="size-4 text-orange-600"
                        />
                    </span>
                    <span v-else class="text-muted-foreground">—</span>
                </li>
            </ul>
        </DialogContent>
    </Dialog>

    <!-- Enter picks for someone -->
    <Dialog
        :open="enteringFor !== null"
        @update:open="(value) => !value && (enteringFor = null)"
    >
        <DialogContent v-if="enteringFor" class="sm:max-w-xl">
            <form class="space-y-4" @submit.prevent="savePicks">
                <DialogHeader>
                    <DialogTitle
                        >Enter picks for
                        {{ enteringFor.user.name }}</DialogTitle
                    >
                    <DialogDescription>
                        For friends who text their picks in. Allowed until the
                        lock ({{ kickoff(week.locks_at) }}).
                    </DialogDescription>
                </DialogHeader>
                <div class="max-h-[60vh] overflow-y-auto pr-1">
                    <PicksForm :games="games" :form="picksForm" />
                </div>
                <DialogFooter>
                    <Button type="submit" :disabled="picksForm.processing"
                        >Save picks</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
