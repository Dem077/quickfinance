<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    items: { type: Object, required: true },
    filters: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const selected = ref([]);

watch(search, (value) => {
    router.get(route('app.items.index'), { search: value || undefined }, {
        preserveState: true,
        replace: true,
    });
});

const allSelected = computed({
    get: () => props.items.data.length > 0 && selected.value.length === props.items.data.length,
    set: (value) => {
        selected.value = value ? props.items.data.map((item) => item.id) : [];
    },
});

const bulkForm = useForm({ ids: [] });

const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} item(s)?`)) {
        return;
    }

    bulkForm.ids = selected.value;
    bulkForm.delete(route('app.items.destroy-many'), {
        onSuccess: () => {
            selected.value = [];
        },
    });
};

const deleteOne = (item) => {
    if (! confirm(`Delete item ${item.item_code}?`)) {
        return;
    }

    router.delete(route('app.items.destroy', item.id));
};
</script>

<template>
    <AppLayout>
        <template #header>Items</template>

        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search code or name…"
                    class="w-full max-w-sm rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:w-72"
                />
                <div class="flex gap-2">
                    <button
                        v-if="can.deleteAny && selected.length"
                        type="button"
                        class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-100"
                        @click="deleteSelected"
                    >
                        Delete selected ({{ selected.length }})
                    </button>
                    <Link
                        v-if="can.create"
                        :href="route('app.items.create')"
                        class="rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700"
                    >
                        Create
                    </Link>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th v-if="can.deleteAny" class="px-4 py-3">
                                <input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                            </th>
                            <th class="px-4 py-3">Item code</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="item in items.data" :key="item.id" class="hover:bg-slate-50">
                            <td v-if="can.deleteAny" class="px-4 py-3">
                                <input v-model="selected" type="checkbox" :value="item.id" class="rounded border-slate-300 text-brand-600" />
                            </td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ item.item_code }}</td>
                            <td class="px-4 py-3 text-slate-700">{{ item.name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                                    {{ item.type_label }}
                                </span>
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link :href="route('app.items.edit', item.id)" class="font-medium text-brand-600 hover:text-brand-700">
                                    Edit
                                </Link>
                                <button type="button" class="font-medium text-rose-600 hover:text-rose-700" @click="deleteOne(item)">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!items.data.length">
                            <td :colspan="can.deleteAny ? 5 : 4" class="px-4 py-10 text-center text-slate-500">
                                No items found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="items.links?.length > 3" class="flex flex-wrap gap-2">
                <Link
                    v-for="link in items.links"
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
