<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    roles: { type: Object, required: true },
    filters: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
let searchTimer = null;

const hasRows = computed(() => (props.roles.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const rangeLabel = computed(() => {
    const from = props.roles.from;
    const to = props.roles.to;
    const total = props.roles.total;
    if (! total) return null;
    return `${from}–${to} of ${total}`;
});

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value || '';
        const current = props.filters.search || '';
        if (next === current) return;
        router.get(route('app.roles.index'), { search: value || undefined }, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    }, 280);
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
});

const roleLabel = (name) => (name || '').replaceAll('_', ' ');

const clearSearch = () => {
    clearTimeout(searchTimer);
    search.value = '';
    if (props.filters.search) {
        router.get(route('app.roles.index'), {}, { preserveState: true, replace: true, preserveScroll: true });
    }
};

const destroy = (row) => {
    if (row.is_super_admin) return;
    if (! confirm(`Delete role "${roleLabel(row.name)}"?`)) return;
    router.delete(route('app.roles.destroy', row.id), { preserveScroll: true });
};
</script>

<template>
    <AppLayout description="Assign resource permissions used across purchase requests, procure, and petty cash.">
        <template #header>Roles</template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="relative w-full max-w-xl">
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
                        v-model="search"
                        type="search"
                        placeholder="Search roles…"
                        class="ui-input pl-9 pr-9"
                        aria-label="Search roles"
                    />
                    <button
                        v-if="hasSearch"
                        type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg px-2 py-1 text-xs font-medium text-slate-500 transition hover:bg-surface-muted hover:text-slate-800 dark:hover:text-slate-200"
                        @click="clearSearch"
                    >
                        Clear
                    </button>
                </div>

                <Link v-if="can.create" :href="route('app.roles.create')" class="ui-btn-primary shrink-0">
                    Create role
                </Link>
            </div>

            <p v-if="hasSearch && rangeLabel" class="text-sm text-slate-500 dark:text-slate-400">
                {{ rangeLabel }}
            </p>

            <div v-if="hasRows" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="row in roles.data"
                    :key="row.id"
                    class="ui-card ui-card-hover flex flex-col p-4"
                >
                    <Link :href="route('app.roles.edit', row.id)" class="flex items-start gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-800 dark:bg-brand-950/50 dark:text-brand-200">
                            {{ roleLabel(row.name).slice(0, 2).toUpperCase() }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-base font-semibold capitalize tracking-tight text-slate-900 dark:text-white">
                                {{ roleLabel(row.name) }}
                            </h2>
                            <p class="truncate text-sm text-slate-500 dark:text-slate-400">
                                {{ row.users_count }} {{ row.users_count === 1 ? 'user' : 'users' }}
                            </p>
                        </div>
                        <span :class="row.is_super_admin ? 'ui-badge-brand' : 'ui-badge-neutral'">
                            {{ row.is_super_admin ? 'Full access' : row.guard_name }}
                        </span>
                    </Link>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">Permissions</dt>
                            <dd class="font-medium text-slate-800 dark:text-slate-200">{{ row.permissions_count }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 flex items-center gap-2">
                        <Link :href="route('app.roles.edit', row.id)" class="ui-btn-secondary flex-1">
                            Edit
                        </Link>
                        <button
                            v-if="!row.is_super_admin"
                            type="button"
                            class="ui-btn-ghost text-rose-600 hover:text-rose-700 dark:text-rose-400"
                            @click="destroy(row)"
                        >
                            Delete
                        </button>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-surface px-6 py-16 text-center dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ hasSearch ? 'No matching roles' : 'No roles yet' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                    {{ hasSearch
                        ? 'Try another search.'
                        : 'Create a role and choose which modules it can access.' }}
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                    <button v-if="hasSearch" type="button" class="ui-btn-secondary" @click="clearSearch">
                        Reset
                    </button>
                    <Link v-if="can.create" :href="route('app.roles.create')" class="ui-btn-primary">
                        Create role
                    </Link>
                </div>
            </div>

            <div
                v-if="roles.links?.length > 3"
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ rangeLabel || ' ' }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <template v-for="(link, index) in roles.links" :key="`${link.label}-${index}`">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-xl border px-2.5 text-sm transition"
                            :class="link.active
                                ? 'border-brand-600 bg-brand-600 text-white'
                                : 'border-slate-200 bg-surface text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:hover:border-slate-600'"
                            :preserve-scroll="true"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="inline-flex h-8 min-w-8 items-center justify-center rounded-xl border border-transparent px-2.5 text-sm text-slate-300 dark:text-slate-600"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
