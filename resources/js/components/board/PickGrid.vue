<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import TeamLogo from '@/components/TeamLogo.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import GameMatchup from '@/components/board/GameMatchup.vue';
import { ordinal, points } from '@/lib/format';
import type { Game, StandingRow, Team } from '@/types';

const props = defineProps<{
    games: Game[];
    rows: StandingRow[];
}>();

const page = usePage();
const viewerId = computed(() => page.props.auth.user.id);

function isViewer(row: StandingRow): boolean {
    return row.user.id === viewerId.value;
}

function pickedTeam(game: Game, row: StandingRow): Team | null {
    const teamId = row.picks[game.id];

    return [game.home, game.away].find((team) => team.id === teamId) ?? null;
}

function cellClass(game: Game, row: StandingRow): string {
    const teamId = row.picks[game.id];

    if (game.status !== 'final' || teamId === undefined) {
        return isViewer(row) ? 'bg-primary/5' : '';
    }

    return game.winning_team_id === teamId
        ? 'bg-green-100 dark:bg-green-900/60'
        : 'bg-orange-100/70 dark:bg-orange-900/40';
}

function columnClass(row: StandingRow): string {
    return isViewer(row) ? 'bg-primary/5' : '';
}

// How many people picked each side, shown as a hint on the game.
function pickShare(game: Game, team: Team): number {
    return props.rows.filter((row) => row.picks[game.id] === team.id).length;
}
</script>

<template>
    <div
        class="max-h-[75dvh] overflow-auto overscroll-x-contain rounded-lg border bg-card md:max-h-none"
    >
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="border-b">
                    <th
                        class="sticky top-0 left-0 z-30 min-w-28 bg-card px-2 py-2 text-left text-xs font-semibold tracking-wider text-muted-foreground uppercase shadow-[inset_0_-1px_0_var(--border)] sm:min-w-40 sm:px-3"
                    >
                        Game
                    </th>
                    <th
                        v-for="row in rows"
                        :key="row.entry_id"
                        class="sticky top-0 z-20 bg-card px-1 py-2 align-bottom shadow-[inset_0_-1px_0_var(--border)]"
                    >
                        <div
                            class="flex w-12 flex-col items-center gap-1 sm:w-16"
                        >
                            <UserAvatar
                                v-bind="row.user"
                                size-class="size-8 text-[11px] sm:size-9"
                                :class="{
                                    'ring-2 ring-primary ring-offset-2 ring-offset-card':
                                        isViewer(row),
                                }"
                            />
                            <span
                                class="w-full truncate text-center text-xs font-medium"
                                :title="row.user.name"
                                >{{
                                    isViewer(row) ? 'You' : row.user.name
                                }}</span
                            >
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="game in games"
                    :key="game.id"
                    class="border-b last:border-b-0"
                >
                    <td
                        class="sticky left-0 z-10 border-r bg-card px-2 py-2 sm:px-3"
                    >
                        <GameMatchup :game="game" />
                        <div
                            class="mt-1 text-[11px] text-muted-foreground tabular-nums"
                        >
                            {{ game.away.abbreviation }}
                            {{ pickShare(game, game.away) }} ·
                            {{ game.home.abbreviation }}
                            {{ pickShare(game, game.home) }}
                        </div>
                    </td>
                    <td
                        v-for="row in rows"
                        :key="row.entry_id"
                        class="px-1 py-2 text-center"
                        :class="cellClass(game, row)"
                    >
                        <TeamLogo
                            v-if="pickedTeam(game, row)"
                            :team="pickedTeam(game, row)!"
                            size-class="mx-auto size-6 sm:size-8"
                        />
                        <span v-else class="text-muted-foreground">—</span>
                    </td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="border-t-2 font-semibold">
                    <td
                        class="sticky left-0 z-10 border-r bg-card px-2 py-2 text-xs tracking-wider text-muted-foreground uppercase sm:px-3"
                    >
                        Correct
                    </td>
                    <td
                        v-for="row in rows"
                        :key="row.entry_id"
                        class="px-1 py-2 text-center text-base tabular-nums"
                        :class="[
                            columnClass(row),
                            {
                                'text-green-700 dark:text-green-300':
                                    row.is_leader,
                            },
                        ]"
                    >
                        {{ row.correct }}
                    </td>
                </tr>
                <tr class="border-t">
                    <td
                        class="sticky left-0 z-10 border-r bg-card px-2 py-2 text-xs tracking-wider text-muted-foreground uppercase sm:px-3"
                    >
                        Place
                    </td>
                    <td
                        v-for="row in rows"
                        :key="row.entry_id"
                        class="px-1 py-2 text-center tabular-nums"
                        :class="columnClass(row)"
                    >
                        {{ row.placement ? ordinal(row.placement) : '—' }}
                    </td>
                </tr>
                <tr class="border-t">
                    <td
                        class="sticky left-0 z-10 border-r bg-card px-2 py-2 text-xs tracking-wider text-muted-foreground uppercase sm:px-3"
                    >
                        Pts
                    </td>
                    <td
                        v-for="row in rows"
                        :key="row.entry_id"
                        class="px-1 py-2 text-center font-semibold tabular-nums"
                        :class="columnClass(row)"
                    >
                        {{ points(row.points) }}
                    </td>
                </tr>
                <tr class="border-t">
                    <td
                        class="sticky left-0 z-10 border-r bg-card px-2 py-2 text-xs tracking-wider text-muted-foreground uppercase sm:px-3"
                    >
                        Tie-breaker
                    </td>
                    <td
                        v-for="row in rows"
                        :key="row.entry_id"
                        class="px-1 py-2 text-center tabular-nums"
                        :class="columnClass(row)"
                    >
                        {{ row.tiebreaker_guess ?? '—' }}
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</template>
