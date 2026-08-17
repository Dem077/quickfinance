<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    roles: { type: Object, required: true },
    filters: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const selected = ref([]);

watch(search, (value) => {
    router.get(route('app.roles.index'), { search: value || undefined }, { preserveState: true, replace: true });
});

const allSelected = computed({
    get: () => props.roles.data.length > 0 && selected.value.length === props.roles.data.length,
    set: (value) => {
        selected.value = value ? props.roles.data.map((r) => r.id) : [];
    },
});

const bulkForm = useForm({ ids: [] });
const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} role(s)?`)) return;
    bulkForm.ids = selected.value;
    bulkForm.delete(route('app.roles.destroy-many'), {
        onSuccess: () => { selected.value = []; },
    });
};

const deleteOne = (row) => {
    if (row.is_super_admin) return;
    if (! confirm(`Delete role "${row.name}"?`)) return;
    router.delete(route('app.roles.destroy', row.id));
};
</script>

<template>
    <AppLayout>
        <template #header>Roles</template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input v-model="search" type="search" placeholder="Search roles…" class="w-full max-w-sm rounded-md border-slate-300 shadow-sm" />
                <div class="flex gap-2">
                    <button v-if="can.deleteAny && selected.length" type="button" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700" @click="deleteSelected">Delete selected</button>
                    <Link v-if="can.create" :href="route('app.roles.create')" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white">Create</Link>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th v-if="can.deleteAny" class="px-4 py-3"><input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" /></th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Guard</th>
                            <th class="px-4 py-3">Permissions</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in roles.data" :key="row.id" class="hover:bg-slate-50">
                            <td v-if="can.deleteAny" class="px-4 py-3">
                                <input v-if="!row.is_super_admin" v-model="selected" type="checkbox" :value="row.id" class="rounded border-slate-300 text-brand-600" />
                            </td>
                            <td class="px-4 py-3 font-medium capitalize">{{ row.name.replaceAll('_', ' ') }}</td>
                            <td class="px-4 py-3"><span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-800">{{ row.guard_name }}</span></td>
                            <td class="px-4 py-3"><span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-800">{{ row.permissions_count }}</span></td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link :href="route('app.roles.edit', row.id)" class="font-medium text-brand-600">Edit</Link>
                                <button v-if="!row.is_super_admin" type="button" class="font-medium text-rose-600" @click="deleteOne(row)">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!roles.data.length">
                            <td :colspan="can.deleteAny ? 5 : 4" class="px-4 py-10 text-center text-slate-500">No roles found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="roles.links?.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in roles.links"
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
