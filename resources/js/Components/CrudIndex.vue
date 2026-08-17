<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    routePrefix: { type: String, required: true },
    columns: { type: Array, required: true },
    records: { type: Object, required: true },
    filters: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const selected = ref([]);

watch(search, (value) => {
    router.get(route(`${props.routePrefix}.index`), { search: value || undefined }, {
        preserveState: true,
        replace: true,
    });
});

const allSelected = computed({
    get: () => props.records.data.length > 0 && selected.value.length === props.records.data.length,
    set: (value) => {
        selected.value = value ? props.records.data.map((row) => row.id) : [];
    },
});

const bulkForm = useForm({ ids: [] });

const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} record(s)?`)) {
        return;
    }
    bulkForm.ids = selected.value;
    bulkForm.delete(route(`${props.routePrefix}.destroy-many`), {
        onSuccess: () => {
            selected.value = [];
        },
    });
};

const deleteOne = (row) => {
    if (! confirm('Delete this record?')) {
        return;
    }
    router.delete(route(`${props.routePrefix}.destroy`, row.id));
};
</script>

<template>
    <AppLayout :title="title">
        <template #header>{{ title }}</template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input v-model="search" type="search" placeholder="Search…" class="ui-input max-w-sm" />
                <div class="flex flex-wrap gap-2">
                    <button
                        v-if="can.deleteAny && selected.length"
                        type="button"
                        class="ui-btn-danger"
                        @click="deleteSelected"
                    >
                        Delete ({{ selected.length }})
                    </button>
                    <Link
                        v-if="can.create"
                        :href="route(`${routePrefix}.create`)"
                        class="ui-btn-primary"
                    >
                        Create
                    </Link>
                </div>
            </div>

            <div class="ui-table-wrap">
                <table class="ui-table">
                    <thead>
                        <tr>
                            <th v-if="can.deleteAny" class="w-10">
                                <input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500/30" />
                            </th>
                            <th v-for="column in columns" :key="column.key">{{ column.label }}</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in records.data" :key="row.id">
                            <td v-if="can.deleteAny">
                                <input v-model="selected" type="checkbox" :value="row.id" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500/30" />
                            </td>
                            <td v-for="column in columns" :key="column.key" class="text-slate-700 dark:text-slate-300">
                                {{ row[column.key] ?? '—' }}
                            </td>
                            <td class="space-x-3 text-right">
                                <Link :href="route(`${routePrefix}.edit`, row.id)" class="ui-link">Edit</Link>
                                <button type="button" class="ui-link-danger" @click="deleteOne(row)">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!records.data.length">
                            <td :colspan="columns.length + (can.deleteAny ? 2 : 1)" class="!py-12 text-center text-slate-500">
                                No records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="records.links?.length > 3" class="flex flex-wrap gap-1.5">
                <Link
                    v-for="link in records.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="inline-flex h-8 min-w-8 items-center justify-center rounded-xl border px-2.5 text-sm transition"
                    :class="link.active
                        ? 'border-brand-600 bg-brand-600 text-white'
                        : 'border-slate-200 bg-surface text-slate-600 hover:border-slate-300 dark:border-slate-700'"
                    v-html="link.label"
                    preserve-scroll
                />
            </div>
        </div>
    </AppLayout>
</template>
