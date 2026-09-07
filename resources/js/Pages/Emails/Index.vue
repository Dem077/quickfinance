<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    emails: { type: Object, required: true },
    filters: { type: Object, required: true },
    tabs: { type: Array, required: true },
});

const search = ref(props.filters.search ?? '');
const expanded = ref([]);
let searchTimer = null;

const activeTab = computed(() => props.filters.status || 'all');
const activeTabMeta = computed(() => props.tabs.find((tab) => tab.key === activeTab.value) || null);
const hasRows = computed(() => (props.emails.data?.length || 0) > 0);
const hasSearch = computed(() => Boolean((search.value || '').trim()));
const rangeLabel = computed(() => {
    const from = props.emails.from;
    const to = props.emails.to;
    const total = props.emails.total;
    if (! total) return null;
    return `${from}–${to} of ${total}`;
});

const applyFilters = (overrides = {}) => {
    router.get(route('app.emails.index'), {
        search: (overrides.search !== undefined ? overrides.search : search.value) || undefined,
        status: (() => {
            const next = overrides.status ?? activeTab.value;
            return next && next !== 'all' ? next : undefined;
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

watch(() => props.emails.data, () => {
    expanded.value = [];
});

onBeforeUnmount(() => clearTimeout(searchTimer));

const selectTab = (key) => applyFilters({ status: key });

const clearSearch = () => {
    search.value = '';
    applyFilters({ search: '' });
};

const toggleExpanded = (id) => {
    if (expanded.value.includes(id)) {
        expanded.value = expanded.value.filter((item) => item !== id);
        return;
    }
    expanded.value = [...expanded.value, id];
};

const isExpanded = (id) => expanded.value.includes(id);

const statusBadgeClass = (status) => ({
    pending: 'ui-badge-warn',
    sending: 'ui-badge-brand',
    sent: 'ui-badge-success',
    failed: 'ui-badge-danger',
}[status] || 'ui-badge-neutral');

const tabBadgeClass = (tone, active) => {
    if (active) {
        return {
            neutral: 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300',
            warn: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
            success: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
            danger: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
            brand: 'bg-brand-50 text-brand-700 dark:bg-brand-950/40 dark:text-brand-300',
        }[tone] || 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200';
    }

    return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300';
};

const formatWhen = (value) => {
    if (! value) return '—';
    try {
        return new Intl.DateTimeFormat(undefined, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(value));
    } catch {
        return value;
    }
};
</script>

<template>
    <AppLayout description="Track queued emails that are still pending, successfully sent, or failed.">
        <template #header>Email status</template>

        <div class="space-y-5">
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
                        placeholder="Search recipient, subject, body, or type…"
                        class="ui-input pl-9 pr-9"
                        aria-label="Search emails"
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
            </div>

            <div v-if="tabs.length" class="overflow-x-auto border-b border-slate-200 dark:border-slate-800">
                <div class="flex min-w-max items-end sm:min-w-0 sm:w-full" role="tablist" aria-label="Email status">
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
                v-if="rangeLabel || hasSearch"
                class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-500 dark:text-slate-400"
            >
                <p>
                    <span v-if="activeTabMeta">{{ activeTabMeta.label }}</span>
                    <span v-if="activeTabMeta && rangeLabel"> · </span>
                    <span v-if="rangeLabel">{{ rangeLabel }}</span>
                </p>
                <p v-if="hasSearch" class="truncate">“{{ search.trim() }}”</p>
            </div>

            <div v-if="hasRows" class="ui-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                        <thead class="bg-surface-muted/60 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-900/40">
                            <tr>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Subject</th>
                                <th class="px-4 py-3">Recipient</th>
                                <th class="px-4 py-3">Type</th>
                                <th class="px-4 py-3">Queued</th>
                                <th class="px-4 py-3">Sent / Failed</th>
                                <th class="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            <template v-for="row in emails.data" :key="row.id">
                                <tr class="align-top">
                                    <td class="px-4 py-3">
                                        <span :class="statusBadgeClass(row.status)">{{ row.status_label }}</span>
                                        <p v-if="row.error" class="mt-1 max-w-xs text-xs text-rose-600 dark:text-rose-400">
                                            {{ row.error }}
                                        </p>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">
                                        {{ row.subject }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                        {{ row.recipients_label }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">
                                        {{ row.mailable_label }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                                        {{ formatWhen(row.queued_at || row.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-500">
                                        {{ formatWhen(row.sent_at || row.failed_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            v-if="row.has_body"
                                            type="button"
                                            class="ui-btn-ghost px-2.5 py-1.5 text-xs"
                                            @click="toggleExpanded(row.id)"
                                        >
                                            {{ isExpanded(row.id) ? 'Hide body' : 'View body' }}
                                        </button>
                                        <span v-else class="text-xs text-slate-400">No body</span>
                                    </td>
                                </tr>
                                <tr v-if="isExpanded(row.id) && row.has_body">
                                    <td colspan="7" class="bg-surface-muted/40 px-4 py-4 dark:bg-slate-900/30">
                                        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-surface">
                                            <iframe
                                                v-if="row.body_html"
                                                :srcdoc="row.body_html"
                                                class="h-[28rem] w-full bg-white"
                                                sandbox=""
                                                title="Email body preview"
                                            />
                                            <pre
                                                v-else
                                                class="max-h-[28rem] overflow-auto whitespace-pre-wrap break-words p-4 text-sm text-slate-700 dark:text-slate-200"
                                            >{{ row.body_text }}</pre>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                v-else
                class="ui-card flex flex-col items-center justify-center px-6 py-16 text-center"
            >
                <p class="text-base font-semibold text-slate-900 dark:text-white">
                    {{ hasSearch || activeTab !== 'all' ? 'No matching emails' : 'No emails logged yet' }}
                </p>
                <p class="mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                    New queued emails appear here as pending, then move to successful or failed after the queue worker processes them.
                </p>
            </div>

            <div
                v-if="emails.prev_page_url || emails.next_page_url"
                class="flex items-center justify-between gap-3"
            >
                <Link
                    v-if="emails.prev_page_url"
                    :href="emails.prev_page_url"
                    class="ui-btn-secondary"
                    preserve-scroll
                >
                    Previous
                </Link>
                <span v-else />
                <Link
                    v-if="emails.next_page_url"
                    :href="emails.next_page_url"
                    class="ui-btn-secondary"
                    preserve-scroll
                >
                    Next
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
