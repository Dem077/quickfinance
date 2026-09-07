<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import UiChart from '../Components/UiChart.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { usePermissions } from '../Composables/usePermissions';

const props = defineProps({
    charts: { type: Array, default: () => [] },
    canManageCharts: { type: Boolean, default: false },
    canCreateCharts: { type: Boolean, default: false },
});

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
        title: 'Dashboard charts',
        blurb: 'Build the charts on this page',
        href: route('app.charts.index'),
        show: can('view_any_chart') || hasRole('super_admin'),
    },
    {
        title: 'Budgets',
        blurb: 'Accounts and top-ups',
        href: route('app.budget-accounts.index'),
        show: can('view_any_budget::accounts') || hasRole('super_admin'),
    },
].filter((mod) => mod.show));

const aggregationLabel = (chart) => {
    if (chart.aggregation === 'sum') return `Sum of ${chart.metric_label || 'value'}`;
    if (chart.aggregation === 'avg') return `Average ${chart.metric_label || 'value'}`;
    return 'Record count';
};
</script>

<template>
    <AppLayout title="Dashboard" description="Jump into a finance workflow.">
        <template #header>Dashboard</template>

        <div class="mb-6 rounded-xl border border-slate-200 bg-surface px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
            Welcome back, {{ user?.name }}.
        </div>

        <section v-if="charts.length || canManageCharts" class="mb-6 space-y-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Charts</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Configured from Reports → Dashboard charts</p>
                </div>
                <Link v-if="canManageCharts" :href="route('app.charts.index')" class="ui-btn-ghost text-sm">
                    Manage
                </Link>
            </div>

            <div v-if="charts.length" class="grid gap-4 xl:grid-cols-2">
                <article v-for="chart in charts" :key="chart.id" class="ui-card p-4">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-semibold text-slate-900 dark:text-white">{{ chart.name }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ chart.model_label }} · {{ aggregationLabel(chart) }}
                                <template v-if="chart.group_label"> · by {{ chart.group_label }}</template>
                            </p>
                        </div>
                    </div>
                    <UiChart :type="chart.chart_type" :labels="chart.labels" :values="chart.values" />
                </article>
            </div>

            <div
                v-else
                class="rounded-xl border border-dashed border-slate-200 px-6 py-10 text-center dark:border-slate-800"
            >
                <p class="text-sm font-medium text-slate-900 dark:text-white">No dashboard charts yet</p>
                <p class="mt-1 text-sm text-slate-500">Create one with a data source, grouping, and optional filters.</p>
                <Link v-if="canCreateCharts" :href="route('app.charts.create')" class="ui-btn-primary mt-4">Create chart</Link>
            </div>
        </section>

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
