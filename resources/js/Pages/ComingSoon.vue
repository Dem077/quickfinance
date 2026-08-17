<script setup>
import AppLayout from '../Layouts/AppLayout.vue';

defineProps({
    module: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <AppLayout>
        <template #header>{{ title }}</template>

        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">
            <p class="text-sm font-medium uppercase tracking-wide text-slate-400">Coming soon</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900">{{ title }}</h2>
            <p class="mx-auto mt-3 max-w-lg text-sm text-slate-600">
                This module (<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ module }}</code>)
                will be migrated with full Filament action and workflow parity. Use Filament Admin for now.
            </p>
            <a
                href="/admin"
                class="mt-6 inline-flex rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700"
            >
                Open Filament
            </a>
        </div>
    </AppLayout>
</template>
