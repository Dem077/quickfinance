<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateInput from '../../Components/UiDateInput.vue';
import UiSearchableMultiSelect from '../../Components/UiSearchableMultiSelect.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, default: null },
    options: { type: Object, required: true },
});

const isEdit = !!props.record;
const fileInputRef = ref(null);

const emptyLine = () => ({
    item_id: '',
    unit: 'Pcs',
    budget_account_id: '',
    amount: '',
    est_cost: '',
});

const form = useForm({
    date: props.record?.date ?? new Date().toISOString().slice(0, 10),
    purpose: props.record?.purpose ?? '',
    project_id: props.record?.project_id ?? '',
    locations: props.record?.locations ? [...props.record.locations].map(Number) : [],
    supporting_document: null,
    details: props.record?.details?.length
        ? props.record.details.map((line) => ({
            id: line.id ?? null,
            item_id: line.item_id ?? '',
            unit: line.unit ?? 'Pcs',
            budget_account_id: line.budget_account_id ?? '',
            amount: line.amount ?? '',
            est_cost: line.est_cost ?? '',
        }))
        : [emptyLine()],
});


const locationOptions = computed(() =>
    props.options.locations.map((location) => ({
        value: location.id,
        label: location.name,
    })),
);

const itemOptions = computed(() =>
    props.options.items.map((item) => ({
        value: item.id,
        label: `${item.item_code} — ${item.name}`,
    })),
);

const budgetOptions = computed(() =>
    props.options.budgets.map((budget) => ({
        value: budget.id,
        label: `${budget.label} (MVR ${Number(budget.available).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })} available)`,
    })),
);

const addLine = () => {
    form.details.push(emptyLine());
};

const removeLine = (index) => {
    if (form.details.length <= 1) return;
    form.details.splice(index, 1);
};

const duplicateLine = (index) => {
    const source = form.details[index];
    form.details.splice(index + 1, 0, { ...source });
};

const budgetById = (budgetId) =>
    props.options.budgets.find((item) => item.id === Number(budgetId));

const allocatedFor = (budgetId) => {
    const budget = budgetById(budgetId);
    return budget ? budget.allocated : null;
};

const availableFor = (budgetId) => {
    const budget = budgetById(budgetId);
    return budget ? Number(budget.available) : null;
};

const budgetLabel = (budgetId) => budgetById(budgetId)?.label || '';

const itemById = (itemId) => props.options.items.find((item) => item.id === Number(itemId));

const estimatedTotal = computed(() =>
    form.details.reduce((sum, line) => sum + (Number(line.est_cost) || 0), 0),
);

const totalQty = computed(() =>
    form.details.reduce((sum, line) => sum + (Number(line.amount) || 0), 0),
);

const completedLines = computed(() =>
    form.details.filter((line) => line.item_id && line.budget_account_id && line.amount && line.est_cost).length,
);

const reservedOnBudget = (budgetId, exceptIndex = null) =>
    form.details.reduce((sum, line, index) => {
        if (exceptIndex !== null && index === exceptIndex) return sum;
        if (Number(line.budget_account_id) !== Number(budgetId)) return sum;
        return sum + (Number(line.est_cost) || 0);
    }, 0);

const remainingFor = (line, index = null) => {
    const available = availableFor(line.budget_account_id);
    if (available === null) return null;
    const lineIndex = index ?? form.details.indexOf(line);
    const otherLines = reservedOnBudget(line.budget_account_id, lineIndex);
    return available - otherLines - (Number(line.est_cost) || 0);
};

const overBudgetLines = computed(() =>
    form.details.filter((line, index) => {
        const remaining = remainingFor(line, index);
        return remaining !== null && remaining < 0;
    }).length,
);

const fieldError = (key) => form.errors[key] || '';

const lineError = (index, field) =>
    form.errors[`details.${index}.${field}`] || '';

const formatMoney = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    return Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
};

const unitCost = (line) => {
    const qty = Number(line.amount);
    const cost = Number(line.est_cost);
    if (! qty || ! cost) return null;
    return cost / qty;
};

const usagePercent = (line, index = null) => {
    const available = availableFor(line.budget_account_id);
    if (! available) return 0;
    const lineIndex = index ?? form.details.indexOf(line);
    const used = reservedOnBudget(line.budget_account_id, lineIndex) + (Number(line.est_cost) || 0);
    return Math.min(100, Math.round((used / available) * 100));
};

const lineStatus = (line, index = null) => {
    if (! line.item_id || ! line.budget_account_id || ! line.amount || ! line.est_cost) {
        return { key: 'draft', label: 'Incomplete', class: 'ui-badge-neutral' };
    }
    const remaining = remainingFor(line, index);
    if (remaining !== null && remaining < 0) {
        return { key: 'over', label: 'Over budget', class: 'ui-badge-warn' };
    }
    return { key: 'ready', label: 'Ready', class: 'ui-badge-success' };
};

const onFileChange = (event) => {
    form.supporting_document = event.target.files?.[0] ?? null;
};

const clearFile = () => {
    form.supporting_document = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const submitEdit = () => {
    form.transform((data) => {
        const payload = { ...data, _method: 'put' };
        delete payload.details;
        return payload;
    }).post(route('app.purchase-requests.update', props.record.id), {
        forceFormData: true,
    });
};

const onSubmit = () => (isEdit
    ? submitEdit()
    : form.post(route('app.purchase-requests.store'), { forceFormData: true }));

const cancelHref = isEdit
    ? route('app.purchase-requests.show', props.record.id)
    : route('app.purchase-requests.index');
</script>

<template>
    <AppLayout>
        <template #header>
            {{ isEdit ? `Edit ${record.pr_no}` : 'New purchase request' }}
        </template>

        <form class="mx-auto max-w-6xl space-y-4 pb-28" @submit.prevent="onSubmit">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="cancelHref" class="ui-link inline-flex items-center gap-1 text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </Link>
                    <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                        {{ isEdit ? `Edit ${record.pr_no}` : 'Create purchase request' }}
                    </h1>
                </div>
            </div>

            <!-- Compact request details -->
            <section class="ui-panel p-4">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Request</h2>
                    <span class="text-xs text-slate-400">Header details</span>
                </div>

                <div class="grid gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-3">
                        <UiDateInput
                            id="pr-date"
                            v-model="form.date"
                            label="Date"
                            required
                            :error="fieldError('date')"
                        />
                    </div>

                    <div class="lg:col-span-4">
                        <label for="pr-project" class="ui-label">Project</label>
                        <select id="pr-project" v-model="form.project_id" class="ui-select">
                            <option value="">No project</option>
                            <option v-for="project in options.projects" :key="project.id" :value="project.id">
                                {{ project.name }}
                            </option>
                        </select>
                    </div>

                    <div class="lg:col-span-5">
                        <label class="ui-label">Document</label>
                        <div class="flex items-center gap-2">
                            <button type="button" class="ui-btn-secondary shrink-0" @click="fileInputRef?.click()">
                                {{ form.supporting_document ? 'Replace' : 'Attach' }}
                            </button>
                            <p class="min-w-0 truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ form.supporting_document?.name || 'Optional · max 10 MB' }}
                            </p>
                            <button
                                v-if="form.supporting_document"
                                type="button"
                                class="shrink-0 text-xs font-medium text-red-600 dark:text-red-400"
                                @click="clearFile"
                            >
                                Clear
                            </button>
                            <input ref="fileInputRef" type="file" class="hidden" @change="onFileChange" />
                        </div>
                    </div>

                    <div class="lg:col-span-12">
                        <label for="pr-purpose" class="ui-label">Purpose</label>
                        <input
                            id="pr-purpose"
                            v-model="form.purpose"
                            type="text"
                            required
                            maxlength="255"
                            placeholder="What is this purchase for?"
                            class="ui-input"
                        />
                        <div class="mt-1 flex justify-between gap-2">
                            <p v-if="fieldError('purpose')" class="text-xs text-red-600 dark:text-red-400">{{ fieldError('purpose') }}</p>
                            <p class="ml-auto text-[11px] text-slate-400">{{ form.purpose.length }}/255</p>
                        </div>
                    </div>

                    <div class="lg:col-span-12">
                        <UiSearchableMultiSelect
                            id="pr-locations"
                            v-model="form.locations"
                            label="Locations"
                            :options="locationOptions"
                            placeholder="Search locations…"
                            hint="Choose one or more locations for this request."
                            :error="fieldError('locations')"
                        />
                    </div>
                </div>
            </section>

            <!-- Existing line items (edit: read-only) -->
            <section v-if="isEdit" class="ui-panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Line items</h2>
                            <p class="mt-0.5 text-sm text-slate-500">
                                {{ form.details.length }} item{{ form.details.length === 1 ? '' : 's' }} · Estimated total MVR {{ formatMoney(estimatedTotal) }}
                            </p>
                        </div>
                        <Link
                            :href="route('app.purchase-requests.show', record.id)"
                            class="ui-link text-sm"
                        >
                            Manage lines on PR page
                        </Link>
                    </div>
                </div>

                <div v-if="form.details.length" class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Item</th>
                                <th>Unit</th>
                                <th>Budget account</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Est. cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, index) in form.details" :key="line.id || index">
                                <td class="tabular-nums text-slate-400">{{ index + 1 }}</td>
                                <td>
                                    <div class="font-medium text-slate-900 dark:text-white">
                                        {{ itemById(line.item_id)?.name || '—' }}
                                    </div>
                                    <div class="mt-0.5 font-mono text-xs text-slate-500">
                                        {{ itemById(line.item_id)?.item_code || '' }}
                                    </div>
                                </td>
                                <td>{{ line.unit || '—' }}</td>
                                <td class="max-w-[16rem]">
                                    <span class="line-clamp-2" :title="budgetLabel(line.budget_account_id)">
                                        {{ budgetLabel(line.budget_account_id) || '—' }}
                                    </span>
                                </td>
                                <td class="text-right tabular-nums">{{ line.amount }}</td>
                                <td class="text-right font-medium tabular-nums text-slate-900 dark:text-white">
                                    {{ formatMoney(line.est_cost) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="px-5 py-10 text-center text-sm text-slate-500">
                    No line items on this purchase request.
                </p>
            </section>

            <!-- Line items (primary focus) -->
            <section v-if="!isEdit" class="ui-panel overflow-hidden">
                <div class="border-b border-slate-100 bg-gradient-to-r from-brand-50/80 to-transparent px-5 py-4 dark:border-slate-800 dark:from-brand-950/30">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Line items</h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                Build the request here — item, quantity, budget, and estimate for each line.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="ui-badge-neutral">{{ completedLines }}/{{ form.details.length }} ready</span>
                            <span v-if="overBudgetLines" class="ui-badge-warn">{{ overBudgetLines }} over budget</span>
                            <span class="ui-badge-brand">Total MVR {{ formatMoney(estimatedTotal) }}</span>
                            <button type="button" class="ui-btn-primary" @click="addLine">
                                <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Add line
                            </button>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article
                        v-for="(line, index) in form.details"
                        :key="index"
                        class="group relative px-5 py-5 transition hover:bg-slate-50/60 dark:hover:bg-surface-muted/20"
                    >
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-semibold text-white">
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ itemById(line.item_id)?.name || 'New line item' }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ itemById(line.item_id)?.item_code || 'Select an item to continue' }}
                                        <template v-if="line.budget_account_id">
                                            · {{ budgetLabel(line.budget_account_id) }}
                                        </template>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="lineStatus(line, index).class">{{ lineStatus(line, index).label }}</span>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-white hover:text-slate-800 dark:hover:bg-surface-elevated dark:hover:text-slate-200"
                                    @click="duplicateLine(index)"
                                >
                                    Duplicate
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 disabled:opacity-40 dark:text-red-400 dark:hover:bg-red-950/40"
                                    :disabled="form.details.length <= 1"
                                    @click="removeLine(index)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-12">
                            <div class="space-y-3 xl:col-span-7">
                                <UiSearchableSelect
                                    :id="`pr-item-${index}`"
                                    v-model="line.item_id"
                                    label="Item"
                                    :options="itemOptions"
                                    placeholder="Search by code or name…"
                                    required
                                    :error="lineError(index, 'item_id')"
                                />

                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="ui-label">Unit</label>
                                        <select v-model="line.unit" required class="ui-select">
                                            <option v-for="unit in options.units" :key="unit.value" :value="unit.value">
                                                {{ unit.label }}
                                            </option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="ui-label">Quantity</label>
                                        <input
                                            v-model="line.amount"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            class="ui-input"
                                            placeholder="0"
                                        />
                                        <p v-if="lineError(index, 'amount')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
                                            {{ lineError(index, 'amount') }}
                                        </p>
                                    </div>
                                    <div>
                                        <label class="ui-label">Est. cost (MVR)</label>
                                        <input
                                            v-model="line.est_cost"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            class="ui-input"
                                            placeholder="0.00"
                                        />
                                        <p v-if="lineError(index, 'est_cost')" class="mt-1.5 text-xs text-red-600 dark:text-red-400">
                                            {{ lineError(index, 'est_cost') }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="xl:col-span-5">
                                <div class="h-full rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-surface-muted/30">
                                    <UiSearchableSelect
                                        :id="`pr-budget-${index}`"
                                        v-model="line.budget_account_id"
                                        label="Budget account"
                                        :options="budgetOptions"
                                        placeholder="Search budget accounts…"
                                        required
                                        :error="lineError(index, 'budget_account_id')"
                                    />

                                    <div v-if="line.budget_account_id" class="mt-4 space-y-3">
                                        <div class="flex items-end justify-between gap-3 text-xs">
                                            <div>
                                                <p class="text-slate-500 dark:text-slate-400">Available</p>
                                                <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">
                                                    MVR {{ formatMoney(availableFor(line.budget_account_id)) }}
                                                </p>
                                            </div>
                                            <div class="text-right">
                                                <p class="text-slate-500 dark:text-slate-400">After this line</p>
                                                <p
                                                    class="mt-0.5 text-sm font-semibold"
                                                    :class="remainingFor(line, index) < 0
                                                        ? 'text-amber-700 dark:text-amber-300'
                                                        : 'text-emerald-700 dark:text-emerald-300'"
                                                >
                                                    MVR {{ formatMoney(remainingFor(line, index)) }}
                                                </p>
                                            </div>
                                        </div>

                                        <div>
                                            <div class="mb-1 flex justify-between text-[11px] text-slate-500">
                                                <span>Budget usage</span>
                                                <span>{{ usagePercent(line, index) }}%</span>
                                            </div>
                                            <div class="h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                                <div
                                                    class="h-full rounded-full transition-all"
                                                    :class="usagePercent(line, index) > 100
                                                        ? 'bg-amber-500'
                                                        : usagePercent(line, index) > 80
                                                            ? 'bg-brand-500'
                                                            : 'bg-emerald-500'"
                                                    :style="{ width: `${Math.min(usagePercent(line, index), 100)}%` }"
                                                />
                                            </div>
                                            <p class="mt-1 text-[11px] text-slate-500">
                                                Allocated MVR {{ formatMoney(allocatedFor(line.budget_account_id)) }}
                                            </p>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 rounded-lg bg-white/80 p-3 text-xs dark:bg-surface-elevated/70">
                                            <div>
                                                <p class="text-slate-500 dark:text-slate-400">Unit cost</p>
                                                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-100">
                                                    {{ unitCost(line) === null ? '—' : `MVR ${formatMoney(unitCost(line))}` }}
                                                </p>
                                            </div>
                                            <div>
                                                <p class="text-slate-500 dark:text-slate-400">Line estimate</p>
                                                <p class="mt-0.5 font-medium text-slate-800 dark:text-slate-100">
                                                    MVR {{ formatMoney(line.est_cost) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <p v-else class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                                        Choose a budget to see available balance and usage.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm font-medium text-slate-600 transition hover:border-brand-400 hover:bg-brand-50/50 hover:text-brand-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-brand-500 dark:hover:bg-brand-950/30 dark:hover:text-brand-200"
                        @click="addLine"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        Add another line item
                    </button>
                    <p v-if="fieldError('details')" class="mt-3 text-sm text-red-600 dark:text-red-400">
                        {{ fieldError('details') }}
                    </p>
                </div>

                <div class="grid gap-3 border-t border-slate-100 bg-slate-50/80 px-5 py-4 sm:grid-cols-3 dark:border-slate-800 dark:bg-surface-muted/30">
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Lines</p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ form.details.length }}
                            <span class="font-normal text-slate-500">({{ completedLines }} ready)</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Total quantity</p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ totalQty || 0 }}
                        </p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Estimated total</p>
                        <p class="mt-0.5 text-base font-semibold text-brand-700 dark:text-brand-300">
                            MVR {{ formatMoney(estimatedTotal) }}
                        </p>
                    </div>
                </div>
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">
                        <template v-if="!isEdit">
                            {{ completedLines }}/{{ form.details.length }} lines ready · Est. MVR {{ formatMoney(estimatedTotal) }}
                        </template>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <Link :href="cancelHref" class="ui-btn-ghost">Cancel</Link>
                        <button type="submit" class="ui-btn-primary min-w-36" :disabled="form.processing">
                            {{ form.processing
                                ? 'Saving…'
                                : (isEdit ? 'Save changes' : 'Create draft') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
