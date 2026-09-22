<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, Hourglass, Pencil } from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import WeekBoardView from '@/components/board/WeekBoardView.vue';
import { Button } from '@/components/ui/button';
import { points, relative } from '@/lib/format';
import { show as picksShow } from '@/routes/picks';
import { show as seasonShow } from '@/routes/seasons';
import { show as userShow } from '@/routes/users';
import type { LeaderboardRow, WeekBoard } from '@/types';

const props = defineProps<{
    board: WeekBoard | null;
    myEntry: { submitted: boolean } | null;
    topThree: LeaderboardRow[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);

const canPick = computed(
    () =>
        user.value.is_active &&
        props.board?.week.phase === 'open' &&
        props.myEntry !== null,
);
</script>

<template>
    <Head title="This week" />

    <div class="mx-auto w-full max-w-6xl space-y-6 p-3 sm:p-4">
        <div
            v-if="!user.is_active"
            class="flex items-start gap-3 rounded-lg border border-yellow-400 bg-yellow-50 p-4 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100"
        >
            <Hourglass class="mt-0.5 size-4 shrink-0" />
            <div>
                <div class="font-semibold">Waiting to be activated</div>
                You can follow along, but an admin needs to activate your
                account before you're entered into weeks.
            </div>
        </div>

        <WeekBoardView v-if="board" :board="board">
            <template #actions>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="canPick" as-child>
                        <Link :href="picksShow()">
                            <Pencil />
                            {{
                                myEntry?.submitted
                                    ? 'Edit your picks'
                                    : 'Make your picks'
                            }}
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link :href="seasonShow(board.week.season_year)"
                            >Season standings</Link
                        >
                    </Button>
                </div>
            </template>

            <template #notice>
                <div
                    v-if="canPick"
                    class="flex items-center gap-2 rounded-lg border p-3 text-sm"
                    :class="
                        myEntry?.submitted
                            ? 'border-green-300 bg-green-50 text-green-800 dark:border-green-800 dark:bg-green-900/40 dark:text-green-100'
                            : 'bg-card text-muted-foreground'
                    "
                >
                    <CircleCheck v-if="myEntry?.submitted" class="size-4" />
                    <template v-if="myEntry?.submitted"
                        >Your picks are in. You can change them until the lock
                        ({{ relative(board.week.locks_at) }}).</template
                    >
                    <template v-else
                        >You haven't made your picks yet. They lock
                        {{ relative(board.week.locks_at) }}.</template
                    >
                </div>

                <div v-if="topThree.length" class="rounded-lg border bg-card">
                    <div
                        class="flex items-center justify-between border-b px-4 py-2 text-sm"
                    >
                        <h2 class="font-semibold">
                            {{ board.week.season_year }} leaders
                        </h2>
                        <Link
                            :href="seasonShow(board.week.season_year)"
                            class="-my-2 -mr-2 p-2 text-muted-foreground hover:underline"
                            >Full leaderboard</Link
                        >
                    </div>
                    <div class="grid gap-2 p-3 sm:grid-cols-3">
                        <Link
                            v-for="row in topThree"
                            :key="row.user.id"
                            :href="userShow(row.user.id)"
                            class="flex items-center gap-3 rounded-md p-2 hover:bg-muted/60"
                        >
                            <span
                                class="w-5 text-right text-sm font-semibold text-muted-foreground tabular-nums"
                                >{{ row.rank }}</span
                            >
                            <UserAvatar
                                v-bind="row.user"
                                size-class="size-9 text-xs"
                            />
                            <div class="min-w-0 leading-tight">
                                <div class="truncate font-medium">
                                    {{ row.user.name }}
                                </div>
                                <div
                                    class="text-xs text-muted-foreground tabular-nums"
                                >
                                    {{ points(row.total_points) }} pts ·
                                    {{ row.weeks_won }} W
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </template>
        </WeekBoardView>

        <div
            v-else
            class="flex flex-col items-center gap-3 rounded-lg border bg-card px-6 py-16 text-center"
        >
            <AppLogoIcon class="size-12 text-primary" />
            <h1 class="text-2xl font-bold">No picks open yet</h1>
            <p class="max-w-md text-muted-foreground">
                Once this week's games are confirmed, picks open here. Check
                back soon.
            </p>
        </div>
    </div>
</template>
