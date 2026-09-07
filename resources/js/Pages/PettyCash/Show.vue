<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateInput from '../../Components/UiDateInput.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    reimbursment: { type: Object, required: true },
    options: { type: Object, required: true },
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
const editingDetailId = ref(null);

const detailForm = useForm({
    date: new Date().toISOString().slice(0, 10),
    is_from_pr: true,
    Vendor_id: '',
    bill_no: '',
    sub_budget_id: '',
    amount: '',
    po_id: '',
    item_id: '',
    details: '',
});

const pvForm = useForm({ pv_numbers: [''] });

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
    (props.reimbursment.details || [])
        .filter((detail) => detail.po_id && detail.item_id && detail.id !== editingDetailId.value)
        .map((detail) => itemKey(detail.po_id, detail.item_id)),
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

const poOptions = computed(() => {
    const availablePoIds = new Set(availableItems.value.map((item) => Number(item.po_id)));
    if (detailForm.po_id) {
        availablePoIds.add(Number(detailForm.po_id));
    }

    return (props.options.purchaseOrders || [])
        .filter((po) => availablePoIds.has(Number(po.id)))
        .map((po) => ({
            value: po.id,
            label: po.label,
        }));
});

const itemOptions = computed(() => {
    if (! detailForm.po_id) return [];

    const currentKey = detailForm.item_id
        ? itemKey(detailForm.po_id, detailForm.item_id)
        : null;

    return allPoItems.value
        .filter((item) => Number(item.po_id) === Number(detailForm.po_id))
        .filter((item) => item.key === currentKey || ! usedItemKeys.value.includes(item.key))
        .map((item) => ({
            value: item.item_id,
            label: item.name,
        }));
});

const budgetHint = (subBudgetId) => {
    const budget = (props.options.budgets || []).find((row) => Number(row.id) === Number(subBudgetId));
    if (! budget) {
        return 'Select a budget account to view your department allocation.';
    }
    if (budget.allocated_amount == null) {
        return 'No allocation found for your department.';
    }
    return `Allocated budget: MVR ${formatMoney(budget.allocated_amount)}`;
};

const statusTone = computed(() => {
    const map = {
        draft: 'neutral',
        submited: 'warn',
        dep_approved: 'warn',
        dep_reject: 'danger',
        fin_approved: 'warn',
        fin_reject: 'danger',
        rembursed: 'success',
    };
    return map[props.reimbursment.status] || 'neutral';
});

const badgeClass = computed(() => ({
    neutral: 'ui-badge-neutral',
    warn: 'ui-badge-warn',
    success: 'ui-badge-success',
    danger: 'ui-badge-danger',
    brand: 'ui-badge-brand',
}[statusTone.value]));

const lineCount = computed(() => props.reimbursment.details?.length || 0);

const selectedPoReceipt = computed(() => {
    if (! detailForm.po_id) return null;
    return poById(detailForm.po_id);
});

const detailFields = computed(() => [
    { label: 'Form number', value: props.reimbursment.form_no },
    { label: 'Date', value: formatDate(props.reimbursment.date) },
    { label: 'Requester', value: props.reimbursment.requested_by || '—' },
    { label: 'Department', value: props.reimbursment.department || '—' },
    { label: 'Status', value: props.reimbursment.status_label },
    { label: 'PV numbers', value: props.reimbursment.pv_numbers || '—' },
    { label: 'Verified by', value: props.reimbursment.verified_by || '—' },
    { label: 'Approved by', value: props.reimbursment.approved_by || '—' },
    { label: 'Total', value: `MVR ${formatMoney(props.reimbursment.total_amount)}` },
]);

const detailsSummary = computed(() => {
    const parts = [
        props.reimbursment.requested_by,
        props.reimbursment.department,
        formatDate(props.reimbursment.date),
    ].filter(Boolean);
    return parts.join(' · ');
});

const primaryActions = computed(() => {
    const list = [];
    if (props.can.submit) {
        list.push({ key: 'submit', label: 'Submit', tone: 'primary', run: () => postAction('app.petty-cash.submit') });
    }
    if (props.can.dep_approve) {
        list.push({ key: 'dep-approve', label: 'Department approve', tone: 'success', run: () => postAction('app.petty-cash.approve-department') });
    }
    if (props.can.dep_reject) {
        list.push({
            key: 'dep-reject',
            label: 'Department reject',
            tone: 'danger',
            run: () => postAction('app.petty-cash.reject-department', 'Reject and return to draft?'),
        });
    }
    if (props.can.add_pv) {
        list.push({ key: 'add-pv', label: 'Add PV numbers', tone: 'primary', run: () => openModal('pv') });
    }
    if (props.can.fin_approve) {
        list.push({
            key: 'fin-approve',
            label: 'Finance approve',
            tone: 'success',
            run: () => postAction('app.petty-cash.approve-finance', 'Approve and deduct department budgets?'),
        });
    }
    if (props.can.fin_reject) {
        list.push({
            key: 'fin-reject',
            label: 'Finance reject',
            tone: 'danger',
            run: () => postAction('app.petty-cash.reject-finance', 'Reject, clear PV numbers, and return to draft?'),
        });
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

const postAction = (routeName, confirmMessage) => {
    if (confirmMessage && ! confirm(confirmMessage)) return;
    router.post(route(routeName, props.reimbursment.id), {}, { preserveScroll: true });
};

const applyPo = (poId) => {
    detailForm.po_id = poId;
    detailForm.item_id = '';
    detailForm.details = '';
    detailForm.amount = '';
    const po = poById(poId);
    if (po?.vendor_id) {
        detailForm.Vendor_id = po.vendor_id;
    }
};

const applyItem = (itemId) => {
    detailForm.item_id = itemId;
    const item = allPoItems.value.find((row) =>
        Number(row.po_id) === Number(detailForm.po_id) && Number(row.item_id) === Number(itemId),
    );
    if (! item) return;
    detailForm.details = item.name;
    detailForm.amount = item.amount;
    if (item.budget_account_id) {
        detailForm.sub_budget_id = item.budget_account_id;
    }
    if (item.vendor_id) {
        detailForm.Vendor_id = item.vendor_id;
    }
};

const resetDetailForm = () => {
    detailForm.clearErrors();
    detailForm.reset();
    detailForm.date = new Date().toISOString().slice(0, 10);
    detailForm.is_from_pr = true;
};

const openCreateDetail = () => {
    editingDetailId.value = null;
    resetDetailForm();
    const first = availableItems.value[0];
    if (first) {
        applyPo(first.po_id);
    } else {
        detailForm.is_from_pr = false;
    }
    openModal('line');
};

const openEditDetail = (detail) => {
    editingDetailId.value = detail.id;
    detailForm.clearErrors();
    detailForm.date = detail.date;
    detailForm.is_from_pr = !!detail.is_from_pr;
    detailForm.Vendor_id = detail.Vendor_id;
    detailForm.bill_no = detail.bill_no;
    detailForm.sub_budget_id = detail.sub_budget_id;
    detailForm.amount = detail.amount;
    detailForm.po_id = detail.po_id ?? '';
    detailForm.item_id = detail.item_id ?? '';
    detailForm.details = detail.details ?? '';
    openModal('line');
};

const setFromPr = (fromPr) => {
    detailForm.is_from_pr = fromPr;
    detailForm.po_id = '';
    detailForm.item_id = '';
    detailForm.details = '';
    detailForm.amount = '';
    if (fromPr && availableItems.value[0]) {
        applyPo(availableItems.value[0].po_id);
    }
};

const saveDetail = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            editingDetailId.value = null;
        },
    };

    if (editingDetailId.value) {
        detailForm.put(
            route('app.petty-cash.details.update', [props.reimbursment.id, editingDetailId.value]),
            options,
        );
        return;
    }

    detailForm.post(route('app.petty-cash.details.store', props.reimbursment.id), options);
};

const deleteDetail = (detail) => {
    if (! confirm('Delete this line item?')) return;
    router.delete(route('app.petty-cash.details.destroy', [props.reimbursment.id, detail.id]), {
        preserveScroll: true,
    });
};

const addPvField = () => pvForm.pv_numbers.push('');
const removePvField = (index) => {
    if (pvForm.pv_numbers.length <= 1) return;
    pvForm.pv_numbers.splice(index, 1);
};

const submitPv = () => {
    pvForm.post(route('app.petty-cash.add-pv', props.reimbursment.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            pvForm.reset();
            pvForm.pv_numbers = [''];
        },
    });
};

const destroyRecord = () => {
    if (! confirm('Delete this reimbursement?')) return;
    router.delete(route('app.petty-cash.destroy', props.reimbursment.id));
};
</script>

<template>
    <AppLayout :title="reimbursment.form_no">
        <template #header>{{ reimbursment.form_no }}</template>

        <div class="space-y-5">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span :class="badgeClass">{{ reimbursment.status_label }}</span>
                        <span class="text-sm text-slate-500">
                            {{ lineCount }} item{{ lineCount === 1 ? '' : 's' }} · MVR {{ formatMoney(reimbursment.total_amount) }}
                        </span>
                    </div>
                    <p class="max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ reimbursment.requested_by || 'Unknown requester' }}
                        <template v-if="reimbursment.department"> · {{ reimbursment.department }}</template>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link :href="route('app.petty-cash.index')" class="ui-btn-ghost">Back</Link>
                    <Link
                        v-if="can.update"
                        :href="route('app.petty-cash.edit', reimbursment.id)"
                        class="ui-btn-secondary"
                    >
                        Edit
                    </Link>
                    <a
                        v-if="reimbursment.supporting_documents_url"
                        :href="reimbursment.supporting_documents_url"
                        target="_blank"
                        class="ui-btn-secondary"
                    >
                        Documents
                    </a>
                    <a
                        v-if="can.download_pdf && reimbursment.pdf_url"
                        :href="reimbursment.pdf_url"
                        target="_blank"
                        class="ui-btn-secondary"
                    >
                        Download PDF
                    </a>
                    <button
                        v-if="can.delete"
                        type="button"
                        class="ui-btn-danger"
                        @click="destroyRecord"
                    >
                        Delete
                    </button>
                </div>
            </div>

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

            <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50/80 dark:hover:bg-surface-elevated/40 sm:px-5"
                    :aria-expanded="detailsOpen"
                    @click="detailsOpen = !detailsOpen"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Reimbursement details</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            {{ detailsOpen ? 'Hide reimbursement information' : detailsSummary }}
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
                    <dl class="grid gap-0 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="field in detailFields"
                            :key="field.label"
                            class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5"
                        >
                            <dt class="text-xs text-slate-500">{{ field.label }}</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ field.value }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Line items</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ lineCount }} item{{ lineCount === 1 ? '' : 's' }} · Total MVR {{ formatMoney(reimbursment.total_amount) }}
                            <template v-if="can.manage_details">
                                · {{ availableItems.length }} unused PO line{{ availableItems.length === 1 ? '' : 's' }}
                            </template>
                        </p>
                    </div>
                    <button
                        v-if="can.manage_details"
                        type="button"
                        class="ui-btn-primary"
                        @click="openCreateDetail"
                    >
                        Add line
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Date</th>
                                <th>Vendor</th>
                                <th>Details</th>
                                <th>Bill no</th>
                                <th>Budget</th>
                                <th>PO / PR</th>
                                <th class="text-right">Amount</th>
                                <th v-if="can.manage_details" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(detail, index) in reimbursment.details" :key="detail.id">
                                <td class="tabular-nums text-slate-400">{{ index + 1 }}</td>
                                <td class="text-slate-700 dark:text-slate-200">{{ formatDate(detail.date) }}</td>
                                <td class="font-medium text-slate-900 dark:text-slate-100">{{ detail.vendor_name }}</td>
                                <td class="text-slate-600 dark:text-slate-300">{{ detail.description }}</td>
                                <td class="text-slate-600 dark:text-slate-300">{{ detail.bill_no }}</td>
                                <td class="text-slate-600 dark:text-slate-300">{{ detail.sub_budget_label || detail.sub_budget_code || '—' }}</td>
                                <td class="text-slate-600 dark:text-slate-300">
                                    <div>{{ detail.po_label || '—' }}</div>
                                    <a
                                        v-if="detail.receipt_url"
                                        :href="detail.receipt_url"
                                        target="_blank"
                                        class="mt-0.5 inline-block text-xs font-medium text-brand-700 dark:text-brand-300"
                                    >
                                        View receipt
                                    </a>
                                </td>
                                <td class="text-right tabular-nums font-medium text-slate-900 dark:text-white">{{ formatMoney(detail.amount) }}</td>
                                <td v-if="can.manage_details" class="space-x-3 text-right">
                                    <button type="button" class="font-medium text-brand-600" @click="openEditDetail(detail)">Edit</button>
                                    <button type="button" class="font-medium text-rose-600" @click="deleteDetail(detail)">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!reimbursment.details.length">
                                <td :colspan="can.manage_details ? 9 : 8" class="px-4 py-10 text-center text-slate-500">
                                    No line items yet.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="reimbursment.details.length">
                            <tr class="border-t border-slate-200 bg-slate-50/70 dark:border-slate-800 dark:bg-surface-elevated/40">
                                <td colspan="7" class="px-4 py-3 text-right text-sm font-medium text-slate-500">Total</td>
                                <td class="px-4 py-3 text-right text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
                                    MVR {{ formatMoney(reimbursment.total_amount) }}
                                </td>
                                <td v-if="can.manage_details" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <Teleport to="body">
            <div v-if="modal === 'line'" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="saveDetail">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ editingDetailId ? 'Edit line' : 'Add line' }}
                        </h3>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div class="inline-flex rounded-xl border border-slate-200 bg-slate-50 p-1 dark:border-slate-700 dark:bg-surface-muted/40">
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                :class="detailForm.is_from_pr
                                    ? 'bg-white text-brand-700 shadow-sm dark:bg-surface-elevated dark:text-brand-300'
                                    : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                                :disabled="!availableItems.length && !detailForm.po_id"
                                @click="setFromPr(true)"
                            >
                                From PR / PO
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                :class="!detailForm.is_from_pr
                                    ? 'bg-white text-brand-700 shadow-sm dark:bg-surface-elevated dark:text-brand-300'
                                    : 'text-slate-500 hover:text-slate-800 dark:text-slate-400'"
                                @click="setFromPr(false)"
                            >
                                Manual
                            </button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <UiDateInput
                                id="pc-line-date"
                                v-model="detailForm.date"
                                label="Date"
                                required
                                :error="detailForm.errors.date"
                            />
                            <div v-if="detailForm.is_from_pr">
                                <UiSearchableSelect
                                    id="pc-line-po"
                                    v-model="detailForm.po_id"
                                    label="Purchase order"
                                    :options="poOptions"
                                    placeholder="Select PO…"
                                    required
                                    :error="detailForm.errors.po_id"
                                    @update:model-value="applyPo"
                                />
                                <a
                                    v-if="selectedPoReceipt?.receipt_url"
                                    :href="selectedPoReceipt.receipt_url"
                                    target="_blank"
                                    class="mt-1.5 inline-block text-xs font-medium text-brand-700 dark:text-brand-300"
                                >
                                    View attached receipt
                                </a>
                            </div>
                            <UiSearchableSelect
                                v-if="detailForm.is_from_pr"
                                id="pc-line-item"
                                v-model="detailForm.item_id"
                                label="Item from PO"
                                :options="itemOptions"
                                placeholder="Select item…"
                                required
                                :disabled="!detailForm.po_id"
                                :error="detailForm.errors.item_id"
                                @update:model-value="applyItem"
                            />
                            <div v-else>
                                <label class="ui-label">Details</label>
                                <input v-model="detailForm.details" type="text" required class="ui-input" />
                                <p v-if="detailForm.errors.details" class="mt-1 text-xs text-red-600">{{ detailForm.errors.details }}</p>
                            </div>
                            <UiSearchableSelect
                                id="pc-line-vendor"
                                v-model="detailForm.Vendor_id"
                                label="Vendor"
                                :options="vendorOptions"
                                placeholder="Search vendors…"
                                required
                                :error="detailForm.errors.Vendor_id"
                            />
                            <div>
                                <label class="ui-label">Bill no</label>
                                <input v-model="detailForm.bill_no" type="text" required class="ui-input" />
                                <p v-if="detailForm.errors.bill_no" class="mt-1 text-xs text-red-600">{{ detailForm.errors.bill_no }}</p>
                            </div>
                            <div>
                                <UiSearchableSelect
                                    id="pc-line-budget"
                                    v-model="detailForm.sub_budget_id"
                                    label="Budget account"
                                    :options="budgetOptions"
                                    placeholder="Search budgets…"
                                    required
                                    :error="detailForm.errors.sub_budget_id"
                                />
                                <p class="mt-1 text-xs text-slate-500">{{ budgetHint(detailForm.sub_budget_id) }}</p>
                            </div>
                            <div>
                                <label class="ui-label">Amount (MVR)</label>
                                <input
                                    v-model="detailForm.amount"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    required
                                    :readonly="detailForm.is_from_pr"
                                    class="ui-input read-only:bg-slate-50"
                                />
                                <p v-if="detailForm.errors.amount" class="mt-1 text-xs text-red-600">{{ detailForm.errors.amount }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="detailForm.processing">
                            {{ detailForm.processing ? 'Saving…' : (editingDetailId ? 'Save line' : 'Add line') }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Teleport to="body">
            <div v-if="modal === 'pv'" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="submitPv">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">PV numbers</h3>
                        <p class="mt-1 text-sm text-slate-500">Add one or more payment voucher numbers.</p>
                    </div>
                    <div class="space-y-3 px-6 py-5">
                        <div v-for="(_, index) in pvForm.pv_numbers" :key="index" class="flex gap-2">
                            <input
                                v-model="pvForm.pv_numbers[index]"
                                type="text"
                                required
                                class="ui-input"
                                placeholder="PV number"
                            />
                            <button
                                v-if="pvForm.pv_numbers.length > 1"
                                type="button"
                                class="shrink-0 text-sm font-medium text-rose-600"
                                @click="removePvField(index)"
                            >
                                Remove
                            </button>
                        </div>
                        <p v-if="pvForm.errors.pv_numbers" class="text-sm text-rose-600">{{ pvForm.errors.pv_numbers }}</p>
                        <button type="button" class="text-sm font-medium text-brand-700" @click="addPvField">
                            Add another
                        </button>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="pvForm.processing">Save PV</button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
