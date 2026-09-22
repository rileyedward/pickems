<script setup lang="ts">
import { Form, Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ChevronRight, Minus, Plus, RefreshCw } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import WeekPhaseBadge from '@/components/WeekPhaseBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { kickoff, ordinal } from '@/lib/format';
import { index, points, show, store } from '@/routes/admin/seasons';
import { show as weekShow } from '@/routes/admin/weeks';
import type { WeekPhase } from '@/types';

const props = defineProps<{
    season: { id: number; year: number; points_table: number[] };
    closedWeeksCount: number;
    weeks: {
        id: number;
        number: number;
        phase: WeekPhase;
        games_count: number;
        entries_count: number;
        submitted_count: number;
        starts_at: string | null;
    }[];
}>();

setLayoutProps({
    breadcrumbs: [
        { title: 'Seasons', href: index() },
        { title: String(props.season.year), href: show(props.season.id) },
    ],
});

// Points table editor: one input per place, index 0 = 1st.
const pointsForm = useForm({
    points_table: [...props.season.points_table] as (number | '')[],
});

function addPlace() {
    const last = pointsForm.points_table.at(-1);

    pointsForm.points_table.push(
        typeof last === 'number' ? Math.max(last - 1, 0) : 0,
    );
}

function removePlace() {
    pointsForm.points_table.pop();
}

function placeError(index: number): string | undefined {
    return (pointsForm.errors as Record<string, string | undefined>)[
        `points_table.${index}`
    ];
}

function savePoints() {
    if (
        props.closedWeeksCount > 0 &&
        !confirm(
            `Save the new points table? ${props.closedWeeksCount} closed ${props.closedWeeksCount === 1 ? 'week' : 'weeks'} will be re-scored.`,
        )
    ) {
        return;
    }

    pointsForm.submit(points(props.season.id), { preserveScroll: true });
}
</script>

<template>
    <Head :title="`${season.year} season`" />

    <div class="max-w-3xl space-y-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="`${season.year} season`"
                description="Click a week to confirm its games and open picks."
            />
            <Form v-bind="store.form()" #default="{ processing }">
                <input type="hidden" name="year" :value="season.year" />
                <Button type="submit" variant="outline" :disabled="processing">
                    <RefreshCw :class="{ 'animate-spin': processing }" />
                    {{ processing ? 'Syncing…' : 'Re-sync schedule' }}
                </Button>
            </Form>
        </div>

        <form class="rounded-lg border p-4" @submit.prevent="savePoints">
            <h3 class="font-semibold">Points table</h3>
            <p class="mb-4 text-sm text-muted-foreground">
                Season points for each weekly placement. Places past the end
                score 0, and tied players split the points for the spots they
                share. Saving re-scores every closed week.
            </p>
            <div class="flex flex-wrap items-start gap-2">
                <div
                    v-for="(_, i) in pointsForm.points_table"
                    :key="i"
                    class="grid w-16 gap-1"
                >
                    <label
                        :for="`place-${i}`"
                        class="text-center text-xs font-medium text-muted-foreground"
                        >{{ ordinal(i + 1) }}</label
                    >
                    <Input
                        :id="`place-${i}`"
                        v-model.number="pointsForm.points_table[i]"
                        type="number"
                        min="0"
                        class="text-center tabular-nums"
                        :aria-invalid="!!placeError(i)"
                    />
                </div>
                <div class="flex gap-1 self-end">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="Add a place"
                        :disabled="pointsForm.points_table.length >= 20"
                        @click="addPlace"
                        ><Plus
                    /></Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        title="Remove the last place"
                        :disabled="pointsForm.points_table.length <= 1"
                        @click="removePlace"
                        ><Minus
                    /></Button>
                </div>
            </div>
            <InputError
                class="mt-2"
                :message="
                    pointsForm.errors.points_table ??
                    pointsForm.points_table
                        .map((_, i) => placeError(i))
                        .find(Boolean)
                "
            />
            <Button
                type="submit"
                variant="secondary"
                class="mt-4"
                :disabled="pointsForm.processing || !pointsForm.isDirty"
                >Save points</Button
            >
        </form>

        <div class="rounded-lg border">
            <Link
                v-for="week in weeks"
                :key="week.id"
                :href="weekShow(week.id)"
                class="flex items-center gap-4 border-b px-4 py-3 last:border-b-0 hover:bg-muted/60"
            >
                <span class="w-20 font-semibold">Week {{ week.number }}</span>
                <WeekPhaseBadge :phase="week.phase" />
                <span class="flex-1 text-sm text-muted-foreground">
                    {{ week.games_count }} games
                    <template v-if="week.starts_at">
                        · starts {{ kickoff(week.starts_at) }}</template
                    >
                    <template v-if="week.entries_count">
                        · {{ week.submitted_count }}/{{
                            week.entries_count
                        }}
                        submitted</template
                    >
                </span>
                <ChevronRight class="size-4 text-muted-foreground" />
            </Link>
        </div>
    </div>
</template>
