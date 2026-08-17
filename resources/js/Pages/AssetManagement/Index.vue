<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    orders: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const assetStatus = ref(props.filters.asset_status ?? '');

const apply = () => {
    router.get(route('app.asset-management.index'), {
        search: search.value || undefined,
        asset_status: assetStatus.value || undefined,
    }, { preserveState: true, replace: true });
};

watch([search, assetStatus], () => apply());
</script>

<template>
    <AppLayout>
        <template #header>Asset Management</template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search PO / PR / vendor…"
                    class="w-full max-w-sm rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <select v-model="assetStatus" class="rounded-md border-slate-300 shadow-sm">
                    <option value="">All asset statuses</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">PO Number</th>
                            <th class="px-4 py-3">PR Number</th>
                            <th class="px-4 py-3">Vendor</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">PO Status</th>
                            <th class="px-4 py-3">Asset Status</th>
                            <th class="px-4 py-3 text-right">Pending</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in orders.data" :key="row.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-medium text-slate-900">{{ row.po_no }}</td>
                            <td class="px-4 py-3">{{ row.pr_no }}</td>
                            <td class="px-4 py-3">{{ row.vendor }}</td>
                            <td class="px-4 py-3">{{ row.date }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ row.status_label }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                                    :class="row.asset_status === 'Pending' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                >
                                    {{ row.asset_status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">{{ row.pending_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="route('app.asset-management.show', row.id)" class="font-medium text-brand-600">View</Link>
                            </td>
                        </tr>
                        <tr v-if="!orders.data.length">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">No asset records found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="orders.links?.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in orders.links"
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
