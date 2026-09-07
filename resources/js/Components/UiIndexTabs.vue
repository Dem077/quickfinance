<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    tabs: { type: Array, required: true },
    modelValue: { type: String, required: true },
    pinned: { type: String, default: null },
    page: { type: String, required: true },
    ariaLabel: { type: String, default: 'Tabs' },
    centered: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'select']);

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

const selectTab = (key) => {
    if (key === props.modelValue) return;
    emit('update:modelValue', key);
    emit('select', key);
};

const togglePin = (key, event) => {
    event.preventDefault();
    event.stopPropagation();

    const next = props.pinned === key ? null : key;

    router.put(route('app.preferences.pinned-tab'), {
        page: props.page,
        tab: next,
    }, {
        preserveScroll: true,
        preserveState: true,
        only: ['pinnedTab'],
    });
};
</script>

<template>
    <div class="overflow-x-auto border-b border-slate-200 dark:border-slate-800">
        <div
            class="flex min-w-max items-end sm:min-w-0 sm:w-full"
            :class="centered ? 'justify-center' : ''"
            role="tablist"
            :aria-label="ariaLabel"
        >
            <div
                v-for="tab in tabs"
                :key="tab.key"
                class="relative inline-flex items-center border-b-2"
                :class="modelValue === tab.key
                    ? 'border-brand-600'
                    : 'border-transparent hover:border-slate-300 dark:hover:border-slate-600'"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="modelValue === tab.key"
                    class="inline-flex items-center gap-2 whitespace-nowrap px-3.5 py-2.5 text-sm transition"
                    :class="modelValue === tab.key
                        ? 'font-semibold text-brand-700 dark:text-brand-300'
                        : 'font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                    @click="selectTab(tab.key)"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        class="inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-md px-1.5 text-[11px] font-semibold leading-none tabular-nums"
                        :class="tabBadgeClass(tab.tone, modelValue === tab.key)"
                    >
                        {{ tab.badge }}
                    </span>
                </button>
                <button
                    type="button"
                    class="mr-1.5 inline-flex h-7 w-7 items-center justify-center rounded-md transition"
                    :class="pinned === tab.key
                        ? 'text-brand-600 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-950/40'
                        : 'text-slate-300 hover:bg-slate-100 hover:text-slate-500 dark:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-300'"
                    :title="pinned === tab.key ? 'Unpin default tab' : 'Pin as default tab'"
                    :aria-label="pinned === tab.key ? `Unpin ${tab.label}` : `Pin ${tab.label} as default`"
                    :aria-pressed="pinned === tab.key"
                    @click="togglePin(tab.key, $event)"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        viewBox="0 0 24 24"
                        :fill="pinned === tab.key ? 'currentColor' : 'none'"
                        stroke="currentColor"
                        stroke-width="1.8"
                        aria-hidden="true"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 17v5M8 2h8l-1 7h3l-6 7-6-7h3L8 2z"
                        />
                    </svg>
                </button>
            </div>
        </div>
    </div>
</template>
