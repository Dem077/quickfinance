<script setup>
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'bar' },
    labels: { type: Array, default: () => [] },
    values: { type: Array, default: () => [] },
});

const palette = [
    '#0891b2', '#0e7490', '#155e75', '#22d3ee', '#0369a1',
    '#0f766e', '#0284c7', '#67e8f9', '#164e63', '#134e4a',
    '#38bdf8', '#14b8a6',
];

const maxValue = computed(() => Math.max(0, ...props.values.map((value) => Number(value) || 0), 1));
const total = computed(() => props.values.reduce((sum, value) => sum + (Number(value) || 0), 0));

const bars = computed(() =>
    props.labels.map((label, index) => {
        const value = Number(props.values[index]) || 0;
        return {
            label,
            value,
            height: Math.max(4, (value / maxValue.value) * 100),
            color: palette[index % palette.length],
        };
    }),
);

const pieSlices = computed(() => {
    const slices = [];
    let angle = -90;
    const sum = total.value || 1;

    props.labels.forEach((label, index) => {
        const value = Number(props.values[index]) || 0;
        const sweep = (value / sum) * 360;
        const start = angle;
        const end = angle + sweep;
        slices.push({
            label,
            value,
            color: palette[index % palette.length],
            path: donutPath(80, 80, props.type === 'doughnut' ? 42 : 0, 68, start, end),
        });
        angle = end;
    });

    return slices;
});

const linePoints = computed(() => {
    const width = 320;
    const height = 160;
    const pad = 16;
    const count = Math.max(props.labels.length - 1, 1);

    return props.values.map((value, index) => {
        const x = pad + (index / count) * (width - pad * 2);
        const y = height - pad - ((Number(value) || 0) / maxValue.value) * (height - pad * 2);
        return `${x},${y}`;
    }).join(' ');
});

function polar(cx, cy, radius, angle) {
    const rad = (angle * Math.PI) / 180;
    return [cx + radius * Math.cos(rad), cy + radius * Math.sin(rad)];
}

function donutPath(cx, cy, inner, outer, start, end) {
    if (end - start >= 359.99) {
        const [x1, y1] = polar(cx, cy, outer, 0);
        const [x2, y2] = polar(cx, cy, outer, 180);
        if (inner <= 0) {
            return `M ${x1} ${y1} A ${outer} ${outer} 0 1 1 ${x2} ${y2} A ${outer} ${outer} 0 1 1 ${x1} ${y1}`;
        }
        const [ix1, iy1] = polar(cx, cy, inner, 0);
        const [ix2, iy2] = polar(cx, cy, inner, 180);
        return `M ${x1} ${y1} A ${outer} ${outer} 0 1 1 ${x2} ${y2} A ${outer} ${outer} 0 1 1 ${x1} ${y1} M ${ix1} ${iy1} A ${inner} ${inner} 0 1 0 ${ix2} ${iy2} A ${inner} ${inner} 0 1 0 ${ix1} ${iy1}`;
    }

    const large = end - start > 180 ? 1 : 0;
    const [sx, sy] = polar(cx, cy, outer, start);
    const [ex, ey] = polar(cx, cy, outer, end);

    if (inner <= 0) {
        return `M ${cx} ${cy} L ${sx} ${sy} A ${outer} ${outer} 0 ${large} 1 ${ex} ${ey} Z`;
    }

    const [isx, isy] = polar(cx, cy, inner, end);
    const [iex, iey] = polar(cx, cy, inner, start);
    return `M ${sx} ${sy} A ${outer} ${outer} 0 ${large} 1 ${ex} ${ey} L ${isx} ${isy} A ${inner} ${inner} 0 ${large} 0 ${iex} ${iey} Z`;
}
</script>

<template>
    <div v-if="!labels.length" class="flex h-48 items-center justify-center text-sm text-slate-500">
        No data for this chart.
    </div>

    <div v-else-if="type === 'bar'" class="space-y-3">
        <div class="flex h-44 items-end gap-1.5 px-1">
            <div
                v-for="bar in bars"
                :key="bar.label"
                class="flex min-w-0 flex-1 flex-col items-center justify-end"
                :title="`${bar.label}: ${bar.value}`"
            >
                <div class="w-full rounded-t-md" :style="{ height: `${bar.height}%`, background: bar.color }" />
            </div>
        </div>
        <div class="flex gap-1.5 px-1">
            <p
                v-for="bar in bars"
                :key="bar.label"
                class="min-w-0 flex-1 truncate text-center text-[10px] text-slate-500"
                :title="bar.label"
            >
                {{ bar.label }}
            </p>
        </div>
    </div>

    <div v-else-if="type === 'line'" class="space-y-2">
        <svg viewBox="0 0 320 160" class="h-44 w-full overflow-visible">
            <polyline fill="none" stroke="#0891b2" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" :points="linePoints" />
            <circle
                v-for="(point, index) in linePoints.split(' ')"
                :key="index"
                :cx="point.split(',')[0]"
                :cy="point.split(',')[1]"
                r="4"
                fill="#0891b2"
            />
        </svg>
        <div class="flex gap-1.5 px-1">
            <p
                v-for="label in labels"
                :key="label"
                class="min-w-0 flex-1 truncate text-center text-[10px] text-slate-500"
            >
                {{ label }}
            </p>
        </div>
    </div>

    <div v-else class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_12rem] sm:items-center">
        <svg viewBox="0 0 160 160" class="mx-auto h-48 w-48">
            <path
                v-for="slice in pieSlices"
                :key="slice.label"
                :d="slice.path"
                :fill="slice.color"
            />
        </svg>
        <ul class="space-y-1.5">
            <li v-for="slice in pieSlices" :key="slice.label" class="flex items-center gap-2 text-xs">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ background: slice.color }" />
                <span class="min-w-0 truncate text-slate-600 dark:text-slate-300">{{ slice.label }}</span>
                <span class="ml-auto tabular-nums font-medium text-slate-800 dark:text-slate-100">{{ slice.value }}</span>
            </li>
        </ul>
    </div>
</template>
