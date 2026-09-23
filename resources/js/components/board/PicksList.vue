<script setup lang="ts">
import { Check, X } from '@lucide/vue';
import TeamLogo from '@/components/TeamLogo.vue';
import type { Game, Team } from '@/types';

const props = defineProps<{
    games: Game[];
    // game id → picked team id
    picks: Record<number, number>;
}>();

function pickedTeam(game: Game): Team | null {
    return (
        [game.home, game.away].find((t) => t.id === props.picks[game.id]) ??
        null
    );
}
</script>

<template>
    <ul class="divide-y">
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
                v-if="pickedTeam(game)"
                class="flex items-center gap-2 font-medium"
            >
                <TeamLogo :team="pickedTeam(game)!" size-class="size-6" />
                {{ pickedTeam(game)!.abbreviation }}
                <Check
                    v-if="
                        game.status === 'final' &&
                        game.winning_team_id === picks[game.id]
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
</template>
