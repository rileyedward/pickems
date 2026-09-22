<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight, RefreshCw } from '@lucide/vue';
import { ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { sync as syncTeams } from '@/routes/admin/teams';
import { index, show, store } from '@/routes/admin/seasons';

defineProps<{
    seasons: {
        id: number;
        year: number;
        weeks_count: number;
    }[];
    teamCount: number;
    suggestedYear: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Seasons', href: index() }],
    },
});

const syncingTeams = ref(false);

function runTeamSync() {
    router.post(
        syncTeams().url,
        {},
        {
            onStart: () => (syncingTeams.value = true),
            onFinish: () => (syncingTeams.value = false),
        },
    );
}
</script>

<template>
    <Head title="Seasons" />

    <div class="max-w-3xl space-y-6 p-4">
        <Heading
            title="Seasons"
            description="Pull the NFL schedule from ESPN. A season sync creates weeks 1–18 with every game."
        />

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border p-4">
                <h3 class="font-semibold">Load a season</h3>
                <p class="mb-3 text-sm text-muted-foreground">
                    Creates the season or refreshes its schedule. Safe to run
                    again — hand-edited games are left alone.
                </p>
                <Form
                    v-bind="store.form()"
                    class="flex items-start gap-2"
                    #default="{ errors, processing }"
                >
                    <div class="grid gap-1">
                        <Label for="year" class="sr-only">Year</Label>
                        <Input
                            id="year"
                            name="year"
                            type="number"
                            :default-value="suggestedYear"
                            class="w-28"
                        />
                        <InputError :message="errors.year" />
                    </div>
                    <Button type="submit" :disabled="processing">
                        <RefreshCw :class="{ 'animate-spin': processing }" />
                        {{ processing ? 'Syncing…' : 'Sync season' }}
                    </Button>
                </Form>
            </div>

            <div class="rounded-lg border p-4">
                <h3 class="font-semibold">Teams &amp; logos</h3>
                <p class="mb-3 text-sm text-muted-foreground">
                    {{ teamCount }} teams stored. Season syncs add teams
                    automatically; run this to refresh names, colors and logos.
                </p>
                <Button
                    variant="outline"
                    :disabled="syncingTeams"
                    @click="runTeamSync"
                >
                    <RefreshCw :class="{ 'animate-spin': syncingTeams }" />
                    Sync teams
                </Button>
            </div>
        </div>

        <div class="rounded-lg border">
            <Link
                v-for="season in seasons"
                :key="season.id"
                :href="show(season.id)"
                class="flex items-center justify-between border-b px-4 py-3 last:border-b-0 hover:bg-muted/60"
            >
                <div>
                    <div class="font-semibold">{{ season.year }}</div>
                    <div class="text-sm text-muted-foreground">
                        {{ season.weeks_count }} weeks
                    </div>
                </div>
                <ChevronRight class="size-4 text-muted-foreground" />
            </Link>
            <p
                v-if="seasons.length === 0"
                class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
                No seasons yet.
            </p>
        </div>
    </div>
</template>
