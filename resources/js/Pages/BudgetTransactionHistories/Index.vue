<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    histories: { type: Object, required: true },
    filters: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const selected = ref([]);

watch(search, (value) => {
    router.get(route('app.budget-transaction-histories.index'), { search: value || undefined }, {
        preserveState: true,
        replace: true,
    });
});

const allSelected = computed({
    get: () => props.histories.data.length > 0 && selected.value.length === props.histories.data.length,
    set: (value) => {
        selected.value = value ? props.histories.data.map((row) => row.id) : [];
    },
});

const bulkForm = useForm({ ids: [] });

const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} record(s)?`)) {
        return;
    }
    bulkForm.ids = selected.value;
    bulkForm.delete(route('app.budget-transaction-histories.destroy-many'), {
        onSuccess: () => { selected.value = []; },
    });
};

const formatMoney = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
</script>

<template>
    <AppLayout>
        <template #header>Budget Transaction History</template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search…"
                    class="w-full max-w-sm rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:w-72"
                />
                <button
                    v-if="can.deleteAny && selected.length"
                    type="button"
                    class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"
                    @click="deleteSelected"
                >
                    Delete selected ({{ selected.length }})
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th v-if="can.deleteAny" class="px-4 py-3">
                                <input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                            </th>
                            <th class="px-4 py-3">Sub budget</th>
                            <th class="px-4 py-3">Code</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Details</th>
                            <th class="px-4 py-3 text-right">Amount</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in histories.data" :key="row.id" class="hover:bg-slate-50">
                            <td v-if="can.deleteAny" class="px-4 py-3">
                                <input v-model="selected" type="checkbox" :value="row.id" class="rounded border-slate-300 text-brand-600" />
                            </td>
                            <td class="px-4 py-3">{{ row.sub_budget_name }}</td>
                            <td class="px-4 py-3">{{ row.sub_budget_code }}</td>
                            <td class="px-4 py-3">{{ row.transaction_type }}</td>
                            <td class="px-4 py-3">{{ row.transaction_date }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-slate-600" :title="row.transaction_details">{{ row.transaction_details }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(row.transaction_amount) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ formatMoney(row.transaction_balance) }}</td>
                            <td class="px-4 py-3">{{ row.transaction_by }}</td>
                        </tr>
                        <tr v-if="!histories.data.length">
                            <td :colspan="can.deleteAny ? 9 : 8" class="px-4 py-10 text-center text-slate-500">No history found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="histories.links?.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in histories.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-md border px-3 py-1 text-sm"
                    :class="link.active ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 bg-white text-slate-600'"
                    v-html="link.label"
                    :preserve-scroll="true"
                />
            </div>
        </div>
    </AppLayout>
</template>
