<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, required: true },
    filterOptions: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const departmentId = ref(props.filters.department_id ? String(props.filters.department_id) : '');
const role = ref(props.filters.role ?? '');
const signature = ref(props.filters.signature ?? '');
const sort = ref(props.filters.sort || 'newest');
const filtersOpen = ref(false);
const filterPanel = ref(null);
let searchTimer = null;

const hasRows = computed(() => (props.users.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const activeFilterCount = computed(() =>
    [departmentId.value, role.value, signature.value].filter(Boolean).length,
);
const hasExtraFilters = computed(() => activeFilterCount.value > 0);
const hasNonDefaultSort = computed(() => sort.value && sort.value !== 'newest');
const hasActiveFilters = computed(() => hasSearch.value || hasExtraFilters.value || hasNonDefaultSort.value);
const rangeLabel = computed(() => {
    const from = props.users.from;
    const to = props.users.to;
    const total = props.users.total;
    if (! total) return null;
    return `${from}–${to} of ${total}`;
});

const applyFilters = (overrides = {}) => {
    router.get(route('app.users.index'), {
        search: (overrides.search !== undefined ? overrides.search : search.value) || undefined,
        department_id: (overrides.department_id !== undefined ? overrides.department_id : departmentId.value) || undefined,
        role: (overrides.role !== undefined ? overrides.role : role.value) || undefined,
        signature: (overrides.signature !== undefined ? overrides.signature : signature.value) || undefined,
        sort: (() => {
            const nextSort = overrides.sort !== undefined ? overrides.sort : sort.value;
            return nextSort && nextSort !== 'newest' ? nextSort : undefined;
        })(),
    }, { preserveState: true, replace: true, preserveScroll: true });
};

watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        const next = value || '';
        const current = props.filters.search || '';
        if (next === current) return;
        applyFilters({ search: value });
    }, 280);
});

watch([departmentId, role, signature, sort], () => {
    applyFilters();
});

const clearSearch = () => {
    clearTimeout(searchTimer);
    search.value = '';
    if (props.filters.search) {
        applyFilters({ search: '' });
    }
};

const clearExtraFilters = () => {
    departmentId.value = '';
    role.value = '';
    signature.value = '';
    sort.value = 'newest';
};

const toggleFilters = () => {
    filtersOpen.value = ! filtersOpen.value;
};

const onDocumentClick = (event) => {
    if (! filtersOpen.value || ! filterPanel.value) return;
    if (! filterPanel.value.contains(event.target)) {
        filtersOpen.value = false;
    }
};

const onDocumentKeydown = (event) => {
    if (event.key === 'Escape') {
        filtersOpen.value = false;
    }
};

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onDocumentKeydown);
});

const roleLabel = (name) => (name || '').replaceAll('_', ' ');

const initials = (name) => {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (! parts.length) return '?';
    return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('');
};

const destroy = (user) => {
    if (! confirm(`Delete ${user.name}?`)) return;
    router.delete(route('app.users.destroy', user.id), { preserveScroll: true });
};
</script>

<template>
    <AppLayout description="Manage accounts, roles, and signatures used on finance documents.">
        <template #header>Users</template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex w-full max-w-xl items-center gap-2">
                    <div class="relative min-w-0 flex-1">
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
                            placeholder="Search name, email, designation…"
                            class="ui-input pl-9 pr-9"
                            aria-label="Search users"
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

                    <div ref="filterPanel" class="relative shrink-0">
                        <button
                            type="button"
                            class="relative inline-flex h-[42px] w-[42px] items-center justify-center rounded-lg border border-slate-200 bg-surface text-slate-600 transition hover:border-slate-300 hover:bg-surface-muted hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600 dark:hover:text-white"
                            :class="filtersOpen || hasExtraFilters || hasNonDefaultSort
                                ? 'border-brand-300 text-brand-700 dark:border-brand-700 dark:text-brand-300'
                                : ''"
                            :aria-expanded="filtersOpen"
                            aria-haspopup="dialog"
                            aria-label="Open filters"
                            @click.stop="toggleFilters"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18M6 12h12M10 19h4" />
                            </svg>
                            <span
                                v-if="activeFilterCount"
                                class="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-600 px-1 text-[10px] font-semibold text-white"
                            >
                                {{ activeFilterCount }}
                            </span>
                        </button>

                        <div
                            v-if="filtersOpen"
                            class="absolute right-0 z-30 mt-2 w-[min(20rem,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-surface-elevated"
                            @click.stop
                        >
                            <div class="space-y-3">
                                <div>
                                    <label class="ui-label">Department</label>
                                    <select v-model="departmentId" class="ui-input">
                                        <option value="">All departments</option>
                                        <option v-for="dept in filterOptions.departments" :key="dept.id" :value="String(dept.id)">
                                            {{ dept.name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ui-label">Role</label>
                                    <select v-model="role" class="ui-input">
                                        <option value="">All roles</option>
                                        <option v-for="name in filterOptions.roles" :key="name" :value="name">
                                            {{ roleLabel(name) }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ui-label">Signature</label>
                                    <select v-model="signature" class="ui-input">
                                        <option value="">Any</option>
                                        <option value="has">On file</option>
                                        <option value="missing">Missing</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ui-label">Sort</label>
                                    <select v-model="sort" class="ui-input">
                                        <option v-for="option in filterOptions.sorts" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>
                                <button
                                    v-if="hasExtraFilters || hasNonDefaultSort"
                                    type="button"
                                    class="ui-btn-ghost w-full"
                                    @click="clearExtraFilters"
                                >
                                    Reset filters
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <Link v-if="can.create" :href="route('app.users.create')" class="ui-btn-primary shrink-0">
                    Create user
                </Link>
            </div>

            <p v-if="hasActiveFilters && rangeLabel" class="text-sm text-slate-500 dark:text-slate-400">
                {{ rangeLabel }}
            </p>

            <div v-if="hasRows" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="user in users.data"
                    :key="user.id"
                    class="ui-card ui-card-hover flex flex-col p-4"
                >
                    <component
                        :is="user.actions?.edit ? Link : 'div'"
                        :href="user.actions?.edit ? route('app.users.edit', user.id) : undefined"
                        class="flex items-start gap-3"
                    >
                        <img
                            v-if="user.avatar_url"
                            :src="user.avatar_url"
                            :alt="user.name"
                            class="h-11 w-11 rounded-full object-cover"
                        />
                        <div
                            v-else
                            class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-sm font-semibold text-brand-800 dark:bg-brand-950/50 dark:text-brand-200"
                        >
                            {{ initials(user.name) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-base font-semibold tracking-tight text-slate-900 dark:text-white">
                                {{ user.name }}
                            </h2>
                            <p class="truncate text-sm text-slate-500 dark:text-slate-400">
                                {{ user.designation || 'No designation' }}
                            </p>
                        </div>
                        <span
                            class="shrink-0"
                            :class="user.has_signature ? 'ui-badge-success' : 'ui-badge-warn'"
                        >
                            {{ user.has_signature ? 'Signed' : 'No signature' }}
                        </span>
                    </component>

                    <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Email</dt>
                            <dd class="truncate text-right font-medium text-slate-700 dark:text-slate-200">{{ user.email }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Department</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">{{ user.department || '—' }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Location</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">{{ user.location || '—' }}</dd>
                        </div>
                        <div v-if="user.mobile" class="flex items-baseline justify-between gap-3">
                            <dt class="shrink-0 text-slate-400">Mobile</dt>
                            <dd class="truncate text-right text-slate-700 dark:text-slate-300">{{ user.mobile }}</dd>
                        </div>
                    </dl>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span
                            v-for="name in user.roles"
                            :key="name"
                            class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium capitalize text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ roleLabel(name) }}
                        </span>
                        <span v-if="!user.roles?.length" class="text-xs text-slate-400">No roles</span>
                    </div>

                    <div class="mt-auto flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-3 dark:border-slate-800">
                        <Link
                            v-if="user.actions?.edit"
                            :href="route('app.users.edit', user.id)"
                            class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-surface px-2.5 py-1.5 text-xs font-medium text-slate-700 transition hover:border-slate-300 hover:bg-surface-muted dark:border-slate-700 dark:text-slate-200"
                        >
                            Edit
                        </Link>
                        <button
                            v-if="user.actions?.delete"
                            type="button"
                            class="inline-flex items-center justify-center rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-xs font-medium text-red-700 transition hover:bg-red-100 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300"
                            @click="destroy(user)"
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
                    {{ hasSearch || hasExtraFilters ? 'No matching users' : 'No users yet' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                    {{ hasSearch || hasExtraFilters
                        ? 'Try another search or reset the filters.'
                        : 'Create the first user account to get started.' }}
                </p>
                <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                    <button
                        v-if="hasSearch || hasExtraFilters"
                        type="button"
                        class="ui-btn-secondary"
                        @click="() => { clearSearch(); clearExtraFilters(); }"
                    >
                        Reset
                    </button>
                    <Link v-if="can.create" :href="route('app.users.create')" class="ui-btn-primary">
                        Create user
                    </Link>
                </div>
            </div>

            <div
                v-if="users.links?.length > 3"
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ rangeLabel || ' ' }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <template v-for="(link, index) in users.links" :key="`${link.label}-${index}`">
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
