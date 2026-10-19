<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: 'Signature' },
    hint: {
        type: String,
        default: 'Draw with your mouse or finger, or upload a photo. Paper background is removed automatically.',
    },
    error: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const wrap = ref(null);
const canvas = ref(null);
const fileInput = ref(null);
const drawing = ref(false);
const isEmpty = ref(true);
const importing = ref(false);
const importError = ref('');
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
    ctx = canvas.value.getContext('2d', { willReadFrequently: true });
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
    if (props.disabled || importing.value) return;
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
    importError.value = '';
    fillBackground();
    isEmpty.value = true;
    emit('update:modelValue', '');
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const drawContainedImage = (image) => {
    if (! ctx || ! canvas.value) return;
    fillBackground();
    const width = canvas.value.clientWidth;
    const height = canvas.value.clientHeight;
    const pad = 16;
    const scale = Math.min((width - pad * 2) / image.width, (height - pad * 2) / image.height, 1);
    const dw = image.width * scale;
    const dh = image.height * scale;
    ctx.drawImage(image, (width - dw) / 2, (height - dh) / 2, dw, dh);
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
        drawContainedImage(image);
        isEmpty.value = false;
    };
    image.src = value;
};

const sampleBackground = (data, width, height) => {
    const points = [
        [2, 2],
        [width - 3, 2],
        [2, height - 3],
        [width - 3, height - 3],
        [Math.floor(width / 2), 2],
        [Math.floor(width / 2), height - 3],
        [2, Math.floor(height / 2)],
        [width - 3, Math.floor(height / 2)],
    ];

    let r = 0;
    let g = 0;
    let b = 0;

    points.forEach(([x, y]) => {
        const index = (y * width + x) * 4;
        r += data[index];
        g += data[index + 1];
        b += data[index + 2];
    });

    return {
        r: r / points.length,
        g: g / points.length,
        b: b / points.length,
    };
};

const extractSignature = (image) => {
    const maxEdge = 1400;
    const scale = Math.min(1, maxEdge / Math.max(image.width, image.height));
    const width = Math.max(1, Math.round(image.width * scale));
    const height = Math.max(1, Math.round(image.height * scale));

    const source = document.createElement('canvas');
    source.width = width;
    source.height = height;
    const sourceCtx = source.getContext('2d', { willReadFrequently: true });
    sourceCtx.drawImage(image, 0, 0, width, height);

    const imageData = sourceCtx.getImageData(0, 0, width, height);
    const pixels = imageData.data;
    const background = sampleBackground(pixels, width, height);

    let minX = width;
    let minY = height;
    let maxX = 0;
    let maxY = 0;
    let inkPixels = 0;

    for (let y = 0; y < height; y += 1) {
        for (let x = 0; x < width; x += 1) {
            const index = (y * width + x) * 4;
            const r = pixels[index];
            const g = pixels[index + 1];
            const b = pixels[index + 2];
            const luminance = 0.299 * r + 0.587 * g + 0.114 * b;
            const distance = Math.sqrt(
                (r - background.r) ** 2
                + (g - background.g) ** 2
                + (b - background.b) ** 2,
            );

            const isPaper = luminance > 228 || distance < 32 || (luminance > 200 && distance < 55);
            if (isPaper) {
                pixels[index + 3] = 0;
                continue;
            }

            const darkness = 1 - Math.min(luminance / 255, 1);
            const alpha = Math.round(Math.min(255, Math.max(40, darkness * 320)));
            const ink = Math.round(Math.max(0, luminance * 0.28));
            pixels[index] = ink;
            pixels[index + 1] = ink;
            pixels[index + 2] = ink;
            pixels[index + 3] = alpha;
            inkPixels += 1;
            minX = Math.min(minX, x);
            minY = Math.min(minY, y);
            maxX = Math.max(maxX, x);
            maxY = Math.max(maxY, y);
        }
    }

    const coverage = inkPixels / (width * height);
    if (inkPixels < 80 || coverage < 0.0015 || maxX <= minX || maxY <= minY) {
        throw new Error('No signature found. Use a clear photo on a plain light background.');
    }

    sourceCtx.putImageData(imageData, 0, 0);

    const pad = 12;
    const cropX = Math.max(0, minX - pad);
    const cropY = Math.max(0, minY - pad);
    const cropW = Math.min(width - cropX, maxX - minX + pad * 2);
    const cropH = Math.min(height - cropY, maxY - minY + pad * 2);

    const output = document.createElement('canvas');
    const maxOut = 900;
    const outScale = Math.min(1, maxOut / Math.max(cropW, cropH));
    output.width = Math.max(1, Math.round(cropW * outScale));
    output.height = Math.max(1, Math.round(cropH * outScale));
    const outputCtx = output.getContext('2d');
    outputCtx.clearRect(0, 0, output.width, output.height);
    outputCtx.drawImage(source, cropX, cropY, cropW, cropH, 0, 0, output.width, output.height);

    return output.toDataURL('image/png');
};

const loadImageFile = (file) => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error('Could not read that file.'));
    reader.onload = () => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('That file is not a valid image.'));
        image.src = reader.result;
    };
    reader.readAsDataURL(file);
});

const onFileChange = async (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (! file || props.disabled) return;

    if (! file.type.startsWith('image/')) {
        importError.value = 'Please choose a PNG, JPG, or WebP image.';
        return;
    }

    if (file.size > 8 * 1024 * 1024) {
        importError.value = 'Image is too large. Use a file under 8 MB.';
        return;
    }

    importing.value = true;
    importError.value = '';

    try {
        const image = await loadImageFile(file);
        const dataUrl = extractSignature(image);
        strokes.value = [];
        currentStroke = [];
        emit('update:modelValue', dataUrl);
        await loadExisting(dataUrl);
        isEmpty.value = false;
    } catch (error) {
        importError.value = error instanceof Error
            ? error.message
            : 'Could not extract a signature from that image.';
    } finally {
        importing.value = false;
    }
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
        <div class="mb-1.5 flex flex-wrap items-center justify-between gap-3">
            <label class="ui-label mb-0">{{ label }}</label>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="text-xs font-medium text-brand-700 transition hover:text-brand-800 disabled:opacity-40 dark:text-brand-300 dark:hover:text-brand-200"
                    :disabled="disabled || importing"
                    @click="fileInput?.click()"
                >
                    {{ importing ? 'Removing background…' : 'Upload image' }}
                </button>
                <button
                    type="button"
                    class="text-xs font-medium text-slate-500 transition hover:text-slate-800 disabled:opacity-40 dark:text-slate-400 dark:hover:text-slate-200"
                    :disabled="disabled || isEmpty"
                    @click="clearPad"
                >
                    Clear
                </button>
            </div>
        </div>
        <div
            ref="wrap"
            class="relative overflow-hidden rounded-xl border border-dashed border-slate-300 bg-white dark:border-slate-600 dark:bg-surface-elevated"
            :class="disabled ? 'opacity-60' : ''"
        >
            <canvas
                ref="canvas"
                class="block h-[180px] w-full touch-none"
                :class="disabled || importing ? 'cursor-not-allowed' : 'cursor-crosshair'"
                @pointerdown="startDraw"
                @pointermove="moveDraw"
                @pointerup="endDraw"
                @pointerleave="endDraw"
                @pointercancel="endDraw"
            />
            <div
                v-if="importing"
                class="absolute inset-0 flex items-center justify-center bg-white/80 text-sm font-medium text-slate-600 dark:bg-surface-elevated/80 dark:text-slate-300"
            >
                Removing background…
            </div>
        </div>
        <input
            ref="fileInput"
            type="file"
            accept="image/png,image/jpeg,image/jpg,image/webp"
            class="hidden"
            @change="onFileChange"
        />
        <p v-if="error || importError" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
            {{ error || importError }}
        </p>
        <p v-else-if="hint" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
    </div>
</template>
