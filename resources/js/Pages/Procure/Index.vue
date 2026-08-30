<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiAuditLink from '../../Components/UiAuditLink.vue';
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
const paymentMethod = ref(props.filters.payment_method ?? '');
const vendorId = ref(props.filters.vendor_id ? String(props.filters.vendor_id) : '');
const prId = ref(props.filters.pr_id ? String(props.filters.pr_id) : '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const sort = ref(props.filters.sort || 'newest');
const filtersOpen = ref(false);
const filterPanel = ref(null);
let searchTimer = null;

const activeTab = computed(() => props.filters.tab || props.tabs[0]?.key || 'all');
const activeTabMeta = computed(() => props.tabs.find((tab) => tab.key === activeTab.value) || null);
const hasRows = computed(() => (props.records.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const activeFilterCount = computed(() =>
    [paymentMethod.value, vendorId.value, prId.value, dateFrom.value, dateTo.value].filter(Boolean).length,
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
    router.get(route('app.purchase-orders.index'), {
        search: (overrides.search !== undefined ? overrides.search : search.value) || undefined,
        tab: overrides.tab ?? activeTab.value,
        payment_method: (overrides.payment_method !== undefined ? overrides.payment_method : paymentMethod.value) || undefined,
        vendor_id: (overrides.vendor_id !== undefined ? overrides.vendor_id : vendorId.value) || undefined,
        pr_id: (overrides.pr_id !== undefined ? overrides.pr_id : prId.value) || undefined,
        date_from: (overrides.date_from !== undefined ? overrides.date_from : dateFrom.value) || undefined,
        date_to: (overrides.date_to !== undefined ? overrides.date_to : dateTo.value) || undefined,
        sort: (() => {
            const nextSort = overrides.sort !== undefined ? overrides.sort : sort.value;
            return nextSort && nextSort !== 'newest' ? nextSort : undefined;
        })(),
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

watch([paymentMethod, vendorId, prId, dateFrom, dateTo, sort], () => {
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
    paymentMethod.value = '';
    vendorId.value = '';
    prId.value = '';
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
    router.visit(route('app.purchase-orders.show', id));
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
        grn_created: 'ui-badge-brand',
        reimbursement_pending: 'ui-badge-warn',
        reimbursed: 'ui-badge-success',
        closed: 'ui-badge-success',
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
    warn: 'inline-flex items-center justify-center rounded-lg bg-amber-500 px-2.5 py-1.5 text-xs font-medium text-white transition hover:bg-amber-400',
    danger: 'inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300',
};

const advanceModal = ref(null);
const advanceTarget = ref(null);
const advanceForm = useForm({
    qoation_no: '',
    expected_delivery: '',
    advance_amount: '',
});

const cardActions = (row) => {
    const actions = row.actions || {};
    const list = [];

    if (actions.edit) {
        list.push({
            key: 'edit',
            label: 'Edit',
            tone: 'secondary',
            type: 'link',
            href: route('app.purchase-orders.edit', row.id),
        });
    }
    if (actions.submit) {
        list.push({ key: 'submit', label: 'Submit', tone: 'primary', type: 'post', route: 'submit' });
    }
    if (actions.generate_advance_form) {
        list.push({ key: 'generate-advance', label: 'Generate advance form', tone: 'primary', type: 'advance-modal', modal: 'generate' });
    }
    if (actions.regenerate_advance_form) {
        list.push({ key: 'regenerate-advance', label: 'Regenerate form', tone: 'warn', type: 'advance-modal', modal: 'regenerate' });
    }
    if (actions.submit_advance_form) {
        list.push({
            key: 'submit-advance',
            label: 'Submit advance form',
            tone: 'success',
            type: 'confirm-post',
            route: 'advance-form.submit',
            confirm: 'Submit advance form for HOD approval?',
        });
    }
    if (actions.hod_approve_advance_form) {
        list.push({
            key: 'hod-approve',
            label: 'HOD approve',
            tone: 'success',
            type: 'confirm-post',
            route: 'advance-form.hod-approve',
            confirm: 'Approve this advance form as HOD?',
        });
        list.push({
            key: 'hod-reject',
            label: 'HOD reject',
            tone: 'danger',
            type: 'confirm-post',
            route: 'advance-form.hod-reject',
            confirm: 'Reject this advance form as HOD?',
        });
    }
    if (actions.md_dmd_approve_advance_form) {
        list.push({
            key: 'md-approve',
            label: 'MD/DMD approve',
            tone: 'success',
            type: 'confirm-post',
            route: 'advance-form.md-dmd-approve',
            confirm: 'Approve this advance form as MD/DMD?',
        });
        list.push({
            key: 'md-reject',
            label: 'MD/DMD reject',
            tone: 'danger',
            type: 'confirm-post',
            route: 'advance-form.md-dmd-reject',
            confirm: 'Reject this advance form as MD/DMD?',
        });
    }
    if (actions.view_advance_form && row.advance_form_pdf_url) {
        list.push({
            key: 'view-advance',
            label: 'View form',
            tone: 'secondary',
            type: 'external',
            href: row.advance_form_pdf_url,
        });
    }
    if (actions.close) {
        list.push({
            key: 'close',
            label: 'Close',
            tone: 'secondary',
            type: 'link',
            href: route('app.purchase-orders.show', row.id),
        });
    }
    if (actions.delete) {
        list.push({ key: 'delete', label: 'Delete', tone: 'danger', type: 'delete' });
    }

    return list;
};

const runCardAction = (row, action) => {
    if (action.type === 'post') {
        router.post(route(`app.purchase-orders.${action.route}`, row.id), {}, { preserveScroll: true });
        return;
    }
    if (action.type === 'confirm-post') {
        if (! confirm(action.confirm)) return;
        router.post(route(`app.purchase-orders.${action.route}`, row.id), {}, { preserveScroll: true });
        return;
    }
    if (action.type === 'advance-modal') {
        openAdvanceModal(row, action.modal);
        return;
    }
    if (action.type === 'delete') {
        if (! confirm('Delete this purchase order?')) return;
        router.delete(route('app.purchase-orders.destroy', row.id), { preserveScroll: true });
    }
};

const openAdvanceModal = (row, mode) => {
    advanceTarget.value = row;
    advanceModal.value = mode;
    advanceForm.clearErrors();
    advanceForm.qoation_no = row.advance_form?.qoation_no ?? '';
    advanceForm.expected_delivery = row.advance_form?.expected_delivery ?? '';
    advanceForm.advance_amount = row.advance_form?.advance_percentage ?? '';
};

const closeAdvanceModal = () => {
    advanceModal.value = null;
    advanceTarget.value = null;
    advanceForm.reset();
    advanceForm.clearErrors();
};

const submitAdvanceGenerate = () => {
    if (! advanceTarget.value) return;
    const routeName = advanceModal.value === 'regenerate'
        ? 'app.purchase-orders.advance-form.regenerate'
        : 'app.purchase-orders.advance-form.generate';

    advanceForm.post(route(routeName, advanceTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (routeName === 'app.purchase-orders.advance-form.regenerate') {
                window.open(route('purchase-orders.advance-form.download', advanceTarget.value.id), '_blank');
            }
            closeAdvanceModal();
        },
    });
};
</script>

<template>
    <AppLayout description="Review, filter, and open purchase orders by status.">
        <template #header>Purchase Orders</template>

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
                            placeholder="Search PO no, vendor, PR, purpose…"
                            class="ui-input pl-9 pr-9"
                            aria-label="Search purchase orders"
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
                            aria-label="Purchase order filters"
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
                                    <label class="ui-label">Payment method</label>
                                    <select v-model="paymentMethod" class="ui-select">
                                        <option value="">All methods</option>
                                        <option value="purchase_order">Purchase Order</option>
                                        <option value="petty_cash">Petty Cash</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="ui-label">Vendor</label>
                                    <select v-model="vendorId" class="ui-select">
                                        <option value="">All vendors</option>
                                        <option
                                            v-for="vendor in filterOptions.vendors"
                                            :key="vendor.id"
                                            :value="String(vendor.id)"
                                        >
                                            {{ vendor.name }}
                                        </option>
                                    </select>
                                </div>

                                <div>
                                    <label class="ui-label">Purchase request</label>
                                    <select v-model="prId" class="ui-select">
                                        <option value="">All PRs</option>
                                        <option
                                            v-for="pr in filterOptions.purchaseRequests"
                                            :key="pr.id"
                                            :value="String(pr.id)"
                                        >
                                            {{ pr.pr_no }}
                                        </option>
                                    </select>
                                </div>

                                <UiDateRangePicker
                                    id="po-filter-dates"
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
                    :href="route('app.purchase-orders.create')"
                    class="ui-btn-primary inline-flex shrink-0 gap-1.5"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New order
                </Link>
            </div>

            <div v-if="tabs.length" class="overflow-x-auto border-b border-slate-200 dark:border-slate-800">
                <div
                    class="flex min-w-max items-end justify-center sm:min-w-0 sm:w-full"
                    role="tablist"
                    aria-label="Purchase order status"
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
                            {{ row.po_no }}
                        </h2>
                        <div class="flex shrink-0 items-center gap-1" @click.stop @keydown.stop>
                            <span :class="statusBadgeClass(row.status)">{{ row.status_label }}</span>
                            <UiAuditLink
                                v-if="can.audit"
                                :href="route('app.purchase-orders.audit', row.id)"
                            />
                        </div>
                    </div>

                    <p class="mt-2 line-clamp-2 min-h-[2.5rem] text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ row.purpose || 'No purpose provided' }}
                    </p>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Vendor</dt>
                            <dd class="truncate text-right font-medium text-slate-700 dark:text-slate-200">
                                {{ row.vendor || '—' }}
                            </dd>
                        </div>
                        <div v-if="row.pr_no" class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">PR</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">
                                {{ row.pr_no }}
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Method</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">
                                {{ row.payment_method_label }}
                            </dd>
                        </div>
                        <div v-if="row.advance_form_status_label" class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Advance</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">
                                {{ row.advance_form_status_label }}
                            </dd>
                        </div>
                        <div v-else-if="row.is_advance_form_required" class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Advance</dt>
                            <dd class="truncate text-right text-amber-700 dark:text-amber-300">
                                Required
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
                                MVR {{ formatMoney(row.total_amount) }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        class="mt-auto flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-800"
                        @click.stop
                        @keydown.stop
                    >
                        <Link
                            :href="route('app.purchase-orders.show', row.id)"
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
                            <a
                                v-else-if="action.type === 'external'"
                                :href="action.href"
                                target="_blank"
                                rel="noopener"
                                :class="btnSm[action.tone] || btnSm.secondary"
                            >
                                {{ action.label }}
                            </a>
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
                    {{ hasSearch ? 'No matching orders' : 'Nothing in this tab' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                    <template v-if="hasSearch">
                        Try another search, or clear the filter to see orders in {{ activeTabMeta?.label || 'this tab' }}.
                    </template>
                    <template v-else>
                        There are no purchase orders in {{ activeTabMeta?.label || 'this view' }} right now.
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
                        :href="route('app.purchase-orders.create')"
                        class="ui-btn-primary"
                    >
                        Create order
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
                v-if="advanceModal"
                class="fixed inset-0 z-[300] flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeAdvanceModal" />
                <form
                    class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated"
                    @submit.prevent="submitAdvanceGenerate"
                >
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ advanceModal === 'regenerate' ? 'Regenerate advance form' : 'Generate advance form' }}
                        </h3>
                        <p v-if="advanceTarget?.po_no" class="mt-1 text-sm text-slate-500">
                            {{ advanceTarget.po_no }}
                        </p>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <label class="ui-label">Quotation no</label>
                            <input v-model="advanceForm.qoation_no" type="text" required class="ui-input" />
                            <p v-if="advanceForm.errors.qoation_no" class="mt-1 text-sm text-rose-600">{{ advanceForm.errors.qoation_no }}</p>
                        </div>
                        <div>
                            <label class="ui-label">Expected delivery (days)</label>
                            <input v-model="advanceForm.expected_delivery" type="number" min="1" required class="ui-input" />
                            <p v-if="advanceForm.errors.expected_delivery" class="mt-1 text-sm text-rose-600">{{ advanceForm.errors.expected_delivery }}</p>
                        </div>
                        <div>
                            <label class="ui-label">Advance amount %</label>
                            <input v-model="advanceForm.advance_amount" type="number" min="0" max="100" step="0.01" required class="ui-input" />
                            <p v-if="advanceForm.errors.advance_amount" class="mt-1 text-sm text-rose-600">{{ advanceForm.errors.advance_amount }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeAdvanceModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="advanceForm.processing">
                            {{ advanceModal === 'regenerate' ? 'Regenerate' : 'Generate' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
