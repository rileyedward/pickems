<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import UserAvatar from '@/components/UserAvatar.vue';
import { points } from '@/lib/format';
import { show as userShow } from '@/routes/users';
import type { StandingRow } from '@/types';

defineProps<{
    rows: StandingRow[];
    tiebreakerTotal: number | null;
    isFinal: boolean;
}>();
</script>

<template>
    <div class="rounded-lg border bg-card">
        <div
            class="flex items-center justify-between gap-2 border-b px-3 py-3 text-sm sm:px-4"
        >
            <h2 class="font-semibold">Standings</h2>
            <span class="text-muted-foreground">
                Tie-breaker total:
                <span class="font-semibold text-foreground">{{
                    tiebreakerTotal ?? 'pending'
                }}</span>
            </span>
        </div>
        <ol>
            <li
                v-for="row in rows"
                :key="row.entry_id"
                class="flex items-center gap-2 border-b px-3 py-2 last:border-b-0 sm:gap-3 sm:px-4"
                :class="{
                    'bg-green-50 dark:bg-green-900/30': row.is_leader,
                    'opacity-50': !row.submitted,
                }"
            >
                <span
                    class="w-8 shrink-0 text-right text-sm font-semibold text-muted-foreground tabular-nums"
                    >{{ row.submitted ? row.placement : 'DNP' }}</span
                >
                <UserAvatar
                    v-bind="row.user"
                    size-class="size-8 shrink-0 text-[11px]"
                />
                <Link
                    :href="userShow(row.user.id)"
                    class="min-w-0 flex-1 truncate font-medium hover:underline"
                    >{{ row.user.name }}</Link
                >
                <template v-if="row.submitted">
                    <span
                        class="hidden text-xs whitespace-nowrap text-muted-foreground tabular-nums sm:inline"
                        title="Tie-breaker guess"
                    >
                        TB {{ row.tiebreaker_guess
                        }}<template v-if="row.tiebreaker_diff !== null">
                            (±{{ row.tiebreaker_diff }})</template
                        >
                    </span>
                    <span
                        class="w-6 shrink-0 text-right font-bold tabular-nums sm:w-8"
                        title="Correct picks"
                        >{{ row.correct }}</span
                    >
                </template>
                <span
                    class="inline-flex min-w-16 shrink-0 items-center justify-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold whitespace-nowrap tabular-nums"
                    :class="
                        row.points > 0
                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100'
                            : 'bg-muted text-muted-foreground'
                    "
                    :title="
                        isFinal
                            ? 'Season points'
                            : 'Projected if the week ended now'
                    "
                >
                    {{ points(row.points) }} pts
                    <span
                        v-if="!isFinal && row.submitted"
                        class="hidden font-normal sm:inline"
                        >· projected</span
                    >
                </span>
            </li>
        </ol>
        <p
            v-if="rows.length === 0"
            class="px-4 py-6 text-center text-sm text-muted-foreground"
        >
            Nobody is entered this week.
        </p>
    </div>
</template>
