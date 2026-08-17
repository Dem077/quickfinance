<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    users: Object,
    filters: Object,
    can: Object,
});

const search = ref(props.filters.search ?? '');

watch(search, (value) => {
    router.get(route('app.users.index'), { search: value || undefined }, { preserveState: true, replace: true });
});

const destroy = (user) => {
    if (! confirm(`Delete ${user.name}?`)) return;
    router.delete(route('app.users.destroy', user.id));
};
</script>

<template>
    <AppLayout>
        <template #header>Users</template>
        <div class="space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <input v-model="search" type="search" placeholder="Search name or email…" class="w-full max-w-sm rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:w-72" />
                <Link v-if="can.create" :href="route('app.users.create')" class="rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white hover:bg-brand-700">Create</Link>
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                            <th class="px-4 py-3">Name</th>
                            <th class="px-4 py-3">Email</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">Roles</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-500">{{ user.id }}</td>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ user.name }}</td>
                            <td class="px-4 py-3">{{ user.email }}</td>
                            <td class="px-4 py-3">{{ user.department ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span v-for="role in user.roles" :key="role" class="mr-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs">{{ role }}</span>
                            </td>
                            <td class="space-x-3 px-4 py-3 text-right">
                                <Link :href="route('app.users.edit', user.id)" class="font-medium text-brand-600">Edit</Link>
                                <button type="button" class="font-medium text-rose-600" @click="destroy(user)">Delete</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
