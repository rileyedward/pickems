<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ClipboardCheck, House, Trophy, Users } from '@lucide/vue';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { home } from '@/routes';
import { show as picksShow } from '@/routes/picks';
import { show as seasonShow } from '@/routes/seasons';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();
const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

const tabs = computed<NavItem[]>(() => {
    const year = page.props.currentSeasonYear;

    return [
        { title: 'This week', href: home(), icon: House },
        ...(page.props.auth.user.is_active
            ? [{ title: 'Picks', href: picksShow(), icon: ClipboardCheck }]
            : []),
        ...(year
            ? [{ title: 'Season', href: seasonShow(year), icon: Trophy }]
            : []),
        { title: 'Players', href: users(), icon: Users },
    ];
});

// "This week" is the root, so only match it exactly; the rest own their sub-pages.
function isActive(item: NavItem): boolean {
    return item.href === tabs.value[0].href
        ? isCurrentUrl(item.href)
        : isCurrentOrParentUrl(item.href);
}
</script>

<template>
    <nav
        class="fixed inset-x-0 bottom-0 z-30 border-t bg-background/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden"
    >
        <ul class="flex">
            <li v-for="item in tabs" :key="item.title" class="flex-1">
                <Link
                    :href="item.href"
                    class="flex min-h-14 flex-col items-center justify-center gap-0.5 text-[11px] font-medium text-muted-foreground transition-colors"
                    :class="{ 'text-primary': isActive(item) }"
                >
                    <component :is="item.icon" class="size-5" />
                    <span>{{ item.title }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
