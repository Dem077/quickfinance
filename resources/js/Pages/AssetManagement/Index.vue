<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateRangePicker from '../../Components/UiDateRangePicker.vue';
import UiIndexTabs from '../../Components/UiIndexTabs.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
    tabs: { type: Array, required: true },
    pinnedTab: { type: String, default: null },
    filterOptions: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const vendorId = ref(props.filters.vendor_id ? String(props.filters.vendor_id) : '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const filtersOpen = ref(false);
const filterPanel = ref(null);
let searchTimer = null;

const activeTab = computed(() => props.filters.tab || 'all');
const activeTabMeta = computed(() => props.tabs.find((tab) => tab.key === activeTab.value) || null);
const hasRows = computed(() => (props.orders.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const activeFilterCount = computed(() =>
    [vendorId.value, dateFrom.value, dateTo.value].filter(Boolean).length,
);
const hasExtraFilters = computed(() => activeFilterCount.value > 0);
const hasActiveFilters = computed(() => hasSearch.value || hasExtraFilters.value);
const rangeLabel = computed(() => {
    const from = props.orders.from;
    const to = props.orders.to;
    const total = props.orders.total;
    if (! total) return null;
    return `${from}–${to} of ${total}`;
});

const applyFilters = (overrides = {}) => {
    router.get(route('app.asset-management.index'), {
        search: (overrides.search !== undefined ? overrides.search : search.value) || undefined,
        tab: overrides.tab ?? activeTab.value,
        vendor_id: (overrides.vendor_id !== undefined ? overrides.vendor_id : vendorId.value) || undefined,
        date_from: (overrides.date_from !== undefined ? overrides.date_from : dateFrom.value) || undefined,
        date_to: (overrides.date_to !== undefined ? overrides.date_to : dateTo.value) || undefined,
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

watch([vendorId, dateFrom, dateTo], () => {
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
    vendorId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
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
    router.visit(route('app.asset-management.show', id));
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

const poBadgeClass = (status) => ({
    submitted: 'ui-badge-warn',
    closed: 'ui-badge-success',
}[status] || 'ui-badge-neutral');
</script>

<template>
    <AppLayout description="Receive Snipe-IT assets and accessories against submitted purchase orders.">
        <template #header>Asset Management</template>

        <div class="space-y-5">
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
                        placeholder="Search PO, PR, vendor, or purpose…"
                        class="ui-input pl-9 pr-9"
                        aria-label="Search asset records"
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
                        :class="filtersOpen || hasExtraFilters
                            ? 'border-brand-300 text-brand-700 dark:border-brand-700 dark:text-brand-300'
                            : ''"
                        :aria-expanded="filtersOpen"
                        aria-haspopup="dialog"
                        aria-label="Open filters"
                        @click.stop="toggleFilters"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M6 12h12M10 19h4" />
                        </svg>
                        <span
                            v-if="activeFilterCount"
                            class="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-600 px-1 text-[10px] font-semibold text-white"
                        >
                            {{ activeFilterCount }}
                        </span>
                    </button>

                    <div
                        v-if="filtersOpen"
                        class="absolute right-0 z-30 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-surface-elevated"
                        @click.stop
                    >
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">Filters</p>
                            <button
                                v-if="hasExtraFilters"
                                type="button"
                                class="text-xs font-medium text-brand-700 hover:text-brand-600 dark:text-brand-400"
                                @click="clearExtraFilters"
                            >
                                Clear
                            </button>
                        </div>
                        <div class="space-y-3">
                            <div>
                                <label class="ui-label">Vendor</label>
                                <select v-model="vendorId" class="ui-input">
                                    <option value="">All vendors</option>
                                    <option v-for="vendor in filterOptions.vendors" :key="vendor.id" :value="String(vendor.id)">
                                        {{ vendor.name }}
                                    </option>
                                </select>
                            </div>
                            <UiDateRangePicker
                                id="asset-filter-dates"
                                v-model:from="dateFrom"
                                v-model:to="dateTo"
                                label="PO date"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <UiIndexTabs
                v-if="tabs.length"
                :tabs="tabs"
                :model-value="activeTab"
                :pinned="pinnedTab"
                page="asset_management"
                aria-label="Asset status"
                @select="selectTab"
            />

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

            <div v-if="hasRows" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="row in orders.data"
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
                        <span
                            class="shrink-0"
                            :class="row.asset_status === 'pending' ? 'ui-badge-warn' : 'ui-badge-success'"
                        >
                            {{ row.asset_status_label }}
                        </span>
                    </div>

                    <p class="mt-2 line-clamp-2 min-h-[2.5rem] text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ row.purpose || 'No purpose provided' }}
                    </p>

                    <div class="mt-3">
                        <div class="mb-1 flex items-center justify-between text-[11px] text-slate-500">
                            <span>{{ row.received_count }}/{{ row.total_count }} received</span>
                            <span class="tabular-nums">{{ row.progress }}%</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="row.progress === 100 ? 'bg-emerald-500' : 'bg-brand-500'"
                                :style="{ width: `${row.progress}%` }"
                            />
                        </div>
                    </div>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Vendor</dt>
                            <dd class="truncate text-right font-medium text-slate-700 dark:text-slate-200">{{ row.vendor || '—' }}</dd>
                        </div>
                        <div v-if="row.pr_no" class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">PR</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">{{ row.pr_no }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">PO status</dt>
                            <dd class="text-right">
                                <span :class="poBadgeClass(row.status)">{{ row.status_label }}</span>
                            </dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Date</dt>
                            <dd class="tabular-nums text-right text-slate-700 dark:text-slate-300">{{ formatDate(row.date) }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Pending</dt>
                            <dd class="tabular-nums text-right font-semibold text-slate-900 dark:text-white">{{ row.pending_count }}</dd>
                        </div>
                    </dl>
                </article>
            </div>

            <div
                v-else
                class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-surface px-6 py-16 text-center dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ hasActiveFilters ? 'No matching orders' : 'Nothing in this tab' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                    {{ hasActiveFilters
                        ? 'Try another search or reset the filters.'
                        : 'Submitted purchase orders with Snipe-IT items will appear here.' }}
                </p>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="ui-btn-secondary mt-5"
                    @click="() => { clearSearch(); clearExtraFilters(); }"
                >
                    Reset
                </button>
            </div>

            <div
                v-if="orders.links?.length > 3"
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ rangeLabel || ' ' }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <template v-for="(link, index) in orders.links" :key="`${link.label}-${index}`">
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
    </AppLayout>
</template>
