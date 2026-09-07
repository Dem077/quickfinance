<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateInput from '../../Components/UiDateInput.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    order: { type: Object, default: null },
    vendors: { type: Array, required: true },
    purchaseRequests: { type: Array, required: true },
    units: { type: Array, required: true },
});

const isEdit = !!props.order;

const form = useForm({
    vendor_id: props.order?.vendor_id ?? '',
    po_no: props.order?.po_no ?? '',
    date: props.order?.date ?? new Date().toISOString().slice(0, 10),
    pr_id: props.order?.pr_id ?? '',
    payment_method: props.order?.payment_method ?? 'purchase_order',
    is_advance_form_required: props.order?.is_advance_form_required ? 1 : 0,
    details: props.order?.details?.length
        ? props.order.details.map((d) => ({
            itemcode: d.itemcode,
            unit_measure: d.unit_measure,
            qty: d.qty,
            unit_price: d.unit_price,
            tax_amount: d.tax_amount,
            amount: d.amount,
            budget_account_id: d.budget_account_id,
            desc: d.desc,
            gst: Number(d.tax_amount) > 0 ? '8' : '0',
        }))
        : [],
});

const vendorOptions = computed(() =>
    props.vendors.map((vendor) => ({
        value: vendor.id,
        label: vendor.name,
    })),
);

const prOptions = computed(() =>
    props.purchaseRequests.map((pr) => ({
        value: pr.id,
        label: pr.purpose
            ? `${pr.pr_no} — ${pr.purpose}`
            : pr.pr_no,
    })),
);

const selectedPr = computed(() =>
    props.purchaseRequests.find((pr) => Number(pr.id) === Number(form.pr_id)),
);

const availableDetails = computed(() => {
    const pr = selectedPr.value;
    if (! pr) return [];

    const used = form.details.map((d) => d.itemcode);

    return (pr.details || []).filter((d) => {
        if (isEdit) {
            return ! d.is_utilized || used.includes(d.itemcode);
        }
        return ! d.is_utilized && ! used.includes(d.itemcode);
    });
});

const itemOptionsForLine = (line) => {
    const options = availableDetails.value.map((d) => ({
        value: d.itemcode,
        label: `${d.itemcode} — ${d.name}`,
        meta: d,
    }));

    if (line.itemcode && ! options.some((o) => o.value === line.itemcode)) {
        const match = (selectedPr.value?.details || []).find((d) => d.itemcode === line.itemcode);
        if (match) {
            options.unshift({
                value: match.itemcode,
                label: `${match.itemcode} — ${match.name}`,
                meta: match,
            });
        }
    }

    return options;
};

watch(() => form.payment_method, (method) => {
    if (method === 'petty_cash') {
        form.is_advance_form_required = 0;
        if (! isEdit) {
            form.po_no = '';
        }
    } else if (! isEdit) {
        form.po_no = '';
    }
});

watch(() => form.pr_id, () => {
    if (! isEdit) {
        form.details = [];
    }
});

const addLine = () => {
    const first = availableDetails.value[0];
    if (! first) return;

    form.details.push({
        itemcode: first.itemcode,
        desc: first.name,
        unit_measure: first.unit,
        qty: first.qty,
        unit_price: 0,
        tax_amount: 0,
        amount: 0,
        budget_account_id: first.budget_account_id,
        gst: '0',
    });
};

const onLineItemChange = (line) => {
    const match = (selectedPr.value?.details || []).find((d) => d.itemcode === line.itemcode);
    if (! match) return;
    line.desc = match.name;
    line.unit_measure = match.unit;
    line.qty = match.qty;
    line.budget_account_id = match.budget_account_id;
    recalculate(line);
};

const lineSubtotal = (line) => {
    const qty = Number(line.qty) || 0;
    const unitPrice = Number(line.unit_price) || 0;
    return qty * unitPrice;
};

const recalculate = (line) => {
    const subtotal = lineSubtotal(line);
    const gst = Number(line.gst) || 0;
    const tax = subtotal * (gst / 100);
    line.tax_amount = Math.round(tax * 1000) / 1000;
    line.amount = Math.round((subtotal + tax) * 1000) / 1000;
};

const applyTaxOverride = (line) => {
    const subtotal = lineSubtotal(line);
    const tax = Math.max(0, Number(line.tax_amount) || 0);
    line.tax_amount = Math.round(tax * 1000) / 1000;
    line.amount = Math.round((subtotal + line.tax_amount) * 1000) / 1000;
};

const removeLine = (index) => {
    form.details.splice(index, 1);
};

const duplicateLine = (index) => {
    const source = form.details[index];
    const clone = { ...source, unit_price: 0, tax_amount: 0, amount: 0, gst: '0' };
    // Only duplicate if another unused item exists, otherwise keep same item only on edit.
    const next = availableDetails.value.find((d) => d.itemcode !== source.itemcode);
    if (next && ! isEdit) {
        clone.itemcode = next.itemcode;
        clone.desc = next.name;
        clone.unit_measure = next.unit;
        clone.qty = next.qty;
        clone.budget_account_id = next.budget_account_id;
    }
    form.details.splice(index + 1, 0, clone);
};

const totalAmount = computed(() =>
    form.details.reduce((sum, line) => sum + (Number(line.amount) || 0), 0),
);

const taxTotal = computed(() =>
    form.details.reduce((sum, line) => sum + (Number(line.tax_amount) || 0), 0),
);

const completedLines = computed(() =>
    form.details.filter((line) => line.itemcode && Number(line.unit_price) > 0 && Number(line.amount) > 0).length,
);

const lineStatus = (line) => {
    if (! line.itemcode) {
        return { key: 'draft', label: 'Incomplete', class: 'ui-badge-neutral' };
    }
    if (! Number(line.unit_price)) {
        return { key: 'price', label: 'Needs price', class: 'ui-badge-warn' };
    }
    return { key: 'ready', label: 'Ready', class: 'ui-badge-success' };
};

const prDetailFor = (line) =>
    (selectedPr.value?.details || []).find((d) => d.itemcode === line.itemcode);

const formatMoney = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const fieldError = (key) => form.errors[key] || '';

const lineError = (index, field) =>
    form.errors[`details.${index}.${field}`] || '';

const cancelHref = isEdit
    ? route('app.purchase-orders.show', props.order.id)
    : route('app.purchase-orders.index');

const submit = () => {
    if (isEdit) {
        form.transform((data) => {
            const { details, ...header } = data;
            return header;
        }).put(route('app.purchase-orders.update', props.order.id));
        return;
    }

    form.post(route('app.purchase-orders.store'));
};

const setPaymentMethod = (method) => {
    if (isEdit) return;
    form.payment_method = method;
};
</script>

<template>
    <AppLayout>
        <template #header>
            {{ isEdit ? `Edit ${order.po_no}` : 'New purchase order' }}
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
                        {{ isEdit ? `Edit ${order.po_no}` : 'Create purchase order' }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Link an approved PR, choose payment method, then price the line items.
                    </p>
                </div>
            </div>

            <!-- Header -->
            <section class="ui-panel p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Order</h2>
                    <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-surface-muted/40">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            :class="form.payment_method === 'purchase_order'
                                ? 'bg-white text-brand-700 shadow-sm dark:bg-surface-elevated dark:text-brand-300'
                                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                            :disabled="isEdit"
                            @click="setPaymentMethod('purchase_order')"
                        >
                            Purchase order
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                            :class="form.payment_method === 'petty_cash'
                                ? 'bg-white text-brand-700 shadow-sm dark:bg-surface-elevated dark:text-brand-300'
                                : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                            :disabled="isEdit"
                            @click="setPaymentMethod('petty_cash')"
                        >
                            Petty cash
                        </button>
                    </div>
                </div>

                <div class="grid gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-4">
                        <UiSearchableSelect
                            id="po-vendor"
                            v-model="form.vendor_id"
                            label="Vendor"
                            :options="vendorOptions"
                            placeholder="Search vendors…"
                            required
                            :error="fieldError('vendor_id')"
                        />
                    </div>

                    <div class="lg:col-span-3">
                        <UiDateInput
                            id="po-date"
                            v-model="form.date"
                            label="Date"
                            required
                            :error="fieldError('date')"
                        />
                    </div>

                    <div class="lg:col-span-5">
                        <UiSearchableSelect
                            id="po-pr"
                            v-model="form.pr_id"
                            label="Purchase request"
                            :options="prOptions"
                            placeholder="Search MD/DMD approved PR…"
                            required
                            :disabled="isEdit"
                            :error="fieldError('pr_id')"
                        />
                    </div>

                    <div v-if="form.payment_method === 'purchase_order'" class="lg:col-span-4">
                        <label class="ui-label">PO number</label>
                        <input
                            v-model="form.po_no"
                            type="text"
                            required
                            class="ui-input"
                            placeholder="e.g. PO-00001234"
                        />
                        <p v-if="fieldError('po_no')" class="mt-1 text-xs text-red-600">{{ fieldError('po_no') }}</p>
                    </div>

                    <div v-else class="lg:col-span-4">
                        <label class="ui-label">Record number</label>
                        <div class="ui-input flex items-center bg-surface-muted text-sm text-slate-600 dark:text-slate-300">
                            {{ order?.po_no || 'Assigned when petty cash request is submitted' }}
                        </div>
                        <p class="mt-1 text-xs text-slate-500">
                            Uses the petty cash request number after you select this PO’s items and submit the request.
                        </p>
                    </div>

                    <div
                        v-if="form.payment_method === 'purchase_order'"
                        class="lg:col-span-8"
                    >
                        <label class="ui-label">Advance form required</label>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition"
                                :class="Number(form.is_advance_form_required) === 0
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'"
                                @click="form.is_advance_form_required = 0"
                            >
                                No
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition"
                                :class="Number(form.is_advance_form_required) === 1
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'"
                                @click="form.is_advance_form_required = 1"
                            >
                                Yes
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    v-if="selectedPr"
                    class="mt-4 rounded-xl border border-slate-100 bg-slate-50/80 px-4 py-3 dark:border-slate-800 dark:bg-surface-muted/30"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Selected PR</p>
                            <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">{{ selectedPr.pr_no }}</p>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-400">
                                {{ selectedPr.purpose || 'No purpose provided' }}
                            </p>
                        </div>
                        <div class="text-right text-xs text-slate-500">
                            <p>{{ availableDetails.length }} unused line{{ availableDetails.length === 1 ? '' : 's' }} available</p>
                            <p v-if="selectedPr.date" class="mt-0.5">{{ selectedPr.date }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Line items (create only) -->
            <section v-if="!isEdit" class="ui-panel overflow-hidden">
                <div class="border-b border-slate-100 bg-gradient-to-r from-brand-50/80 to-transparent px-5 py-4 dark:border-slate-800 dark:from-brand-950/30">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-slate-900 dark:text-white">Line items</h2>
                            <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                Pull unutilized PR lines, set GST and unit price.
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="ui-badge-neutral">{{ completedLines }}/{{ form.details.length || 0 }} ready</span>
                            <span class="ui-badge-brand">Total MVR {{ formatMoney(totalAmount) }}</span>
                            <button
                                type="button"
                                class="ui-btn-primary"
                                :disabled="!availableDetails.length"
                                @click="addLine"
                            >
                                <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Add line
                            </button>
                        </div>
                    </div>
                </div>

                <div v-if="!form.pr_id" class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">Select a purchase request first</p>
                    <p class="mt-1 text-sm text-slate-500">Available PR lines will appear here once a request is chosen.</p>
                </div>

                <div v-else-if="!form.details.length" class="px-5 py-12 text-center">
                    <p class="text-sm font-medium text-slate-900 dark:text-white">
                        {{ availableDetails.length ? 'No lines added yet' : 'No unused PR lines available' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ availableDetails.length
                            ? 'Add a line from the unused items on this purchase request.'
                            : 'All items on this PR are already utilized.' }}
                    </p>
                    <button
                        v-if="availableDetails.length"
                        type="button"
                        class="ui-btn-primary mt-4"
                        @click="addLine"
                    >
                        Add first line
                    </button>
                </div>

                <div v-else class="divide-y divide-slate-100 dark:divide-slate-800">
                    <article
                        v-for="(line, index) in form.details"
                        :key="`${line.itemcode}-${index}`"
                        class="group relative px-5 py-5 transition hover:bg-slate-50/60 dark:hover:bg-surface-muted/20"
                    >
                        <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-semibold text-white">
                                    {{ index + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                        {{ line.desc || 'New line item' }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">
                                        {{ line.itemcode || 'Select an item' }}
                                        <template v-if="prDetailFor(line)?.budget_label || prDetailFor(line)?.budget_code">
                                            · {{ prDetailFor(line)?.budget_label || prDetailFor(line)?.budget_code }}
                                        </template>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span :class="lineStatus(line).class">{{ lineStatus(line).label }}</span>
                                <button
                                    type="button"
                                    class="rounded-lg px-2 py-1 text-xs font-medium text-slate-500 hover:bg-white hover:text-slate-800 dark:hover:bg-surface-elevated dark:hover:text-slate-200"
                                    :disabled="!availableDetails.length"
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

                        <div class="grid gap-4 xl:grid-cols-12">
                            <div class="space-y-3 xl:col-span-7">
                                <UiSearchableSelect
                                    :id="`po-item-${index}`"
                                    v-model="line.itemcode"
                                    label="Item from PR"
                                    :options="itemOptionsForLine(line)"
                                    placeholder="Search PR items…"
                                    required
                                    :error="lineError(index, 'itemcode')"
                                    @update:model-value="onLineItemChange(line)"
                                />

                                <div class="grid gap-3 sm:grid-cols-3">
                                    <div>
                                        <label class="ui-label">Unit</label>
                                        <input :value="line.unit_measure" type="text" disabled class="ui-input bg-slate-50" />
                                    </div>
                                    <div>
                                        <label class="ui-label">Quantity</label>
                                        <input :value="line.qty" type="text" disabled class="ui-input bg-slate-50" />
                                    </div>
                                    <div>
                                        <label class="ui-label">GST %</label>
                                        <select v-model="line.gst" class="ui-select" @change="recalculate(line)">
                                            <option value="0">0%</option>
                                            <option value="8">8%</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="xl:col-span-5">
                                <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-surface-elevated/40">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <label class="ui-label">Unit price (MVR)</label>
                                            <input
                                                v-model.number="line.unit_price"
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                required
                                                class="ui-input"
                                                placeholder="0.000"
                                                @input="recalculate(line)"
                                            />
                                            <p v-if="lineError(index, 'unit_price')" class="mt-1 text-xs text-red-600">
                                                {{ lineError(index, 'unit_price') }}
                                            </p>
                                        </div>
                                        <div>
                                            <label class="ui-label">Tax amount (MVR)</label>
                                            <input
                                                v-model.number="line.tax_amount"
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                required
                                                class="ui-input"
                                                placeholder="0.000"
                                                @input="applyTaxOverride(line)"
                                            />
                                            <p class="mt-1 text-[11px] text-slate-400">
                                                Auto from GST%; you can override.
                                            </p>
                                            <p v-if="lineError(index, 'tax_amount')" class="mt-1 text-xs text-red-600">
                                                {{ lineError(index, 'tax_amount') }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-500">Subtotal</p>
                                            <p class="mt-0.5 font-medium tabular-nums text-slate-800 dark:text-slate-100">
                                                MVR {{ formatMoney(lineSubtotal(line)) }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Line total</p>
                                            <p class="mt-0.5 font-semibold tabular-nums text-brand-700 dark:text-brand-300">
                                                MVR {{ formatMoney(line.amount) }}
                                            </p>
                                        </div>
                                        <div v-if="prDetailFor(line)?.est_cost" class="col-span-2">
                                            <p class="text-xs text-slate-500">PR estimate</p>
                                            <p class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">
                                                MVR {{ formatMoney(prDetailFor(line).est_cost) }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-if="form.details.length" class="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <button
                        type="button"
                        class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-3 text-sm font-medium text-slate-600 transition hover:border-brand-400 hover:bg-brand-50/50 hover:text-brand-800 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:text-slate-300 dark:hover:border-brand-500 dark:hover:bg-brand-950/30 dark:hover:text-brand-200"
                        :disabled="!availableDetails.length"
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

                <div
                    v-if="form.details.length"
                    class="grid gap-3 border-t border-slate-100 bg-slate-50/80 px-5 py-4 sm:grid-cols-3 dark:border-slate-800 dark:bg-surface-muted/30"
                >
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Lines</p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">
                            {{ form.details.length }}
                            <span class="font-normal text-slate-500">({{ completedLines }} ready)</span>
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Tax total</p>
                        <p class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">
                            MVR {{ formatMoney(taxTotal) }}
                        </p>
                    </div>
                    <div class="sm:text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">Order total</p>
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
                <Link :href="route('app.purchase-orders.show', order.id)" class="font-medium text-brand-700 dark:text-brand-300">
                    order view page
                </Link>
                while the record is in draft.
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95">
                <div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <div class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">
                        <template v-if="!isEdit">
                            {{ completedLines }}/{{ form.details.length || 0 }} lines ready · MVR {{ formatMoney(totalAmount) }}
                        </template>
                        <template v-else>
                            Header details only — lines stay on the view page
                        </template>
                    </div>
                    <div class="ml-auto flex items-center gap-2">
                        <Link :href="cancelHref" class="ui-btn-ghost">Cancel</Link>
                        <button
                            type="submit"
                            class="ui-btn-primary min-w-36"
                            :disabled="form.processing || (!isEdit && !form.details.length)"
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
