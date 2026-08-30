<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    role: { type: Object, default: null },
    permissionGroups: { type: Array, required: true },
});

const isEdit = !!props.role;
const query = ref('');

const form = useForm({
    name: props.role?.name ?? '',
    guard_name: props.role?.guard_name ?? 'web',
    permissions: props.role?.permissions ? [...props.role.permissions] : [],
});

const allPermissionNames = computed(() =>
    props.permissionGroups.flatMap((group) => group.permissions.map((permission) => permission.name)),
);

const selectedCount = computed(() => form.permissions.length);
const totalCount = computed(() => allPermissionNames.value.length);

const filteredGroups = computed(() => {
    const term = query.value.trim().toLowerCase();
    if (! term) return props.permissionGroups;

    return props.permissionGroups
        .map((group) => {
            const permissions = group.permissions.filter((permission) =>
                permission.label.toLowerCase().includes(term)
                || permission.name.toLowerCase().includes(term)
                || group.label.toLowerCase().includes(term),
            );

            return { ...group, permissions };
        })
        .filter((group) => group.permissions.length > 0);
});

const allSelected = computed({
    get: () => totalCount.value > 0 && allPermissionNames.value.every((name) => form.permissions.includes(name)),
    set: (value) => {
        form.permissions = value ? [...allPermissionNames.value] : [];
    },
});

const groupState = (group) => {
    const names = group.permissions.map((permission) => permission.name);
    const selected = names.filter((name) => form.permissions.includes(name)).length;

    return {
        names,
        selected,
        total: names.length,
        checked: names.length > 0 && selected === names.length,
        indeterminate: selected > 0 && selected < names.length,
    };
};

const toggleGroup = (group, checked) => {
    const names = group.permissions.map((permission) => permission.name);
    if (checked) {
        form.permissions = [...new Set([...form.permissions, ...names])];
        return;
    }
    form.permissions = form.permissions.filter((name) => ! names.includes(name));
};

const fieldError = (key) => form.errors[key] || '';

const submit = () => {
    if (isEdit) {
        form.put(route('app.roles.update', props.role.id));
        return;
    }
    form.post(route('app.roles.store'));
};
</script>

<template>
    <AppLayout>
        <template #header>{{ isEdit ? 'Edit role' : 'Create role' }}</template>

        <form class="mx-auto max-w-5xl space-y-4 pb-28" @submit.prevent="submit">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="route('app.roles.index')" class="ui-link inline-flex items-center gap-1 text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </Link>
                    <h1 class="mt-1.5 text-xl font-semibold capitalize tracking-tight text-slate-900 dark:text-white">
                        {{ isEdit ? role.name.replaceAll('_', ' ') : 'Create role' }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ isEdit
                            ? 'Choose which modules and workflow actions this role can use.'
                            : 'Name the role, then tick the permissions it should have.' }}
                    </p>
                </div>
                <span v-if="role?.is_super_admin" class="ui-badge-brand">Full access</span>
            </div>

            <section class="ui-panel p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Role</h2>
                <div class="grid gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-8">
                        <label class="ui-label">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            :disabled="role?.is_super_admin"
                            class="ui-input disabled:bg-surface-muted"
                        />
                        <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
                    </div>
                    <div class="lg:col-span-4">
                        <label class="ui-label">Guard</label>
                        <input v-model="form.guard_name" type="text" class="ui-input" />
                    </div>
                </div>
            </section>

            <section class="ui-panel p-4">
                <div class="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Permissions</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Grouped by module. Workflow actions such as approve, close, and generate advance form sit with their resource.
                        </p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Select all
                    </label>
                </div>

                <div class="relative mb-4">
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
                        v-model="query"
                        type="search"
                        placeholder="Search permissions…"
                        class="ui-input pl-9"
                        aria-label="Search permissions"
                    />
                </div>

                <div class="space-y-3">
                    <div
                        v-for="group in filteredGroups"
                        :key="group.key"
                        class="rounded-xl border border-slate-200/90 p-3 dark:border-slate-800"
                    >
                        <label class="mb-3 flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-slate-900 dark:text-white">{{ group.label }}</span>
                            <span class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                {{ groupState(group).selected }}/{{ groupState(group).total }}
                                <input
                                    type="checkbox"
                                    class="rounded border-slate-300 text-brand-600"
                                    :checked="groupState(group).checked"
                                    :indeterminate.prop="groupState(group).indeterminate"
                                    @change="toggleGroup(group, $event.target.checked)"
                                />
                            </span>
                        </label>
                        <div class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-3">
                            <label
                                v-for="perm in group.permissions"
                                :key="perm.name"
                                class="flex items-start gap-2 rounded-lg px-2 py-1.5 text-sm text-slate-700 hover:bg-surface-muted dark:text-slate-200"
                            >
                                <input
                                    v-model="form.permissions"
                                    type="checkbox"
                                    :value="perm.name"
                                    class="mt-0.5 rounded border-slate-300 text-brand-600"
                                />
                                <span>
                                    <span class="block">{{ perm.label }}</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <p v-if="!filteredGroups.length" class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                        No permissions match that search.
                    </p>
                </div>
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95">
                <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <p class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">
                        {{ selectedCount }} of {{ totalCount }} permission{{ totalCount === 1 ? '' : 's' }}
                    </p>
                    <div class="ml-auto flex items-center gap-2">
                        <Link :href="route('app.roles.index')" class="ui-btn-ghost">Cancel</Link>
                        <button type="submit" class="ui-btn-primary min-w-36" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create role') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
