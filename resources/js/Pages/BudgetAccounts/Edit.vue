<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    account: { type: Object, required: true },
    subBudgets: { type: Array, required: true },
    departments: { type: Array, required: true },
    locations: { type: Array, required: true },
    can: { type: Object, required: true },
});

const accountForm = useForm({
    expenditure_type: props.account.expenditure_type ?? '',
    account: props.account.account ?? '',
    name: props.account.name ?? '',
});

const saveAccount = () => accountForm.put(route('app.budget-accounts.update', props.account.id));

const emptyAllocation = () => ({
    id: null,
    department_id: '',
    location_id: '',
    amount: 0,
});

const showSubForm = ref(false);
const editingSubId = ref(null);
const subForm = useForm({
    code: '',
    name: '',
    display_name: '',
    allocations: [emptyAllocation()],
});

const openCreateSub = () => {
    editingSubId.value = null;
    subForm.reset();
    subForm.clearErrors();
    subForm.allocations = [emptyAllocation()];
    showSubForm.value = true;
};

const openEditSub = (sub) => {
    editingSubId.value = sub.id;
    subForm.clearErrors();
    subForm.code = sub.code;
    subForm.name = sub.name;
    subForm.display_name = sub.display_name ?? '';
    subForm.allocations = sub.allocations.length
        ? sub.allocations.map((a) => ({
            id: a.id,
            department_id: a.department_id,
            location_id: a.location_id ?? '',
            amount: a.amount,
        }))
        : [emptyAllocation()];
    showSubForm.value = true;
};

const addAllocation = () => {
    subForm.allocations.push(emptyAllocation());
};

const removeAllocation = (index) => {
    if (subForm.allocations.length <= 1) {
        return;
    }
    subForm.allocations.splice(index, 1);
};

const saveSub = () => {
    const payload = {
        preserveScroll: true,
        onSuccess: () => {
            showSubForm.value = false;
            editingSubId.value = null;
        },
    };

    if (editingSubId.value) {
        subForm.put(route('app.budget-accounts.sub-budgets.update', [props.account.id, editingSubId.value]), payload);
        return;
    }

    subForm.post(route('app.budget-accounts.sub-budgets.store', props.account.id), payload);
};

const deleteSub = (sub) => {
    if (! confirm(`Delete sub budget ${sub.code}?`)) {
        return;
    }
    router.delete(route('app.budget-accounts.sub-budgets.destroy', [props.account.id, sub.id]), {
        preserveScroll: true,
    });
};

const selectedSubs = ref([]);
const bulkSubForm = useForm({ ids: [] });

const allSubsSelected = computed({
    get: () => props.subBudgets.length > 0 && selectedSubs.value.length === props.subBudgets.length,
    set: (value) => {
        selectedSubs.value = value ? props.subBudgets.map((s) => s.id) : [];
    },
});

const deleteSelectedSubs = () => {
    if (! selectedSubs.value.length || ! confirm(`Delete ${selectedSubs.value.length} sub budget(s)?`)) {
        return;
    }
    bulkSubForm.ids = selectedSubs.value;
    bulkSubForm.delete(route('app.budget-accounts.sub-budgets.destroy-many', props.account.id), {
        preserveScroll: true,
        onSuccess: () => { selectedSubs.value = []; },
    });
};

const showTopUp = ref(false);
const topUpForm = useForm({
    allocations: [],
});

const openTopUp = () => {
    topUpForm.allocations = props.subBudgets.flatMap((sub) =>
        sub.allocations.map((a) => ({
            id: a.id,
            sub_budget_account_id: sub.id,
            code: sub.code,
            name: sub.name,
            department: a.department,
            location: a.location,
            amount: a.amount,
        })),
    );
    showTopUp.value = true;
};

const submitTopUp = () => {
    topUpForm.post(route('app.budget-accounts.top-up', props.account.id), {
        preserveScroll: true,
        onSuccess: () => { showTopUp.value = false; },
    });
};

const showHoldModal = ref(false);
const holdLoading = ref(false);
const holdError = ref('');
const holdSub = ref(null);
const holdRecords = ref([]);

const openHoldModal = async (sub) => {
    if (! Number(sub.on_hold)) {
        return;
    }

    showHoldModal.value = true;
    holdSub.value = sub;
    holdRecords.value = [];
    holdError.value = '';
    holdLoading.value = true;

    try {
        const { data } = await axios.get(
            route('app.budget-accounts.sub-budgets.on-hold-purchase-requests', [
                props.account.id,
                sub.id,
            ]),
        );
        holdRecords.value = data.records ?? [];
        holdSub.value = {
            ...sub,
            ...(data.sub_budget ?? {}),
        };
    } catch {
        holdError.value = 'Could not load purchase requests on hold.';
    } finally {
        holdLoading.value = false;
    }
};

const closeHoldModal = () => {
    showHoldModal.value = false;
    holdSub.value = null;
    holdRecords.value = [];
    holdError.value = '';
};

const openPurchaseRequest = (id) => {
    router.visit(route('app.purchase-requests.show', id));
};

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

const statusBadgeClass = (status) => {
    const map = {
        draft: 'ui-badge-neutral',
        submitted: 'ui-badge-warn',
        hod_approved: 'ui-badge-success',
        hod_rejected: 'ui-badge-danger',
        approved: 'ui-badge-success',
        rejected: 'ui-badge-danger',
        md_dmd_approved: 'ui-badge-success',
        md_dmd_rejected: 'ui-badge-danger',
        canceled: 'ui-badge-danger',
        closed: 'ui-badge-success',
        document_uploaded: 'ui-badge-brand',
    };
    return map[status] || 'ui-badge-neutral';
};

const deleteAccount = () => {
    if (! confirm('Delete this budget account?')) {
        return;
    }
    router.delete(route('app.budget-accounts.destroy', props.account.id));
};
</script>

<template>
    <AppLayout>
        <template #header>Edit budget account</template>

        <div class="space-y-6">
            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="saveAccount">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Expenditure type</label>
                        <input v-model="accountForm.expenditure_type" type="text" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Account</label>
                        <input v-model="accountForm.account" type="text" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input v-model="accountForm.name" type="text" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                </div>
                <div class="flex items-center justify-between">
                    <Link :href="route('app.budget-accounts.index')" class="text-sm text-slate-500">Back to list</Link>
                    <div class="flex gap-2">
                        <button
                            v-if="can.delete"
                            type="button"
                            class="rounded-md border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700"
                            @click="deleteAccount"
                        >
                            Delete
                        </button>
                        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="accountForm.processing">
                            Save account
                        </button>
                    </div>
                </div>
            </form>

            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-base font-semibold text-slate-900">Sub budget accounts</h2>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="selectedSubs.length"
                            type="button"
                            class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-medium text-rose-700"
                            @click="deleteSelectedSubs"
                        >
                            Delete selected
                        </button>
                        <button
                            type="button"
                            class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800"
                            @click="openTopUp"
                        >
                            Top Up Account
                        </button>
                        <button
                            type="button"
                            class="rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white"
                            @click="openCreateSub"
                        >
                            Add sub budget
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-3 py-2">
                                    <input v-model="allSubsSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                                </th>
                                <th class="px-3 py-2">Code</th>
                                <th class="px-3 py-2">Name</th>
                                <th class="px-3 py-2">Display name</th>
                                <th class="px-3 py-2">Allocations</th>
                                <th class="px-3 py-2 text-right">Allocated</th>
                                <th class="px-3 py-2 text-right">On hold</th>
                                <th class="px-3 py-2">Usage</th>
                                <th class="px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="sub in subBudgets" :key="sub.id">
                                <td class="px-3 py-3">
                                    <input v-model="selectedSubs" type="checkbox" :value="sub.id" class="rounded border-slate-300 text-brand-600" />
                                </td>
                                <td class="px-3 py-3 font-medium">{{ sub.code }}</td>
                                <td class="px-3 py-3">{{ sub.name }}</td>
                                <td class="px-3 py-3">{{ sub.display_name }}</td>
                                <td class="px-3 py-3">
                                    <ul class="list-disc space-y-1 pl-4 text-slate-600">
                                        <li v-for="a in sub.allocations" :key="a.id">
                                            {{ a.department || 'Department' }}{{ a.location ? ` / ${a.location}` : '' }}: {{ formatMoney(a.amount) }}
                                        </li>
                                    </ul>
                                </td>
                                <td class="px-3 py-3 text-right tabular-nums">{{ formatMoney(sub.total_amount) }}</td>
                                <td class="px-3 py-3 text-right">
                                    <button
                                        v-if="Number(sub.on_hold) > 0"
                                        type="button"
                                        class="tabular-nums font-medium text-brand-700 underline decoration-brand-300 underline-offset-2 transition hover:text-brand-800"
                                        @click="openHoldModal(sub)"
                                    >
                                        {{ formatMoney(sub.on_hold) }}
                                    </button>
                                    <span v-else class="tabular-nums font-medium text-slate-800">
                                        {{ formatMoney(sub.on_hold) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="min-w-[7rem]">
                                        <div class="mb-1 flex items-center justify-between gap-2 text-[11px] text-slate-500">
                                            <span>{{ sub.usage_percent }}%</span>
                                            <span class="tabular-nums">Avail. {{ formatMoney(sub.available) }}</span>
                                        </div>
                                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100">
                                            <div
                                                class="h-full rounded-full"
                                                :class="sub.usage_percent >= 100
                                                    ? 'bg-amber-500'
                                                    : sub.usage_percent >= 80
                                                        ? 'bg-brand-500'
                                                        : 'bg-emerald-500'"
                                                :style="{ width: `${Math.min(sub.usage_percent || 0, 100)}%` }"
                                            />
                                        </div>
                                    </div>
                                </td>
                                <td class="space-x-3 px-3 py-3 text-right">
                                    <button type="button" class="font-medium text-brand-600" @click="openEditSub(sub)">Edit</button>
                                    <button type="button" class="font-medium text-rose-600" @click="deleteSub(sub)">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="!subBudgets.length">
                                <td colspan="9" class="px-3 py-8 text-center text-slate-500">No sub budgets yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="showSubForm" class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 pt-16">
                <form class="w-full max-w-3xl space-y-4 rounded-xl bg-white p-6 shadow-xl" @submit.prevent="saveSub">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">{{ editingSubId ? 'Edit sub budget' : 'Create sub budget' }}</h3>
                        <button type="button" class="text-slate-500" @click="showSubForm = false">Close</button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Code</label>
                            <input v-model="subForm.code" type="text" required class="w-full rounded-md border-slate-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Name</label>
                            <input v-model="subForm.name" type="text" required class="w-full rounded-md border-slate-300 shadow-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Display name</label>
                            <input v-model="subForm.display_name" type="text" class="w-full rounded-md border-slate-300 shadow-sm" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-slate-800">Department allocations</h4>
                            <button type="button" class="text-sm font-medium text-brand-600" @click="addAllocation">Add allocation</button>
                        </div>
                        <div
                            v-for="(row, index) in subForm.allocations"
                            :key="index"
                            class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-4"
                        >
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Department</label>
                                <select v-model="row.department_id" required class="w-full rounded-md border-slate-300 shadow-sm">
                                    <option value="" disabled>Select…</option>
                                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Location</label>
                                <select v-model="row.location_id" class="w-full rounded-md border-slate-300 shadow-sm">
                                    <option value="">None</option>
                                    <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-600">Amount</label>
                                <input v-model="row.amount" type="number" step="0.01" required class="w-full rounded-md border-slate-300 shadow-sm" />
                            </div>
                            <div class="flex items-end">
                                <button type="button" class="text-sm text-rose-600" :disabled="subForm.allocations.length <= 1" @click="removeAllocation(index)">
                                    Remove
                                </button>
                            </div>
                        </div>
                        <p v-if="subForm.errors.allocations" class="text-sm text-rose-600">{{ subForm.errors.allocations }}</p>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-sm" @click="showSubForm = false">Cancel</button>
                        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="subForm.processing">Save</button>
                    </div>
                </form>
            </div>

            <div v-if="showTopUp" class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 pt-16">
                <form class="w-full max-w-4xl space-y-4 rounded-xl bg-white p-6 shadow-xl" @submit.prevent="submitTopUp">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-slate-900">Top Up Account</h3>
                        <button type="button" class="text-slate-500" @click="showTopUp = false">Close</button>
                    </div>
                    <p v-if="!topUpForm.allocations.length" class="text-sm text-slate-500">No allocations to top up.</p>
                    <div
                        v-for="(row, index) in topUpForm.allocations"
                        :key="row.id"
                        class="grid gap-3 rounded-lg border border-slate-200 p-3 sm:grid-cols-5"
                    >
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">Code</label>
                            <input :value="row.code" type="text" disabled class="w-full rounded-md border-slate-200 bg-slate-50" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">Sub budget</label>
                            <input :value="row.name" type="text" disabled class="w-full rounded-md border-slate-200 bg-slate-50" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">Department</label>
                            <input :value="row.department" type="text" disabled class="w-full rounded-md border-slate-200 bg-slate-50" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">Location</label>
                            <input :value="row.location" type="text" disabled class="w-full rounded-md border-slate-200 bg-slate-50" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-slate-500">New amount</label>
                            <input v-model="topUpForm.allocations[index].amount" type="number" step="0.01" required class="w-full rounded-md border-slate-300 shadow-sm" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" class="rounded-md border border-slate-200 px-3 py-2 text-sm" @click="showTopUp = false">Cancel</button>
                        <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="topUpForm.processing || !topUpForm.allocations.length">
                            Apply top up
                        </button>
                    </div>
                </form>
            </div>

            <div
                v-if="showHoldModal"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 pt-12"
                role="dialog"
                aria-modal="true"
                @click.self="closeHoldModal"
            >
                <div class="w-full max-w-5xl space-y-4 rounded-xl bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Purchase requests on hold</h3>
                            <p v-if="holdSub" class="mt-1 text-sm text-slate-500">
                                {{ holdSub.code }} · {{ holdSub.name }}
                                <span v-if="Number(holdSub.on_hold)" class="ml-1 tabular-nums">
                                    · MVR {{ formatMoney(holdSub.on_hold) }}
                                </span>
                            </p>
                        </div>
                        <button type="button" class="text-sm text-slate-500 hover:text-slate-800" @click="closeHoldModal">
                            Close
                        </button>
                    </div>

                    <p v-if="holdLoading" class="py-10 text-center text-sm text-slate-500">Loading…</p>
                    <p v-else-if="holdError" class="py-10 text-center text-sm text-rose-600">{{ holdError }}</p>
                    <p v-else-if="!holdRecords.length" class="py-10 text-center text-sm text-slate-500">
                        No purchase requests currently on hold for this sub budget.
                    </p>
                    <div
                        v-else
                        class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                    >
                        <article
                            v-for="row in holdRecords"
                            :key="row.id"
                            role="button"
                            tabindex="0"
                            class="group flex cursor-pointer flex-col rounded-xl border border-slate-200/90 bg-white p-4 transition hover:border-brand-400/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/30"
                            @click="openPurchaseRequest(row.id)"
                            @keydown.enter.prevent="openPurchaseRequest(row.id)"
                            @keydown.space.prevent="openPurchaseRequest(row.id)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <h4 class="truncate text-base font-semibold tracking-tight text-slate-900 group-hover:text-brand-700">
                                    {{ row.pr_no }}
                                </h4>
                                <span :class="statusBadgeClass(row.status)" class="shrink-0">{{ row.status_label }}</span>
                            </div>

                            <p class="mt-2 line-clamp-2 min-h-[2.5rem] text-sm leading-relaxed text-slate-600">
                                {{ row.purpose || 'No purpose provided' }}
                            </p>

                            <dl class="mt-4 space-y-2 border-t border-slate-100 pt-3 text-xs">
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Requester</dt>
                                    <dd class="truncate text-right font-medium text-slate-700">
                                        {{ row.user || '—' }}
                                    </dd>
                                </div>
                                <div v-if="row.department" class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Department</dt>
                                    <dd class="truncate text-right text-slate-700">{{ row.department }}</dd>
                                </div>
                                <div v-if="row.project" class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Project</dt>
                                    <dd class="truncate text-right text-slate-700">{{ row.project }}</dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">Date</dt>
                                    <dd class="tabular-nums text-right text-slate-700">{{ formatDate(row.date) }}</dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">On hold here</dt>
                                    <dd class="tabular-nums text-right font-semibold text-slate-900">
                                        MVR {{ formatMoney(row.hold_amount) }}
                                    </dd>
                                </div>
                                <div class="flex items-baseline justify-between gap-3">
                                    <dt class="shrink-0 text-slate-400">PR total</dt>
                                    <dd class="tabular-nums text-right text-slate-700">
                                        MVR {{ formatMoney(row.total_est_cost) }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="mt-auto border-t border-slate-100 pt-3" @click.stop @keydown.stop>
                                <Link
                                    :href="route('app.purchase-requests.show', row.id)"
                                    class="inline-flex items-center justify-center rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900"
                                >
                                    View
                                </Link>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
