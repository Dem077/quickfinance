<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: { type: String, required: true },
});
</script>

<template>
    <Link
        :href="href"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        aria-label="Audit trail"
        title="Audit"
    >
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
    </Link>
</template>
