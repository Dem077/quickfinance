<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    description: { type: String, required: true },
    backUrl: { type: String, required: true },
    backLabel: { type: String, required: true },
    activities: { type: Object, required: true },
});

const expanded = ref(
    (props.activities.data || []).filter((row) => row.has_details).map((row) => row.id),
);
const hasRows = computed(() => (props.activities.data?.length || 0) > 0);
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

const isExpanded = (id) => expanded.value.includes(id);

const toggleExpanded = (id) => {
    expanded.value = isExpanded(id)
        ? expanded.value.filter((value) => value !== id)
        : [...expanded.value, id];
};

const initials = (name) => {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (! parts.length) return '?';
    return parts.slice(0, 2).map((part) => part[0]?.toUpperCase() || '').join('');
};

const formatDay = (iso, day) => {
    const date = iso ? new Date(iso) : (day ? new Date(`${day}T00:00:00`) : null);
    if (! date || Number.isNaN(date.getTime())) return 'Unknown date';
    return date.toLocaleDateString(undefined, {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const relativeTime = (iso) => {
    if (! iso) return '';
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return '';
    const seconds = Math.round((Date.now() - date.getTime()) / 1000);
    const rtf = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' });
    const units = [
        ['year', 31536000],
        ['month', 2592000],
        ['week', 604800],
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size || unit === 'minute') {
            return rtf.format(-Math.round(seconds / size), unit);
        }
    }
    return 'just now';
};

const eventBadgeClass = (event) => {
    const key = String(event || '').toLowerCase();
    if (key === 'created') return 'ui-badge-success';
    if (key === 'deleted') return 'ui-badge-danger';
    if (key === 'updated') return 'ui-badge-warn';
    return 'ui-badge-neutral';
};

const summaryLine = (row) => {
    const who = row.causer || 'System';
    const action = (row.event_label || row.description || 'changed').toLowerCase();
    if (row.subject) return `${who} ${action} ${row.subject}`;
    return `${who} ${action}`;
};
</script>

<template>
    <AppLayout :title="`${title} audit`" :description="description">
        <template #header>{{ title }}</template>

        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <Link :href="backUrl" class="ui-link inline-flex items-center gap-1 text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        {{ backLabel }}
                    </Link>
                    <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                        Audit
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ description }}
                    </p>
                </div>
                <p v-if="rangeLabel" class="text-sm text-slate-500 dark:text-slate-400">
                    {{ rangeLabel }}
                </p>
            </div>

            <div v-if="hasRows" class="space-y-8">
                <section v-for="group in grouped" :key="group.key">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ group.label }}
                    </h2>
                    <ol class="relative space-y-3 border-l border-slate-200 pl-5 dark:border-slate-800">
                        <li v-for="row in group.items" :key="row.id" class="relative">
                            <span class="absolute -left-[1.45rem] top-5 h-2.5 w-2.5 rounded-full border-2 border-white bg-brand-500 dark:border-surface" />
                            <article class="ui-card p-4">
                                <div class="flex items-start gap-3">
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        :title="row.causer"
                                    >
                                        {{ initials(row.causer) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <span :class="eventBadgeClass(row.event)">{{ row.event_label }}</span>
                                        </div>
                                        <p class="mt-1.5 text-sm font-medium text-slate-900 dark:text-white">
                                            {{ summaryLine(row) }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                            <span>{{ row.causer }}</span>
                                            <span> · {{ relativeTime(row.created_at) }}</span>
                                            <span> · {{ row.created_at_label }}</span>
                                        </p>
                                    </div>
                                    <button
                                        v-if="row.has_details"
                                        type="button"
                                        class="ui-btn-ghost px-2.5 py-1.5 text-xs"
                                        @click="toggleExpanded(row.id)"
                                    >
                                        {{ isExpanded(row.id) ? 'Hide' : 'Details' }}
                                    </button>
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
                                                        <span v-if="change.old" class="line-through decoration-slate-300">{{ change.old }}</span>
                                                        <span v-else>—</span>
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
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">No changes recorded</h2>
                <p class="mt-1 max-w-sm text-sm text-slate-500 dark:text-slate-400">
                    Updates to this record will appear here.
                </p>
            </div>

            <div
                v-if="activities.links?.length > 3"
                class="flex flex-wrap gap-1.5"
            >
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
    </AppLayout>
</template>
