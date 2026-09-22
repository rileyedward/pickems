<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TeamLogo from '@/components/TeamLogo.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { kickoff } from '@/lib/format';
import type { Game, Team } from '@/types';

export type PicksFormData = {
    picks: Record<number, number>;
    tiebreaker_guess: number | '';
};

/**
 * One tap-to-pick card per game plus the tie-breaker guess. Used by players on
 * their picks page and by admins entering picks for someone.
 */
const props = defineProps<{
    games: Game[];
    form: InertiaForm<PicksFormData>;
    locked?: boolean;
}>();

const tiebreakerGame = computed(() => props.games.find((g) => g.is_tiebreaker));

function pick(game: Game, team: Team) {
    if (!props.locked) {
        props.form.picks[game.id] = team.id;
        props.form.clearErrors(`picks.${game.id}` as never);
    }
}

function gameError(game: Game): string | undefined {
    return (props.form.errors as Record<string, string | undefined>)[
        `picks.${game.id}`
    ];
}
</script>

<template>
    <div class="space-y-3">
        <InputError :message="form.errors.picks" />

        <div
            v-for="game in games"
            :key="game.id"
            class="rounded-lg border bg-card p-2 sm:p-3"
            :class="{ 'border-destructive': gameError(game) }"
        >
            <div
                class="mb-2 flex items-center justify-between text-xs text-muted-foreground"
            >
                <span>{{
                    game.is_tbd_flex ? 'Time TBD' : kickoff(game.kickoff_at)
                }}</span>
                <span
                    v-if="game.is_tiebreaker"
                    class="rounded bg-yellow-200 px-1.5 font-semibold text-yellow-900"
                    >Tie-breaker game</span
                >
            </div>
            <div
                class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-center gap-1.5 sm:gap-2"
            >
                <template
                    v-for="(team, index) in [game.away, game.home]"
                    :key="team.id"
                >
                    <span
                        v-if="index === 1"
                        class="text-xs font-semibold text-muted-foreground"
                        >@</span
                    >
                    <button
                        type="button"
                        :disabled="locked"
                        :aria-pressed="form.picks[game.id] === team.id"
                        class="flex min-h-14 min-w-0 items-center gap-2 rounded-md border-2 p-1.5 text-left transition-colors disabled:cursor-default sm:gap-3 sm:p-2"
                        :class="
                            form.picks[game.id] === team.id
                                ? 'border-primary bg-primary/10'
                                : 'border-transparent bg-muted/60 hover:border-border'
                        "
                        @click="pick(game, team)"
                    >
                        <TeamLogo :team="team" size-class="size-8 sm:size-10" />
                        <span class="min-w-0 leading-tight">
                            <span class="block font-semibold">{{
                                team.abbreviation
                            }}</span>
                            <span
                                class="block truncate text-xs text-muted-foreground"
                                >{{ team.name }}</span
                            >
                        </span>
                        <Check
                            v-if="form.picks[game.id] === team.id"
                            class="ml-auto size-4 shrink-0 text-primary"
                        />
                    </button>
                </template>
            </div>
            <InputError class="mt-1" :message="gameError(game)" />
        </div>

        <div class="rounded-lg border bg-card p-4">
            <Label for="tiebreaker_guess" class="font-semibold"
                >Tie-breaker: total points</Label
            >
            <p class="mb-3 text-sm text-muted-foreground">
                <template v-if="tiebreakerGame"
                    >Combined final score of
                    {{ tiebreakerGame.away.abbreviation }} @
                    {{ tiebreakerGame.home.abbreviation }}. </template
                >Closest guess wins a tie.
            </p>
            <Input
                id="tiebreaker_guess"
                v-model.number="form.tiebreaker_guess"
                type="number"
                inputmode="numeric"
                min="0"
                max="200"
                class="w-32"
                :disabled="locked"
            />
            <InputError :message="form.errors.tiebreaker_guess" />
        </div>
    </div>
</template>
