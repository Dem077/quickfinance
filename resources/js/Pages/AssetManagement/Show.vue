<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiDateInput from '../../Components/UiDateInput.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    order: { type: Object, required: true },
    receipts: { type: Array, required: true },
    snipe: { type: Object, required: true },
});

const selected = ref([]);
const modal = ref(null);
const bulkType = ref('asset');
const activeReceipt = ref(null);
const serialMessage = ref('');
const serialOk = ref(null);
const receiptFilter = ref(props.order.pending_count > 0 ? 'pending' : 'all');
const typeFilter = ref('all');

const pendingReceipts = computed(() => props.receipts.filter((row) => row.is_pending));
const selectedPending = computed(() =>
    props.receipts.filter((row) => selected.value.includes(row.id) && row.is_pending),
);
const selectedAssets = computed(() => selectedPending.value.filter((row) => ! row.is_accessory));
const selectedAccessories = computed(() => selectedPending.value.filter((row) => row.is_accessory));

const progress = computed(() => {
    const total = props.order.total_count || 0;
    if (! total) return 0;
    return Math.round(((props.order.received_count || 0) / total) * 100);
});

const filteredReceipts = computed(() => props.receipts.filter((row) => {
    if (receiptFilter.value === 'pending' && ! row.is_pending) return false;
    if (receiptFilter.value === 'received' && row.is_pending) return false;
    if (typeFilter.value === 'asset' && row.is_accessory) return false;
    if (typeFilter.value === 'accessory' && ! row.is_accessory) return false;
    return true;
}));

const allVisiblePendingSelected = computed({
    get: () => {
        const pending = filteredReceipts.value.filter((row) => row.is_pending);
        return pending.length > 0 && pending.every((row) => selected.value.includes(row.id));
    },
    set: (value) => {
        const pendingIds = filteredReceipts.value.filter((row) => row.is_pending).map((row) => row.id);
        if (value) {
            selected.value = [...new Set([...selected.value, ...pendingIds])];
            return;
        }
        selected.value = selected.value.filter((id) => ! pendingIds.includes(id));
    },
});

const snipeOptions = (key) =>
    (props.snipe.options?.[key] || []).map((option) => ({
        value: option.id,
        label: option.label,
    }));

const emptyReceive = () => ({
    name: '',
    snipe_status_id: null,
    snipe_model_id: null,
    serial_number: '',
    snipe_location_id: null,
    order_number: '',
    purchase_date: '',
    purchase_cost: '',
    snipe_supplier_id: null,
    notes: '',
    cao_asset_code: '',
    finance_old_asset_tag: '',
    asset_class: '',
    mac_address: '',
    snipe_category_id: null,
    snipe_quantity: 1,
    model_number: '',
});

const receiveForm = useForm(emptyReceive());
const bulkForm = useForm({ type: 'asset' });

const fieldError = (form, key) => form.errors[key] || '';

const openReceive = (receipt) => {
    activeReceipt.value = receipt;
    serialMessage.value = '';
    serialOk.value = null;
    receiveForm.clearErrors();
    receiveForm.defaults({ ...emptyReceive(), ...receipt.defaults });
    receiveForm.reset();
    modal.value = 'receive';
};

const closeModal = () => {
    modal.value = null;
    activeReceipt.value = null;
};

const submitReceive = () => {
    receiveForm.post(route('app.asset-management.receive', [props.order.id, activeReceipt.value.id]), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    });
};

const checkSerial = async () => {
    serialMessage.value = 'Checking…';
    try {
        const { data } = await axios.post(route('app.asset-management.check-serial'), {
            serial_number: receiveForm.serial_number,
            exclude_hardware_id: activeReceipt.value?.snipe_it_hardware_id || null,
        });
        serialOk.value = data.available;
        serialMessage.value = data.message;
    } catch (error) {
        serialOk.value = false;
        serialMessage.value = error.response?.data?.message || 'Serial check failed.';
    }
};

const openBulk = (type) => {
    const rows = type === 'accessory' ? selectedAccessories.value : selectedAssets.value;

    if (! rows.length) {
        alert(type === 'accessory'
            ? 'Select pending accessory lines only.'
            : 'Select pending asset units only.');
        return;
    }

    bulkType.value = type;
    bulkForm.clearErrors();

    if (type === 'accessory') {
        const first = rows[0].defaults;
        bulkForm.defaults({
            type: 'accessory',
            snipe_category_id: first.snipe_category_id ?? props.snipe.defaults.accessory_category_id,
            snipe_location_id: first.snipe_location_id,
            order_number: first.order_number,
            purchase_date: first.purchase_date,
            snipe_supplier_id: first.snipe_supplier_id,
            notes: first.notes ?? '',
            accessories: rows.map((row) => ({
                asset_receipt_id: row.id,
                line_label: `${row.item_name} — ${row.unit_label || 'Qty'}`,
                name: row.defaults.name,
                snipe_quantity: row.defaults.snipe_quantity,
                purchase_cost: row.defaults.purchase_cost,
            })),
            assets: [],
        });
        bulkForm.reset();
    } else {
        const first = rows[0].defaults;
        bulkForm.defaults({
            type: 'asset',
            snipe_status_id: first.snipe_status_id ?? props.snipe.defaults.status_id,
            snipe_model_id: first.snipe_model_id,
            snipe_location_id: first.snipe_location_id,
            order_number: first.order_number,
            purchase_date: first.purchase_date,
            purchase_cost: first.purchase_cost,
            snipe_supplier_id: first.snipe_supplier_id,
            cao_asset_code: first.cao_asset_code ?? '',
            finance_old_asset_tag: first.finance_old_asset_tag ?? '',
            asset_class: first.asset_class ?? '',
            notes: first.notes ?? '',
            assets: rows.map((row) => ({
                asset_receipt_id: row.id,
                unit_label: [row.item_name, row.unit_label].filter(Boolean).join(' — '),
                name: row.defaults.name,
                serial_number: row.defaults.serial_number ?? '',
                mac_address: row.defaults.mac_address ?? '',
            })),
            accessories: [],
        });
        bulkForm.reset();
    }

    modal.value = 'bulk';
};

const submitBulk = () => {
    bulkForm.post(route('app.asset-management.bulk-receive', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            closeModal();
            selected.value = [];
        },
    });
};

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

const poBadgeClass = computed(() => ({
    submitted: 'ui-badge-warn',
    closed: 'ui-badge-success',
}[props.order.status] || 'ui-badge-neutral'));
</script>

<template>
    <AppLayout>
        <template #header>{{ order.po_no }}</template>

        <div class="space-y-4">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0 space-y-2">
                    <Link :href="route('app.asset-management.index')" class="ui-link inline-flex items-center gap-1 text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </Link>
                    <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                        {{ order.po_no }}
                    </h1>
                    <div class="flex flex-wrap items-center gap-2">
                        <span :class="poBadgeClass">{{ order.status_label }}</span>
                        <span :class="order.asset_status === 'pending' ? 'ui-badge-warn' : 'ui-badge-success'">
                            {{ order.asset_status_label }}
                        </span>
                        <span class="text-sm text-slate-500">
                            {{ order.received_count }}/{{ order.total_count }} received
                        </span>
                    </div>
                    <p class="max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ order.purpose || 'No linked purchase request purpose.' }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        v-if="order.pr_id"
                        :href="route('app.purchase-requests.show', order.pr_id)"
                        class="ui-btn-secondary"
                    >
                        View PR
                    </Link>
                    <Link :href="route('app.purchase-orders.show', order.id)" class="ui-btn-secondary">
                        View PO
                    </Link>
                    <button
                        v-if="snipe.enabled && selectedAssets.length"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-emerald-500"
                        @click="openBulk('asset')"
                    >
                        Receive {{ selectedAssets.length }} asset{{ selectedAssets.length === 1 ? '' : 's' }}
                    </button>
                    <button
                        v-if="snipe.enabled && selectedAccessories.length"
                        type="button"
                        class="inline-flex items-center justify-center rounded-lg bg-sky-600 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-sky-500"
                        @click="openBulk('accessory')"
                    >
                        Receive {{ selectedAccessories.length }} accessor{{ selectedAccessories.length === 1 ? 'y' : 'ies' }}
                    </button>
                </div>
            </div>

            <section class="ui-panel p-4">
                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-xs text-slate-500">Vendor</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ order.vendor || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">PR</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ order.pr_no || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Date</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ formatDate(order.date) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Progress</dt>
                        <dd class="mt-2">
                            <div class="mb-1 flex justify-between text-[11px] text-slate-500">
                                <span>{{ order.pending_count }} pending</span>
                                <span>{{ progress }}%</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div
                                    class="h-full rounded-full"
                                    :class="progress === 100 ? 'bg-emerald-500' : 'bg-brand-500'"
                                    :style="{ width: `${progress}%` }"
                                />
                            </div>
                        </dd>
                    </div>
                </dl>
            </section>

            <p v-if="!snipe.enabled" class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                Snipe-IT is not configured. Add SNIPE_IT_URL and SNIPE_IT_API_TOKEN to receive items.
            </p>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="tab in [
                            { key: 'all', label: 'All' },
                            { key: 'pending', label: 'Pending' },
                            { key: 'received', label: 'Received' },
                        ]"
                        :key="tab.key"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                        :class="receiptFilter === tab.key
                            ? 'bg-brand-600 text-white'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'"
                        @click="receiptFilter = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                    <span class="mx-1 hidden h-6 w-px bg-slate-200 sm:inline-block dark:bg-slate-700" />
                    <button
                        v-for="tab in [
                            { key: 'all', label: 'All types' },
                            { key: 'asset', label: 'Assets' },
                            { key: 'accessory', label: 'Accessories' },
                        ]"
                        :key="`type-${tab.key}`"
                        type="button"
                        class="rounded-lg px-3 py-1.5 text-xs font-medium transition"
                        :class="typeFilter === tab.key
                            ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900'
                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300'"
                        @click="typeFilter = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <label v-if="filteredReceipts.some((row) => row.is_pending)" class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="allVisiblePendingSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                    Select pending
                </label>
            </div>

            <div v-if="filteredReceipts.length" class="space-y-3">
                <article
                    v-for="row in filteredReceipts"
                    :key="row.id"
                    class="ui-card p-4"
                >
                    <div class="flex items-start gap-3">
                        <input
                            v-if="row.is_pending"
                            v-model="selected"
                            type="checkbox"
                            :value="row.id"
                            class="mt-1 rounded border-slate-300 text-brand-600"
                        />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span :class="row.is_accessory ? 'ui-badge-brand' : 'ui-badge-neutral'">
                                    {{ row.type_label || (row.is_accessory ? 'Accessory' : 'Asset') }}
                                </span>
                                <span :class="row.is_pending ? 'ui-badge-warn' : 'ui-badge-success'">
                                    {{ row.status_label }}
                                </span>
                                <span v-if="row.unit_label" class="text-xs text-slate-500">{{ row.unit_label }}</span>
                            </div>
                            <h2 class="mt-1.5 text-sm font-semibold text-slate-900 dark:text-white">
                                {{ row.item_name || row.name || 'Untitled item' }}
                            </h2>
                            <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <dt class="text-slate-400">Asset tag</dt>
                                    <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">{{ row.asset_tag || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400">Serial</dt>
                                    <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">{{ row.serial_number || '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400">Snipe ID</dt>
                                    <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                                        {{ row.snipe_it_hardware_id || row.snipe_it_accessory_id || '—' }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-slate-400">Received</dt>
                                    <dd class="mt-0.5 font-medium text-slate-700 dark:text-slate-200">
                                        {{ row.received_at_label || '—' }}
                                        <span v-if="row.received_by" class="font-normal text-slate-500"> · {{ row.received_by }}</span>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                        <button
                            v-if="row.is_pending && snipe.enabled"
                            type="button"
                            class="ui-btn-primary shrink-0"
                            @click="openReceive(row)"
                        >
                            {{ row.is_accessory ? 'Receive accessory' : 'Receive item' }}
                        </button>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="flex flex-col items-center rounded-xl border border-dashed border-slate-200 bg-surface px-6 py-14 text-center dark:border-slate-800"
            >
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">No items in this view</h2>
                <p class="mt-1 text-sm text-slate-500">Try another status or type filter.</p>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-if="modal === 'receive' && activeReceipt"
                class="fixed inset-0 z-[300] flex items-start justify-center overflow-y-auto p-4 py-10"
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form
                    class="relative w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated"
                    @submit.prevent="submitReceive"
                >
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            {{ activeReceipt.is_accessory ? 'Receive accessory' : 'Receive item' }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">{{ activeReceipt.item_name }} <span v-if="activeReceipt.unit_label">· {{ activeReceipt.unit_label }}</span></p>
                    </div>

                    <div class="grid gap-3 px-6 py-5 sm:grid-cols-2">
                        <template v-if="activeReceipt.is_accessory">
                            <div class="sm:col-span-2">
                                <label class="ui-label">Accessory name</label>
                                <input v-model="receiveForm.name" type="text" required class="ui-input" />
                                <p v-if="fieldError(receiveForm, 'name')" class="mt-1 text-xs text-red-600">{{ fieldError(receiveForm, 'name') }}</p>
                            </div>
                            <UiSearchableSelect
                                id="recv-category"
                                v-model="receiveForm.snipe_category_id"
                                label="Category"
                                :options="snipeOptions('categories')"
                                placeholder="Search categories…"
                                required
                                :error="fieldError(receiveForm, 'snipe_category_id')"
                            />
                            <div>
                                <label class="ui-label">Quantity</label>
                                <input v-model="receiveForm.snipe_quantity" type="number" min="1" required class="ui-input" />
                            </div>
                            <UiSearchableSelect
                                id="recv-acc-location"
                                v-model="receiveForm.snipe_location_id"
                                label="Location"
                                :options="snipeOptions('locations')"
                                placeholder="Search locations…"
                                empty-label="None"
                            />
                            <div>
                                <label class="ui-label">Order number</label>
                                <input v-model="receiveForm.order_number" type="text" class="ui-input" />
                            </div>
                            <UiDateInput
                                id="recv-acc-date"
                                v-model="receiveForm.purchase_date"
                                label="Purchase date"
                            />
                            <div>
                                <label class="ui-label">Purchase cost</label>
                                <input v-model="receiveForm.purchase_cost" type="number" step="0.01" class="ui-input" />
                            </div>
                            <UiSearchableSelect
                                id="recv-acc-supplier"
                                v-model="receiveForm.snipe_supplier_id"
                                label="Supplier"
                                :options="snipeOptions('suppliers')"
                                placeholder="Search suppliers…"
                                empty-label="None"
                            />
                            <div>
                                <label class="ui-label">Model number</label>
                                <input v-model="receiveForm.model_number" type="text" class="ui-input" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="ui-label">Notes</label>
                                <textarea v-model="receiveForm.notes" rows="2" class="ui-input" />
                            </div>
                        </template>

                        <template v-else>
                            <p class="sm:col-span-2 text-xs text-slate-500">Asset tag is assigned automatically by Snipe-IT.</p>
                            <UiSearchableSelect
                                id="recv-status"
                                v-model="receiveForm.snipe_status_id"
                                label="Status"
                                :options="snipeOptions('statuses')"
                                placeholder="Search statuses…"
                                required
                                :error="fieldError(receiveForm, 'snipe_status_id')"
                            />
                            <UiSearchableSelect
                                id="recv-model"
                                v-model="receiveForm.snipe_model_id"
                                label="Model"
                                :options="snipeOptions('models')"
                                placeholder="Search models…"
                                required
                                :error="fieldError(receiveForm, 'snipe_model_id')"
                            />
                            <div class="sm:col-span-2">
                                <label class="ui-label">Asset name</label>
                                <input v-model="receiveForm.name" type="text" required class="ui-input" />
                            </div>
                            <div>
                                <label class="ui-label">Serial</label>
                                <div class="flex gap-2">
                                    <input v-model="receiveForm.serial_number" type="text" class="ui-input" />
                                    <button type="button" class="ui-btn-secondary shrink-0" @click="checkSerial">Check</button>
                                </div>
                                <p v-if="serialMessage" class="mt-1 text-xs" :class="serialOk ? 'text-emerald-600' : 'text-amber-700'">{{ serialMessage }}</p>
                            </div>
                            <UiSearchableSelect
                                id="recv-location"
                                v-model="receiveForm.snipe_location_id"
                                label="Location"
                                :options="snipeOptions('locations')"
                                placeholder="Search locations…"
                                empty-label="None"
                            />
                            <div>
                                <label class="ui-label">Order number</label>
                                <input v-model="receiveForm.order_number" type="text" class="ui-input" />
                            </div>
                            <UiDateInput
                                id="recv-date"
                                v-model="receiveForm.purchase_date"
                                label="Purchase date"
                            />
                            <div>
                                <label class="ui-label">Purchase cost</label>
                                <input v-model="receiveForm.purchase_cost" type="number" step="0.01" class="ui-input" />
                            </div>
                            <UiSearchableSelect
                                id="recv-supplier"
                                v-model="receiveForm.snipe_supplier_id"
                                label="Supplier"
                                :options="snipeOptions('suppliers')"
                                placeholder="Search suppliers…"
                                empty-label="None"
                            />
                            <div>
                                <label class="ui-label">CAO asset code</label>
                                <input v-model="receiveForm.cao_asset_code" type="text" required class="ui-input" />
                                <p v-if="fieldError(receiveForm, 'cao_asset_code')" class="mt-1 text-xs text-red-600">{{ fieldError(receiveForm, 'cao_asset_code') }}</p>
                            </div>
                            <div>
                                <label class="ui-label">Finance old asset tag</label>
                                <input v-model="receiveForm.finance_old_asset_tag" type="text" class="ui-input" />
                            </div>
                            <div>
                                <label class="ui-label">Asset class</label>
                                <input v-model="receiveForm.asset_class" type="text" class="ui-input" />
                            </div>
                            <div>
                                <label class="ui-label">MAC address</label>
                                <input v-model="receiveForm.mac_address" type="text" placeholder="AA:BB:CC:DD:EE:FF" class="ui-input" />
                                <p v-if="fieldError(receiveForm, 'mac_address')" class="mt-1 text-xs text-red-600">{{ fieldError(receiveForm, 'mac_address') }}</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="ui-label">Notes</label>
                                <textarea v-model="receiveForm.notes" rows="2" class="ui-input" />
                            </div>
                        </template>
                    </div>

                    <p v-if="receiveForm.errors.snipe" class="px-6 text-sm text-rose-600">{{ receiveForm.errors.snipe }}</p>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="receiveForm.processing">
                            {{ receiveForm.processing ? 'Saving…' : 'Mark received' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="modal === 'bulk'"
                class="fixed inset-0 z-[300] flex items-start justify-center overflow-y-auto p-4 py-10"
                role="dialog"
                aria-modal="true"
            >
                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm dark:bg-black/60" @click="closeModal" />
                <form
                    class="relative w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-surface-elevated"
                    @submit.prevent="submitBulk"
                >
                    <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                            Bulk receive {{ bulkType === 'accessory' ? 'accessories' : 'assets' }}
                        </h3>
                    </div>

                    <div class="space-y-4 px-6 py-5">
                        <div v-if="bulkType === 'asset'" class="grid gap-3 sm:grid-cols-2">
                            <UiSearchableSelect
                                id="bulk-status"
                                v-model="bulkForm.snipe_status_id"
                                label="Status"
                                :options="snipeOptions('statuses')"
                                required
                            />
                            <UiSearchableSelect
                                id="bulk-model"
                                v-model="bulkForm.snipe_model_id"
                                label="Model"
                                :options="snipeOptions('models')"
                                required
                            />
                            <UiSearchableSelect
                                id="bulk-location"
                                v-model="bulkForm.snipe_location_id"
                                label="Location"
                                :options="snipeOptions('locations')"
                                empty-label="None"
                            />
                            <div>
                                <label class="ui-label">CAO asset code</label>
                                <input v-model="bulkForm.cao_asset_code" type="text" required class="ui-input" />
                            </div>
                            <div>
                                <label class="ui-label">Order number</label>
                                <input v-model="bulkForm.order_number" type="text" class="ui-input" />
                            </div>
                            <UiDateInput
                                id="bulk-date"
                                v-model="bulkForm.purchase_date"
                                label="Purchase date"
                            />
                            <div class="sm:col-span-2 space-y-3">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Units</h4>
                                <div
                                    v-for="row in bulkForm.assets"
                                    :key="row.asset_receipt_id"
                                    class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-3 dark:border-slate-700"
                                >
                                    <p class="sm:col-span-3 text-xs font-medium text-slate-500">{{ row.unit_label }}</p>
                                    <input v-model="row.name" type="text" required placeholder="Name" class="ui-input" />
                                    <input v-model="row.serial_number" type="text" required placeholder="Serial" class="ui-input" />
                                    <input v-model="row.mac_address" type="text" placeholder="MAC" class="ui-input" />
                                </div>
                            </div>
                        </div>

                        <div v-else class="grid gap-3 sm:grid-cols-2">
                            <UiSearchableSelect
                                id="bulk-category"
                                v-model="bulkForm.snipe_category_id"
                                label="Category"
                                :options="snipeOptions('categories')"
                                required
                            />
                            <UiSearchableSelect
                                id="bulk-acc-location"
                                v-model="bulkForm.snipe_location_id"
                                label="Location"
                                :options="snipeOptions('locations')"
                                empty-label="None"
                            />
                            <div class="sm:col-span-2 space-y-3">
                                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">Lines</h4>
                                <div
                                    v-for="row in bulkForm.accessories"
                                    :key="row.asset_receipt_id"
                                    class="grid gap-2 rounded-xl border border-slate-200 p-3 sm:grid-cols-3 dark:border-slate-700"
                                >
                                    <p class="sm:col-span-3 text-xs font-medium text-slate-500">{{ row.line_label }}</p>
                                    <input v-model="row.name" type="text" required placeholder="Name" class="ui-input" />
                                    <input v-model="row.snipe_quantity" type="number" min="1" required placeholder="Qty" class="ui-input" />
                                    <input v-model="row.purchase_cost" type="number" step="0.01" placeholder="Cost" class="ui-input" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <p v-if="bulkForm.errors.bulk" class="px-6 text-sm text-rose-600">{{ bulkForm.errors.bulk }}</p>
                    <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                        <button type="button" class="ui-btn-secondary" @click="closeModal">Cancel</button>
                        <button type="submit" class="ui-btn-primary" :disabled="bulkForm.processing">
                            {{ bulkForm.processing ? 'Receiving…' : 'Receive selected' }}
                        </button>
                    </div>
                </form>
            </div>
        </Teleport>
    </AppLayout>
</template>
