<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePermissions } from '../Composables/usePermissions';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { can, hasRole } = usePermissions();

const modules = computed(() => [
    {
        title: 'Purchase Requests',
        blurb: 'Create, route, and close PRs',
        href: route('app.purchase-requests.index'),
        show: can('view_any_purchase::requests') || hasRole('super_admin'),
    },
    {
        title: 'Procure',
        blurb: 'Orders and advance forms',
        href: route('app.purchase-orders.index'),
        show: can('view_any_purchase::orders') || hasRole('super_admin'),
    },
    {
        title: 'Petty Cash',
        blurb: 'Reimburse and approve',
        href: route('app.petty-cash.index'),
        show: can('view_any_petty::cash::reimbursment') || hasRole('super_admin'),
    },
    {
        title: 'Assets',
        blurb: 'Receive and serial sync',
        href: route('app.asset-management.index'),
        show: can('view_any_asset::management') || hasRole('super_admin'),
    },
    {
        title: 'Reports',
        blurb: 'Templates and CSV export',
        href: route('app.reports.index'),
        show: can('view_any_report') || hasRole('super_admin'),
    },
    {
        title: 'Budgets',
        blurb: 'Accounts and top-ups',
        href: route('app.budget-accounts.index'),
        show: can('view_any_budget::accounts') || hasRole('super_admin'),
    },
].filter((m) => m.show));
</script>

<template>
    <AppLayout title="Dashboard" description="Jump into a finance workflow.">
        <template #header>Dashboard</template>

        <div class="mb-6 rounded-xl border border-slate-200 bg-surface px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
            Welcome back, {{ user?.name }}. Filament remains available at
            <a href="/admin" class="font-medium text-brand-700 underline dark:text-brand-400">/admin</a>
            for reference.
        </div>

        <section class="ui-card">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Modules</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Quick access based on your permissions</p>
                </div>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                <Link
                    v-for="mod in modules"
                    :key="mod.title"
                    :href="mod.href"
                    class="flex items-center justify-between gap-4 px-6 py-4 transition hover:bg-surface-muted"
                >
                    <div>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ mod.title }}</p>
                        <p class="mt-0.5 text-sm text-slate-500">{{ mod.blurb }}</p>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </Link>
            </div>
        </section>
    </AppLayout>
</template>
