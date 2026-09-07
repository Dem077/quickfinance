<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiChart from '../../Components/UiChart.vue';
import UiDateRangePicker from '../../Components/UiDateRangePicker.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    chart: { type: Object, default: null },
    catalog: { type: Object, required: true },
    chartTypes: { type: Array, required: true },
    aggregations: { type: Array, required: true },
    groupPeriods: { type: Array, required: true },
});

const isEdit = !!props.chart;
const step = ref(1);
const nameTouched = ref(!!props.chart?.name);
const showAllGroupFields = ref(false);
const liveChart = ref(null);
const previewLoading = ref(false);
let previewTimer = null;

const emptyFilter = () => ({
    field: '',
    heading: '',
    filter_type: '',
    filter_value: '',
    filter_value_from: '',
    filter_value_to: '',
    filter_values: [],
});

const form = useForm({
    name: props.chart?.name ?? '',
    description: props.chart?.description ?? '',
    model_type: props.chart?.model_type ?? '',
    chart_type: props.chart?.chart_type ?? 'bar',
    group_by: props.chart?.group_by ?? '',
    group_period: props.chart?.group_period ?? 'value',
    aggregation: props.chart?.aggregation ?? 'count',
    metric_field: props.chart?.metric_field ?? '',
    filters: props.chart?.filters?.length
        ? props.chart.filters.map((filter) => ({ ...emptyFilter(), ...filter }))
        : [],
    from_date: props.chart?.from_date ?? '',
    to_date: props.chart?.to_date ?? '',
    show_on_dashboard: props.chart?.show_on_dashboard ?? true,
    sort_order: props.chart?.sort_order ?? 0,
});

const steps = [
    { n: 1, label: 'Records' },
    { n: 2, label: 'Split by' },
    { n: 3, label: 'Look' },
    { n: 4, label: 'Save' },
];

const currentModel = computed(() =>
    props.catalog.models.find((model) => model.value === form.model_type) || null,
);

const fields = computed(() => currentModel.value?.fields ?? []);

const fieldMeta = (name) => fields.value.find((field) => field.value === name);

const needsMetric = computed(() => form.aggregation === 'sum' || form.aggregation === 'avg');
const groupField = computed(() => fieldMeta(form.group_by));
const isDateGroup = computed(() => groupField.value?.kind === 'date');

const suggestedGroupFields = computed(() => {
    const ranked = [...fields.value]
        .filter((field) => field.kind !== 'id' && field.kind !== 'number')
        .sort((a, b) => kindRank(a.kind) - kindRank(b.kind));

    if (showAllGroupFields.value) {
        return ranked;
    }

    return ranked.slice(0, 8);
});

const metricFields = computed(() =>
    fields.value.filter((field) => field.kind === 'number' || ! ['id', 'date', 'category'].includes(field.kind)),
);

const metricOptions = computed(() =>
    (metricFields.value.length ? metricFields.value : fields.value).map((field) => ({
        value: field.value,
        label: field.label,
    })),
);

const fieldOptions = computed(() =>
    fields.value.map((field) => ({ value: field.value, label: field.label })),
);

const categoryFields = computed(() =>
    fields.value.filter((field) => field.kind === 'category' && (field.options || []).length),
);

const starters = computed(() => {
    const ideas = [
        { label: 'Purchase requests by status', model: 'PurchaseRequests', group_by: 'status', group_period: 'value', chart_type: 'bar', aggregation: 'count' },
        { label: 'Purchase requests by month', model: 'PurchaseRequests', group_by: 'created_at', group_period: 'month', chart_type: 'line', aggregation: 'count' },
        { label: 'Purchase orders by status', model: 'PurchaseOrders', group_by: 'status', group_period: 'value', chart_type: 'doughnut', aggregation: 'count' },
        { label: 'Petty cash by status', model: 'PettyCashReimbursment', group_by: 'status', group_period: 'value', chart_type: 'bar', aggregation: 'count' },
        { label: 'Advance forms by status', model: 'AdvanceForm', group_by: 'status', group_period: 'value', chart_type: 'pie', aggregation: 'count' },
    ];

    return ideas.filter((idea) => {
        const model = props.catalog.models.find((item) => item.value === idea.model);
        return model?.fields.some((field) => field.value === idea.group_by);
    });
});

const chartTypeCards = [
    { value: 'bar', label: 'Bars', hint: 'Compare groups side by side' },
    { value: 'line', label: 'Line', hint: 'Best for change over time' },
    { value: 'pie', label: 'Pie', hint: 'Share of a whole' },
    { value: 'doughnut', label: 'Ring', hint: 'Share of a whole, compact' },
];

const aggregationCards = [
    { value: 'count', label: 'How many', hint: 'Count the records in each group' },
    { value: 'sum', label: 'Add up', hint: 'Total a money or quantity field' },
    { value: 'avg', label: 'Average', hint: 'Average a money or quantity field' },
];

const preview = computed(() => {
    if (form.chart_type === 'line') {
        return { labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'], values: [12, 18, 14, 22, 19, 28] };
    }

    return { labels: ['Group A', 'Group B', 'Group C', 'Group D'], values: [42, 28, 18, 12] };
});

const story = computed(() => {
    const source = currentModel.value?.label || 'these records';
    const group = groupField.value?.label;
    const period = form.group_period === 'month'
        ? 'month'
        : form.group_period === 'year' ? 'year' : null;
    const metric = fieldMeta(form.metric_field)?.label;

    let measure = 'how many there are';
    if (form.aggregation === 'sum' && metric) measure = `the total ${metric.toLowerCase()}`;
    if (form.aggregation === 'avg' && metric) measure = `the average ${metric.toLowerCase()}`;

    if (! form.model_type) return 'Start by choosing which records to chart.';
    if (! form.group_by) return `Show ${measure} for ${source}. Next, choose how to split them.`;

    const split = period ? `${group} (${period})` : group;
    const activeFilters = form.filters.filter((filter) => filter.field && filter.filter_type && (
        (filter.filter_values || []).length || filter.filter_value || (filter.filter_value_from && filter.filter_value_to)
    )).length;
    const dateBit = form.from_date || form.to_date ? ' in a date range' : '';
    const filterBit = activeFilters ? `, with ${activeFilters} filter${activeFilters === 1 ? '' : 's'}` : '';
    return `Show ${measure} for ${source}, split by ${split}${dateBit}${filterBit}.`;
});

const suggestedName = computed(() => {
    if (! currentModel.value || ! groupField.value) return '';
    const source = currentModel.value.label;
    if (form.aggregation === 'count') {
        return `${source} by ${groupField.value.label}`;
    }
    const metric = fieldMeta(form.metric_field)?.label || 'value';
    const word = form.aggregation === 'avg' ? 'Average' : 'Total';
    return `${word} ${metric.toLowerCase()} by ${groupField.value.label}`;
});

const canNext = computed(() => {
    if (step.value === 1) return Boolean(form.model_type);
    if (step.value === 2) return Boolean(form.group_by);
    if (step.value === 3) return Boolean(form.chart_type) && (! needsMetric.value || form.metric_field);
    return Boolean(form.name.trim());
});

watch(
    () => [form.model_type, form.group_by, form.aggregation, form.metric_field],
    () => {
        if (! nameTouched.value) {
            form.name = suggestedName.value;
        }
    },
);

watch(
    () => form.group_by,
    (value) => {
        const meta = fieldMeta(value);
        if (meta?.kind === 'date' && form.group_period === 'value') {
            form.group_period = 'month';
            if (form.chart_type === 'bar' || form.chart_type === 'pie' || form.chart_type === 'doughnut') {
                form.chart_type = 'line';
            }
        }
        if (meta && meta.kind !== 'date' && form.group_period !== 'value') {
            form.group_period = 'value';
        }
    },
);

watch(
    () => form.aggregation,
    (value) => {
        if (value === 'count') {
            form.metric_field = '';
        } else if (! form.metric_field) {
            form.metric_field = metricFields.value[0]?.value ?? '';
        }
    },
);

const kindRank = (kind) => ({
    category: 0,
    date: 1,
    related: 2,
    text: 3,
    number: 4,
    id: 5,
}[kind] ?? 6);

const kindLabel = (kind) => ({
    category: 'Status',
    date: 'Date',
    related: 'Related',
    text: 'Field',
    number: 'Number',
}[kind] || 'Field');

const applyStarter = (idea) => {
    form.model_type = idea.model;
    form.group_by = idea.group_by;
    form.group_period = idea.group_period;
    form.chart_type = idea.chart_type;
    form.aggregation = idea.aggregation;
    form.metric_field = '';
    form.filters = [];
    nameTouched.value = false;
    form.name = '';
    step.value = 3;
};

const setModel = (value) => {
    if (value === form.model_type) return;
    form.model_type = value;
    form.group_by = '';
    form.metric_field = '';
    form.filters = [];
    form.group_period = 'value';
    showAllGroupFields.value = false;
};

const chooseGroup = (value) => {
    form.group_by = value;
};

const serializedFilters = () => form.filters
    .filter((filter) => filter.field && filter.filter_type)
    .map((filter) => ({
        field: filter.field,
        heading: filter.heading || '',
        filter_type: filter.filter_type,
        filter_value: filter.filter_value ?? '',
        filter_value_from: filter.filter_value_from ?? '',
        filter_value_to: filter.filter_value_to ?? '',
        filter_values: Array.isArray(filter.filter_values) ? [...filter.filter_values] : [],
    }));

const selectedCategoryValues = (field) => {
    const row = form.filters.find((filter) => filter.field === field && filter.filter_type === 'in');
    return (row?.filter_values || []).map(String);
};

const isCategorySelected = (field, value) => selectedCategoryValues(field).includes(String(value));

const toggleCategoryValue = (field, value) => {
    const nextValue = String(value);
    const others = form.filters.filter((filter) => filter.field !== field);
    const current = selectedCategoryValues(field);
    const selected = current.includes(nextValue)
        ? current.filter((item) => item !== nextValue)
        : [...current, nextValue];

    form.filters = selected.length
        ? [...others, { ...emptyFilter(), field, filter_type: 'in', filter_values: selected }]
        : others;
};

const addFilter = () => {
    form.filters = [...form.filters, emptyFilter()];
};

const removeFilter = (index) => {
    form.filters = form.filters.filter((_, i) => i !== index);
};

const onFilterFieldChange = (filter) => {
    const meta = fieldMeta(filter.field);
    filter.filter_value = '';
    filter.filter_value_from = '';
    filter.filter_value_to = '';
    filter.filter_values = [];
    if (! meta) {
        filter.filter_type = '';
        return;
    }
    if (meta.input === 'status') {
        filter.filter_type = 'in';
        return;
    }
    filter.filter_type = 'equals';
};

const filterTypeOptions = (filter) => {
    const meta = fieldMeta(filter.field);
    if (! meta) return [];
    return meta.filter_types || [];
};

const statusOptions = (filter) =>
    (fieldMeta(filter.field)?.options || []).map((option) => ({
        value: option.value,
        label: option.label,
    }));

const fieldError = (key) => form.errors[key] || '';

const loadPreview = async () => {
    if (! form.model_type || ! form.group_by) {
        liveChart.value = null;
        return;
    }

    previewLoading.value = true;
    try {
        const { data } = await axios.post(route('app.charts.preview'), {
            name: form.name || 'Preview',
            description: form.description,
            model_type: form.model_type,
            chart_type: form.chart_type,
            group_by: form.group_by,
            group_period: form.group_period,
            aggregation: form.aggregation,
            metric_field: form.metric_field || null,
            filters: serializedFilters(),
            from_date: form.from_date || null,
            to_date: form.to_date || null,
            show_on_dashboard: form.show_on_dashboard,
            sort_order: form.sort_order,
        });
        liveChart.value = data;
    } catch {
        liveChart.value = null;
    } finally {
        previewLoading.value = false;
    }
};

watch(
    () => [
        form.model_type,
        form.group_by,
        form.group_period,
        form.aggregation,
        form.metric_field,
        form.chart_type,
        form.from_date,
        form.to_date,
        JSON.stringify(form.filters),
    ],
    () => {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(loadPreview, 350);
    },
    { immediate: true },
);

onBeforeUnmount(() => clearTimeout(previewTimer));

watch(
    () => form.errors,
    (errors) => {
        if (errors.model_type) {
            step.value = 1;
        } else if (errors.group_by || errors.group_period) {
            step.value = 2;
        } else if (errors.chart_type || errors.aggregation || errors.metric_field) {
            step.value = 3;
        } else if (Object.keys(errors).length) {
            step.value = 4;
        }
    },
    { deep: true },
);

const goNext = () => {
    if (! canNext.value || step.value >= 4) return;
    step.value += 1;
};

const goBack = () => {
    if (step.value <= 1) return;
    step.value -= 1;
};

const submit = () => {
    if (! form.name.trim() && suggestedName.value) {
        form.name = suggestedName.value;
    }

    form.filters = serializedFilters();

    if (isEdit) {
        form.put(route('app.charts.update', props.chart.id));
        return;
    }

    form.post(route('app.charts.store'));
};
</script>

<template>
    <AppLayout>
        <template #header>{{ isEdit ? `Edit ${chart.name}` : 'New dashboard chart' }}</template>

        <form class="mx-auto max-w-3xl space-y-5 pb-28" @submit.prevent="submit">
            <div>
                <Link :href="route('app.charts.index')" class="ui-link inline-flex items-center gap-1 text-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back
                </Link>
                <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    {{ isEdit ? `Edit ${chart.name}` : 'Create a dashboard chart' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Answer a few questions. We’ll turn them into a dashboard chart.
                </p>
            </div>

            <ol class="grid grid-cols-4 gap-2">
                <li v-for="item in steps" :key="item.n">
                    <button
                        type="button"
                        class="w-full rounded-xl border px-2 py-2 text-center text-xs font-medium transition sm:text-sm"
                        :class="step === item.n
                            ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                            : item.n < step
                                ? 'border-slate-200 text-slate-700 dark:border-slate-700 dark:text-slate-200'
                                : 'border-slate-200 text-slate-400 dark:border-slate-800'"
                        @click="step = item.n"
                    >
                        {{ item.n }}. {{ item.label }}
                    </button>
                </li>
            </ol>

            <p class="rounded-xl border border-slate-200 bg-surface px-4 py-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
                {{ story }}
            </p>

            <section v-show="step === 1" class="space-y-4">
                <section v-if="starters.length && !isEdit" class="ui-panel p-4">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Start from an idea</h2>
                    <p class="mt-0.5 text-xs text-slate-500">One click fills in a common chart. You can still change it.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-for="idea in starters"
                            :key="idea.label"
                            type="button"
                            class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-brand-300 hover:text-brand-800 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-200"
                            @click="applyStarter(idea)"
                        >
                            {{ idea.label }}
                        </button>
                    </div>
                </section>

                <section class="ui-panel p-4">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Which records?</h2>
                    <p class="mt-0.5 mb-3 text-xs text-slate-500">Same sources as report templates.</p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="model in catalog.models"
                            :key="model.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left transition"
                            :class="form.model_type === model.value
                                ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200'"
                            @click="setModel(model.value)"
                        >
                            <p class="text-sm font-semibold">{{ model.label }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ model.fields.length }} fields available</p>
                        </button>
                    </div>
                    <p v-if="fieldError('model_type')" class="mt-2 text-xs text-red-600">{{ fieldError('model_type') }}</p>
                </section>
            </section>

            <section v-show="step === 2" class="ui-panel p-4">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">How should we split them?</h2>
                <p class="mt-0.5 mb-3 text-xs text-slate-500">
                    Each group becomes a bar, slice, or point on the chart.
                </p>
                <p v-if="!currentModel" class="text-sm text-slate-500">Choose records first.</p>
                <template v-else>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="field in suggestedGroupFields"
                            :key="field.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left transition"
                            :class="form.group_by === field.value
                                ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200'"
                            @click="chooseGroup(field.value)"
                        >
                            <p class="text-sm font-semibold">{{ field.label }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ kindLabel(field.kind) }}</p>
                        </button>
                    </div>
                    <button
                        v-if="fields.length > 8"
                        type="button"
                        class="ui-link mt-3 text-sm"
                        @click="showAllGroupFields = !showAllGroupFields"
                    >
                        {{ showAllGroupFields ? 'Show fewer fields' : 'Show all fields' }}
                    </button>
                    <p v-if="fieldError('group_by')" class="mt-2 text-xs text-red-600">{{ fieldError('group_by') }}</p>

                    <div v-if="isDateGroup" class="mt-4">
                        <p class="ui-label">For dates, group by</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <button
                                v-for="period in groupPeriods"
                                :key="period.value"
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm"
                                :class="form.group_period === period.value
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40'
                                    : 'border-slate-200 dark:border-slate-700'"
                                @click="form.group_period = period.value"
                            >
                                {{ period.label }}
                            </button>
                        </div>
                    </div>
                </template>
            </section>

            <section v-show="step === 3" class="space-y-4">
                <section class="ui-panel p-4">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">What number should each group show?</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-3">
                        <button
                            v-for="item in aggregationCards"
                            :key="item.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left"
                            :class="form.aggregation === item.value
                                ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                : 'border-slate-200 dark:border-slate-700'"
                            @click="form.aggregation = item.value"
                        >
                            <p class="text-sm font-semibold">{{ item.label }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ item.hint }}</p>
                        </button>
                    </div>
                    <div v-if="needsMetric" class="mt-4">
                        <UiSearchableSelect
                            id="chart-metric"
                            v-model="form.metric_field"
                            label="Which field to add up?"
                            :options="metricOptions"
                            placeholder="Search amounts…"
                            required
                            :error="fieldError('metric_field')"
                        />
                    </div>
                </section>

                <section class="ui-panel p-4">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">How should it look?</h2>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="type in chartTypeCards"
                            :key="type.value"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left"
                            :class="form.chart_type === type.value
                                ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                : 'border-slate-200 dark:border-slate-700'"
                            @click="form.chart_type = type.value"
                        >
                            <p class="text-sm font-semibold">{{ type.label }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ type.hint }}</p>
                        </button>
                    </div>
                    <div class="mt-4 rounded-xl border border-slate-100 bg-slate-50/80 p-3 dark:border-slate-800 dark:bg-slate-900/40">
                        <p class="mb-2 text-xs font-medium text-slate-500">Preview of the shape (sample numbers)</p>
                        <UiChart :type="form.chart_type" :labels="preview.labels" :values="preview.values" />
                    </div>
                </section>
            </section>

            <section v-show="step === 4" class="space-y-4">
                <section class="ui-panel p-4">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Live preview</h2>
                        <span v-if="previewLoading" class="text-xs text-slate-500">Updating…</span>
                    </div>
                    <UiChart
                        v-if="liveChart?.labels?.length"
                        :type="form.chart_type"
                        :labels="liveChart.labels"
                        :values="liveChart.values"
                    />
                    <p v-else class="py-8 text-center text-sm text-slate-500">
                        {{ previewLoading ? 'Loading chart data…' : 'No records match these filters yet.' }}
                    </p>
                </section>

                <section class="ui-panel p-4">
                    <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Name it</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="ui-label">Title on the dashboard</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                maxlength="255"
                                class="ui-input"
                                placeholder="e.g. Purchase requests by status"
                                @input="nameTouched = true"
                            />
                            <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
                        </div>
                        <div>
                            <label class="ui-label">Note (optional)</label>
                            <textarea v-model="form.description" rows="2" class="ui-input" placeholder="Shown only in the chart list" />
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input v-model="form.show_on_dashboard" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                            Show this chart on the dashboard
                        </label>
                    </div>
                </section>

                <section class="ui-panel p-4">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Filter the records</h2>
                    <p class="mt-0.5 mb-4 text-xs text-slate-500">
                        Tap statuses to include them. Leave them all off to include every status. The preview updates as you change this.
                    </p>

                    <div class="space-y-4">
                        <div v-for="field in categoryFields" :key="field.value">
                            <p class="ui-label">{{ field.label }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="option in field.options"
                                    :key="option.value"
                                    type="button"
                                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition"
                                    :class="isCategorySelected(field.value, option.value)
                                        ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                        : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300'"
                                    @click="toggleCategoryValue(field.value, option.value)"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                            <p class="mt-1.5 text-[11px] text-slate-500">
                                {{ selectedCategoryValues(field.value).length
                                    ? `${selectedCategoryValues(field.value).length} selected`
                                    : 'All values included' }}
                            </p>
                        </div>

                        <UiDateRangePicker
                            id="chart-dates"
                            v-model:from="form.from_date"
                            v-model:to="form.to_date"
                            label="Only records created between"
                            :error="fieldError('from_date') || fieldError('to_date')"
                        />
                    </div>

                    <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-medium text-slate-800 dark:text-slate-100">Other conditions</p>
                            <button type="button" class="ui-btn-secondary" @click="addFilter">Add condition</button>
                        </div>

                        <p v-if="!form.filters.some((filter) => fieldMeta(filter.field)?.kind !== 'category')" class="mt-3 text-sm text-slate-500">
                            No extra field conditions.
                        </p>

                        <article
                            v-for="(filter, index) in form.filters"
                            v-show="!filter.field || fieldMeta(filter.field)?.kind !== 'category'"
                            :key="index"
                            class="mt-3 space-y-3 rounded-xl border border-slate-200 p-3 dark:border-slate-700"
                        >
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium">Only include where</p>
                                <button type="button" class="text-xs font-medium text-rose-600" @click="removeFilter(index)">Remove</button>
                            </div>
                            <div class="grid gap-3 lg:grid-cols-2">
                                <UiSearchableSelect
                                    :id="`chart-filter-field-${index}`"
                                    v-model="filter.field"
                                    label="Field"
                                    :options="fieldOptions"
                                    placeholder="Search fields…"
                                    @update:model-value="onFilterFieldChange(filter)"
                                />
                                <UiSearchableSelect
                                    :id="`chart-filter-type-${index}`"
                                    v-model="filter.filter_type"
                                    label="Condition"
                                    :options="filterTypeOptions(filter)"
                                    empty-label="No condition"
                                    placeholder="Equals, contains…"
                                    :disabled="!filter.field"
                                />
                            </div>
                            <div v-if="filter.filter_type" class="grid gap-3 sm:grid-cols-2">
                                <template v-if="filter.filter_type === 'between'">
                                    <div>
                                        <label class="ui-label">From</label>
                                        <input v-model="filter.filter_value_from" type="text" class="ui-input" />
                                    </div>
                                    <div>
                                        <label class="ui-label">To</label>
                                        <input v-model="filter.filter_value_to" type="text" class="ui-input" />
                                    </div>
                                </template>
                                <template v-else-if="filter.filter_type === 'in' && fieldMeta(filter.field)?.input === 'status'">
                                    <div class="sm:col-span-2">
                                        <label class="ui-label">Values</label>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <button
                                                v-for="option in statusOptions(filter)"
                                                :key="option.value"
                                                type="button"
                                                class="rounded-full border px-3 py-1.5 text-xs font-medium"
                                                :class="(filter.filter_values || []).map(String).includes(String(option.value))
                                                    ? 'border-brand-300 bg-brand-50 text-brand-800'
                                                    : 'border-slate-200 text-slate-600'"
                                                @click="filter.filter_values = (filter.filter_values || []).map(String).includes(String(option.value))
                                                    ? (filter.filter_values || []).filter((item) => String(item) !== String(option.value))
                                                    : [...(filter.filter_values || []), option.value]"
                                            >
                                                {{ option.label }}
                                            </button>
                                        </div>
                                    </div>
                                </template>
                                <template v-else-if="filter.filter_type === 'in'">
                                    <div class="sm:col-span-2">
                                        <label class="ui-label">Values</label>
                                        <input
                                            :value="(filter.filter_values || []).join(', ')"
                                            type="text"
                                            class="ui-input"
                                            placeholder="Comma-separated values"
                                            @input="filter.filter_values = $event.target.value.split(',').map((s) => s.trim()).filter(Boolean)"
                                        />
                                    </div>
                                </template>
                                <template v-else-if="fieldMeta(filter.field)?.input === 'status'">
                                    <UiSearchableSelect
                                        :id="`chart-filter-status-${index}`"
                                        v-model="filter.filter_value"
                                        label="Value"
                                        :options="statusOptions(filter)"
                                        placeholder="Select status…"
                                    />
                                </template>
                                <template v-else>
                                    <div>
                                        <label class="ui-label">Value</label>
                                        <input v-model="filter.filter_value" type="text" class="ui-input" />
                                    </div>
                                </template>
                            </div>
                        </article>
                    </div>
                </section>
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95">
                <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <button v-if="step > 1" type="button" class="ui-btn-ghost" @click="goBack">Back</button>
                    <Link v-else :href="route('app.charts.index')" class="ui-btn-ghost">Cancel</Link>
                    <div class="ml-auto flex items-center gap-2">
                        <button
                            v-if="step < 4"
                            type="button"
                            class="ui-btn-primary min-w-36"
                            :disabled="!canNext"
                            @click="goNext"
                        >
                            Continue
                        </button>
                        <button
                            v-else
                            type="submit"
                            class="ui-btn-primary min-w-36"
                            :disabled="form.processing || !canNext"
                        >
                            {{ form.processing ? 'Saving…' : (isEdit ? 'Save chart' : 'Create chart') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
