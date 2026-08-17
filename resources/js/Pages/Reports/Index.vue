<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    reports: { type: Object, required: true },
    filters: { type: Object, required: true },
    modelTypes: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const modelType = ref(props.filters.model_type ?? '');
const selected = ref([]);

watch([search, modelType], () => {
    router.get(route('app.reports.index'), {
        search: search.value || undefined,
        model_type: modelType.value || undefined,
    }, { preserveState: true, replace: true });
});

const allSelected = computed({
    get: () => props.reports.data.length > 0 && selected.value.length === props.reports.data.length,
    set: (value) => {
        selected.value = value ? props.reports.data.map((r) => r.id) : [];
    },
});

const bulkForm = useForm({ ids: [] });
const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} template(s)?`)) return;
    bulkForm.ids = selected.value;
    bulkForm.delete(route('app.reports.destroy-many'), {
        onSuccess: () => { selected.value = []; },
    });
};

const deleteOne = (row) => {
    if (! confirm(`Delete "${row.name}"?`)) return;
    router.delete(route('app.reports.destroy', row.id));
};
</script>

<template>
    <AppLayout>
        <template #header>Report Templates</template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                    <input v-model="search" type="search" placeholder="Search…" class="w-full max-w-sm rounded-md border-slate-300 shadow-sm" />
                    <select v-model="modelType" class="rounded-md border-slate-300 shadow-sm">
                        <option value="">All sources</option>
                        <option v-for="m in modelTypes" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button v-if="can.deleteAny && selected.length" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700" @click="deleteSelected">
                        Delete selected
                    </button>
                    <Link v-if="can.create" :href="route('app.reports.create')" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Create</Link>
                </div>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th v-if="can.deleteAny" class="px-4 py-3"><input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" /></th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Source</th>
                            <th class="px-4 py-3 text-center">Columns</th>
                            <th class="px-4 py-3">Created by</th>
                            <th class="px-4 py-3">Created</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in reports.data" :key="row.id" class="hover:bg-slate-50">
                            <td v-if="can.deleteAny" class="px-4 py-3"><input v-model="selected" type="checkbox" :value="row.id" class="rounded border-slate-300 text-brand-600" /></td>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900">{{ row.name }}</p>
                                <p v-if="row.description" class="text-xs text-slate-500">{{ row.description }}</p>
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium">{{ row.model_label }}</span></td>
                            <td class="px-4 py-3 text-center">{{ row.column_count }}</td>
                            <td class="px-4 py-3">{{ row.creator || '—' }}</td>
                            <td class="px-4 py-3">{{ row.created_at }}</td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <a :href="route('app.reports.download', row.id)" class="font-medium text-emerald-700">CSV</a>
                                <Link :href="route('app.reports.edit', row.id)" class="font-medium text-brand-600">Edit</Link>
                                <button type="button" class="font-medium text-rose-600" @click="deleteOne(row)">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!reports.data.length">
                            <td :colspan="can.deleteAny ? 7 : 6" class="px-4 py-10 text-center text-slate-500">No report templates yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="reports.links?.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in reports.links"
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
