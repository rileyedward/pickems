<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardList,
    House,
    Trophy,
    UserCog,
    Users,
} from '@lucide/vue';
import { computed, onUnmounted } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { home } from '@/routes';
import { home as adminHome } from '@/routes/admin';
import { index as adminSeasons } from '@/routes/admin/seasons';
import { index as adminUsers } from '@/routes/admin/users';
import { show as seasonShow } from '@/routes/seasons';
import { index as users } from '@/routes/users';
import type { NavItem } from '@/types';

const page = usePage();
const { setOpenMobile } = useSidebar();

// The layout persists across visits, so close the mobile drawer ourselves.
onUnmounted(router.on('navigate', () => setOpenMobile(false)));

const mainNavItems = computed<NavItem[]>(() => {
    const year = page.props.currentSeasonYear;

    return [
        { title: 'This week', href: home(), icon: House },
        ...(year
            ? [{ title: 'Season', href: seasonShow(year), icon: Trophy }]
            : []),
        { title: 'Players', href: users(), icon: Users },
    ];
});

const adminNavItems: NavItem[] = [
    { title: 'This week', href: adminHome(), icon: ClipboardList },
    { title: 'Seasons', href: adminSeasons(), icon: CalendarDays },
    { title: 'Users', href: adminUsers(), icon: UserCog },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
            <NavMain
                v-if="page.props.auth.user.is_admin"
                label="Admin"
                :items="adminNavItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
