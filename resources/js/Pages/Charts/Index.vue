<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    charts: { type: Object, required: true },
    filters: { type: Object, required: true },
    modelTypes: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const modelType = ref(props.filters.model_type ?? '');
let searchTimer = null;

const hasRows = computed(() => (props.charts.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()) || Boolean(modelType.value));

const applyFilters = () => {
    router.get(route('app.charts.index'), {
        search: search.value || undefined,
        model_type: modelType.value || undefined,
    }, { preserveState: true, replace: true, preserveScroll: true });
};

watch(search, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 280);
});

watch(modelType, applyFilters);

onBeforeUnmount(() => clearTimeout(searchTimer));

const destroy = (row) => {
    if (! confirm(`Delete chart "${row.name}"?`)) return;
    router.delete(route('app.charts.destroy', row.id), { preserveScroll: true });
};

const typeLabel = (type) => ({
    bar: 'Bar',
    line: 'Line',
    pie: 'Pie',
    doughnut: 'Doughnut',
}[type] || type);
</script>

<template>
    <AppLayout description="Define the datasets shown as charts on the dashboard.">
        <template #header>Dashboard charts</template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex w-full max-w-xl flex-col gap-2 sm:flex-row">
                    <input v-model="search" type="search" placeholder="Search charts…" class="ui-input" />
                    <select v-model="modelType" class="ui-input sm:max-w-52">
                        <option value="">All sources</option>
                        <option v-for="model in modelTypes" :key="model.value" :value="model.value">
                            {{ model.label }}
                        </option>
                    </select>
                </div>
                <Link v-if="can.create" :href="route('app.charts.create')" class="ui-btn-primary shrink-0">
                    Create chart
                </Link>
            </div>

            <div v-if="hasRows" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article v-for="row in charts.data" :key="row.id" class="ui-card ui-card-hover flex flex-col p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-semibold text-slate-900 dark:text-white">{{ row.name }}</h2>
                            <p class="mt-0.5 truncate text-sm text-slate-500">{{ row.model_label }}</p>
                        </div>
                        <span :class="row.show_on_dashboard ? 'ui-badge-success' : 'ui-badge-neutral'">
                            {{ row.show_on_dashboard ? 'On dashboard' : 'Hidden' }}
                        </span>
                    </div>
                    <p v-if="row.description" class="mt-2 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">
                        {{ row.description }}
                    </p>
                    <dl class="mt-4 space-y-1.5 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Type</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-100">{{ typeLabel(row.chart_type) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-500">Aggregation</dt>
                            <dd class="capitalize font-medium text-slate-800 dark:text-slate-100">{{ row.aggregation }}</dd>
                        </div>
                    </dl>
                    <div class="mt-4 flex items-center gap-2">
                        <Link :href="route('app.charts.edit', row.id)" class="ui-btn-secondary flex-1">Edit</Link>
                        <button type="button" class="ui-btn-ghost text-rose-600" @click="destroy(row)">Delete</button>
                    </div>
                </article>
            </div>

            <div v-if="charts.links?.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in charts.links"
                    :key="link.label"
                    :href="link.url || '#'"
                    class="rounded-md border px-3 py-1.5 text-sm"
                    :class="link.active
                        ? 'border-brand-300 bg-brand-50 text-brand-800'
                        : 'border-slate-200 text-slate-600'"
                    :tabindex="link.url ? 0 : -1"
                    v-html="link.label"
                />
            </div>

            <div
                v-else
                class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-surface px-6 py-16 text-center dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ hasSearch ? 'No matching charts' : 'No charts yet' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500">
                    Pick a data source, how to split it, and what number to show. Starter ideas are available on the create page.
                </p>
                <Link v-if="can.create" :href="route('app.charts.create')" class="ui-btn-primary mt-5">
                    Create chart
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
