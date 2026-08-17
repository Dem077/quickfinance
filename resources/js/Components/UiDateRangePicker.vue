<script setup>
import dateRangeComponent from '../vendor/filament-daterangepicker/filament-daterangepicker.js';
import '../vendor/filament-daterangepicker/filament-daterangepicker.css';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = defineProps({
    id: { type: String, default: undefined },
    label: { type: String, default: 'Date range' },
    from: { type: String, default: '' },
    to: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    disabledDates: { type: Array, default: () => [] },
    minDate: { type: String, default: null },
    placeholder: { type: String, default: 'DD/MM/YYYY - DD/MM/YYYY' },
});

const emit = defineEmits(['update:from', 'update:to']);

const SEPARATOR = ' - ';
const DISPLAY_FORMAT = 'DD/MM/YYYY';

const inputRef = ref(null);
let pickerApi = null;
let stopStateWatch = null;
let syncing = false;

const hasValue = computed(() => Boolean(props.from && props.to));

function formatIsoToDisplay(value) {
    const match = String(value || '').trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (! match) return String(value || '').trim();
    return `${match[3]}/${match[2]}/${match[1]}`;
}

function normalizeToIso(value) {
    const raw = String(value || '').trim();
    if (! raw) return '';

    const ymd = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (ymd) return raw;

    const dmy = raw.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
    if (dmy) {
        return `${dmy[3]}-${dmy[2].padStart(2, '0')}-${dmy[1].padStart(2, '0')}`;
    }

    return '';
}

function rangeStringFromProps(from = props.from, to = props.to) {
    if (! from || ! to) return '';
    return `${formatIsoToDisplay(from)}${SEPARATOR}${formatIsoToDisplay(to)}`;
}

function emitFromRangeString(value) {
    const raw = String(value || '').trim();
    if (! raw) {
        syncing = true;
        emit('update:from', '');
        emit('update:to', '');
        queueMicrotask(() => {
            syncing = false;
        });
        return;
    }

    const parts = raw.split(/\s+(?:-|to|–|—)\s+/i).map((item) => item.trim());
    if (parts.length < 2) return;

    const from = normalizeToIso(parts[0]);
    const to = normalizeToIso(parts[1]);
    if (! from || ! to) return;

    syncing = true;
    emit('update:from', from);
    emit('update:to', to);
    queueMicrotask(() => {
        syncing = false;
    });
}

function syncInputDisplay(value) {
    if (inputRef.value) {
        inputRef.value.value = value ?? '';
    }
}

function buildPickerConfig(initialState) {
    return {
        name: `finance-date-range-${Math.random().toString(36).slice(2, 9)}`,
        state: initialState,
        alwaysShowCalendars: true,
        autoApply: true,
        linkedCalendars: true,
        singleCalendar: false,
        startDate: null,
        endDate: null,
        maxDate: null,
        minDate: props.minDate || null,
        timePicker: false,
        timePicker24: false,
        timePickerSecond: false,
        timePickerIncrement: 30,
        displayFormat: DISPLAY_FORMAT,
        applyLabel: 'Apply',
        cancelLabel: 'Cancel',
        fromLabel: 'From',
        toLabel: 'To',
        customRangeLabel: 'Custom',
        disableCustomRange: false,
        disabledDates: props.disabledDates,
        drops: 'auto',
        opens: 'left',
        sunday: 'Su',
        monday: 'Mo',
        tuesday: 'Tu',
        wednesday: 'We',
        thursday: 'Th',
        friday: 'Fr',
        saturday: 'Sa',
        january: 'January',
        february: 'February',
        march: 'March',
        april: 'April',
        may: 'May',
        june: 'June',
        july: 'July',
        august: 'August',
        september: 'September',
        october: 'October',
        november: 'November',
        december: 'December',
        firstDay: 1,
        ranges: {},
        maxSpan: null,
        disableRange: false,
        separator: SEPARATOR,
        useRangeLabels: false,
        handleValueChangeUsing: (value) => {
            const next = value ?? '';
            if (pickerApi) {
                pickerApi.state = next;
            }
            syncInputDisplay(next);
            emitFromRangeString(next);
        },
        showWeekNumbers: false,
        showISOWeekNumbers: false,
        weekLabel: 'W',
        showDropdowns: false,
        minYear: null,
        maxYear: null,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    };
}

function clearRange() {
    if (props.disabled) return;
    if (pickerApi) {
        pickerApi.state = '';
    }
    syncInputDisplay('');
    emitFromRangeString('');
}

onMounted(() => {
    if (! inputRef.value) return;

    const initial = rangeStringFromProps();
    pickerApi = dateRangeComponent(buildPickerConfig(initial));
    pickerApi.$refs = { daterange: inputRef.value };
    pickerApi.state = initial;
    pickerApi.$watch = (property, callback) => {
        if (property === 'state') {
            stopStateWatch = watch(
                () => rangeStringFromProps(),
                (value) => {
                    if (syncing) return;
                    pickerApi.state = value;
                    callback(value);
                },
            );
        }
    };

    pickerApi.init();
    syncInputDisplay(initial);
});

watch(
    () => [props.from, props.to],
    () => {
        if (syncing) return;
        const next = rangeStringFromProps();
        if (pickerApi) {
            pickerApi.state = next;
        }
        syncInputDisplay(next);
    },
);

watch(
    () => props.disabledDates,
    (value) => {
        if (! pickerApi) return;
        pickerApi.disabledDates = value ?? [];
    },
    { deep: true },
);

onBeforeUnmount(() => {
    stopStateWatch?.();
    pickerApi = null;
});
</script>

<template>
    <div>
        <label v-if="label" :for="id" class="ui-label">{{ label }}</label>
        <div class="relative">
            <input
                :id="id"
                ref="inputRef"
                type="text"
                class="ui-input"
                :class="hasValue ? 'pr-20' : 'pr-11'"
                :placeholder="placeholder"
                :disabled="disabled"
                readonly
            />
            <button
                v-if="hasValue"
                type="button"
                class="absolute right-9 top-1/2 z-10 -translate-y-1/2 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-surface-muted dark:hover:text-slate-200"
                :disabled="disabled"
                title="Clear"
                @click.stop="clearRange"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
            <span
                class="pointer-events-none absolute right-1.5 top-1/2 z-10 -translate-y-1/2 rounded-lg p-2 text-slate-500"
                title="Open calendar"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                </svg>
            </span>
        </div>
        <p v-if="hint && !error" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
        <p v-if="error" class="mt-1.5 text-xs text-red-600 dark:text-red-400">{{ error }}</p>
    </div>
</template>
