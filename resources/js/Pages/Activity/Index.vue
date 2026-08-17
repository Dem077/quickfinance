<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    activities: { type: Object, required: true },
    filters: { type: Object, required: true },
    logNames: { type: Array, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const logName = ref(props.filters.log_name ?? '');
const selected = ref([]);

watch([search, logName], () => {
    router.get(route('app.activity.index'), {
        search: search.value || undefined,
        log_name: logName.value || undefined,
    }, { preserveState: true, replace: true });
});

const allSelected = computed({
    get: () => props.activities.data.length > 0 && selected.value.length === props.activities.data.length,
    set: (value) => {
        selected.value = value ? props.activities.data.map((r) => r.id) : [];
    },
});

const bulkForm = useForm({ ids: [] });
const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} log(s)?`)) return;
    bulkForm.ids = selected.value;
    bulkForm.delete(route('app.activity.destroy-many'), {
        onSuccess: () => { selected.value = []; },
    });
};
</script>

<template>
    <AppLayout>
        <template #header>Activity Log</template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-1 flex-col gap-2 sm:flex-row">
                    <input v-model="search" type="search" placeholder="Search…" class="w-full max-w-sm rounded-md border-slate-300 shadow-sm" />
                    <select v-model="logName" class="rounded-md border-slate-300 shadow-sm">
                        <option value="">All types</option>
                        <option v-for="name in logNames" :key="name" :value="name">{{ name }}</option>
                    </select>
                </div>
                <button
                    v-if="can.deleteAny && selected.length"
                    type="button"
                    class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"
                    @click="deleteSelected"
                >
                    Delete selected
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th v-if="can.deleteAny" class="px-4 py-3"><input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" /></th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Event</th>
                            <th class="px-4 py-3">Subject</th>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Logged at</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in activities.data" :key="row.id" class="hover:bg-slate-50">
                            <td v-if="can.deleteAny" class="px-4 py-3">
                                <input v-model="selected" type="checkbox" :value="row.id" class="rounded border-slate-300 text-brand-600" />
                            </td>
                            <td class="px-4 py-3"><span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium capitalize">{{ row.log_name }}</span></td>
                            <td class="px-4 py-3">{{ row.event || '—' }}</td>
                            <td class="px-4 py-3">{{ row.subject || '—' }}</td>
                            <td class="px-4 py-3">{{ row.causer }}</td>
                            <td class="px-4 py-3">{{ row.created_at }}</td>
                        </tr>
                        <tr v-if="!activities.data.length">
                            <td :colspan="can.deleteAny ? 6 : 5" class="px-4 py-10 text-center text-slate-500">No activity found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="activities.links?.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in activities.links"
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
