<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { onMounted, useTemplateRef } from 'vue';
import WeekBoardView from '@/components/board/WeekBoardView.vue';
import { Button } from '@/components/ui/button';
import { show as seasonShow } from '@/routes/seasons';
import { show as weekShow } from '@/routes/weeks';
import type { WeekBoard } from '@/types';

defineProps<{ board: WeekBoard; weekNumbers: number[] }>();

const weekStrip = useTemplateRef('weekStrip');

// On phones the week strip scrolls sideways, so bring the current week into view.
onMounted(() => {
    weekStrip.value
        ?.querySelector('[aria-current="page"]')
        ?.scrollIntoView({ block: 'nearest', inline: 'center' });
});
</script>

<template>
    <Head :title="`Week ${board.week.number}`" />

    <div class="mx-auto w-full max-w-6xl p-3 sm:p-4">
        <WeekBoardView :board="board">
            <template #actions>
                <div
                    class="flex w-full min-w-0 flex-wrap items-center gap-2 sm:w-auto"
                >
                    <Button variant="outline" as-child>
                        <Link :href="seasonShow(board.week.season_year)"
                            >All {{ board.week.season_year }} weeks</Link
                        >
                    </Button>
                    <div
                        ref="weekStrip"
                        class="-mx-3 flex w-[calc(100%+1.5rem)] gap-1 overflow-x-auto px-3 pb-1 sm:mx-0 sm:w-auto sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
                    >
                        <Button
                            v-for="n in weekNumbers"
                            :key="n"
                            size="sm"
                            class="h-10 min-w-10 shrink-0 sm:h-8 sm:min-w-8"
                            :variant="
                                n === board.week.number ? 'default' : 'ghost'
                            "
                            as-child
                        >
                            <Link
                                :href="
                                    weekShow({
                                        season: board.week.season_year,
                                        week: n,
                                    })
                                "
                                :aria-current="
                                    n === board.week.number ? 'page' : undefined
                                "
                                >{{ n }}</Link
                            >
                        </Button>
                    </div>
                </div>
            </template>
        </WeekBoardView>
    </div>
</template>
