<script setup>
defineProps({
    size: {
        type: String,
        default: 'md',
    },
});

const sizeClasses = {
    sm: 'h-9 w-9 rounded-lg text-xs',
    md: 'h-10 w-10 rounded-xl text-sm',
    lg: 'h-28 w-28 rounded-3xl text-5xl',
};
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center bg-brand-600 font-semibold tracking-tight text-white"
        :class="sizeClasses[size] || sizeClasses.md"
    >
        F
    </div>
</template>
