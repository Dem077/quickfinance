<script setup>
import AppLayout from '../Layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';

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

        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm dark:border-slate-700 dark:bg-surface">
            <p class="text-sm font-medium uppercase tracking-wide text-slate-400">Coming soon</p>
            <h2 class="mt-2 text-xl font-semibold text-slate-900 dark:text-white">{{ title }}</h2>
            <p class="mx-auto mt-3 max-w-lg text-sm text-slate-600 dark:text-slate-400">
                This module (<code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">{{ module }}</code>)
                is not available in the app yet.
            </p>
            <Link :href="route('app.dashboard')" class="ui-btn-primary mt-6 inline-flex">
                Back to dashboard
            </Link>
        </div>
    </AppLayout>
</template>
