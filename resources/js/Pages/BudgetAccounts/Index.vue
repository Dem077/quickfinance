<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiIndexTabs from '../../Components/UiIndexTabs.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    groups: { type: Array, required: true },
    filters: { type: Object, required: true },
    tabs: { type: Array, required: true },
    pinnedTab: { type: String, default: null },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const selected = ref([]);
let searchTimer = null;

const activeType = computed(() => props.filters.expenditure_type || 'all');
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const hasRows = computed(() => props.groups.some((group) => group.accounts.length));
const allAccountIds = computed(() => props.groups.flatMap((group) => group.accounts.map((row) => row.id)));
const grandTotal = computed(() =>
    props.groups.reduce((sum, group) => sum + Number(group.total_amount || 0), 0),
);

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        applyFilters({ search: value });
    }, 280);
});

const applyFilters = (overrides = {}) => {
    router.get(route('app.budget-accounts.index'), {
        search: (overrides.search !== undefined ? overrides.search : search.value) || undefined,
        expenditure_type: (() => {
            const next = overrides.expenditure_type !== undefined
                ? overrides.expenditure_type
                : activeType.value;
            return next && next !== 'all' ? next : undefined;
        })(),
    }, { preserveState: true, replace: true, preserveScroll: true });
};

const selectType = (type) => {
    if (type === activeType.value) return;
    applyFilters({ expenditure_type: type });
};

const clearSearch = () => {
    clearTimeout(searchTimer);
    search.value = '';
    applyFilters({ search: '' });
};

const allSelected = computed({
    get: () => allAccountIds.value.length > 0 && selected.value.length === allAccountIds.value.length,
    set: (value) => {
        selected.value = value ? [...allAccountIds.value] : [];
    },
});

const bulkForm = useForm({ ids: [] });

const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} account(s)?`)) {
        return;
    }
    bulkForm.ids = selected.value;
    bulkForm.delete(route('app.budget-accounts.destroy-many'), {
        onSuccess: () => { selected.value = []; },
    });
};

const formatMoney = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <AppLayout description="Budget accounts grouped by expenditure type.">
        <template #header>Budget Accounts</template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full max-w-xl">
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
                        placeholder="Search type, account, or name…"
                        class="ui-input pl-9 pr-9"
                        aria-label="Search budget accounts"
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

                <div class="flex gap-2">
                    <label
                        v-if="can.deleteAny && hasRows"
                        class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-surface px-3 py-2 text-sm text-slate-600 dark:border-slate-700 dark:text-slate-300"
                    >
                        <input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Select all
                    </label>
                    <button
                        v-if="can.deleteAny && selected.length"
                        type="button"
                        class="ui-btn-danger"
                        @click="deleteSelected"
                    >
                        Delete selected ({{ selected.length }})
                    </button>
                    <Link
                        v-if="can.create"
                        :href="route('app.budget-accounts.create')"
                        class="ui-btn-primary inline-flex gap-1.5"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        New account
                    </Link>
                </div>
            </div>

            <UiIndexTabs
                v-if="tabs.length"
                :tabs="tabs"
                :model-value="activeType"
                :pinned="pinnedTab"
                page="budget_accounts"
                aria-label="Expenditure type"
                @select="selectType"
            />

            <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500 dark:text-slate-400">
                <p>
                    {{ groups.reduce((sum, group) => sum + group.count, 0) }} account{{ groups.reduce((sum, group) => sum + group.count, 0) === 1 ? '' : 's' }}
                    · {{ groups.length }} type{{ groups.length === 1 ? '' : 's' }}
                </p>
                <p class="font-medium tabular-nums text-slate-800 dark:text-slate-200">
                    Total MVR {{ formatMoney(grandTotal) }}
                </p>
            </div>

            <div v-if="hasRows" class="space-y-4">
                <section
                    v-for="group in groups"
                    :key="group.type"
                    class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-surface-muted/30 sm:px-5">
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">{{ group.type }}</h2>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ group.count }} account{{ group.count === 1 ? '' : 's' }}
                            </p>
                        </div>
                        <p class="text-sm font-semibold tabular-nums text-brand-700 dark:text-brand-300">
                            MVR {{ formatMoney(group.total_amount) }}
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th v-if="can.deleteAny" class="w-10"></th>
                                    <th>Account</th>
                                    <th>Name</th>
                                    <th class="text-right">Total (MVR)</th>
                                    <th class="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in group.accounts" :key="row.id">
                                    <td v-if="can.deleteAny">
                                        <input v-model="selected" type="checkbox" :value="row.id" class="rounded border-slate-300 text-brand-600" />
                                    </td>
                                    <td class="font-medium text-slate-900 dark:text-slate-100">{{ row.account }}</td>
                                    <td class="text-slate-700 dark:text-slate-300">{{ row.name || '—' }}</td>
                                    <td class="text-right tabular-nums text-slate-800 dark:text-slate-100">{{ formatMoney(row.total_amount) }}</td>
                                    <td class="text-right">
                                        <Link :href="route('app.budget-accounts.edit', row.id)" class="font-medium text-brand-600">
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div
                v-else
                class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-surface px-6 py-16 text-center dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ hasSearch ? 'No matching accounts' : 'No budget accounts' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500">
                    {{ hasSearch ? 'Try another search, or clear the filter.' : 'Create a budget account to get started.' }}
                </p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <button v-if="hasSearch" type="button" class="ui-btn-secondary" @click="clearSearch">Clear search</button>
                    <Link v-if="can.create && !hasSearch" :href="route('app.budget-accounts.create')" class="ui-btn-primary">
                        Create account
                    </Link>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
