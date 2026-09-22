<script setup lang="ts">
import { computed } from 'vue';
import TeamLogo from '@/components/TeamLogo.vue';
import { kickoff } from '@/lib/format';
import type { Game } from '@/types';

const props = defineProps<{ game: Game }>();

const started = computed(() => props.game.status !== 'scheduled');

const statusLabel = computed(() => {
    if (props.game.status === 'final') {
        return 'Final';
    }

    if (props.game.status === 'in_progress') {
        return 'Live';
    }

    return props.game.is_tbd_flex ? 'Time TBD' : kickoff(props.game.kickoff_at);
});

function isLoser(teamId: number): boolean {
    return (
        props.game.status === 'final' &&
        props.game.winning_team_id !== null &&
        props.game.winning_team_id !== teamId
    );
}
</script>

<template>
    <div class="min-w-0">
        <div class="flex flex-col gap-1">
            <div
                v-for="side in [
                    { team: game.away, score: game.away_score },
                    { team: game.home, score: game.home_score },
                ]"
                :key="side.team.id"
                class="flex items-center gap-2"
                :class="{ 'opacity-50': isLoser(side.team.id) }"
            >
                <TeamLogo :team="side.team" size-class="size-6" />
                <span class="text-sm font-semibold">{{
                    side.team.abbreviation
                }}</span>
                <span
                    v-if="started"
                    class="ml-auto text-sm font-bold tabular-nums"
                    >{{ side.score }}</span
                >
            </div>
        </div>
        <div
            class="mt-1 flex items-center gap-1.5 text-[11px] text-muted-foreground"
        >
            <span
                v-if="game.status === 'in_progress'"
                class="size-1.5 animate-pulse rounded-full bg-orange-500"
            />
            <span class="truncate">{{ statusLabel }}</span>
            <span
                v-if="game.is_tiebreaker"
                class="rounded bg-yellow-200 px-1 font-semibold text-yellow-900"
                title="Tie-breaker game"
                >TB</span
            >
        </div>
    </div>
</template>
