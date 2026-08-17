<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    order: { type: Object, required: true },
    availablePrDetails: { type: Array, required: true },
    units: { type: Array, required: true },
    can: { type: Object, required: true },
});

const formatMoney = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value) => {
    if (! value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleDateString(undefined, {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const modal = ref(null);
const detailsOpen = ref(false);
const advanceOpen = ref(false);

const closeForm = useForm({ grn_number: '' });
const advanceForm = useForm({
    qoation_no: props.order.advance_form?.qoation_no ?? '',
    expected_delivery: props.order.advance_form?.expected_delivery ?? '',
    advance_amount: props.order.advance_form?.advance_percentage ?? '',
});
const receiptForm = useForm({ supporting_document: null });
const lineForm = useForm({
    itemcode: '',
    unit_measure: '',
    qty: 0,
    unit_price: 0,
    tax_amount: 0,
    amount: 0,
    budget_account_id: '',
    gst: '0',
});
const editLineForm = useForm({
    unit_price: 0,
    tax_amount: 0,
    amount: 0,
    gst: '0',
    qty: 0,
});
const editingLineId = ref(null);

watch(() => props.order.advance_form, (af) => {
    if (af) {
        advanceForm.qoation_no = af.qoation_no ?? '';
        advanceForm.expected_delivery = af.expected_delivery ?? '';
        advanceForm.advance_amount = af.advance_percentage ?? '';
    }
}, { deep: true });

const statusTone = computed(() => {
    const map = {
        draft: 'neutral',
        submitted: 'warn',
        grn_created: 'brand',
        reimbursement_pending: 'warn',
        reimbursed: 'success',
        closed: 'success',
    };
    return map[props.order.status] || 'neutral';
});

const badgeClass = computed(() => ({
    neutral: 'ui-badge-neutral',
    warn: 'ui-badge-warn',
    success: 'ui-badge-success',
    danger: 'ui-badge-danger',
    brand: 'ui-badge-brand',
}[statusTone.value]));

const lineCount = computed(() => props.order.details?.length || 0);

const detailFields = computed(() => {
    const fields = [
        { label: 'PO number', value: props.order.po_no },
        { label: 'Date', value: formatDate(props.order.date) },
        { label: 'Vendor', value: props.order.vendor?.name || '—' },
        { label: 'Payment method', value: props.order.payment_method_label },
        { label: 'Status', value: props.order.status_label },
        { label: 'GRN', value: props.order.grn_number || '—' },
        { label: 'Tax total', value: `MVR ${formatMoney(props.order.tax_total)}` },
        { label: 'Order total', value: `MVR ${formatMoney(props.order.total_amount)}` },
    ];
    if (props.order.purchase_request?.pr_no) {
        fields.splice(2, 0, { label: 'Purchase request', value: props.order.purchase_request.pr_no });
    }
    return fields;
});

const detailsSummary = computed(() => {
    const parts = [
        props.order.vendor?.name,
        props.order.payment_method_label,
        props.order.purchase_request?.pr_no,
        formatDate(props.order.date),
    ].filter(Boolean);
    return parts.join(' · ');
});

const advanceSummary = computed(() => {
    if (! props.order.advance_form) {
        return props.order.is_advance_form_required ? 'Not generated yet' : 'Not required';
    }
    return `${props.order.advance_form.request_number} · ${props.order.advance_form.status_label}`;
});

const primaryActions = computed(() => {
    const list = [];
    if (props.can.submit) {
        list.push({ key: 'submit', label: 'Submit order', tone: 'primary', run: submitPo });
    }
    if (props.can.close && props.order.payment_method === 'purchase_order') {
        list.push({ key: 'close', label: 'Close order', tone: 'danger', run: () => openModal('close') });
    }
    if (props.can.close && props.order.payment_method === 'petty_cash') {
        list.push({ key: 'close-petty', label: 'Close order', tone: 'danger', run: confirmClosePetty });
    }
    if (props.can.upload_receipt) {
        list.push({ key: 'receipt', label: 'Upload receipt', tone: 'secondary', run: () => openModal('receipt') });
    }
    if (props.can.generate_advance_form) {
        list.push({ key: 'gen-advance', label: 'Generate advance form', tone: 'primary', run: () => openModal('generate-advance') });
    }
    if (props.can.regenerate_advance_form) {
        list.push({ key: 'regen-advance', label: 'Regenerate advance', tone: 'warn', run: () => openModal('regenerate-advance') });
    }
    if (props.can.submit_advance_form) {
        list.push({ key: 'submit-advance', label: 'Submit advance form', tone: 'success', run: submitAdvance });
    }
    if (props.can.hod_approve_advance_form) {
        list.push({ key: 'hod-approve', label: 'HOD approve', tone: 'success', run: hodApprove });
        list.push({ key: 'hod-reject', label: 'HOD reject', tone: 'danger', run: hodReject });
    }
    if (props.can.md_dmd_approve_advance_form) {
        list.push({ key: 'md-approve', label: 'MD/DMD approve', tone: 'success', run: mdApprove });
        list.push({ key: 'md-reject', label: 'MD/DMD reject', tone: 'danger', run: mdReject });
    }
    return list;
});

const actionClass = (tone) => ({
    primary: 'ui-btn-primary',
    secondary: 'ui-btn-secondary',
    success: 'inline-flex items-center justify-center rounded-xl border border-transparent bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-500',
    danger: 'ui-btn-danger',
    warn: 'inline-flex items-center justify-center rounded-xl border border-transparent bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-amber-400',
}[tone] || 'ui-btn-secondary');

const openModal = (name) => {
    modal.value = name;
};

const closeModal = () => {
    modal.value = null;
};

const submitPo = () => {
    if (! confirm('Submit this purchase order?')) return;
    router.post(route('app.purchase-orders.submit', props.order.id), {}, { preserveScroll: true });
};

const deletePo = () => {
    if (! confirm('Delete this draft purchase order?')) return;
    router.delete(route('app.purchase-orders.destroy', props.order.id));
};

const submitClose = () => {
    closeForm.post(route('app.purchase-orders.close', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeForm.reset();
            closeModal();
        },
    });
};

const confirmClosePetty = () => {
    if (! confirm('Close this petty cash procure record? This cannot be undone.')) return;
    closeForm.post(route('app.purchase-orders.close', props.order.id), { preserveScroll: true });
};

const submitAdvanceGenerate = () => {
    const routeName = modal.value === 'regenerate-advance'
        ? 'app.purchase-orders.advance-form.regenerate'
        : 'app.purchase-orders.advance-form.generate';

    advanceForm.post(route(routeName, props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            if (routeName === 'app.purchase-orders.advance-form.regenerate') {
                window.open(route('purchase-orders.advance-form.download', props.order.id), '_blank');
            }
        },
    });
};

const submitAdvance = () => {
    if (! confirm('Submit advance form for HOD approval?')) return;
    router.post(route('app.purchase-orders.advance-form.submit', props.order.id), {}, { preserveScroll: true });
};

const hodApprove = () => {
    if (! confirm('Approve this advance form as HOD?')) return;
    router.post(route('app.purchase-orders.advance-form.hod-approve', props.order.id), {}, { preserveScroll: true });
};

const hodReject = () => {
    if (! confirm('Reject this advance form as HOD?')) return;
    router.post(route('app.purchase-orders.advance-form.hod-reject', props.order.id), {}, { preserveScroll: true });
};

const mdApprove = () => {
    if (! confirm('Approve this advance form as MD/DMD?')) return;
    router.post(route('app.purchase-orders.advance-form.md-dmd-approve', props.order.id), {}, { preserveScroll: true });
};

const mdReject = () => {
    if (! confirm('Reject this advance form as MD/DMD?')) return;
    router.post(route('app.purchase-orders.advance-form.md-dmd-reject', props.order.id), {}, { preserveScroll: true });
};

const onReceiptChange = (event) => {
    receiptForm.supporting_document = event.target.files?.[0] ?? null;
};

const submitReceipt = () => {
    receiptForm.post(route('app.purchase-orders.upload-receipt', props.order.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            receiptForm.reset();
            closeModal();
        },
    });
};

const onAddLineItemChange = () => {
    const match = props.availablePrDetails.find((d) => d.itemcode === lineForm.itemcode);
    if (! match) return;
    lineForm.unit_measure = match.unit;
    lineForm.qty = match.qty;
    lineForm.budget_account_id = match.budget_account_id;
    recalculateLine(lineForm);
};

const recalculateLine = (target) => {
    const qty = Number(target.qty) || 0;
    const unitPrice = Number(target.unit_price) || 0;
    const gst = Number(target.gst) || 0;
    const subtotal = qty * unitPrice;
    const tax = subtotal * (gst / 100);
    target.tax_amount = Math.round(tax * 1000) / 1000;
    target.amount = Math.round((subtotal + tax) * 1000) / 1000;
};

const applyTaxOverride = (target) => {
    const qty = Number(target.qty) || 0;
    const unitPrice = Number(target.unit_price) || 0;
    const subtotal = qty * unitPrice;
    const tax = Math.max(0, Number(target.tax_amount) || 0);
    target.tax_amount = Math.round(tax * 1000) / 1000;
    target.amount = Math.round((subtotal + target.tax_amount) * 1000) / 1000;
};

const openAddLine = () => {
    lineForm.reset();
    lineForm.clearErrors();
    lineForm.gst = '0';
    if (props.availablePrDetails[0]) {
        lineForm.itemcode = props.availablePrDetails[0].itemcode;
        onAddLineItemChange();
    }
    openModal('add-line');
};

const submitAddLine = () => {
    lineForm.post(route('app.purchase-orders.details.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const openEditLine = (detail) => {
    editingLineId.value = detail.id;
    editLineForm.clearErrors();
    editLineForm.unit_price = detail.unit_price;
    editLineForm.tax_amount = detail.tax_amount;
    editLineForm.amount = detail.amount;
    editLineForm.gst = Number(detail.tax_amount) > 0 ? '8' : '0';
    editLineForm.qty = detail.qty;
    openModal('edit-line');
};

const submitEditLine = () => {
    editLineForm.put(route('app.purchase-orders.details.update', [props.order.id, editingLineId.value]), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const deleteLine = (detail) => {
    if (! confirm(`Remove line ${detail.desc}?`)) return;
    router.delete(route('app.purchase-orders.details.destroy', [props.order.id, detail.id]), {
        preserveScroll: true,
    });
};

const editingDetail = computed(() =>
    props.order.details.find((d) => d.id === editingLineId.value),
);

watch(() => editLineForm.unit_price, () => {
    if (modal.value === 'edit-line' && editingDetail.value) {
        editLineForm.qty = editingDetail.value.qty;
        recalculateLine(editLineForm);
    }
});

watch(() => editLineForm.gst, () => {
    if (modal.value === 'edit-line' && editingDetail.value) {
        editLineForm.qty = editingDetail.value.qty;
        recalculateLine(editLineForm);
    }
});
</script>

<template>
    <AppLayout :title="order.po_no">
        <template #header>{{ order.po_no }}</template>

        <div class="space-y-5">
            <!-- Top bar -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span :class="badgeClass">{{ order.status_label }}</span>
                        <span class="ui-badge-neutral">{{ order.payment_method_label }}</span>
                        <span class="text-sm text-slate-500">
                            {{ lineCount }} item{{ lineCount === 1 ? '' : 's' }} · MVR {{ formatMoney(order.total_amount) }}
                        </span>
                    </div>
                    <p class="max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ order.purchase_request?.purpose || 'No linked purchase request purpose.' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link :href="route('app.purchase-orders.index')" class="ui-btn-ghost">Back</Link>
                    <Link
                        v-if="order.purchase_request?.id"
                        :href="route('app.purchase-requests.show', order.purchase_request.id)"
                        class="ui-btn-secondary"
                    >
                        View PR
                    </Link>
                    <Link
                        v-if="can.update"
                        :href="route('app.purchase-orders.edit', order.id)"
                        class="ui-btn-secondary"
                    >
                        Edit
                    </Link>
                    <a
                        v-if="order.supporting_document_url"
                        :href="order.supporting_document_url"
                        target="_blank"
                        class="ui-btn-secondary"
                    >
                        Receipt
                    </a>
                    <a
                        v-if="order.advance_form_pdf_url"
                        :href="order.advance_form_pdf_url"
                        target="_blank"
                        class="ui-btn-secondary"
                    >
                        Advance PDF
                    </a>
                    <button
                        v-if="can.delete"
                        type="button"
                        class="ui-btn-danger"
                        @click="deletePo"
                    >
                        Delete
                    </button>
                </div>
            </div>

            <!-- Workflow actions -->
            <div
                v-if="primaryActions.length"
                class="flex flex-col gap-3 rounded-xl border border-slate-200/90 bg-surface p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5"
            >
                <p class="text-sm text-slate-600 dark:text-slate-400">
                    Actions for the current step
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="action in primaryActions"
                        :key="action.key"
                        type="button"
                        :class="actionClass(action.tone)"
                        @click="action.run"
                    >
                        {{ action.label }}
                    </button>
                </div>
            </div>

            <!-- Order details -->
            <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50/80 dark:hover:bg-surface-elevated/40 sm:px-5"
                    :aria-expanded="detailsOpen"
                    @click="detailsOpen = !detailsOpen"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Order details</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            {{ detailsOpen ? 'Hide order information' : detailsSummary }}
                        </p>
                    </div>
                    <span
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition"
                        :class="detailsOpen ? 'rotate-180 bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' : ''"
                        aria-hidden="true"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </span>
                </button>

                <div v-show="detailsOpen" class="border-t border-slate-100 dark:border-slate-800">
                    <dl class="grid gap-0 sm:grid-cols-2">
                        <div
                            v-for="field in detailFields"
                            :key="field.label"
                            class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5"
                        >
                            <dt class="text-xs text-slate-500">{{ field.label }}</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ field.value }}</dd>
                        </div>
                        <div
                            v-if="order.purchase_request?.purpose"
                            class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:col-span-2 sm:px-5"
                        >
                            <dt class="text-xs text-slate-500">PR purpose</dt>
                            <dd class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-800 dark:text-slate-200">
                                {{ order.purchase_request.purpose }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- Advance form -->
            <section
                v-if="order.is_advance_form_required && order.payment_method === 'purchase_order'"
                class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50/80 dark:hover:bg-surface-elevated/40 sm:px-5"
                    :aria-expanded="advanceOpen"
                    @click="advanceOpen = !advanceOpen"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Advance form</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            {{ advanceOpen ? 'Hide advance form' : advanceSummary }}
                        </p>
                    </div>
                    <span
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition"
                        :class="advanceOpen ? 'rotate-180 bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' : ''"
                        aria-hidden="true"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </span>
                </button>

                <div v-show="advanceOpen" class="border-t border-slate-100 dark:border-slate-800">
                    <dl v-if="order.advance_form" class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">Request number</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ order.advance_form.request_number }}</dd>
                        </div>
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">Status</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ order.advance_form.status_label }}</dd>
                        </div>
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">Quotation no</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ order.advance_form.qoation_no || '—' }}</dd>
                        </div>
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">Expected delivery</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ order.advance_form.expected_delivery }} days</dd>
                        </div>
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">Advance %</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ order.advance_form.advance_percentage }}%</dd>
                        </div>
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">Advance amount</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">MVR {{ formatMoney(order.advance_form.advance_amount) }}</dd>
                        </div>
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">Balance</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">MVR {{ formatMoney(order.advance_form.balance_amount) }}</dd>
                        </div>
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">Generated by</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ order.advance_form.generated_by || '—' }}</dd>
                        </div>
                        <div v-if="order.advance_form.hod_approved_by" class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">HOD approved by</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ order.advance_form.hod_approved_by }}</dd>
                        </div>
                        <div v-if="order.advance_form.md_dmd_approved_by" class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5">
                            <dt class="text-xs text-slate-500">MD/DMD approved by</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ order.advance_form.md_dmd_approved_by }}</dd>
                        </div>
                    </dl>
                    <p v-else class="px-4 py-8 text-center text-sm text-slate-500 sm:px-5">
                        No advance form generated yet.
                    </p>
                </div>
            </section>

            <!-- Line items -->
            <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Line items</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ lineCount }} item{{ lineCount === 1 ? '' : 's' }} · Total MVR {{ formatMoney(order.total_amount) }}
                        </p>
                    </div>
                    <button
                        v-if="can.manage_details && availablePrDetails.length"
                        type="button"
                        class="ui-btn-primary"
                        @click="openAddLine"
                    >
                        Add line
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Item</th>
                                <th>Code</th>
                                <th>Budget</th>
                                <th>U/M</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Unit price</th>
                                <th class="text-right">Tax</th>
                                <th class="text-right">Amount</th>
                                <th v-if="can.manage_details" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(detail, index) in order.details" :key="detail.id">
                                <td class="tabular-nums text-slate-400">{{ index + 1 }}</td>
                                <td class="font-medium text-slate-900 dark:text-slate-100">{{ detail.desc }}</td>
                                <td class="text-slate-600 dark:text-slate-300">{{ detail.itemcode }}</td>
                                <td class="text-slate-600 dark:text-slate-300">{{ detail.budget_code || '—' }}</td>
                                <td class="text-slate-600 dark:text-slate-300">{{ detail.unit_measure }}</td>
                                <td class="text-right tabular-nums text-slate-700 dark:text-slate-200">{{ detail.qty }}</td>
                                <td class="text-right tabular-nums text-slate-700 dark:text-slate-200">{{ formatMoney(detail.unit_price) }}</td>
                                <td class="text-right tabular-nums text-slate-700 dark:text-slate-200">{{ formatMoney(detail.tax_amount) }}</td>
                                <td class="text-right tabular-nums font-medium text-slate-900 dark:text-white">{{ formatMoney(detail.amount) }}</td>
                                <td v-if="can.manage_details" class="space-x-3 text-right">
                                    <button type="button" class="font-medium text-brand-600" @click="openEditLine(detail)">Edit</button>
                                    <button type="button" class="font-medium text-rose-600" @click="deleteLine(detail)">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!order.details.length">
                                <td :colspan="can.manage_details ? 10 : 9" class="px-4 py-10 text-center text-slate-500">
                                    No line items yet.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="order.details.length">
                            <tr class="border-t border-slate-200 bg-slate-50/70 dark:border-slate-800 dark:bg-surface-elevated/40">
                                <td :colspan="can.manage_details ? 8 : 7" class="px-5 py-3.5 text-sm font-medium text-slate-500">
                                    Order total
                                </td>
                                <td class="px-5 py-3.5 text-right text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
                                    MVR {{ formatMoney(order.total_amount) }}
                                </td>
                                <td v-if="can.manage_details" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <!-- Close (GRN) modal -->
        <Teleport to="body">
            <div v-if="modal === 'close'" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="submitClose">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Close purchase order</h3>
                        <p class="mt-1 text-sm text-slate-500">Enter the GRN number. This cannot be undone.</p>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <label class="ui-label">GRN number</label>
                            <input v-model="closeForm.grn_number" type="text" required class="ui-input" />
                            <p v-if="closeForm.errors.grn_number" class="mt-1 text-sm text-rose-600">{{ closeForm.errors.grn_number }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-danger" :disabled="closeForm.processing">Close PO</button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Advance form modal -->
        <Teleport to="body">
            <div
                v-if="modal === 'generate-advance' || modal === 'regenerate-advance'"
                class="fixed inset-0 z-[300] flex items-center justify-center p-4"
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="submitAdvanceGenerate">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ modal === 'regenerate-advance' ? 'Regenerate advance form' : 'Generate advance form' }}
                        </h3>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <label class="ui-label">Quotation no</label>
                            <input v-model="advanceForm.qoation_no" type="text" required class="ui-input" />
                            <p v-if="advanceForm.errors.qoation_no" class="mt-1 text-sm text-rose-600">{{ advanceForm.errors.qoation_no }}</p>
                        </div>
                        <div>
                            <label class="ui-label">Expected delivery (days)</label>
                            <input v-model="advanceForm.expected_delivery" type="number" min="1" required class="ui-input" />
                            <p v-if="advanceForm.errors.expected_delivery" class="mt-1 text-sm text-rose-600">{{ advanceForm.errors.expected_delivery }}</p>
                        </div>
                        <div>
                            <label class="ui-label">Advance amount %</label>
                            <input v-model="advanceForm.advance_amount" type="number" min="0" max="100" step="0.01" required class="ui-input" />
                            <p v-if="advanceForm.errors.advance_amount" class="mt-1 text-sm text-rose-600">{{ advanceForm.errors.advance_amount }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="advanceForm.processing">
                            {{ modal === 'regenerate-advance' ? 'Regenerate' : 'Generate' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Receipt upload modal -->
        <Teleport to="body">
            <div v-if="modal === 'receipt'" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="submitReceipt">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Upload receipt</h3>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <input type="file" required class="block w-full text-sm" @change="onReceiptChange" />
                        <p v-if="receiptForm.errors.supporting_document" class="text-sm text-rose-600">{{ receiptForm.errors.supporting_document }}</p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="receiptForm.processing">Upload</button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Add line modal -->
        <Teleport to="body">
            <div v-if="modal === 'add-line'" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="submitAddLine">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Add line</h3>
                    </div>
                    <div class="grid gap-4 px-6 py-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="ui-label">Item</label>
                            <select v-model="lineForm.itemcode" required class="ui-select" @change="onAddLineItemChange">
                                <option v-for="d in availablePrDetails" :key="d.itemcode" :value="d.itemcode">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">GST %</label>
                            <select v-model="lineForm.gst" class="ui-select" @change="recalculateLine(lineForm)">
                                <option value="0">0%</option>
                                <option value="8">8%</option>
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Unit price</label>
                            <input v-model.number="lineForm.unit_price" type="number" step="0.001" min="0" required class="ui-input" @input="recalculateLine(lineForm)" />
                        </div>
                        <div>
                            <label class="ui-label">Tax amount</label>
                            <input
                                v-model.number="lineForm.tax_amount"
                                type="number"
                                step="0.001"
                                min="0"
                                required
                                class="ui-input"
                                @input="applyTaxOverride(lineForm)"
                            />
                            <p class="mt-1 text-[11px] text-slate-400">Auto from GST%; you can override.</p>
                        </div>
                        <div>
                            <label class="ui-label">Amount</label>
                            <input :value="formatMoney(lineForm.amount)" type="text" disabled class="ui-input bg-slate-50" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="lineForm.processing">Add</button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Edit line modal -->
        <Teleport to="body">
            <div v-if="modal === 'edit-line'" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="submitEditLine">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Edit line</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ editingDetail?.desc }}</p>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <label class="ui-label">GST %</label>
                            <select v-model="editLineForm.gst" class="ui-select">
                                <option value="0">0%</option>
                                <option value="8">8%</option>
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Unit price</label>
                            <input v-model.number="editLineForm.unit_price" type="number" step="0.001" min="0" required class="ui-input" />
                        </div>
                        <div>
                            <label class="ui-label">Tax amount</label>
                            <input
                                v-model.number="editLineForm.tax_amount"
                                type="number"
                                step="0.001"
                                min="0"
                                required
                                class="ui-input"
                                @input="applyTaxOverride(editLineForm)"
                            />
                            <p class="mt-1 text-[11px] text-slate-400">Auto from GST%; you can override.</p>
                        </div>
                        <div>
                            <label class="ui-label">Amount</label>
                            <p class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">MVR {{ formatMoney(editLineForm.amount) }}</p>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="editLineForm.processing">Save</button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
