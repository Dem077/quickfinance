<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateRangePicker from '../../Components/UiDateRangePicker.vue';
import UiIndexTabs from '../../Components/UiIndexTabs.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    histories: { type: Object, required: true },
    filters: { type: Object, required: true },
    tabs: { type: Array, required: true },
    pinnedTab: { type: String, default: null },
    filterOptions: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const userId = ref(props.filters.user_id ? String(props.filters.user_id) : '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const filtersOpen = ref(false);
const filterPanel = ref(null);
const selected = ref([]);
let searchTimer = null;

const activeTab = computed(() => props.filters.tab || 'all');
const activeTabMeta = computed(() => props.tabs.find((tab) => tab.key === activeTab.value) || null);
const hasRows = computed(() => (props.histories.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const activeFilterCount = computed(() =>
    [userId.value, dateFrom.value, dateTo.value].filter(Boolean).length,
);
const hasExtraFilters = computed(() => activeFilterCount.value > 0);
const hasActiveFilters = computed(() => hasSearch.value || hasExtraFilters.value);
const rangeLabel = computed(() => {
    const from = props.histories.from;
    const to = props.histories.to;
    const total = props.histories.total;
    if (! total) return null;
    return `${from}–${to} of ${total}`;
});

const grouped = computed(() => {
    const groups = [];
    const map = new Map();
    (props.histories.data || []).forEach((row) => {
        const key = row.day || 'unknown';
        if (! map.has(key)) {
            const group = { key, label: formatDay(row.transaction_date || row.day), items: [] };
            map.set(key, group);
            groups.push(group);
        }
        map.get(key).items.push(row);
    });
    return groups;
});

const allSelected = computed({
    get: () => hasRows.value && selected.value.length === props.histories.data.length,
    set: (value) => {
        selected.value = value ? props.histories.data.map((row) => row.id) : [];
    },
});

const applyFilters = (overrides = {}) => {
    router.get(route('app.budget-transaction-histories.index'), {
        search: (overrides.search !== undefined ? overrides.search : search.value) || undefined,
        tab: (() => {
            const next = overrides.tab ?? activeTab.value;
            return next && next !== 'all' ? next : undefined;
        })(),
        user_id: (overrides.user_id !== undefined ? overrides.user_id : userId.value) || undefined,
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

watch([userId, dateFrom, dateTo], () => applyFilters());

watch(() => props.histories.data, () => {
    selected.value = [];
});

const selectTab = (tab) => {
    if (tab === activeTab.value) return;
    applyFilters({ tab });
};

const clearSearch = () => {
    clearTimeout(searchTimer);
    search.value = '';
    if (props.filters.search) applyFilters({ search: '' });
};

const clearExtraFilters = () => {
    userId.value = '';
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
    if (! filterPanel.value.contains(target)) filtersOpen.value = false;
};

const onDocumentKeydown = (event) => {
    if (event.key === 'Escape') filtersOpen.value = false;
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
});

const bulkForm = useForm({ ids: [] });
const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} record${selected.value.length === 1 ? '' : 's'}?`)) return;
    bulkForm.ids = selected.value;
    bulkForm.delete(route('app.budget-transaction-histories.destroy-many'), {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
    });
};

const formatMoney = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDay = (value) => {
    if (! value) return 'Unknown date';
    const date = new Date(String(value).includes('T') ? value : `${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    if (sameDay(date, today)) return 'Today';
    if (sameDay(date, yesterday)) return 'Yesterday';
    return date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
};

const typeBadgeClass = (type) => {
    const key = String(type || '').toLowerCase();
    if (['top up', 'topup'].includes(key)) return 'ui-badge-success';
    if (key === 'transfer') return 'ui-badge-brand';
    if (['purchase order', 'petty cash reimbursement'].includes(key)) return 'ui-badge-warn';
    return 'ui-badge-neutral';
};

const amountClass = (direction) => ({
    in: 'text-emerald-700 dark:text-emerald-300',
    out: 'text-rose-700 dark:text-rose-300',
    neutral: 'text-slate-900 dark:text-white',
}[direction] || 'text-slate-900 dark:text-white');

const amountPrefix = (direction) => ({
    in: '+',
    out: '−',
    neutral: '',
}[direction] || '');

</script>

<template>
    <AppLayout description="Every top-up, transfer, purchase order, and petty cash movement on budget accounts.">
        <template #header>Budget History</template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex w-full max-w-xl items-center gap-2">
                    <div class="relative min-w-0 flex-1">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                        </svg>
                        <input
                            v-model="search"
                            type="search"
                            placeholder="Search budget, type, or details…"
                            class="ui-input pl-9 pr-9"
                            aria-label="Search history"
                        />
                        <button
                            v-if="hasSearch"
                            type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-surface-muted"
                            @click="clearSearch"
                        >
                            Clear
                        </button>
                    </div>
                    <div ref="filterPanel" class="relative shrink-0">
                        <button
                            type="button"
                            class="relative inline-flex h-[42px] w-[42px] items-center justify-center rounded-lg border border-slate-200 bg-surface text-slate-600 transition hover:border-slate-300 hover:bg-surface-muted dark:border-slate-700 dark:text-slate-300"
                            :class="filtersOpen || hasExtraFilters ? 'border-brand-300 text-brand-700 dark:border-brand-700 dark:text-brand-300' : ''"
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
                            <div class="mb-3 flex items-center justify-between">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">Filters</p>
                                <button v-if="hasExtraFilters" type="button" class="text-xs font-medium text-brand-700" @click="clearExtraFilters">Clear</button>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="ui-label">User</label>
                                    <select v-model="userId" class="ui-input">
                                        <option value="">Anyone</option>
                                        <option v-for="person in filterOptions.users" :key="person.id" :value="String(person.id)">{{ person.name }}</option>
                                    </select>
                                </div>
                                <UiDateRangePicker id="history-dates" v-model:from="dateFrom" v-model:to="dateTo" label="Date range" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <label v-if="can.deleteAny && hasRows" class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Select page
                    </label>
                    <button v-if="can.deleteAny && selected.length" type="button" class="ui-btn-danger" @click="deleteSelected">
                        Delete {{ selected.length }}
                    </button>
                </div>
            </div>

            <UiIndexTabs
                v-if="tabs.length"
                :tabs="tabs"
                :model-value="activeTab"
                :pinned="pinnedTab"
                page="budget_transaction_histories"
                aria-label="Transaction type"
                @select="selectTab"
            />

            <div v-if="rangeLabel || hasActiveFilters" class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500">
                <p>
                    <span v-if="activeTabMeta">{{ activeTabMeta.label }}</span>
                    <span v-if="activeTabMeta && rangeLabel"> · </span>
                    <span v-if="rangeLabel">{{ rangeLabel }}</span>
                </p>
                <p v-if="hasSearch" class="truncate">“{{ search.trim() }}”</p>
            </div>

            <div v-if="hasRows" class="space-y-8">
                <section v-for="group in grouped" :key="group.key">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">{{ group.label }}</h2>
                    <ol class="relative space-y-3 border-l border-slate-200 pl-5 dark:border-slate-800">
                        <li v-for="row in group.items" :key="row.id" class="relative">
                            <span
                                class="absolute -left-[1.45rem] top-5 h-2.5 w-2.5 rounded-full border-2 border-white dark:border-surface"
                                :class="{
                                    'bg-emerald-500': row.direction === 'in',
                                    'bg-rose-500': row.direction === 'out',
                                    'bg-brand-500': row.direction === 'neutral',
                                }"
                            />
                            <article class="ui-card p-4">
                                <div class="flex items-start gap-3">
                                    <input
                                        v-if="can.deleteAny"
                                        v-model="selected"
                                        type="checkbox"
                                        :value="row.id"
                                        class="mt-1 rounded border-slate-300 text-brand-600"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span :class="typeBadgeClass(row.transaction_type)">{{ row.transaction_type_label }}</span>
                                            <span v-if="row.sub_budget_code" class="text-xs text-slate-500">{{ row.sub_budget_code }}</span>
                                        </div>
                                        <p class="mt-1.5 text-sm font-medium text-slate-900 dark:text-white">
                                            {{ row.sub_budget_name || 'Unknown budget' }}
                                        </p>
                                        <p v-if="row.transaction_details" class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                            {{ row.transaction_details }}
                                        </p>
                                        <p class="mt-2 text-xs text-slate-500">
                                            {{ row.transaction_by || 'System' }} · {{ row.transaction_date_label }}
                                        </p>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-sm font-semibold tabular-nums" :class="amountClass(row.direction)">
                                            {{ amountPrefix(row.direction) }}MVR {{ formatMoney(row.transaction_amount) }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Bal. {{ formatMoney(row.transaction_balance) }}
                                        </p>
                                    </div>
                                </div>
                            </article>
                        </li>
                    </ol>
                </section>
            </div>

            <div
                v-else
                class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-surface px-6 py-16 text-center dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ hasActiveFilters ? 'No matching history' : 'No history yet' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500">
                    {{ hasActiveFilters ? 'Try another search or reset the filters.' : 'Top-ups, transfers, and deductions will show up here.' }}
                </p>
                <button v-if="hasActiveFilters" type="button" class="ui-btn-secondary mt-5" @click="() => { clearSearch(); clearExtraFilters(); }">
                    Reset
                </button>
            </div>

            <div v-if="histories.links?.length > 3" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-500">{{ rangeLabel || ' ' }}</p>
                <div class="flex flex-wrap gap-1.5">
                    <template v-for="(link, index) in histories.links" :key="`${link.label}-${index}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-xl border px-2.5 text-sm transition"
                            :class="link.active ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 bg-surface text-slate-600 hover:border-slate-300 dark:border-slate-700'"
                            :preserve-scroll="true"
                            v-html="link.label"
                        />
                        <span v-else class="inline-flex h-8 min-w-8 items-center justify-center px-2.5 text-sm text-slate-300" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
