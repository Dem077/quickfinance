<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateRangePicker from '../../Components/UiDateRangePicker.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    report: { type: Object, default: null },
    catalog: { type: Object, required: true },
});

const isEdit = !!props.report;

const emptyColumn = () => ({
    field: '',
    heading: '',
    filter_type: '',
    filter_value: '',
    filter_value_from: '',
    filter_value_to: '',
    filter_values: [],
});

const form = useForm({
    name: props.report?.name ?? '',
    description: props.report?.description ?? '',
    model_type: props.report?.model_type ?? (props.catalog.models[0]?.value ?? ''),
    field_configs: props.report?.field_configs?.length
        ? props.report.field_configs.map((c) => ({
            field: c.field ?? '',
            heading: c.heading ?? '',
            filter_type: c.filter_type ?? '',
            filter_value: c.filter_value ?? '',
            filter_value_from: c.filter_value_from ?? '',
            filter_value_to: c.filter_value_to ?? '',
            filter_values: c.filter_values ?? [],
        }))
        : [],
    from_date: props.report?.from_date ?? '',
    to_date: props.report?.to_date ?? '',
});

const currentModel = computed(() =>
    props.catalog.models.find((m) => m.value === form.model_type) || null,
);

const fields = computed(() => currentModel.value?.fields ?? []);

const fieldMeta = (fieldName) => fields.value.find((f) => f.value === fieldName);

const usedFields = computed(() =>
    form.field_configs.map((col) => col.field).filter(Boolean),
);

const unusedFields = computed(() =>
    fields.value.filter((field) => ! usedFields.value.includes(field.value)),
);

const suggestedFields = computed(() => unusedFields.value.slice(0, 8));

const fieldOptionsForColumn = (col) => {
    const options = unusedFields.value.map((field) => ({
        value: field.value,
        label: field.label,
    }));

    if (col.field && ! options.some((option) => option.value === col.field)) {
        const current = fieldMeta(col.field);
        if (current) {
            options.unshift({ value: current.value, label: current.label });
        }
    }

    return options;
};

const columnHeading = (col) =>
    (col.heading || '').trim() || fieldMeta(col.field)?.label || 'New column';

const columnStatus = (col) => {
    if (! col.field) {
        return { label: 'Incomplete', class: 'ui-badge-neutral' };
    }
    if (col.filter_type) {
        return { label: 'Filtered', class: 'ui-badge-warn' };
    }
    return { label: 'Ready', class: 'ui-badge-success' };
};

const readyColumns = computed(() =>
    form.field_configs.filter((col) => col.field).length,
);

const filteredCount = computed(() =>
    form.field_configs.filter((col) => col.field && col.filter_type).length,
);

const previewHeaders = computed(() =>
    form.field_configs
        .filter((col) => col.field)
        .map((col) => columnHeading(col)),
);

const setModel = (value) => {
    if (value === form.model_type) return;
    if (form.field_configs.length && ! confirm('Switching data source clears the selected columns. Continue?')) {
        return;
    }
    form.model_type = value;
    form.field_configs = [];
};

const addColumn = (field = '') => {
    const col = emptyColumn();
    if (field) {
        col.field = field;
    }
    form.field_configs.push(col);
};

const removeColumn = (index) => {
    form.field_configs.splice(index, 1);
};

const duplicateColumn = (index) => {
    const source = form.field_configs[index];
    const next = unusedFields.value[0];
    form.field_configs.splice(index + 1, 0, {
        ...emptyColumn(),
        ...source,
        field: next?.value || '',
        heading: '',
        filter_type: '',
        filter_value: '',
        filter_value_from: '',
        filter_value_to: '',
        filter_values: [],
    });
};

const moveColumn = (index, direction) => {
    const next = index + direction;
    if (next < 0 || next >= form.field_configs.length) return;
    const rows = form.field_configs;
    const [row] = rows.splice(index, 1);
    rows.splice(next, 0, row);
};

const onFieldChange = (col) => {
    col.heading = '';
    col.filter_type = '';
    col.filter_value = '';
    col.filter_value_from = '';
    col.filter_value_to = '';
    col.filter_values = [];
};

const filterTypeOptions = (col) => {
    const meta = fieldMeta(col.field);
    if (! meta || meta.is_relation) return [];
    return (meta.filter_types || []).map((type) => ({
        value: type.value,
        label: type.label,
    }));
};

const statusOptions = (col) =>
    (fieldMeta(col.field)?.options || []).map((option) => ({
        value: option.value,
        label: option.label,
    }));

const fieldError = (key) => form.errors[key] || '';
const columnError = (index, field) => form.errors[`field_configs.${index}.${field}`] || '';

const cancelHref = route('app.reports.index');

const submit = () => {
    if (isEdit) {
        form.put(route('app.reports.update', props.report.id));
        return;
    }
    form.post(route('app.reports.store'));
};
</script>

<template>
    <AppLayout>
        <template #header>
            {{ isEdit ? `Edit ${report.name}` : 'New report template' }}
        </template>

        <form class="mx-auto max-w-6xl space-y-4 pb-28" @submit.prevent="submit">
            <div class="min-w-0">
                <Link :href="cancelHref" class="ui-link inline-flex items-center gap-1 text-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back
                </Link>
                <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    {{ isEdit ? `Edit ${report.name}` : 'Create report template' }}
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Choose a data source, add the columns you want, then optionally filter and date-range the export.
                </p>
            </div>

            <div class="grid gap-4 xl:grid-cols-12">
                <div class="space-y-4 xl:col-span-8">
                    <section class="ui-panel p-4">
                        <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Template</h2>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label class="ui-label">Name</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    maxlength="255"
                                    class="ui-input"
                                    placeholder="e.g. Open purchase orders by vendor"
                                />
                                <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="ui-label">Description</label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    class="ui-input"
                                    placeholder="What is this export for?"
                                />
                            </div>
                        </div>
                    </section>

                    <section class="ui-panel p-4">
                        <div class="mb-3 flex items-center justify-between gap-2">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Data source</h2>
                            <span v-if="currentModel" class="ui-badge-brand">{{ currentModel.label }}</span>
                        </div>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <button
                                v-for="model in catalog.models"
                                :key="model.value"
                                type="button"
                                class="rounded-xl border px-3 py-3 text-left transition"
                                :class="form.model_type === model.value
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-700 hover:border-slate-300 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-surface-muted/40'"
                                @click="setModel(model.value)"
                            >
                                <p class="text-sm font-semibold">{{ model.label }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ model.fields.length }} fields
                                </p>
                            </button>
                        </div>
                        <p v-if="fieldError('model_type')" class="mt-2 text-xs text-red-600">{{ fieldError('model_type') }}</p>
                    </section>

                    <section class="ui-panel overflow-hidden">
                        <div class="border-b border-slate-100 bg-gradient-to-r from-brand-50/80 to-transparent px-5 py-4 dark:border-slate-800 dark:from-brand-950/30">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">Columns</h2>
                                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                        Add fields in the order they should appear in the CSV.
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="ui-badge-neutral">{{ readyColumns }}/{{ form.field_configs.length || 0 }} ready</span>
                                    <button type="button" class="ui-btn-primary" :disabled="!unusedFields.length && form.field_configs.length" @click="addColumn()">
                                        <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add column
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="!form.field_configs.length" class="px-5 py-12 text-center">
                            <p class="text-sm font-medium text-slate-900 dark:text-white">No columns yet</p>
                            <p class="mt-1 text-sm text-slate-500">Pick a field below, or add a blank column and search for it.</p>
                            <div v-if="suggestedFields.length" class="mt-4 flex flex-wrap justify-center gap-2">
                                <button
                                    v-for="field in suggestedFields"
                                    :key="field.value"
                                    type="button"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-brand-300 hover:text-brand-800 dark:border-slate-700 dark:bg-surface-elevated dark:text-slate-200"
                                    @click="addColumn(field.value)"
                                >
                                    {{ field.label }}
                                </button>
                            </div>
                            <button type="button" class="ui-btn-primary mt-4" @click="addColumn()">
                                Add first column
                            </button>
                        </div>

                        <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                            <article
                                v-for="(col, index) in form.field_configs"
                                :key="index"
                                class="px-5 py-5"
                            >
                                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-semibold text-white">
                                            {{ index + 1 }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                                {{ columnHeading(col) }}
                                            </p>
                                            <p class="truncate text-xs text-slate-500">
                                                {{ fieldMeta(col.field)?.label || 'Select a field' }}
                                                <template v-if="fieldMeta(col.field)?.is_relation"> · related</template>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span :class="columnStatus(col).class">{{ columnStatus(col).label }}</span>
                                        <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 disabled:opacity-40" :disabled="index === 0" @click="moveColumn(index, -1)">Up</button>
                                        <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100 disabled:opacity-40" :disabled="index === form.field_configs.length - 1" @click="moveColumn(index, 1)">Down</button>
                                        <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-slate-100" :disabled="!unusedFields.length" @click="duplicateColumn(index)">Duplicate</button>
                                        <button type="button" class="rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400" @click="removeColumn(index)">Remove</button>
                                    </div>
                                </div>

                                <div class="grid gap-3 lg:grid-cols-12">
                                    <div class="lg:col-span-5">
                                        <UiSearchableSelect
                                            :id="`report-field-${index}`"
                                            v-model="col.field"
                                            label="Field"
                                            :options="fieldOptionsForColumn(col)"
                                            placeholder="Search fields…"
                                            required
                                            :error="columnError(index, 'field')"
                                            @update:model-value="onFieldChange(col)"
                                        />
                                    </div>
                                    <div class="lg:col-span-4">
                                        <label class="ui-label">CSV heading</label>
                                        <input
                                            v-model="col.heading"
                                            type="text"
                                            class="ui-input"
                                            :placeholder="fieldMeta(col.field)?.label || 'Optional custom name'"
                                        />
                                    </div>
                                    <div class="lg:col-span-3">
                                        <UiSearchableSelect
                                            :id="`report-filter-${index}`"
                                            v-model="col.filter_type"
                                            label="Filter"
                                            :options="filterTypeOptions(col)"
                                            empty-label="No filter"
                                            placeholder="No filter"
                                            :disabled="!col.field || fieldMeta(col.field)?.is_relation"
                                            :error="columnError(index, 'filter_type')"
                                        />
                                        <p v-if="fieldMeta(col.field)?.is_relation" class="mt-1 text-[11px] text-slate-400">
                                            Related fields cannot be filtered.
                                        </p>
                                    </div>
                                </div>

                                <div v-if="col.filter_type && !fieldMeta(col.field)?.is_relation" class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <template v-if="col.filter_type === 'between'">
                                        <div>
                                            <label class="ui-label">From</label>
                                            <input v-model="col.filter_value_from" type="text" class="ui-input" />
                                        </div>
                                        <div>
                                            <label class="ui-label">To</label>
                                            <input v-model="col.filter_value_to" type="text" class="ui-input" />
                                        </div>
                                    </template>
                                    <template v-else-if="col.filter_type === 'in' && fieldMeta(col.field)?.input === 'status'">
                                        <div class="sm:col-span-2">
                                            <label class="ui-label">Statuses</label>
                                            <select v-model="col.filter_values" multiple class="ui-select min-h-28">
                                                <option v-for="option in statusOptions(col)" :key="option.value" :value="option.value">
                                                    {{ option.label }}
                                                </option>
                                            </select>
                                        </div>
                                    </template>
                                    <template v-else-if="col.filter_type === 'in'">
                                        <div class="sm:col-span-2">
                                            <label class="ui-label">Values</label>
                                            <input
                                                :value="(col.filter_values || []).join(', ')"
                                                type="text"
                                                class="ui-input"
                                                placeholder="Comma-separated values"
                                                @input="col.filter_values = $event.target.value.split(',').map((s) => s.trim()).filter(Boolean)"
                                            />
                                        </div>
                                    </template>
                                    <template v-else-if="fieldMeta(col.field)?.input === 'status'">
                                        <UiSearchableSelect
                                            :id="`report-status-${index}`"
                                            v-model="col.filter_value"
                                            label="Value"
                                            :options="statusOptions(col)"
                                            placeholder="Select status…"
                                        />
                                    </template>
                                    <template v-else>
                                        <div>
                                            <label class="ui-label">Value</label>
                                            <input v-model="col.filter_value" type="text" class="ui-input" />
                                        </div>
                                    </template>
                                </div>
                            </article>
                        </div>

                        <div v-if="form.field_configs.length" class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                            <button
                                type="button"
                                class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm font-medium text-slate-600 transition hover:border-brand-400 hover:bg-brand-50/50 hover:text-brand-800 dark:border-slate-600 dark:text-slate-300"
                                @click="addColumn()"
                            >
                                Add another column
                            </button>
                            <div v-if="suggestedFields.length" class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="field in suggestedFields"
                                    :key="field.value"
                                    type="button"
                                    class="rounded-full border border-slate-200 px-2.5 py-1 text-[11px] font-medium text-slate-600 hover:border-brand-300 hover:text-brand-800 dark:border-slate-700 dark:text-slate-300"
                                    @click="addColumn(field.value)"
                                >
                                    + {{ field.label }}
                                </button>
                            </div>
                            <p v-if="fieldError('field_configs')" class="mt-3 text-sm text-red-600">{{ fieldError('field_configs') }}</p>
                        </div>
                    </section>

                    <section class="ui-panel p-4">
                        <h2 class="mb-1 text-sm font-semibold text-slate-900 dark:text-white">Date range</h2>
                        <p class="mb-3 text-xs text-slate-500">Optional. Applied to the record created date.</p>
                        <UiDateRangePicker
                            id="report-dates"
                            v-model:from="form.from_date"
                            v-model:to="form.to_date"
                            label="Created between"
                            :error="fieldError('from_date') || fieldError('to_date')"
                        />
                    </section>
                </div>

                <aside class="xl:col-span-4">
                    <div class="sticky top-20 space-y-3">
                        <section class="ui-panel p-4">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Preview</h2>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Source</dt>
                                    <dd class="font-medium text-slate-900 dark:text-white">{{ currentModel?.label || '—' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Columns</dt>
                                    <dd class="font-medium text-slate-900 dark:text-white">{{ readyColumns }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Filters</dt>
                                    <dd class="font-medium text-slate-900 dark:text-white">{{ filteredCount }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-500">Period</dt>
                                    <dd class="text-right font-medium text-slate-900 dark:text-white">
                                        {{ form.from_date && form.to_date ? `${form.from_date} → ${form.to_date}` : 'All dates' }}
                                    </dd>
                                </div>
                            </dl>
                        </section>

                        <section class="ui-panel p-4">
                            <h2 class="text-sm font-semibold text-slate-900 dark:text-white">CSV headers</h2>
                            <ol v-if="previewHeaders.length" class="mt-3 space-y-1.5">
                                <li
                                    v-for="(header, index) in previewHeaders"
                                    :key="`${header}-${index}`"
                                    class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"
                                >
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded bg-slate-100 text-[11px] font-semibold text-slate-500 dark:bg-slate-800">
                                        {{ index + 1 }}
                                    </span>
                                    <span class="truncate">{{ header }}</span>
                                </li>
                            </ol>
                            <p v-else class="mt-3 text-sm text-slate-500">Headers appear here as you add columns.</p>
                        </section>
                    </div>
                </aside>
            </div>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <p class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">
                        {{ readyColumns }} column{{ readyColumns === 1 ? '' : 's' }}
                        <template v-if="currentModel"> · {{ currentModel.label }}</template>
                    </p>
                    <div class="ml-auto flex items-center gap-2">
                        <Link :href="cancelHref" class="ui-btn-ghost">Cancel</Link>
                        <button
                            type="submit"
                            class="ui-btn-primary min-w-36"
                            :disabled="form.processing || !readyColumns"
                        >
                            {{ form.processing ? 'Saving…' : (isEdit ? 'Save template' : 'Create template') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
