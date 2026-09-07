<script setup>
import { computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppBrandMark from '../Components/AppBrandMark.vue';
import FlashMessage from '../Components/FlashMessage.vue';
import ThemeToggle from '../Components/ThemeToggle.vue';
import { usePermissions } from '../Composables/usePermissions';
import { useSidebar } from '../Composables/useSidebar';

defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
});

const page = usePage();
const { can, hasRole, roles } = usePermissions();
const {
    collapsed,
    mobileOpen,
    toggleCollapsed,
    toggleNavGroupExpanded,
    showNavGroupItems,
    setNavGroupExpanded,
    openMobile,
    closeMobile,
} = useSidebar();

const user = computed(() => page.props.auth?.user);
const primaryRole = computed(() => roles.value?.[0]?.replaceAll('_', ' ') || 'User');

const icon = {
    dashboard: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    pr: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
    po: 'M3 10h18M7 15h1m4 0h1m4 0h1M5 6h14a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z',
    cash: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
    asset: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    transfer: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4',
    history: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    report: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    chart: 'M4 19V5m4 14V9m4 10v-6m4 6V7m4 12V11',
    activity: 'M13 10V3L4 14h7v7l9-11h-7z',
    email: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    budget: 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
    item: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
    vendor: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    location: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
    project: 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z',
    dept: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
    user: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
    role: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
    profile: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
};

const primaryNavItems = computed(() => [
    {
        label: 'Dashboard',
        href: route('app.dashboard'),
        icon: icon.dashboard,
        show: true,
        match: (url) => url === '/app' || url === '/app/' || url.startsWith('/app?'),
    },
    {
        label: 'Purchase Requests',
        href: route('app.purchase-requests.index'),
        icon: icon.pr,
        show: can('view_any_purchase::requests') || hasRole('super_admin'),
        match: (url) => url.startsWith('/app/purchase-requests'),
    },
    {
        label: 'Procure',
        href: route('app.purchase-orders.index'),
        icon: icon.po,
        show: can('view_any_purchase::orders') || hasRole('super_admin'),
        match: (url) => url.startsWith('/app/purchase-orders'),
    },
    {
        label: 'Petty Cash',
        href: route('app.petty-cash.index'),
        icon: icon.cash,
        show: can('view_any_petty::cash::reimbursment') || hasRole('super_admin'),
        match: (url) => url.startsWith('/app/petty-cash'),
    },
    {
        label: 'Assets',
        href: route('app.asset-management.index'),
        icon: icon.asset,
        show: can('view_any_asset::management') || hasRole('super_admin'),
        match: (url) => url.startsWith('/app/asset-management'),
    },
].filter((item) => item.show));

const navGroups = computed(() =>
    [
        {
            key: 'money',
            label: 'Budgets',
            items: [
                { label: 'Budget Accounts', href: route('app.budget-accounts.index'), icon: icon.budget, show: can('view_any_budget::accounts') || hasRole('super_admin'), match: (url) => url.startsWith('/app/budget-accounts') },
                { label: 'Transfers', href: route('app.budget-transfers.index'), icon: icon.transfer, show: can('view_any_budget::transfer') || hasRole('super_admin'), match: (url) => url.startsWith('/app/budget-transfers') },
                { label: 'History', href: route('app.budget-transaction-histories.index'), icon: icon.history, show: can('view_any_budget::transaction::history') || hasRole('super_admin'), match: (url) => url.startsWith('/app/budget-transaction-histories') },
            ].filter((item) => item.show),
        },
        {
            key: 'insights',
            label: 'Reports',
            items: [
                { label: 'Report Templates', href: route('app.reports.index'), icon: icon.report, show: can('view_any_report') || hasRole('super_admin'), match: (url) => url.startsWith('/app/reports') },
                { label: 'Dashboard Charts', href: route('app.charts.index'), icon: icon.chart, show: can('view_any_chart') || hasRole('super_admin'), match: (url) => url.startsWith('/app/charts') },
                { label: 'Activity', href: route('app.activity.index'), icon: icon.activity, show: can('view_any_activity') || hasRole('super_admin'), match: (url) => url.startsWith('/app/activity') },
                { label: 'Email Status', href: route('app.emails.index'), icon: icon.email, show: can('view_any_email::log') || hasRole('super_admin'), match: (url) => url.startsWith('/app/emails') },
            ].filter((item) => item.show),
        },
        {
            key: 'catalog',
            label: 'Master Data',
            items: [
                { label: 'Items', href: route('app.items.index'), icon: icon.item, show: can('view_any_item') || hasRole('super_admin'), match: (url) => url.startsWith('/app/items') },
                { label: 'Vendors', href: route('app.vendors.index'), icon: icon.vendor, show: can('view_any_vendors') || hasRole('super_admin'), match: (url) => url.startsWith('/app/vendors') },
                { label: 'Locations', href: route('app.locations.index'), icon: icon.location, show: can('view_any_location') || hasRole('super_admin'), match: (url) => url.startsWith('/app/locations') },
                { label: 'Projects', href: route('app.projects.index'), icon: icon.project, show: can('view_any_project') || hasRole('super_admin'), match: (url) => url.startsWith('/app/projects') },
                { label: 'Departments', href: route('app.departments.index'), icon: icon.dept, show: can('view_any_departments') || hasRole('super_admin'), match: (url) => url.startsWith('/app/departments') },
            ].filter((item) => item.show),
        },
        {
            key: 'access',
            label: 'Configurations',
            items: [
                { label: 'Users', href: route('app.users.index'), icon: icon.user, show: can('view_any_user') || hasRole('super_admin'), match: (url) => url.startsWith('/app/users') },
                { label: 'Roles', href: route('app.roles.index'), icon: icon.role, show: can('view_any_role') || hasRole('super_admin'), match: (url) => url.startsWith('/app/roles') },
                { label: 'Profile', href: route('app.profile'), icon: icon.profile, show: true, match: (url) => url.startsWith('/app/profile') },
            ].filter((item) => item.show),
        },
    ].filter((group) => group.items.length > 0),
);

function isActive(match) {
    return match(page.url);
}

function isGroupActive(items) {
    return items.some((item) => isActive(item.match));
}

const sidebarWidthClass = computed(() => (collapsed.value ? 'lg:w-[4.5rem]' : 'lg:w-[280px]'));
const mainOffsetClass = computed(() => (collapsed.value ? 'lg:pl-[4.5rem]' : 'lg:pl-[280px]'));

function navLinkClass(item, nested = false) {
    return [
        collapsed.value
            ? 'gap-3 px-3 py-2.5 lg:justify-center lg:gap-0 lg:px-0 lg:py-3'
            : nested
              ? 'gap-2.5 py-2 pl-3 pr-3'
              : 'gap-3 px-3 py-2.5',
        isActive(item.match)
            ? 'bg-brand-600/15 text-brand-600 ring-1 ring-brand-500/20 dark:text-brand-400'
            : 'text-sidebar-muted hover:bg-sidebar-active hover:text-slate-900 dark:hover:text-slate-100',
    ];
}

function userInitials(name) {
    if (!name) return 'U';
    return name
        .split(' ')
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
}

watch(mobileOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

watch(
    () => page.url,
    () => {
        closeMobile();
        navGroups.value.forEach((group) => {
            if (isGroupActive(group.items)) {
                setNavGroupExpanded(group.key, true);
            }
        });
    },
    { immediate: true },
);
</script>

<template>
    <div class="min-h-screen bg-canvas">
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm dark:bg-black/60 lg:hidden"
            @click="closeMobile"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[min(100vw-3rem,280px)] flex-col overflow-x-hidden border-r border-sidebar-border bg-sidebar transition-[width,transform] duration-300 ease-out"
            :class="[mobileOpen ? 'translate-x-0' : '-translate-x-full', 'lg:translate-x-0', sidebarWidthClass]"
        >
            <div
                class="flex shrink-0 items-center border-b border-sidebar-border px-3"
                :class="collapsed ? 'h-16 lg:h-auto lg:flex-col lg:gap-2 lg:px-2 lg:py-3' : 'h-16'"
            >
                <Link
                    :href="route('app.dashboard')"
                    class="flex items-center gap-3 overflow-hidden"
                    :class="collapsed ? 'min-w-0 flex-1 lg:flex-none lg:justify-center' : 'min-w-0 flex-1'"
                    @click="closeMobile"
                >
                    <AppBrandMark />
                    <div :class="collapsed ? 'lg:hidden' : ''" class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">Finance</p>
                        <p class="truncate text-xs text-sidebar-muted">Control center</p>
                    </div>
                </Link>

                <button
                    type="button"
                    class="hidden shrink-0 rounded-lg p-2 text-sidebar-muted transition hover:bg-sidebar-active hover:text-slate-900 dark:hover:text-white lg:inline-flex"
                    :class="collapsed ? 'lg:w-full lg:justify-center' : ''"
                    :title="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    @click="toggleCollapsed"
                >
                    <svg class="h-5 w-5 transition-transform duration-300" :class="collapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-hidden py-4" :class="collapsed ? 'px-3 lg:px-2' : 'px-3'">
                <Link
                    v-for="item in primaryNavItems"
                    :key="item.href"
                    :href="item.href"
                    :title="collapsed ? item.label : undefined"
                    class="ui-nav-link"
                    :class="navLinkClass(item)"
                    @click="closeMobile"
                >
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                    </svg>
                    <span :class="collapsed ? 'truncate lg:hidden' : 'truncate'">{{ item.label }}</span>
                </Link>

                <div v-for="group in navGroups" :key="group.key" class="pt-3">
                    <button
                        type="button"
                        class="mb-2 flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left transition hover:bg-sidebar-active hover:text-slate-900 dark:hover:text-slate-100"
                        :class="collapsed ? 'lg:hidden' : ''"
                        @click="toggleNavGroupExpanded(group.key)"
                    >
                        <span
                            class="text-[11px] font-semibold uppercase tracking-[0.12em]"
                            :class="isGroupActive(group.items) ? 'text-brand-600 dark:text-brand-400' : 'text-sidebar-muted'"
                        >
                            {{ group.label }}
                        </span>
                        <span class="ml-auto flex h-5 w-5 items-center justify-center text-sidebar-muted">
                            <svg class="h-4 w-4 transition-transform duration-200" :class="showNavGroupItems(group.key) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </span>
                    </button>

                    <div
                        v-show="showNavGroupItems(group.key)"
                        class="space-y-1"
                        :class="collapsed ? '' : 'rounded-xl border border-sidebar-border/70 bg-sidebar-active/30 p-1.5 dark:bg-surface-elevated/20'"
                    >
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            :title="collapsed ? item.label : undefined"
                            class="ui-nav-link"
                            :class="navLinkClass(item, true)"
                            @click="closeMobile"
                        >
                            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            <span :class="collapsed ? 'truncate lg:hidden' : 'truncate'">{{ item.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <div class="shrink-0 border-t border-sidebar-border p-3" :class="collapsed ? 'lg:p-2' : ''">
                <div
                    class="flex items-center gap-3 rounded-xl bg-surface-muted px-3 py-3 dark:bg-surface-elevated/80"
                    :class="collapsed ? 'lg:justify-center lg:px-0 lg:py-2' : ''"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-600/15 text-xs font-semibold text-brand-700 dark:bg-brand-600/20 dark:text-brand-400">
                        {{ userInitials(user?.name) }}
                    </div>
                    <div :class="collapsed ? 'lg:hidden' : ''" class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
                        <p class="truncate text-xs text-sidebar-muted">{{ user?.email }}</p>
                    </div>
                </div>

                <Link
                    :href="route('app.logout')"
                    method="post"
                    as="button"
                    :title="collapsed ? 'Sign out' : undefined"
                    class="mt-2 flex w-full items-center justify-center rounded-xl border border-sidebar-border text-sidebar-muted transition hover:border-slate-300 hover:bg-sidebar-active hover:text-slate-900 dark:hover:border-slate-600 dark:hover:text-white"
                    :class="collapsed ? 'p-2.5 lg:px-0' : 'px-3 py-2 text-sm'"
                    @click="closeMobile"
                >
                    <svg class="h-5 w-5 shrink-0" :class="collapsed ? 'lg:block' : 'hidden'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span :class="collapsed ? 'lg:hidden' : ''">Sign out</span>
                </Link>
            </div>
        </aside>

        <div class="flex min-h-screen flex-col transition-[padding] duration-300 ease-out" :class="mainOffsetClass">
            <header class="sticky top-0 z-30 border-b border-slate-200/80 bg-canvas/80 backdrop-blur-xl dark:border-slate-800/80">
                <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            class="inline-flex rounded-xl border border-slate-200 bg-surface-elevated p-2.5 text-slate-600 transition hover:bg-surface-muted dark:border-slate-800 dark:text-slate-300 lg:hidden"
                            @click="openMobile"
                        >
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-3 sm:gap-4">
                        <ThemeToggle />
                        <div class="hidden items-center gap-3 lg:flex">
                            <div class="text-right">
                                <p class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ user?.name }}</p>
                                <p class="text-xs capitalize text-slate-500">{{ primaryRole }}</p>
                            </div>
                            <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600/15 text-xs font-semibold text-brand-700 dark:bg-brand-600/20 dark:text-brand-400">
                                {{ userInitials(user?.name) }}
                            </div>
                        </div>
                        <Link
                            :href="route('app.logout')"
                            method="post"
                            as="button"
                            class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-medium text-slate-500 transition hover:bg-surface-elevated hover:text-slate-900 dark:border-slate-800 dark:text-slate-400 dark:hover:text-white lg:hidden"
                        >
                            Sign out
                        </Link>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] sm:px-6 lg:px-8 lg:py-8">
                <div class="mb-6 lg:mb-8">
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white lg:text-2xl">
                        <slot name="header">{{ title }}</slot>
                    </h1>
                    <p v-if="description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
                </div>

                <FlashMessage />
                <slot />
            </main>
        </div>
    </div>
</template>
