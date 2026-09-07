<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateInput from '../../Components/UiDateInput.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    reimbursment: { type: Object, default: null },
    formNoPreview: { type: String, default: '' },
    options: { type: Object, required: true },
});

const isEdit = !!props.reimbursment;
const canManageLines = ! isEdit || !!props.reimbursment?.is_draft;
const fileInputRef = ref(null);

const emptyLine = () => ({
    is_from_pr: true,
    date: new Date().toISOString().slice(0, 10),
    po_id: '',
    Vendor_id: '',
    bill_no: '',
    item_id: '',
    details: '',
    sub_budget_id: '',
    amount: '',
});

const form = useForm({
    date: props.reimbursment?.date ?? new Date().toISOString().slice(0, 10),
    supporting_documents: null,
    details: (props.reimbursment?.details || []).map((detail) => ({
        date: detail.date,
        is_from_pr: !!detail.is_from_pr,
        po_id: detail.po_id || '',
        Vendor_id: detail.Vendor_id || '',
        bill_no: detail.bill_no || '',
        item_id: detail.item_id || '',
        details: detail.details || '',
        sub_budget_id: detail.sub_budget_id || '',
        amount: detail.amount || '',
    })),
});

const vendorOptions = computed(() =>
    (props.options.vendors || []).map((vendor) => ({
        value: vendor.id,
        label: vendor.name,
    })),
);

const budgetOptions = computed(() =>
    (props.options.budgets || []).map((budget) => ({
        value: budget.id,
        label: budget.label,
    })),
);

const itemKey = (poId, itemId) => `${poId}:${itemId}`;

const usedItemKeys = computed(() =>
    form.details
        .filter((line) => line.is_from_pr && line.po_id && line.item_id)
        .map((line) => itemKey(line.po_id, line.item_id)),
);

const allPoItems = computed(() =>
    (props.options.purchaseOrders || []).flatMap((po) =>
        (po.items || []).map((item) => ({
            po_id: po.id,
            po_label: po.label,
            vendor_id: po.vendor_id,
            item_id: item.item_id,
            name: item.name,
            amount: item.amount,
            budget_account_id: item.budget_account_id,
            key: itemKey(po.id, item.item_id),
        })),
    ),
);

const availableItems = computed(() =>
    allPoItems.value.filter((item) => ! usedItemKeys.value.includes(item.key)),
);

const poById = (poId) =>
    (props.options.purchaseOrders || []).find((po) => Number(po.id) === Number(poId));

const poReceiptUrl = (line) => {
    if (! line?.is_from_pr || ! line.po_id) return null;
    return poById(line.po_id)?.receipt_url || null;
};

const poOptionsForLine = (line) => {
    const availablePoIds = new Set(availableItems.value.map((item) => Number(item.po_id)));
    if (line.po_id) {
        availablePoIds.add(Number(line.po_id));
    }

    return (props.options.purchaseOrders || [])
        .filter((po) => availablePoIds.has(Number(po.id)))
        .map((po) => ({
            value: po.id,
            label: po.label,
        }));
};

const itemOptionsForLine = (line) => {
    if (! line.po_id) return [];

    const currentKey = line.item_id ? itemKey(line.po_id, line.item_id) : null;

    return allPoItems.value
        .filter((item) => Number(item.po_id) === Number(line.po_id))
        .filter((item) => item.key === currentKey || ! usedItemKeys.value.includes(item.key))
        .map((item) => ({
            value: item.item_id,
            label: item.name,
        }));
};

const applyPoToLine = (line, poId) => {
    line.po_id = poId;
    line.item_id = '';
    line.details = '';
    line.amount = '';
    const po = poById(poId);
    if (po?.vendor_id) {
        line.Vendor_id = po.vendor_id;
    }
};

const applyItemToLine = (line, itemId) => {
    line.item_id = itemId;
    const item = allPoItems.value.find((row) =>
        Number(row.po_id) === Number(line.po_id) && Number(row.item_id) === Number(itemId),
    );
    if (! item) return;
    line.details = item.name;
    line.amount = item.amount;
    if (item.budget_account_id) {
        line.sub_budget_id = item.budget_account_id;
    }
    if (item.vendor_id) {
        line.Vendor_id = item.vendor_id;
    }
};

const lineFromPo = (poId) => {
    const po = poById(poId);
    return {
        is_from_pr: true,
        date: new Date().toISOString().slice(0, 10),
        po_id: poId,
        Vendor_id: po?.vendor_id || '',
        bill_no: '',
        item_id: '',
        details: '',
        sub_budget_id: '',
        amount: '',
    };
};

const budgetHint = (subBudgetId) => {
    const budget = (props.options.budgets || []).find((row) => Number(row.id) === Number(subBudgetId));
    if (! budget) return '';
    if (budget.allocated_amount == null) return 'No allocation found for your department.';
    return `Allocated: MVR ${formatMoney(budget.allocated_amount)}`;
};

const setFromPr = (line, fromPr) => {
    line.is_from_pr = fromPr;
    line.po_id = '';
    line.item_id = '';
    line.details = '';
    line.amount = '';
    if (fromPr && availableItems.value[0]) {
        applyPoToLine(line, availableItems.value[0].po_id);
    }
};

const addLine = () => {
    const first = availableItems.value[0];
    if (first) {
        form.details.push(lineFromPo(first.po_id));
        return;
    }
    form.details.push({ ...emptyLine(), is_from_pr: false });
};

const addManualLine = () => {
    form.details.push({ ...emptyLine(), is_from_pr: false });
};

const removeLine = (index) => {
    form.details.splice(index, 1);
};

const duplicateLine = (index) => {
    const source = form.details[index];
    const next = availableItems.value.find((item) =>
        ! (Number(item.po_id) === Number(source.po_id) && Number(item.item_id) === Number(source.item_id)),
    );

    if (source.is_from_pr && next) {
        form.details.splice(index + 1, 0, {
            ...lineFromPo(next.po_id),
            date: source.date,
            bill_no: source.bill_no,
        });
        return;
    }

    form.details.splice(index + 1, 0, { ...emptyLine(), is_from_pr: false, date: source.date });
};

const lineTitle = (line) => {
    if (line.is_from_pr) {
        const item = allPoItems.value.find((row) =>
            Number(row.po_id) === Number(line.po_id) && Number(row.item_id) === Number(line.item_id),
        );
        return item?.name || 'New line item';
    }
    return line.details || 'New line item';
};

const lineSubtitle = (line) => {
    const vendor = (props.options.vendors || []).find((v) => Number(v.id) === Number(line.Vendor_id));
    const item = allPoItems.value.find((row) =>
        Number(row.po_id) === Number(line.po_id) && Number(row.item_id) === Number(line.item_id),
    );
    const parts = [item?.po_label, vendor?.name, line.bill_no].filter(Boolean);
    return parts.join(' · ') || 'Select vendor and bill no';
};

const lineStatus = (line) => {
    if (line.is_from_pr && (! line.po_id || ! line.item_id)) {
        return { label: 'Incomplete', class: 'ui-badge-neutral' };
    }
    if (! line.is_from_pr && ! line.details) {
        return { label: 'Incomplete', class: 'ui-badge-neutral' };
    }
    if (! line.Vendor_id || ! line.bill_no || ! line.sub_budget_id || ! Number(line.amount)) {
        return { label: 'Needs details', class: 'ui-badge-warn' };
    }
    return { label: 'Ready', class: 'ui-badge-success' };
};

const totalAmount = computed(() =>
    form.details.reduce((sum, line) => sum + (Number(line.amount) || 0), 0),
);

const completedLines = computed(() =>
    form.details.filter((line) => lineStatus(line).label === 'Ready').length,
);

const formatMoney = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const fieldError = (key) => form.errors[key] || '';
const lineError = (index, field) => form.errors[`details.${index}.${field}`] || '';

const dateDisabled = isEdit && ! props.reimbursment?.is_draft;

const cancelHref = isEdit
    ? route('app.petty-cash.show', props.reimbursment.id)
    : route('app.petty-cash.index');

const onFileChange = (event) => {
    form.supporting_documents = event.target.files?.[0] ?? null;
};

const clearFile = () => {
    form.supporting_documents = null;
    if (fileInputRef.value) fileInputRef.value.value = '';
};

const submit = () => {
    if (isEdit) {
        form.transform((data) => {
            const payload = { ...data };
            if (! canManageLines) {
                delete payload.details;
            }
            return payload;
        }).post(route('app.petty-cash.update', props.reimbursment.id), { forceFormData: true });
        return;
    }
    form.post(route('app.petty-cash.store'), { forceFormData: true });
};
</script>

<template>
    <AppLayout>
        <template #header>
            {{ isEdit ? `Edit ${reimbursment.form_no}` : 'New petty cash' }}
        </template>

        <form class="mx-auto max-w-6xl space-y-4 pb-28" @submit.prevent="submit">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="cancelHref" class="ui-link inline-flex items-center gap-1 text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </Link>
                    <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                        {{ isEdit ? `Edit ${reimbursment.form_no}` : 'Create petty cash' }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ canManageLines
                            ? 'Set the date, attach documents, then add reimbursement lines from a PO or manually.'
                            : 'Update supporting documents. Line items can only be changed while the record is in draft.' }}
                    </p>
                </div>
            </div>

            <section class="ui-panel p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Reimbursement</h2>
                <div class="grid gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-4">
                        <label class="ui-label">Form number</label>
                        <input :value="formNoPreview" type="text" disabled class="ui-input bg-slate-50" />
                    </div>
                    <div class="lg:col-span-4">
                        <UiDateInput
                            id="pc-date"
                            v-model="form.date"
                            label="Date"
                            required
                            :disabled="dateDisabled"
                            :error="fieldError('date')"
                        />
                    </div>
                    <div class="lg:col-span-4">
                        <label class="ui-label">Supporting documents</label>
                        <div class="mt-1.5 flex items-center gap-2">
                            <button type="button" class="ui-btn-secondary shrink-0" @click="fileInputRef?.click()">
                                {{ form.supporting_documents ? 'Replace' : 'Attach' }}
                            </button>
                            <p class="min-w-0 truncate text-xs text-slate-500 dark:text-slate-400">
                                {{ form.supporting_documents?.name || 'Optional · max 10 MB' }}
                            </p>
                            <button
                                v-if="form.supporting_documents"
                                type="button"
                                class="shrink-0 text-xs font-medium text-red-600 dark:text-red-400"
                                @click="clearFile"
                            >
                                Clear
                            </button>
                            <input ref="fileInputRef" type="file" class="hidden" @change="onFileChange" />
                        </div>
                        <p v-if="fieldError('supporting_documents')" class="mt-1 text-xs text-red-600">
                            {{ fieldError('supporting_documents') }}
                        </p>
                    </div>
                </div>
            </section>

            <section v-if="canManageLines" class="ui-panel overflow-hidden">
                <div class="border-b border-slate-100 bg-gradient-to-r from-brand-50/80 to-transparent px-5 py-4 dark:border-slate-800 dark:from-brand-950/30">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Line items</h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                Pull unused petty-cash PO lines, then enter bill details.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="ui-badge-neutral">{{ availableItems.length }} unused PO line{{ availableItems.length === 1 ? '' : 's' }}</span>
                            <span class="ui-badge-neutral">{{ completedLines }}/{{ form.details.length || 0 }} ready</span>
                            <span class="ui-badge-brand">Total MVR {{ formatMoney(totalAmount) }}</span>
                            <button type="button" class="ui-btn-primary" @click="addLine">
                                <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Add line
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!form.details.length" class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">
                        {{ availableItems.length ? 'No lines added yet' : 'No unused PO lines available' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ availableItems.length
                            ? 'Add a line from unused petty-cash purchase order items.'
                            : 'You can still add a manual expense line.' }}
                    </p>
                    <div class="mt-4 flex flex-wrap items-center justify-center gap-2">
                        <button type="button" class="ui-btn-primary" @click="addLine">
                            {{ availableItems.length ? 'Add first line' : 'Add manual line' }}
                        </button>
                        <button
                            v-if="availableItems.length"
                            type="button"
                            class="ui-btn-secondary"
                            @click="addManualLine"
                        >
                            Add manual line
                        </button>
                    </div>
                </div>

                <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
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
                                        {{ lineTitle(line) }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ lineSubtitle(line) }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="lineStatus(line).class">{{ lineStatus(line).label }}</span>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-white hover:text-slate-800 disabled:opacity-40 dark:hover:bg-surface-elevated dark:hover:text-slate-200"
                                    :disabled="line.is_from_pr && !availableItems.length"
                                    @click="duplicateLine(index)"
                                >
                                    Duplicate
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/40"
                                    @click="removeLine(index)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>

                        <div class="mb-4 inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-surface-muted/40">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                :class="line.is_from_pr
                                    ? 'bg-white text-brand-700 shadow-sm dark:bg-surface-elevated dark:text-brand-300'
                                    : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                                @click="setFromPr(line, true)"
                            >
                                From PR / PO
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                :class="!line.is_from_pr
                                    ? 'bg-white text-brand-700 shadow-sm dark:bg-surface-elevated dark:text-brand-300'
                                    : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                                @click="setFromPr(line, false)"
                            >
                                Manual
                            </button>
                        </div>

                        <div class="grid gap-4 xl:grid-cols-12">
                            <div class="space-y-3 xl:col-span-7">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <UiDateInput
                                        :id="`pc-line-date-${index}`"
                                        v-model="line.date"
                                        label="Date"
                                        required
                                        :error="lineError(index, 'date')"
                                    />
                                    <div v-if="line.is_from_pr">
                                        <UiSearchableSelect
                                            :id="`pc-line-po-${index}`"
                                            v-model="line.po_id"
                                            label="Purchase order"
                                            :options="poOptionsForLine(line)"
                                            placeholder="Select PO…"
                                            required
                                            :error="lineError(index, 'po_id')"
                                            @update:model-value="applyPoToLine(line, $event)"
                                        />
                                        <a
                                            v-if="poReceiptUrl(line)"
                                            :href="poReceiptUrl(line)"
                                            target="_blank"
                                            class="mt-1.5 inline-block text-xs font-medium text-brand-700 dark:text-brand-300"
                                        >
                                            View attached receipt
                                        </a>
                                    </div>
                                    <UiSearchableSelect
                                        v-if="line.is_from_pr"
                                        :id="`pc-line-item-${index}`"
                                        v-model="line.item_id"
                                        label="Item from PO"
                                        :options="itemOptionsForLine(line)"
                                        placeholder="Select item…"
                                        required
                                        :disabled="!line.po_id"
                                        :error="lineError(index, 'item_id')"
                                        @update:model-value="applyItemToLine(line, $event)"
                                    />
                                    <UiSearchableSelect
                                        :id="`pc-line-vendor-${index}`"
                                        v-model="line.Vendor_id"
                                        label="Vendor"
                                        :options="vendorOptions"
                                        placeholder="Search vendors…"
                                        required
                                        :error="lineError(index, 'Vendor_id')"
                                    />
                                    <div>
                                        <label class="ui-label">Bill no</label>
                                        <input v-model="line.bill_no" type="text" required class="ui-input" placeholder="Invoice / bill number" />
                                        <p v-if="lineError(index, 'bill_no')" class="mt-1 text-xs text-red-600">{{ lineError(index, 'bill_no') }}</p>
                                    </div>
                                    <div v-if="!line.is_from_pr">
                                        <label class="ui-label">Details</label>
                                        <input v-model="line.details" type="text" required class="ui-input" placeholder="What was purchased?" />
                                        <p v-if="lineError(index, 'details')" class="mt-1 text-xs text-red-600">{{ lineError(index, 'details') }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="xl:col-span-5">
                                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-surface-elevated/40">
                                    <UiSearchableSelect
                                        :id="`pc-line-budget-${index}`"
                                        v-model="line.sub_budget_id"
                                        label="Budget account"
                                        :options="budgetOptions"
                                        placeholder="Search budgets…"
                                        required
                                        :error="lineError(index, 'sub_budget_id')"
                                    />
                                    <p v-if="budgetHint(line.sub_budget_id)" class="mt-1 text-xs text-slate-500">
                                        {{ budgetHint(line.sub_budget_id) }}
                                    </p>
                                    <div class="mt-3">
                                        <label class="ui-label">Amount (MVR)</label>
                                        <input
                                            v-model="line.amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            required
                                            :readonly="line.is_from_pr"
                                            class="ui-input read-only:bg-slate-50"
                                            placeholder="0.00"
                                        />
                                        <p v-if="lineError(index, 'amount')" class="mt-1 text-xs text-red-600">{{ lineError(index, 'amount') }}</p>
                                    </div>
                                    <div class="mt-4">
                                        <p class="text-xs text-slate-500">Line total</p>
                                        <p class="mt-0.5 font-semibold tabular-nums text-brand-700 dark:text-brand-300">
                                            MVR {{ formatMoney(line.amount) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-if="form.details.length" class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm font-medium text-slate-600 transition hover:border-brand-400 hover:bg-brand-50/50 hover:text-brand-800 dark:border-slate-600 dark:text-slate-300 dark:hover:border-brand-500 dark:hover:bg-brand-950/30 dark:hover:text-brand-200"
                        @click="addLine"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                        </svg>
                        {{ availableItems.length ? 'Add another line item' : 'Add a manual line item' }}
                    </button>
                    <p v-if="fieldError('details')" class="mt-3 text-sm text-red-600 dark:text-red-400">
                        {{ fieldError('details') }}
                    </p>
                </div>

                <div
                    v-if="form.details.length"
                    class="grid gap-3 border-t border-slate-100 bg-slate-50/80 px-5 py-4 sm:grid-cols-2 dark:border-slate-800 dark:bg-surface-muted/30"
                >
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Lines</p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ form.details.length }}
                            <span class="font-normal text-slate-500">({{ completedLines }} ready)</span>
                        </p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Reimbursement total</p>
                        <p class="mt-0.5 text-base font-semibold text-brand-700 dark:text-brand-300">
                            MVR {{ formatMoney(totalAmount) }}
                        </p>
                    </div>
                </div>
            </section>

            <section
                v-else
                class="ui-panel px-5 py-6 text-sm text-slate-600 dark:text-slate-300"
            >
                Line items are managed on the
                <Link :href="route('app.petty-cash.show', reimbursment.id)" class="font-medium text-brand-700 dark:text-brand-300">
                    reimbursement view page
                </Link>
                while the record is in draft.
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">
                        <template v-if="canManageLines">
                            {{ completedLines }}/{{ form.details.length || 0 }} lines ready · MVR {{ formatMoney(totalAmount) }}
                        </template>
                        <template v-else>
                            Supporting documents only — lines stay on the view page
                        </template>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <Link :href="cancelHref" class="ui-btn-ghost">Cancel</Link>
                        <button
                            type="submit"
                            class="ui-btn-primary min-w-36"
                            :disabled="form.processing || (canManageLines && !form.details.length)"
                        >
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
