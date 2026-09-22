<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { CircleCheck, Lock } from '@lucide/vue';
import { computed } from 'vue';
import PicksForm from '@/components/picks/PicksForm.vue';
import type { PicksFormData } from '@/components/picks/PicksForm.vue';
import { Button } from '@/components/ui/button';
import { kickoff, relative } from '@/lib/format';
import { update } from '@/routes/picks';
import type { Game, WeekSummary } from '@/types';

const props = defineProps<{
    week: WeekSummary;
    games: Game[];
    entry: {
        submitted_at: string | null;
        tiebreaker_guess: number | null;
        picks: Record<number, number>;
    };
}>();

const page = usePage();
const firstName = computed(
    () => page.props.auth.user.display_name.split(' ')[0],
);

const form = useForm<PicksFormData>({
    picks: { ...props.entry.picks },
    tiebreaker_guess: props.entry.tiebreaker_guess ?? '',
});

const locked = computed(() => props.week.is_locked);
const pickedCount = computed(
    () => props.games.filter((g) => form.picks[g.id]).length,
);

function submit() {
    form.submit(update(), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Week ${week.number} picks`" />

    <form
        class="mx-auto w-full max-w-2xl space-y-5 p-3 sm:p-4"
        @submit.prevent="submit"
    >
        <div>
            <p
                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
            >
                {{ week.season_year }} · Week {{ week.number }}
            </p>
            <h1 class="text-2xl font-bold">
                {{
                    locked ? 'Your picks' : `Hey ${firstName}, make your picks`
                }}
            </h1>
        </div>

        <div
            v-if="locked"
            class="flex items-start gap-3 rounded-lg border border-yellow-400 bg-yellow-100 p-4 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100"
        >
            <Lock class="mt-0.5 size-4 shrink-0" />
            <div>
                Picks are locked — the first game has kicked off.
                <template v-if="!entry.submitted_at"
                    >You didn't submit picks this week.</template
                >
            </div>
        </div>
        <div
            v-else
            class="rounded-lg border bg-card p-4 text-sm text-muted-foreground"
        >
            Tap the team you think wins each game. Picks lock
            <span class="font-semibold text-foreground">{{
                kickoff(week.locks_at)
            }}</span>
            ({{ relative(week.locks_at) }}). You can come back and change them
            until then.
        </div>

        <div
            v-if="entry.submitted_at && !locked"
            class="flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/40 dark:text-green-100"
        >
            <CircleCheck class="size-4" />
            Picks submitted. Update anything below and save again.
        </div>

        <PicksForm :games="games" :form="form" :locked="locked" />

        <div
            v-if="!locked"
            class="sticky bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-20 -mx-3 border-t bg-background/95 px-3 py-3 backdrop-blur sm:-mx-4 sm:px-4 md:bottom-0"
        >
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted-foreground tabular-nums"
                    >{{ pickedCount }}/{{ games.length }} picked</span
                >
                <Button
                    type="submit"
                    class="ml-auto"
                    size="lg"
                    :disabled="form.processing"
                >
                    {{ entry.submitted_at ? 'Update picks' : 'Submit picks' }}
                </Button>
            </div>
        </div>
    </form>
</template>
