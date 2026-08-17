<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Signature' },
    hint: { type: String, default: 'Sign with your mouse or finger. This appears on generated PDFs.' },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const wrap = ref(null);
const canvas = ref(null);
const drawing = ref(false);
const isEmpty = ref(true);
const strokes = ref([]);
let currentStroke = [];
let ctx = null;

const canvasBackground = () => '#ffffff';
const strokeColor = () => '#0f172a';

const sizeCanvas = () => {
    if (! canvas.value || ! wrap.value) return;
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const width = wrap.value.clientWidth || 560;
    const height = 180;
    canvas.value.width = Math.floor(width * ratio);
    canvas.value.height = Math.floor(height * ratio);
    canvas.value.style.width = `${width}px`;
    canvas.value.style.height = `${height}px`;
    ctx = canvas.value.getContext('2d');
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.lineWidth = 2.2;
    redraw();
};

const fillBackground = () => {
    if (! ctx || ! canvas.value) return;
    const width = canvas.value.clientWidth;
    const height = canvas.value.clientHeight;
    ctx.fillStyle = canvasBackground();
    ctx.fillRect(0, 0, width, height);
};

const pointFromEvent = (event) => {
    const rect = canvas.value.getBoundingClientRect();
    const source = event.touches?.[0] || event.changedTouches?.[0] || event;
    return {
        x: source.clientX - rect.left,
        y: source.clientY - rect.top,
    };
};

const drawStroke = (points) => {
    if (! ctx || points.length === 0) return;
    ctx.strokeStyle = strokeColor();
    ctx.beginPath();
    ctx.moveTo(points[0].x, points[0].y);
    points.forEach((point, index) => {
        if (index === 0) return;
        ctx.lineTo(point.x, point.y);
    });
    if (points.length === 1) {
        ctx.lineTo(points[0].x + 0.1, points[0].y);
    }
    ctx.stroke();
};

const redraw = () => {
    fillBackground();
    strokes.value.forEach((stroke) => drawStroke(stroke));
    isEmpty.value = strokes.value.length === 0 && ! props.modelValue;
};

const exportPng = () => {
    if (! canvas.value) return;
    emit('update:modelValue', canvas.value.toDataURL('image/png'));
    isEmpty.value = false;
};

const startDraw = (event) => {
    if (props.disabled) return;
    event.preventDefault();
    drawing.value = true;
    canvas.value?.setPointerCapture?.(event.pointerId);
    currentStroke = [pointFromEvent(event)];
    drawStroke(currentStroke);
};

const moveDraw = (event) => {
    if (! drawing.value) return;
    event.preventDefault();
    currentStroke.push(pointFromEvent(event));
    drawStroke(currentStroke.slice(-2));
};

const endDraw = (event) => {
    if (! drawing.value) return;
    event.preventDefault();
    drawing.value = false;
    if (currentStroke.length) {
        strokes.value = [...strokes.value, currentStroke];
    }
    currentStroke = [];
    exportPng();
};

const clearPad = () => {
    if (props.disabled) return;
    strokes.value = [];
    currentStroke = [];
    fillBackground();
    isEmpty.value = true;
    emit('update:modelValue', '');
};

const loadExisting = async (value) => {
    if (! value || ! value.startsWith('data:image') || ! canvas.value) {
        redraw();
        isEmpty.value = ! value;
        return;
    }

    await nextTick();
    const image = new Image();
    image.onload = () => {
        fillBackground();
        const width = canvas.value.clientWidth;
        const height = canvas.value.clientHeight;
        ctx.drawImage(image, 0, 0, width, height);
        isEmpty.value = false;
    };
    image.src = value;
};

const onResize = () => {
    const current = props.modelValue;
    sizeCanvas();
    if (current) {
        loadExisting(current);
    }
};

onMounted(() => {
    sizeCanvas();
    loadExisting(props.modelValue);
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
});

watch(() => props.modelValue, (value) => {
    if (drawing.value) return;
    if (! value) {
        strokes.value = [];
        fillBackground();
        isEmpty.value = true;
        return;
    }
    if (strokes.value.length) return;
    loadExisting(value);
});
</script>

<template>
    <div>
        <div class="mb-1.5 flex items-center justify-between gap-3">
            <label class="ui-label mb-0">{{ label }}</label>
            <button
                type="button"
                class="text-xs font-medium text-slate-500 transition hover:text-slate-800 disabled:opacity-40 dark:text-slate-400 dark:hover:text-slate-200"
                :disabled="disabled || isEmpty"
                @click="clearPad"
            >
                Clear
            </button>
        </div>
        <div
            ref="wrap"
            class="overflow-hidden rounded-xl border border-dashed border-slate-300 bg-white dark:border-slate-600 dark:bg-surface-elevated"
            :class="disabled ? 'opacity-60' : ''"
        >
            <canvas
                ref="canvas"
                class="block h-[180px] w-full touch-none"
                :class="disabled ? 'cursor-not-allowed' : 'cursor-crosshair'"
                @pointerdown="startDraw"
                @pointermove="moveDraw"
                @pointerup="endDraw"
                @pointerleave="endDraw"
                @pointercancel="endDraw"
            />
        </div>
        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
    </div>
</template>
