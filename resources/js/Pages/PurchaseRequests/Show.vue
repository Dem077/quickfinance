<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    record: { type: Object, required: true },
    budgetSummary: { type: Array, default: () => [] },
    openPurchaseOrders: { type: Array, required: true },
    purchaseOrders: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    actions: { type: Object, required: true },
    pdfUrl: { type: String, required: true },
    documentUrl: { type: String, default: null },
});

const remarkForm = useForm({ cancel_remark: '' });
const showRemark = ref(null);
const closeForm = useForm({
    purchase_order_grns: props.openPurchaseOrders.map((po) => ({
        po_id: po.po_id,
        grn_number: po.grn_number || '',
    })),
});
const showClose = ref(false);
const showPurchaseOrders = ref(false);
const lineForm = useForm({
    item_id: '',
    unit: 'Pcs',
    budget_account_id: '',
    amount: '',
    est_cost: '',
});
const showLine = ref(false);
const editingLineId = ref(null);
const detailsOpen = ref(false);
const budgetOpen = ref(false);

const statusTone = computed(() => {
    const map = {
        draft: 'neutral',
        submitted: 'warn',
        hod_approved: 'success',
        hod_rejected: 'danger',
        document_uploaded: 'brand',
        md_dmd_approved: 'success',
        md_dmd_rejected: 'danger',
        canceled: 'danger',
        approved: 'success',
        rejected: 'danger',
        closed: 'success',
    };
    return map[props.record.status] || 'neutral';
});

const badgeClass = computed(() => ({
    neutral: 'ui-badge-neutral',
    warn: 'ui-badge-warn',
    success: 'ui-badge-success',
    danger: 'ui-badge-danger',
    brand: 'ui-badge-brand',
}[statusTone.value]));

const lineCount = computed(() => props.record.details?.length || 0);
const totalEstCost = computed(() =>
    (props.record.details || []).reduce((sum, line) => sum + Number(line.est_cost || 0), 0),
);

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

const formatMoney = (value) =>
    Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const primaryActions = computed(() => {
    const list = [];
    if (props.actions.submit) list.push({ key: 'submit', label: 'Submit for approval', tone: 'primary', run: () => postAction('submit') });
    if (props.actions.hodApprove) list.push({ key: 'hod-approve', label: 'HOD approve', tone: 'success', run: () => postAction('hod-approve') });
    if (props.actions.hodReject) list.push({ key: 'hod-reject', label: 'HOD reject', tone: 'danger', run: () => postAction('hod-reject') });
    if (props.actions.financeApprove) list.push({ key: 'finance-approve', label: 'Finance approve', tone: 'success', run: () => postAction('finance-approve') });
    if (props.actions.financeReject) list.push({ key: 'finance-reject', label: 'Finance reject', tone: 'danger', run: () => { showRemark.value = 'finance-reject'; } });
    if (props.actions.sendBack) list.push({ key: 'send-back', label: 'Send back to draft', tone: 'warn', run: () => postAction('send-back') });
    if (props.actions.cancel) list.push({ key: 'cancel', label: 'Cancel PR', tone: 'danger', run: () => { showRemark.value = 'cancel'; } });
    if (props.actions.mdDmdApprove) list.push({ key: 'md-approve', label: 'MD/DMD approve', tone: 'success', run: () => postAction('md-dmd-approve') });
    if (props.actions.mdDmdReject) list.push({ key: 'md-reject', label: 'MD/DMD reject', tone: 'danger', run: () => postAction('md-dmd-reject') });
    if (props.actions.close) list.push({ key: 'close', label: 'Close PR', tone: 'primary', run: () => { showClose.value = true; } });
    return list;
});

const detailFields = computed(() => {
    const fields = [
        { label: 'PR number', value: props.record.pr_no },
        { label: 'Date', value: formatDate(props.record.date) },
        { label: 'Requester', value: props.record.user || '—' },
        { label: 'Department', value: props.record.department || '—' },
        { label: 'Locations', value: props.record.locations?.length ? props.record.locations.join(', ') : '—' },
        { label: 'Status', value: props.record.status_label },
    ];
    if (props.record.project) {
        fields.splice(4, 0, { label: 'Project', value: props.record.project });
    }
    if (props.record.hod) fields.push({ label: 'HOD approved by', value: props.record.hod });
    if (props.record.finance) fields.push({ label: 'Finance approved by', value: props.record.finance });
    if (props.record.md_dmd) fields.push({ label: 'MD/DMD approved by', value: props.record.md_dmd });
    return fields;
});

const detailsSummary = computed(() => {
    const parts = [
        props.record.user,
        props.record.department,
        props.record.project,
        formatDate(props.record.date),
    ].filter(Boolean);
    return parts.join(' · ');
});

const budgetSummaryCollapsed = computed(() => {
    const rows = props.budgetSummary || [];
    if (! rows.length) return '';
    const totalAvailable = rows.reduce((sum, row) => sum + Number(row.available || 0), 0);
    const count = rows.length;
    return `${count} account${count === 1 ? '' : 's'} · Available MVR ${formatMoney(totalAvailable)}`;
});

const actionClass = (tone) => ({
    primary: 'ui-btn-primary',
    success: 'inline-flex items-center justify-center rounded-xl border border-transparent bg-emerald-600 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-500',
    danger: 'ui-btn-danger',
    warn: 'inline-flex items-center justify-center rounded-xl border border-transparent bg-amber-500 px-4 py-2 text-sm font-medium text-white shadow-sm transition hover:bg-amber-400',
}[tone] || 'ui-btn-secondary');

const postAction = (name, data = {}) => {
    router.post(route(`app.purchase-requests.${name}`, props.record.id), data, { preserveScroll: true });
};

const submitRemark = () => {
    remarkForm.post(route(`app.purchase-requests.${showRemark.value}`, props.record.id), {
        preserveScroll: true,
        onSuccess: () => {
            showRemark.value = null;
            remarkForm.reset();
        },
    });
};

const submitClose = () => {
    closeForm.post(route('app.purchase-requests.close', props.record.id), {
        preserveScroll: true,
        onSuccess: () => { showClose.value = false; },
    });
};

const openEditLine = (line) => {
    editingLineId.value = line.id;
    lineForm.item_id = line.item_id;
    lineForm.unit = line.unit;
    lineForm.budget_account_id = line.budget_account_id;
    lineForm.amount = line.amount;
    lineForm.est_cost = line.est_cost;
    showLine.value = true;
};

const openAddLine = () => {
    editingLineId.value = null;
    lineForm.reset();
    lineForm.unit = 'Pcs';
    showLine.value = true;
};

const saveLine = () => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showLine.value = false;
            editingLineId.value = null;
        },
    };
    if (editingLineId.value) {
        lineForm.put(route('app.purchase-requests.details.update', [props.record.id, editingLineId.value]), opts);
        return;
    }
    lineForm.post(route('app.purchase-requests.details.store', props.record.id), opts);
};

const deleteLine = (line) => {
    if (! confirm('Delete this line?')) return;
    router.delete(route('app.purchase-requests.details.destroy', [props.record.id, line.id]), { preserveScroll: true });
};

const deletePr = () => {
    if (! confirm('Delete this purchase request?')) return;
    router.delete(route('app.purchase-requests.destroy', props.record.id));
};

const poStatusBadgeClass = (status) => {
    const map = {
        submitted: 'ui-badge-brand',
        grn_created: 'ui-badge-brand',
        reimbursement_pending: 'ui-badge-warn',
        reimbursed: 'ui-badge-success',
        closed: 'ui-badge-success',
        draft: 'ui-badge-neutral',
    };
    return map[status] || 'ui-badge-neutral';
};

const openPurchaseOrder = (order) => {
    if (! order?.can_view) {
        return;
    }
    router.visit(route('app.purchase-orders.show', order.id));
};
</script>

<template>
    <AppLayout :title="record.pr_no">
        <template #header>{{ record.pr_no }}</template>

        <div class="space-y-5">
            <!-- Top bar -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span :class="badgeClass">{{ record.status_label }}</span>
                        <span class="text-sm text-slate-500">
                            {{ lineCount }} item{{ lineCount === 1 ? '' : 's' }} · {{ formatMoney(totalEstCost) }}
                        </span>
                    </div>
                    <p class="max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ record.purpose }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link :href="route('app.purchase-requests.index')" class="ui-btn-ghost">Back</Link>
                    <button
                        type="button"
                        class="ui-btn-secondary"
                        @click="showPurchaseOrders = true"
                    >
                        Purchase orders
                        <span
                            v-if="purchaseOrders.length"
                            class="ml-1.5 inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-brand-100 px-1.5 text-xs font-semibold text-brand-800"
                        >
                            {{ purchaseOrders.length }}
                        </span>
                    </button>
                    <Link v-if="actions.edit" :href="route('app.purchase-requests.edit', record.id)" class="ui-btn-secondary">Edit</Link>
                    <a v-if="actions.downloadPdf" :href="pdfUrl" target="_blank" class="ui-btn-secondary">PDF</a>
                    <a v-if="actions.viewDocument" :href="documentUrl" target="_blank" class="ui-btn-secondary">Document</a>
                    <button v-if="actions.delete" type="button" class="ui-btn-danger" @click="deletePr">Delete</button>
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

            <!-- Collapsible request details -->
            <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50/80 dark:hover:bg-surface-elevated/40 sm:px-5"
                    :aria-expanded="detailsOpen"
                    @click="detailsOpen = !detailsOpen"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Request details</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            {{ detailsOpen ? 'Hide request information' : detailsSummary }}
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

                <div
                    v-show="detailsOpen"
                    class="border-t border-slate-100 dark:border-slate-800"
                >
                    <dl class="grid gap-0 sm:grid-cols-2">
                        <div
                            v-for="field in detailFields"
                            :key="field.label"
                            class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:px-5"
                        >
                            <dt class="text-xs text-slate-500">{{ field.label }}</dt>
                            <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ field.value }}</dd>
                        </div>
                        <div class="border-b border-slate-100 px-4 py-3.5 text-sm dark:border-slate-800 sm:col-span-2 sm:px-5">
                            <dt class="text-xs text-slate-500">Purpose</dt>
                            <dd class="mt-1 whitespace-pre-wrap leading-relaxed text-slate-800 dark:text-slate-200">
                                {{ record.purpose }}
                            </dd>
                        </div>
                        <div
                            v-if="record.cancel_remark"
                            class="px-4 py-3.5 text-sm sm:col-span-2 sm:px-5"
                        >
                            <dt class="text-xs text-slate-500">Remark</dt>
                            <dd class="mt-1 whitespace-pre-wrap text-red-700 dark:text-red-300">{{ record.cancel_remark }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <!-- Budget usage (collapsible) -->
            <section
                v-if="budgetSummary.length"
                class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 px-4 py-3.5 text-left transition hover:bg-slate-50/80 dark:hover:bg-surface-elevated/40 sm:px-5"
                    :aria-expanded="budgetOpen"
                    @click="budgetOpen = !budgetOpen"
                >
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">Budget</p>
                        <p class="mt-0.5 truncate text-xs text-slate-500">
                            {{ budgetOpen ? 'Hide budget usage' : budgetSummaryCollapsed }}
                        </p>
                    </div>
                    <span
                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition"
                        :class="budgetOpen ? 'rotate-180 bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' : ''"
                        aria-hidden="true"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </span>
                </button>

                <ul
                    v-show="budgetOpen"
                    class="divide-y divide-slate-100 border-t border-slate-100 dark:divide-slate-800 dark:border-slate-800"
                >
                    <li
                        v-for="budget in budgetSummary"
                        :key="budget.id"
                        class="px-4 py-3.5 sm:px-5"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <Link
                                v-if="budget.can_open && budget.budget_account_id"
                                :href="route('app.budget-accounts.edit', budget.budget_account_id)"
                                class="min-w-0 text-sm font-medium text-brand-700 transition hover:text-brand-800 hover:underline dark:text-brand-300 dark:hover:text-brand-200"
                            >
                                {{ budget.label }}
                            </Link>
                            <p
                                v-else
                                class="min-w-0 text-sm font-medium text-slate-800 dark:text-slate-100"
                            >
                                {{ budget.label }}
                            </p>
                            <p class="text-xs tabular-nums text-slate-500 dark:text-slate-400">
                                Available
                                <span class="ml-1 font-semibold text-slate-800 dark:text-slate-100">
                                    MVR {{ formatMoney(budget.available) }}
                                </span>
                            </p>
                        </div>
                        <div class="mt-2.5">
                            <div class="mb-1 flex items-center justify-between gap-3 text-[11px] text-slate-500 dark:text-slate-400">
                                <span>Usage {{ budget.usage_percent }}%</span>
                                <span class="tabular-nums">
                                    Allocated MVR {{ formatMoney(budget.allocated) }}
                                </span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="budget.usage_percent >= 100
                                        ? 'bg-amber-500'
                                        : budget.usage_percent >= 80
                                            ? 'bg-brand-500'
                                            : 'bg-emerald-500'"
                                    :style="{ width: `${Math.min(budget.usage_percent, 100)}%` }"
                                />
                            </div>
                            <p class="mt-1.5 text-[11px] text-slate-400">
                                This PR MVR {{ formatMoney(budget.this_pr) }}
                            </p>
                        </div>
                    </li>
                </ul>
            </section>

            <!-- Line items -->
            <section class="overflow-hidden rounded-xl border border-slate-200/90 bg-surface dark:border-slate-800">
                <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 dark:text-white">Line items</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            {{ lineCount }} item{{ lineCount === 1 ? '' : 's' }} · Estimated total {{ formatMoney(totalEstCost) }}
                        </p>
                    </div>
                    <button
                        v-if="actions.manageLines"
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
                                <th>Unit</th>
                                <th>Budget account</th>
                                <th class="text-right">Qty</th>
                                <th class="text-right">Est. cost</th>
                                <th v-if="actions.manageLines" class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, index) in record.details" :key="line.id">
                                <td class="tabular-nums text-slate-400">{{ index + 1 }}</td>
                                <td>
                                    <div class="font-medium text-slate-900 dark:text-white">{{ line.item }}</div>
                                    <div class="mt-0.5 font-mono text-xs text-slate-500">{{ line.item_code }}</div>
                                </td>
                                <td>{{ line.unit }}</td>
                                <td class="max-w-[16rem]">
                                    <span class="line-clamp-2" :title="line.budget">{{ line.budget || '—' }}</span>
                                </td>
                                <td class="text-right tabular-nums">{{ line.amount }}</td>
                                <td class="text-right font-medium tabular-nums text-slate-900 dark:text-white">
                                    {{ formatMoney(line.est_cost) }}
                                </td>
                                <td v-if="actions.manageLines" class="space-x-3 text-right">
                                    <button type="button" class="ui-link" @click="openEditLine(line)">Edit</button>
                                    <button type="button" class="ui-link-danger" @click="deleteLine(line)">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!record.details.length">
                                <td :colspan="actions.manageLines ? 7 : 6" class="!py-14 text-center">
                                    <p class="font-medium text-slate-700 dark:text-slate-300">No line items yet</p>
                                    <p class="mt-1 text-sm text-slate-500">Add items to define what this PR covers.</p>
                                    <button
                                        v-if="actions.manageLines"
                                        type="button"
                                        class="ui-btn-primary mt-4"
                                        @click="openAddLine"
                                    >
                                        Add first line
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="record.details.length">
                            <tr class="border-t border-slate-200 bg-slate-50/70 dark:border-slate-800 dark:bg-surface-elevated/40">
                                <td :colspan="actions.manageLines ? 5 : 4" class="px-5 py-3.5 text-sm font-medium text-slate-500">
                                    Estimated total
                                </td>
                                <td class="px-5 py-3.5 text-right text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
                                    {{ formatMoney(totalEstCost) }}
                                </td>
                                <td v-if="actions.manageLines" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <!-- Purchase orders modal -->
        <Teleport to="body">
            <div
                v-if="showPurchaseOrders"
                class="fixed inset-0 z-[300] flex items-start justify-center overflow-y-auto p-4 pt-12"
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="showPurchaseOrders = false" />
                <div class="relative w-full max-w-4xl space-y-4 overflow-hidden rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-700 dark:bg-surface-elevated">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Purchase orders</h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Submitted and later statuses for {{ record.pr_no }}
                            </p>
                        </div>
                        <button type="button" class="text-sm text-slate-500 hover:text-slate-800 dark:hover:text-slate-200" @click="showPurchaseOrders = false">
                            Close
                        </button>
                    </div>

                    <p v-if="!purchaseOrders.length" class="py-10 text-center text-sm text-slate-500">
                        No submitted purchase orders for this request yet.
                    </p>
                    <div
                        v-else
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                    >
                        <article
                            v-for="order in purchaseOrders"
                            :key="order.id"
                            class="group flex flex-col rounded-xl border border-slate-200/90 bg-white p-4 transition dark:border-slate-800 dark:bg-surface"
                            :class="order.can_view
                                ? 'cursor-pointer hover:border-brand-400/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30'
                                : 'opacity-90'"
                            :role="order.can_view ? 'button' : undefined"
                            :tabindex="order.can_view ? 0 : undefined"
                            @click="openPurchaseOrder(order)"
                            @keydown.enter.prevent="openPurchaseOrder(order)"
                            @keydown.space.prevent="openPurchaseOrder(order)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <h4
                                    class="truncate text-base font-semibold tracking-tight text-slate-900 dark:text-white"
                                    :class="order.can_view ? 'group-hover:text-brand-700 dark:group-hover:text-brand-300' : ''"
                                >
                                    {{ order.po_no }}
                                </h4>
                                <span :class="poStatusBadgeClass(order.status)" class="shrink-0">{{ order.status_label }}</span>
                            </div>

                            <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs dark:border-slate-800">
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Vendor</dt>
                                    <dd class="truncate text-right font-medium text-slate-700 dark:text-slate-200">
                                        {{ order.vendor || '—' }}
                                    </dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Method</dt>
                                    <dd class="truncate text-right text-slate-700 dark:text-slate-300">
                                        {{ order.payment_method_label }}
                                    </dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Date</dt>
                                    <dd class="tabular-nums text-right text-slate-700 dark:text-slate-300">
                                        {{ formatDate(order.date) }}
                                    </dd>
                                </div>
                                <div v-if="order.grn_number" class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">GRN</dt>
                                    <dd class="truncate text-right text-slate-700 dark:text-slate-300">
                                        {{ order.grn_number }}
                                    </dd>
                                </div>
                                <div v-if="order.advance_form_status_label" class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Advance</dt>
                                    <dd class="truncate text-right text-slate-700 dark:text-slate-300">
                                        {{ order.advance_form_status_label }}
                                    </dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Total</dt>
                                    <dd class="tabular-nums text-right font-semibold text-slate-900 dark:text-white">
                                        MVR {{ formatMoney(order.total_amount) }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="mt-auto border-t border-slate-100 pt-3 dark:border-slate-800" @click.stop @keydown.stop>
                                <Link
                                    v-if="order.can_view"
                                    :href="route('app.purchase-orders.show', order.id)"
                                    class="inline-flex items-center justify-center rounded-lg px-2.5 py-1.5 text-xs font-medium text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-950/40"
                                >
                                    View
                                </Link>
                                <span v-else class="text-xs text-slate-400">No access to view</span>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Remark modal -->
        <Teleport to="body">
            <div v-if="showRemark" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="showRemark = null" />
                <form class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="submitRemark">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ showRemark === 'cancel' ? 'Cancel purchase request' : 'Reject purchase request' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">Provide a clear reason for the record.</p>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <label class="ui-label">Reason</label>
                            <textarea
                                v-model="remarkForm.cancel_remark"
                                required
                                rows="4"
                                class="ui-input"
                                placeholder="Explain why…"
                            />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="showRemark = null">Cancel</button>
                        <button type="submit" class="ui-btn-danger">Confirm</button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Close modal -->
        <Teleport to="body">
            <div v-if="showClose" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="showClose = false" />
                <form class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="submitClose">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">Close purchase request</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ openPurchaseOrders.length ? 'Enter GRN numbers for open purchase orders.' : 'Related open POs will also close.' }}
                        </p>
                    </div>
                    <div class="space-y-3 px-6 py-5">
                        <div
                            v-for="(row, i) in closeForm.purchase_order_grns"
                            :key="row.po_id"
                            class="grid gap-2 rounded-xl border border-slate-200 p-3 dark:border-slate-700 sm:grid-cols-[1fr_1.2fr] sm:items-center"
                        >
                            <p class="text-sm font-medium text-slate-900 dark:text-white">{{ openPurchaseOrders[i]?.po_no }}</p>
                            <input v-model="row.grn_number" type="text" required placeholder="GRN number" class="ui-input" />
                        </div>
                        <p v-if="!openPurchaseOrders.length" class="text-sm text-slate-600 dark:text-slate-400">
                            No open purchase orders require a GRN.
                        </p>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="showClose = false">Cancel</button>
                        <button type="submit" class="ui-btn-primary">Close PR</button>
                    </div>
                </form>
            </div>
        </Teleport>

        <!-- Line modal -->
        <Teleport to="body">
            <div v-if="showLine" class="fixed inset-0 z-[300] flex items-center justify-center p-4" role="dialog" aria-modal="true">
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="showLine = false" />
                <form class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated" @submit.prevent="saveLine">
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ editingLineId ? 'Edit line item' : 'Add line item' }}
                        </h3>
                    </div>
                    <div class="space-y-4 px-6 py-5">
                        <div>
                            <label class="ui-label">Item</label>
                            <select v-model="lineForm.item_id" required class="ui-select">
                                <option value="" disabled>Select item…</option>
                                <option v-for="item in options.items" :key="item.id" :value="item.id">
                                    {{ item.item_code }} — {{ item.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="ui-label">Unit</label>
                                <select v-model="lineForm.unit" required class="ui-select">
                                    <option v-for="u in options.units" :key="u.value" :value="u.value">{{ u.label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="ui-label">Quantity</label>
                                <input v-model="lineForm.amount" type="number" step="0.01" required class="ui-input" />
                            </div>
                        </div>
                        <div>
                            <label class="ui-label">Budget account</label>
                            <select v-model="lineForm.budget_account_id" required class="ui-select">
                                <option value="" disabled>Select budget…</option>
                                <option v-for="b in options.budgets" :key="b.id" :value="b.id">{{ b.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="ui-label">Estimated cost</label>
                            <input v-model="lineForm.est_cost" type="number" step="0.01" required class="ui-input" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="showLine = false">Cancel</button>
                        <button type="submit" class="ui-btn-primary">Save line</button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
