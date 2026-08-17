<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    id: { type: String, default: undefined },
    label: { type: String, default: '' },
    modelValue: { type: [String, Number], default: null },
    options: { type: Array, required: true },
    placeholder: { type: String, default: 'Search…' },
    emptyLabel: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const root = ref(null);
const inputRef = ref(null);
const dropdownRef = ref(null);
const isOpen = ref(false);
const query = ref('');
const highlightIndex = ref(-1);
const dropdownStyle = ref({ top: '0px', left: '0px', width: '0px' });

const selectedOption = computed(() => {
    if (props.modelValue === '' || props.modelValue === null || props.modelValue === undefined) {
        return null;
    }
    return props.options.find((option) => String(option.value) === String(props.modelValue)) ?? null;
});

const closedLabel = computed(() => selectedOption.value?.label ?? '');

const filteredOptions = computed(() => {
    const search = query.value.trim().toLowerCase();
    if (! search) return props.options;
    return props.options.filter((option) => option.label.toLowerCase().includes(search));
});

const updateDropdownPosition = () => {
    if (! inputRef.value) return;
    const rect = inputRef.value.getBoundingClientRect();
    dropdownStyle.value = {
        top: `${rect.bottom + 6}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
    };
};

const open = () => {
    if (props.disabled) return;
    isOpen.value = true;
    query.value = '';
    highlightIndex.value = filteredOptions.value.length ? 0 : -1;
    nextTick(updateDropdownPosition);
};

const close = () => {
    isOpen.value = false;
    query.value = '';
    highlightIndex.value = -1;
};

const select = (value) => {
    emit('update:modelValue', value);
    close();
};

const onInput = (event) => {
    query.value = event.target.value;
    if (! isOpen.value) isOpen.value = true;
    highlightIndex.value = filteredOptions.value.length ? 0 : -1;
    nextTick(updateDropdownPosition);
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        close();
        inputRef.value?.blur();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        if (! isOpen.value) {
            open();
            return;
        }
        if (! filteredOptions.value.length) return;
        highlightIndex.value = (highlightIndex.value + 1) % filteredOptions.value.length;
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        if (! filteredOptions.value.length) return;
        highlightIndex.value = highlightIndex.value <= 0
            ? filteredOptions.value.length - 1
            : highlightIndex.value - 1;
        return;
    }

    if (event.key === 'Enter') {
        event.preventDefault();
        if (! isOpen.value) {
            open();
            return;
        }
        const option = filteredOptions.value[highlightIndex.value];
        if (option) select(option.value);
    }
};

const onClickOutside = (event) => {
    const target = event.target;
    if (root.value?.contains(target) || dropdownRef.value?.contains(target)) return;
    close();
};

watch(isOpen, (openState) => {
    if (openState) {
        nextTick(updateDropdownPosition);
        window.addEventListener('scroll', updateDropdownPosition, true);
        window.addEventListener('resize', updateDropdownPosition);
        return;
    }
    window.removeEventListener('scroll', updateDropdownPosition, true);
    window.removeEventListener('resize', updateDropdownPosition);
});

onMounted(() => document.addEventListener('mousedown', onClickOutside));
onUnmounted(() => {
    document.removeEventListener('mousedown', onClickOutside);
    window.removeEventListener('scroll', updateDropdownPosition, true);
    window.removeEventListener('resize', updateDropdownPosition);
});
</script>

<template>
    <div ref="root" class="relative">
        <label v-if="label" :for="id" class="ui-label">{{ label }}</label>
        <div class="relative">
            <input
                :id="id"
                ref="inputRef"
                :value="isOpen ? query : closedLabel"
                type="text"
                autocomplete="off"
                role="combobox"
                aria-autocomplete="list"
                :aria-expanded="isOpen"
                :placeholder="placeholder"
                :disabled="disabled"
                :required="required && !modelValue"
                class="ui-input pr-10"
                @focus="open"
                @click="open"
                @input="onInput"
                @keydown="onKeydown"
            />
            <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 100-14 7 7 0 000 14z" />
                </svg>
            </span>
        </div>

        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="dropdownRef"
                :style="dropdownStyle"
                class="fixed z-[400] max-h-64 overflow-auto rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg shadow-slate-200/70 dark:border-slate-700 dark:bg-surface-elevated dark:shadow-black/40"
                role="listbox"
            >
                <button
                    v-if="emptyLabel"
                    type="button"
                    class="block w-full px-3.5 py-2 text-left text-sm text-slate-500 transition hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-surface-muted"
                    @mousedown.prevent
                    @click="select('')"
                >
                    {{ emptyLabel }}
                </button>

                <button
                    v-for="(option, index) in filteredOptions"
                    :key="option.value"
                    type="button"
                    class="block w-full px-3.5 py-2 text-left text-sm transition"
                    :class="[
                        String(option.value) === String(modelValue)
                            ? 'bg-brand-50 text-brand-800 dark:bg-brand-950/40 dark:text-brand-200'
                            : 'text-slate-700 dark:text-slate-200',
                        index === highlightIndex
                            ? 'bg-slate-50 dark:bg-surface-muted'
                            : 'hover:bg-slate-50 dark:hover:bg-surface-muted',
                    ]"
                    @mousedown.prevent
                    @click="select(option.value)"
                    @mouseenter="highlightIndex = index"
                >
                    {{ option.label }}
                </button>

                <p v-if="filteredOptions.length === 0" class="px-3.5 py-3 text-sm text-slate-500 dark:text-slate-400">
                    No matches found.
                </p>
            </div>
        </Teleport>

        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
