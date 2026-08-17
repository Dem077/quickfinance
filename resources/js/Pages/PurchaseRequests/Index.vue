<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateRangePicker from '../../Components/UiDateRangePicker.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    records: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    tabs: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const projectId = ref(props.filters.project_id ? String(props.filters.project_id) : '');
const departmentId = ref(props.filters.department_id ? String(props.filters.department_id) : '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const sort = ref(props.filters.sort || 'newest');
const filtersOpen = ref(false);
const filterPanel = ref(null);
let searchTimer = null;

const remarkForm = useForm({ cancel_remark: '' });
const remarkAction = ref(null);
const remarkRecordId = ref(null);

const activeTab = computed(() => props.filters.tab || props.tabs[0]?.key || 'all');
const activeTabMeta = computed(() => props.tabs.find((tab) => tab.key === activeTab.value) || null);
const hasRows = computed(() => (props.records.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const activeFilterCount = computed(() =>
    [projectId.value, departmentId.value, dateFrom.value, dateTo.value].filter(Boolean).length,
);
const hasExtraFilters = computed(() => activeFilterCount.value > 0);
const hasNonDefaultSort = computed(() => sort.value && sort.value !== 'newest');
const hasActiveFilters = computed(() => hasSearch.value || hasExtraFilters.value || hasNonDefaultSort.value);
const rangeLabel = computed(() => {
    const from = props.records.from;
    const to = props.records.to;
    const total = props.records.total;
    if (! total) return null;
    return `${from}–${to} of ${total}`;
});

const applyFilters = (overrides = {}) => {
    const nextProject = overrides.project_id !== undefined ? overrides.project_id : projectId.value;
    const nextDepartment = overrides.department_id !== undefined ? overrides.department_id : departmentId.value;
    const nextDateFrom = overrides.date_from !== undefined ? overrides.date_from : dateFrom.value;
    const nextDateTo = overrides.date_to !== undefined ? overrides.date_to : dateTo.value;
    const nextSort = overrides.sort !== undefined ? overrides.sort : sort.value;

    router.get(route('app.purchase-requests.index'), {
        search: (overrides.search !== undefined ? overrides.search : search.value) || undefined,
        tab: overrides.tab ?? activeTab.value,
        project_id: nextProject || undefined,
        department_id: nextDepartment || undefined,
        date_from: nextDateFrom || undefined,
        date_to: nextDateTo || undefined,
        sort: nextSort && nextSort !== 'newest' ? nextSort : undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value || '';
        const current = props.filters.search || '';
        if (next === current) return;
        applyFilters({ search: value });
    }, 280);
});

watch([projectId, departmentId, dateFrom, dateTo, sort], () => {
    applyFilters();
});

const selectTab = (tab) => {
    if (tab === activeTab.value) return;
    applyFilters({ tab });
};

const clearSearch = () => {
    clearTimeout(searchTimer);
    search.value = '';
    if (props.filters.search) {
        applyFilters({ search: '' });
    }
};

const clearExtraFilters = () => {
    projectId.value = '';
    departmentId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    sort.value = 'newest';
};

const toggleFilters = () => {
    filtersOpen.value = ! filtersOpen.value;
};

const onDocumentClick = (event) => {
    if (! filtersOpen.value || ! filterPanel.value) return;
    const target = event.target;
    if (target?.closest?.('.daterangepicker') || target?.closest?.('.flatpickr-calendar')) return;
    if (! filterPanel.value.contains(target)) {
        filtersOpen.value = false;
    }
};

const onDocumentKeydown = (event) => {
    if (event.key === 'Escape') {
        filtersOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
});

const openRow = (id) => {
    router.visit(route('app.purchase-requests.show', id));
};

const formatDate = (value) => {
    if (! value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const formatMoney = (value) =>
    Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const statusBadgeClass = (status) => {
    const map = {
        draft: 'ui-badge-neutral',
        submitted: 'ui-badge-warn',
        hod_approved: 'ui-badge-success',
        hod_rejected: 'ui-badge-danger',
        approved: 'ui-badge-success',
        rejected: 'ui-badge-danger',
        md_dmd_approved: 'ui-badge-success',
        md_dmd_rejected: 'ui-badge-danger',
        canceled: 'ui-badge-danger',
        closed: 'ui-badge-success',
        document_uploaded: 'ui-badge-brand',
    };
    return map[status] || 'ui-badge-neutral';
};

const tabBadgeClass = (tone, active) => {
    if (active) {
        return {
            neutral: 'bg-brand-100 text-brand-800 dark:bg-brand-950/50 dark:text-brand-200',
            warn: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
            success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
            danger: 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200',
        }[tone] || 'bg-brand-100 text-brand-800';
    }

    return {
        neutral: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        warn: 'bg-amber-50 text-amber-600 dark:bg-amber-950/30 dark:text-amber-400',
        success: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400',
        danger: 'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400',
    }[tone] || 'bg-slate-100 text-slate-500';
};

const btnSm = {
    ghost: 'inline-flex items-center justify-center rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
    secondary: 'inline-flex items-center justify-center rounded-lg border border-slate-200 bg-surface px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-surface-muted dark:border-slate-700 dark:text-slate-200',
    primary: 'inline-flex items-center justify-center rounded-lg bg-brand-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-brand-500',
    success: 'inline-flex items-center justify-center rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-emerald-500',
    danger: 'inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300',
    warn: 'inline-flex items-center justify-center rounded-lg bg-amber-500 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-amber-400',
};

const cardActions = (row) => {
    const actions = row.actions || {};
    const list = [];

    if (actions.submit) {
        list.push({ key: 'submit', label: 'Submit', tone: 'primary', type: 'post', route: 'submit' });
    }
    if (actions.hodApprove) {
        list.push({ key: 'hod-approve', label: 'Approve', tone: 'success', type: 'post', route: 'hod-approve' });
    }
    if (actions.hodReject) {
        list.push({ key: 'hod-reject', label: 'Reject', tone: 'danger', type: 'post', route: 'hod-reject' });
    }
    if (actions.financeApprove) {
        list.push({ key: 'finance-approve', label: 'Approve', tone: 'success', type: 'post', route: 'finance-approve' });
    }
    if (actions.financeReject) {
        list.push({ key: 'finance-reject', label: 'Reject', tone: 'danger', type: 'remark', route: 'finance-reject' });
    }
    if (actions.sendBack) {
        list.push({ key: 'send-back', label: 'Send back', tone: 'warn', type: 'post', route: 'send-back' });
    }
    if (actions.cancel) {
        list.push({ key: 'cancel', label: 'Cancel', tone: 'danger', type: 'remark', route: 'cancel' });
    }
    if (actions.mdDmdApprove) {
        list.push({ key: 'md-approve', label: 'Approve', tone: 'success', type: 'post', route: 'md-dmd-approve' });
    }
    if (actions.mdDmdReject) {
        list.push({ key: 'md-reject', label: 'Reject', tone: 'danger', type: 'post', route: 'md-dmd-reject' });
    }
    if (actions.close) {
        list.push({ key: 'close', label: 'Close', tone: 'primary', type: 'link', href: route('app.purchase-requests.show', row.id) });
    }
    if (actions.edit) {
        list.push({ key: 'edit', label: 'Edit', tone: 'secondary', type: 'link', href: route('app.purchase-requests.edit', row.id) });
    }
    if (actions.delete) {
        list.push({ key: 'delete', label: 'Delete', tone: 'danger', type: 'delete' });
    }

    return list;
};

const runCardAction = (row, action) => {
    if (action.type === 'post') {
        if (! confirm(`${action.label} this purchase request?`)) return;
        router.post(route(`app.purchase-requests.${action.route}`, row.id), {}, { preserveScroll: true });
        return;
    }

    if (action.type === 'remark') {
        remarkRecordId.value = row.id;
        remarkAction.value = action.route;
        remarkForm.reset();
        remarkForm.clearErrors();
        return;
    }

    if (action.type === 'delete') {
        if (! confirm('Delete this purchase request?')) return;
        router.delete(route('app.purchase-requests.destroy', row.id), { preserveScroll: true });
    }
};

const closeRemark = () => {
    remarkAction.value = null;
    remarkRecordId.value = null;
    remarkForm.reset();
};

const submitRemark = () => {
    if (! remarkAction.value || ! remarkRecordId.value) return;
    remarkForm.post(route(`app.purchase-requests.${remarkAction.value}`, remarkRecordId.value), {
        preserveScroll: true,
        onSuccess: () => closeRemark(),
    });
};
</script>

<template>
    <AppLayout description="Review, filter, and open purchase requests by workflow stage.">
        <template #header>Purchase Requests</template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex w-full max-w-xl items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <svg
                            class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            aria-hidden="true"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search PR no, purpose, requester, project…"
                            class="ui-input pl-9 pr-9"
                            aria-label="Search purchase requests"
                        />
                        <button
                            v-if="hasSearch"
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-medium text-slate-500 transition hover:bg-surface-muted hover:text-slate-800 dark:hover:text-slate-200"
                            @click="clearSearch"
                        >
                            Clear
                        </button>
                    </div>

                    <div ref="filterPanel" class="relative shrink-0">
                        <button
                            type="button"
                            class="relative inline-flex h-[42px] w-[42px] items-center justify-center rounded-lg border border-slate-200 bg-surface text-slate-600 transition hover:border-slate-300 hover:bg-surface-muted hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white"
                            :class="filtersOpen || hasExtraFilters || hasNonDefaultSort
                                ? 'border-brand-300 text-brand-700 dark:border-brand-700 dark:text-brand-300'
                                : ''"
                            :aria-expanded="filtersOpen"
                            aria-haspopup="dialog"
                            aria-label="Open filters"
                            @click.stop="toggleFilters"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />
                            </svg>
                            <span
                                v-if="activeFilterCount || hasNonDefaultSort"
                                class="absolute -right-1.5 -top-1.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-600 px-1 text-[10px] font-semibold text-white"
                            >
                                {{ activeFilterCount + (hasNonDefaultSort ? 1 : 0) }}
                            </span>
                        </button>

                        <div
                            v-if="filtersOpen"
                            class="absolute right-0 z-40 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-3 shadow-lg shadow-slate-200/70 dark:border-slate-700 dark:bg-surface-elevated dark:shadow-black/30"
                            role="dialog"
                            aria-label="Purchase request filters"
                            @click.stop
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">Filters</p>
                                <button
                                    v-if="hasExtraFilters || hasNonDefaultSort"
                                    type="button"
                                    class="text-xs font-medium text-brand-700 hover:text-brand-600 dark:text-brand-400"
                                    @click="clearExtraFilters"
                                >
                                    Clear
                                </button>
                            </div>

                            <div class="space-y-3">
                                <div>
                                    <label class="ui-label">Sort by</label>
                                    <select v-model="sort" class="ui-select">
                                        <option
                                            v-for="option in filterOptions.sorts"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="ui-label">Project</label>
                                    <select v-model="projectId" class="ui-select">
                                        <option value="">All projects</option>
                                        <option
                                            v-for="project in filterOptions.projects"
                                            :key="project.id"
                                            :value="String(project.id)"
                                        >
                                            {{ project.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="ui-label">Department</label>
                                    <select v-model="departmentId" class="ui-select">
                                        <option value="">All departments</option>
                                        <option
                                            v-for="department in filterOptions.departments"
                                            :key="department.id"
                                            :value="String(department.id)"
                                        >
                                            {{ department.name }}
                                        </option>
                                    </select>
                                </div>

                                <UiDateRangePicker
                                    id="pr-filter-dates"
                                    v-model:from="dateFrom"
                                    v-model:to="dateTo"
                                    label="Date range"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <Link
                    v-if="can.create"
                    :href="route('app.purchase-requests.create')"
                    class="ui-btn-primary inline-flex shrink-0 gap-1.5"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New request
                </Link>
            </div>

            <div v-if="tabs.length" class="overflow-x-auto border-b border-slate-200 dark:border-slate-800">
                <div
                    class="flex min-w-max items-end justify-center sm:min-w-0 sm:w-full"
                    role="tablist"
                    aria-label="Purchase request status"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.key"
                        class="relative inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-3.5 py-2.5 text-sm transition"
                        :class="activeTab === tab.key
                            ? 'border-brand-600 font-semibold text-brand-700 dark:border-brand-400 dark:text-brand-300'
                            : 'border-transparent font-medium text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200'"
                        @click="selectTab(tab.key)"
                    >
                        <span>{{ tab.label }}</span>
                        <span
                            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1.5 text-[11px] font-semibold leading-none tabular-nums"
                            :class="tabBadgeClass(tab.tone, activeTab === tab.key)"
                        >
                            {{ tab.badge }}
                        </span>
                    </button>
                </div>
            </div>

            <div
                v-if="rangeLabel || hasActiveFilters"
                class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500 dark:text-slate-400"
            >
                <p>
                    <span v-if="activeTabMeta">{{ activeTabMeta.label }}</span>
                    <span v-if="activeTabMeta && rangeLabel"> · </span>
                    <span v-if="rangeLabel">{{ rangeLabel }}</span>
                </p>
                <p v-if="hasSearch" class="truncate">“{{ search.trim() }}”</p>
            </div>

            <div
                v-if="hasRows"
                class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="row in records.data"
                    :key="row.id"
                    role="button"
                    tabindex="0"
                    class="group flex cursor-pointer flex-col rounded-xl border border-slate-200/90 bg-surface p-4 transition hover:border-brand-400/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30 dark:border-slate-800 dark:hover:border-brand-500/40"
                    @click="openRow(row.id)"
                    @keydown.enter.prevent="openRow(row.id)"
                    @keydown.space.prevent="openRow(row.id)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="truncate text-base font-semibold tracking-tight text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-300">
                            {{ row.pr_no }}
                        </h2>
                        <span :class="statusBadgeClass(row.status)" class="shrink-0">{{ row.status_label }}</span>
                    </div>

                    <p class="mt-2 line-clamp-2 min-h-[2.5rem] text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ row.purpose || 'No purpose provided' }}
                    </p>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Requester</dt>
                            <dd class="truncate text-right font-medium text-slate-700 dark:text-slate-200">
                                {{ row.user || '—' }}
                            </dd>
                        </div>
                        <div v-if="row.department" class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Department</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">
                                {{ row.department }}
                            </dd>
                        </div>
                        <div v-if="row.project" class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Project</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">
                                {{ row.project }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Date</dt>
                            <dd class="tabular-nums text-right text-slate-700 dark:text-slate-300">
                                {{ formatDate(row.date) }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Total</dt>
                            <dd class="tabular-nums text-right font-semibold text-slate-900 dark:text-white">
                                MVR {{ formatMoney(row.total_est_cost) }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        class="mt-auto flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-800"
                        @click.stop
                        @keydown.stop
                    >
                        <Link
                            :href="route('app.purchase-requests.show', row.id)"
                            :class="btnSm.ghost"
                        >
                            View
                        </Link>
                        <template v-for="action in cardActions(row)" :key="action.key">
                            <Link
                                v-if="action.type === 'link'"
                                :href="action.href"
                                :class="btnSm[action.tone] || btnSm.secondary"
                            >
                                {{ action.label }}
                            </Link>
                            <button
                                v-else
                                type="button"
                                :class="btnSm[action.tone] || btnSm.secondary"
                                @click="runCardAction(row, action)"
                            >
                                {{ action.label }}
                            </button>
                        </template>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-surface px-6 py-16 text-center dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ hasSearch ? 'No matching requests' : 'Nothing in this tab' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                    <template v-if="hasSearch">
                        Try another search, or clear the filter to see requests in {{ activeTabMeta?.label || 'this tab' }}.
                    </template>
                    <template v-else>
                        There are no purchase requests in {{ activeTabMeta?.label || 'this view' }} right now.
                    </template>
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                    <button
                        v-if="hasSearch"
                        type="button"
                        class="ui-btn-secondary"
                        @click="clearSearch"
                    >
                        Clear search
                    </button>
                    <button
                        v-if="activeTab !== 'all' && tabs.some((t) => t.key === 'all')"
                        type="button"
                        class="ui-btn-ghost"
                        @click="selectTab('all')"
                    >
                        View all
                    </button>
                    <Link
                        v-if="can.create && !hasSearch"
                        :href="route('app.purchase-requests.create')"
                        class="ui-btn-primary"
                    >
                        Create request
                    </Link>
                </div>
            </div>

            <div
                v-if="records.links?.length > 3"
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ rangeLabel || ' ' }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <template v-for="(link, index) in records.links" :key="`${link.label}-${index}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-xl border px-2.5 text-sm transition"
                            :class="link.active
                                ? 'border-brand-600 bg-brand-600 text-white'
                                : 'border-slate-200 bg-surface text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'"
                            :preserve-scroll="true"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-xl border border-transparent px-2.5 text-sm text-slate-300 dark:text-slate-600"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="remarkAction"
                class="fixed inset-0 z-[300] flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeRemark" />
                <form
                    class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated"
                    @submit.prevent="submitRemark"
                >
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ remarkAction === 'cancel' ? 'Cancel purchase request' : 'Reject purchase request' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Provide a clear reason for the record.</p>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <label class="ui-label">Reason</label>
                            <textarea
                                v-model="remarkForm.cancel_remark"
                                required
                                rows="4"
                                class="ui-input"
                                placeholder="Explain why…"
                            />
                            <p v-if="remarkForm.errors.cancel_remark" class="mt-1 text-sm text-red-600">
                                {{ remarkForm.errors.cancel_remark }}
                            </p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeRemark">Cancel</button>
                        <button type="submit" class="ui-btn-danger" :disabled="remarkForm.processing">Confirm</button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
