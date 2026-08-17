<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateRangePicker from '../../Components/UiDateRangePicker.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    activities: { type: Object, required: true },
    filters: { type: Object, required: true },
    tabs: { type: Array, required: true },
    filterOptions: { type: Object, required: true },
    can: { type: Object, required: true },
});

const search = ref(props.filters.search ?? '');
const event = ref(props.filters.event ?? '');
const causerId = ref(props.filters.causer_id ? String(props.filters.causer_id) : '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const filtersOpen = ref(false);
const filterPanel = ref(null);
const selected = ref([]);
const expanded = ref([]);
let searchTimer = null;

const activeTab = computed(() => props.filters.log_name || 'all');
const activeTabMeta = computed(() => props.tabs.find((tab) => tab.key === activeTab.value) || null);
const hasRows = computed(() => (props.activities.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const activeFilterCount = computed(() =>
    [event.value, causerId.value, dateFrom.value, dateTo.value].filter(Boolean).length,
);
const hasExtraFilters = computed(() => activeFilterCount.value > 0);
const hasActiveFilters = computed(() => hasSearch.value || hasExtraFilters.value);
const rangeLabel = computed(() => {
    const from = props.activities.from;
    const to = props.activities.to;
    const total = props.activities.total;
    if (! total) return null;
    return `${from}–${to} of ${total}`;
});

const grouped = computed(() => {
    const groups = [];
    const map = new Map();

    (props.activities.data || []).forEach((row) => {
        const key = row.day || 'unknown';
        if (! map.has(key)) {
            const group = { key, label: formatDay(row.created_at, row.day), items: [] };
            map.set(key, group);
            groups.push(group);
        }
        map.get(key).items.push(row);
    });

    return groups;
});

const allSelected = computed({
    get: () => hasRows.value && selected.value.length === props.activities.data.length,
    set: (value) => {
        selected.value = value ? props.activities.data.map((row) => row.id) : [];
    },
});

const applyFilters = (overrides = {}) => {
    router.get(route('app.activity.index'), {
        search: (overrides.search !== undefined ? overrides.search : search.value) || undefined,
        log_name: (() => {
            const next = overrides.log_name ?? activeTab.value;
            return next && next !== 'all' ? next : undefined;
        })(),
        event: (overrides.event !== undefined ? overrides.event : event.value) || undefined,
        causer_id: (overrides.causer_id !== undefined ? overrides.causer_id : causerId.value) || undefined,
        date_from: (overrides.date_from !== undefined ? overrides.date_from : dateFrom.value) || undefined,
        date_to: (overrides.date_to !== undefined ? overrides.date_to : dateTo.value) || undefined,
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

watch([event, causerId, dateFrom, dateTo], () => {
    applyFilters();
});

watch(() => props.activities.data, () => {
    selected.value = [];
    expanded.value = [];
});

const selectTab = (tab) => {
    if (tab === activeTab.value) return;
    applyFilters({ log_name: tab });
};

const clearSearch = () => {
    clearTimeout(searchTimer);
    search.value = '';
    if (props.filters.search) {
        applyFilters({ search: '' });
    }
};

const clearExtraFilters = () => {
    event.value = '';
    causerId.value = '';
    dateFrom.value = '';
    dateTo.value = '';
};

const toggleFilters = () => {
    filtersOpen.value = ! filtersOpen.value;
};

const onDocumentClick = (eventTarget) => {
    if (! filtersOpen.value || ! filterPanel.value) return;
    const target = eventTarget.target;
    if (target?.closest?.('.daterangepicker') || target?.closest?.('.flatpickr-calendar')) return;
    if (! filterPanel.value.contains(target)) {
        filtersOpen.value = false;
    }
};

const onDocumentKeydown = (eventTarget) => {
    if (eventTarget.key === 'Escape') {
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

const toggleExpanded = (id) => {
    if (expanded.value.includes(id)) {
        expanded.value = expanded.value.filter((item) => item !== id);
        return;
    }
    expanded.value = [...expanded.value, id];
};

const isExpanded = (id) => expanded.value.includes(id);

const bulkForm = useForm({ ids: [] });

const deleteSelected = () => {
    if (! selected.value.length || ! confirm(`Delete ${selected.value.length} log${selected.value.length === 1 ? '' : 's'}?`)) return;
    bulkForm.ids = selected.value;
    bulkForm.delete(route('app.activity.destroy-many'), {
        preserveScroll: true,
        onSuccess: () => { selected.value = []; },
    });
};

const deleteOne = (row) => {
    if (! confirm('Delete this activity log?')) return;
    router.delete(route('app.activity.destroy', row.id), { preserveScroll: true });
};

const formatDay = (iso, day) => {
    const date = iso ? new Date(iso) : (day ? new Date(`${day}T00:00:00`) : null);
    if (! date || Number.isNaN(date.getTime())) return 'Unknown date';
    const today = new Date();
    const yesterday = new Date();
    yesterday.setDate(today.getDate() - 1);
    const sameDay = (a, b) => a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    if (sameDay(date, today)) return 'Today';
    if (sameDay(date, yesterday)) return 'Yesterday';
    return date.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
};

const relativeTime = (iso) => {
    if (! iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    const seconds = Math.round((Date.now() - date.getTime()) / 1000);
    const abs = Math.abs(seconds);
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    if (abs < 60) return rtf.format(-Math.round(seconds), 'second');
    if (abs < 3600) return rtf.format(-Math.round(seconds / 60), 'minute');
    if (abs < 86400) return rtf.format(-Math.round(seconds / 3600), 'hour');
    if (abs < 604800) return rtf.format(-Math.round(seconds / 86400), 'day');
    return rtf.format(-Math.round(seconds / 604800), 'week');
};

const initials = (name) => {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (! parts.length) return '?';
    return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('');
};

const eventBadgeClass = (eventName) => {
    const key = String(eventName || '').toLowerCase();
    if (['created', 'login', 'logged in'].includes(key)) return 'ui-badge-success';
    if (['updated', 'restored'].includes(key)) return 'ui-badge-brand';
    if (['deleted', 'failed', 'logout'].includes(key)) return 'ui-badge-danger';
    return 'ui-badge-neutral';
};

const logToneClass = (name) => {
    const key = String(name || '').toLowerCase();
    return {
        access: 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300',
        resource: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        model: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        notification: 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300',
    }[key] || 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
};

const tabBadgeClass = (tone, active) => {
    if (active) {
        return {
            neutral: 'bg-brand-100 text-brand-800 dark:bg-brand-950/50 dark:text-brand-200',
            warn: 'bg-amber-100 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200',
            success: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
            danger: 'bg-red-100 text-red-800 dark:bg-red-950/50 dark:text-red-200',
            brand: 'bg-brand-100 text-brand-800 dark:bg-brand-950/50 dark:text-brand-200',
        }[tone] || 'bg-brand-100 text-brand-800';
    }

    return {
        neutral: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        warn: 'bg-amber-50 text-amber-600 dark:bg-amber-950/30 dark:text-amber-400',
        success: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30 dark:text-emerald-400',
        danger: 'bg-red-50 text-red-600 dark:bg-red-950/30 dark:text-red-400',
        brand: 'bg-brand-50 text-brand-600 dark:bg-brand-950/30 dark:text-brand-400',
    }[tone] || 'bg-slate-100 text-slate-500';
};

const summaryLine = (row) => {
    const who = row.causer || 'System';
    const action = (row.event_label || row.description || 'did something').toLowerCase();
    if (row.subject) return `${who} ${action} ${row.subject}`;
    return `${who} ${action}`;
};
</script>

<template>
    <AppLayout description="See who changed records, signed in, and when it happened.">
        <template #header>Activity Log</template>

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
                            placeholder="Search user, event, or record…"
                            class="ui-input pl-9 pr-9"
                            aria-label="Search activity"
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
                            :class="filtersOpen || hasExtraFilters
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
                            class="absolute right-0 z-30 mt-2 w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-slate-200 bg-white p-4 shadow-lg dark:border-slate-700 dark:bg-surface-elevated"
                            @click.stop
                        >
                            <div class="mb-3 flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">Filters</p>
                                <button
                                    v-if="hasExtraFilters"
                                    type="button"
                                    class="text-xs font-medium text-brand-700 hover:text-brand-600 dark:text-brand-400"
                                    @click="clearExtraFilters"
                                >
                                    Clear
                                </button>
                            </div>
                            <div class="space-y-3">
                                <div>
                                    <label class="ui-label">Event</label>
                                    <select v-model="event" class="ui-input">
                                        <option value="">All events</option>
                                        <option v-for="name in filterOptions.events" :key="name" :value="name">
                                            {{ name }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="ui-label">User</label>
                                    <select v-model="causerId" class="ui-input">
                                        <option value="">Anyone</option>
                                        <option v-for="person in filterOptions.causers" :key="person.id" :value="String(person.id)">
                                            {{ person.name }}
                                        </option>
                                    </select>
                                </div>
                                <UiDateRangePicker
                                    id="activity-filter-dates"
                                    v-model:from="dateFrom"
                                    v-model:to="dateTo"
                                    label="Date range"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <label v-if="can.deleteAny && hasRows" class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                        <input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                        Select page
                    </label>
                    <button
                        v-if="can.deleteAny && selected.length"
                        type="button"
                        class="ui-btn-danger"
                        @click="deleteSelected"
                    >
                        Delete {{ selected.length }}
                    </button>
                </div>
            </div>

            <div v-if="tabs.length" class="overflow-x-auto border-b border-slate-200 dark:border-slate-800">
                <div class="flex min-w-max items-end sm:min-w-0 sm:w-full" role="tablist" aria-label="Activity type">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.key"
                        class="relative inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-3.5 py-2.5 text-sm transition"
                        :class="activeTab === tab.key
                            ? 'border-brand-600 font-semibold text-brand-700 dark:border-brand-400 dark:text-brand-300'
                            : 'border-transparent font-medium text-slate-500 hover:border-slate-300 hover:text-slate-800 dark:text-slate-400 dark:hover:border-slate-600 dark:hover:text-slate-200'"
                        @click="selectTab(tab.key)"
                    >
                        <span>{{ tab.label }}</span>
                        <span
                            class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1.5 text-[11px] font-semibold leading-none tabular-nums"
                            :class="tabBadgeClass(tab.tone, activeTab === tab.key)"
                        >
                            {{ tab.badge }}
                        </span>
                    </button>
                </div>
            </div>

            <div
                v-if="rangeLabel || hasActiveFilters"
                class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500 dark:text-slate-400"
            >
                <p>
                    <span v-if="activeTabMeta">{{ activeTabMeta.label }}</span>
                    <span v-if="activeTabMeta && rangeLabel"> · </span>
                    <span v-if="rangeLabel">{{ rangeLabel }}</span>
                </p>
                <p v-if="hasSearch" class="truncate">“{{ search.trim() }}”</p>
            </div>

            <div v-if="hasRows" class="space-y-8">
                <section v-for="group in grouped" :key="group.key">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ group.label }}
                    </h2>
                    <ol class="relative space-y-3 border-l border-slate-200 pl-5 dark:border-slate-800">
                        <li v-for="row in group.items" :key="row.id" class="relative">
                            <span class="absolute -left-[1.45rem] top-5 h-2.5 w-2.5 rounded-full border-2 border-white bg-brand-500 dark:border-surface" />
                            <article class="ui-card ui-card-hover p-4">
                                <div class="flex items-start gap-3">
                                    <input
                                        v-if="can.deleteAny"
                                        v-model="selected"
                                        type="checkbox"
                                        :value="row.id"
                                        class="mt-1 rounded border-slate-300 text-brand-600"
                                        @click.stop
                                    />
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        :title="row.causer"
                                    >
                                        {{ initials(row.causer) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span :class="eventBadgeClass(row.event)">{{ row.event_label }}</span>
                                            <span
                                                class="inline-flex rounded-md px-2 py-0.5 text-[11px] font-medium"
                                                :class="logToneClass(row.log_name)"
                                            >
                                                {{ row.log_label }}
                                            </span>
                                        </div>
                                        <p class="mt-1.5 text-sm font-medium text-slate-900 dark:text-white">
                                            {{ summaryLine(row) }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            <span>{{ row.causer }}</span>
                                            <span v-if="row.causer_email"> · {{ row.causer_email }}</span>
                                            <span> · {{ relativeTime(row.created_at) }}</span>
                                            <span> · {{ row.created_at_label }}</span>
                                        </p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button
                                            v-if="row.has_details"
                                            type="button"
                                            class="ui-btn-ghost px-2.5 py-1.5 text-xs"
                                            @click="toggleExpanded(row.id)"
                                        >
                                            {{ isExpanded(row.id) ? 'Hide' : 'Details' }}
                                        </button>
                                        <button
                                            v-if="row.can_delete"
                                            type="button"
                                            class="inline-flex items-center justify-center rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 transition hover:bg-rose-50 dark:hover:bg-rose-950/30"
                                            @click="deleteOne(row)"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>

                                <div v-if="isExpanded(row.id) && row.has_details" class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                                    <div v-if="row.changes?.length" class="overflow-hidden rounded-xl border border-slate-100 dark:border-slate-800">
                                        <table class="min-w-full text-sm">
                                            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-surface-muted/40 dark:text-slate-400">
                                                <tr>
                                                    <th class="px-3 py-2">Field</th>
                                                    <th class="px-3 py-2">From</th>
                                                    <th class="px-3 py-2">To</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                <tr v-for="change in row.changes" :key="change.field">
                                                    <td class="px-3 py-2 font-medium text-slate-700 dark:text-slate-200">{{ change.label }}</td>
                                                    <td class="px-3 py-2 text-slate-500 dark:text-slate-400">
                                                        <span class="line-through decoration-slate-300">{{ change.old ?? '—' }}</span>
                                                    </td>
                                                    <td class="px-3 py-2 text-slate-800 dark:text-slate-100">{{ change.new ?? '—' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <dl v-if="row.extra_properties?.length" class="mt-3 grid gap-2 sm:grid-cols-2">
                                        <div
                                            v-for="item in row.extra_properties"
                                            :key="item.label"
                                            class="rounded-xl bg-slate-50 px-3 py-2 dark:bg-surface-muted/40"
                                        >
                                            <dt class="text-[11px] uppercase tracking-wide text-slate-400">{{ item.label }}</dt>
                                            <dd class="mt-0.5 break-all text-sm text-slate-700 dark:text-slate-200">{{ item.value }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </article>
                        </li>
                    </ol>
                </section>
            </div>

            <div
                v-else
                class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-surface px-6 py-16 text-center dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ hasActiveFilters ? 'No matching activity' : 'No activity yet' }}
                </h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                    {{ hasActiveFilters
                        ? 'Try another search or reset the filters.'
                        : 'Changes to records and sign-ins will show up here.' }}
                </p>
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="ui-btn-secondary mt-5"
                    @click="() => { clearSearch(); clearExtraFilters(); }"
                >
                    Reset
                </button>
            </div>

            <div
                v-if="activities.links?.length > 3"
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ rangeLabel || ' ' }}
                </p>
                <div class="flex flex-wrap gap-1.5">
                    <template v-for="(link, index) in activities.links" :key="`${link.label}-${index}`">
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
