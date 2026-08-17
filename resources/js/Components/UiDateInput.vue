<script setup>
import { ref, watch } from 'vue';
import { displayToIso, formatDate } from '../lib/dateFormat';

const props = defineProps({
    id: { type: String, default: undefined },
    label: { type: String, required: true },
    modelValue: { type: [String, Number], default: null },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    placeholder: { type: String, default: 'DD/MM/YYYY' },
});

const emit = defineEmits(['update:modelValue', 'input']);

const display = ref('');
const pickerRef = ref(null);

watch(
    () => props.modelValue,
    (value) => {
        display.value = value ? formatDate(String(value)) : '';
    },
    { immediate: true },
);

const commitDisplay = () => {
    if (! display.value.trim()) {
        emit('update:modelValue', '');
        emit('input');
        return;
    }

    const iso = displayToIso(display.value);
    if (iso) {
        display.value = formatDate(iso);
        emit('update:modelValue', iso);
        emit('input');
        return;
    }

    if (props.modelValue) {
        display.value = formatDate(String(props.modelValue));
    }
};

const onPickerChange = (event) => {
    emit('update:modelValue', event.target.value);
    emit('input');
};

const openPicker = () => {
    if (props.disabled) return;
    pickerRef.value?.showPicker?.();
};
</script>

<template>
    <div>
        <label :for="id" class="ui-label">{{ label }}</label>
        <div class="relative flex items-center">
            <input
                :id="id"
                v-model="display"
                type="text"
                inputmode="numeric"
                :placeholder="placeholder"
                :readonly="disabled"
                :required="required"
                class="ui-input pr-11"
                @blur="commitDisplay"
                @keydown.enter.prevent="commitDisplay"
            />
            <button
                type="button"
                class="absolute right-1.5 rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-surface-muted dark:hover:text-slate-200"
                :disabled="disabled"
                title="Open calendar"
                @click="openPicker"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </button>
            <input
                ref="pickerRef"
                type="date"
                tabindex="-1"
                aria-hidden="true"
                class="pointer-events-none absolute h-0 w-0 opacity-0"
                :value="modelValue ?? ''"
                @change="onPickerChange"
            />
        </div>
        <p v-if="hint && !error" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
