<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    order: { type: Object, required: true },
    receipts: { type: Array, required: true },
    snipe: { type: Object, required: true },
});

const selected = ref([]);
const showReceive = ref(false);
const showBulk = ref(false);
const bulkType = ref('asset');
const activeReceipt = ref(null);
const serialMessage = ref('');
const serialOk = ref(null);

const pendingReceipts = computed(() => props.receipts.filter((r) => r.is_pending));

const selectedPending = computed(() =>
    props.receipts.filter((r) => selected.value.includes(r.id) && r.is_pending),
);

const allPendingSelected = computed({
    get: () => pendingReceipts.value.length > 0 && selected.value.length === pendingReceipts.value.length,
    set: (value) => {
        selected.value = value ? pendingReceipts.value.map((r) => r.id) : [];
    },
});

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

const openReceive = (receipt) => {
    activeReceipt.value = receipt;
    serialMessage.value = '';
    serialOk.value = null;
    receiveForm.clearErrors();
    receiveForm.defaults({ ...emptyReceive(), ...receipt.defaults });
    receiveForm.reset();
    showReceive.value = true;
};

const submitReceive = () => {
    receiveForm.post(route('app.asset-management.receive', [props.order.id, activeReceipt.value.id]), {
        preserveScroll: true,
        onSuccess: () => {
            showReceive.value = false;
            activeReceipt.value = null;
        },
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
    } catch (e) {
        serialOk.value = false;
        serialMessage.value = e.response?.data?.message || 'Serial check failed.';
    }
};

const openBulk = (type) => {
    const rows = type === 'accessory'
        ? selectedPending.value.filter((r) => r.is_accessory)
        : selectedPending.value.filter((r) => ! r.is_accessory);

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
            accessories: rows.map((r) => ({
                asset_receipt_id: r.id,
                line_label: `${r.item_name} — ${r.unit_label || 'Qty'}`,
                name: r.defaults.name,
                snipe_quantity: r.defaults.snipe_quantity,
                purchase_cost: r.defaults.purchase_cost,
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
            assets: rows.map((r) => ({
                asset_receipt_id: r.id,
                unit_label: [r.item_name, r.unit_label].filter(Boolean).join(' — '),
                name: r.defaults.name,
                serial_number: r.defaults.serial_number ?? '',
                mac_address: r.defaults.mac_address ?? '',
            })),
            accessories: [],
        });
        bulkForm.reset();
    }

    showBulk.value = true;
};

const submitBulk = () => {
    bulkForm.post(route('app.asset-management.bulk-receive', props.order.id), {
        preserveScroll: true,
        onSuccess: () => {
            showBulk.value = false;
            selected.value = [];
        },
    });
};

const fieldClass = 'w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500';
</script>

<template>
    <AppLayout>
        <template #header>{{ order.po_no }}</template>

        <div class="space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">{{ order.status_label }} · Asset {{ order.asset_status }}</p>
                    <h1 class="text-xl font-semibold text-slate-900">{{ order.po_no }}</h1>
                    <p class="text-sm text-slate-600">{{ order.pr_no }} · {{ order.vendor }} · {{ order.date }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link :href="route('app.asset-management.index')" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-sm">Back</Link>
                    <button
                        v-if="snipe.enabled && selectedPending.some((r) => !r.is_accessory)"
                        type="button"
                        class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white"
                        @click="openBulk('asset')"
                    >
                        Receive selected assets
                    </button>
                    <button
                        v-if="snipe.enabled && selectedPending.some((r) => r.is_accessory)"
                        type="button"
                        class="rounded-md bg-sky-600 px-3 py-2 text-sm font-semibold text-white"
                        @click="openBulk('accessory')"
                    >
                        Receive selected accessories
                    </button>
                </div>
            </div>

            <p v-if="!snipe.enabled" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Snipe-IT is not configured. Add SNIPE_IT_URL and SNIPE_IT_API_TOKEN to your .env file.
            </p>

            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">
                                <input v-model="allPendingSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" :disabled="!pendingReceipts.length" />
                            </th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Item</th>
                            <th class="px-3 py-2">Unit / Qty</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2">Asset Tag</th>
                            <th class="px-3 py-2">Serial</th>
                            <th class="px-3 py-2">Snipe ID</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in receipts" :key="row.id">
                            <td class="px-3 py-2">
                                <input
                                    v-if="row.is_pending"
                                    v-model="selected"
                                    type="checkbox"
                                    :value="row.id"
                                    class="rounded border-slate-300 text-brand-600"
                                />
                            </td>
                            <td class="px-3 py-2">{{ row.type_label }}</td>
                            <td class="px-3 py-2 font-medium">{{ row.item_name }}</td>
                            <td class="px-3 py-2">{{ row.unit_label || '1' }}</td>
                            <td class="px-3 py-2">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="row.is_pending ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                >
                                    {{ row.status_label }}
                                </span>
                            </td>
                            <td class="px-3 py-2">{{ row.asset_tag || '—' }}</td>
                            <td class="px-3 py-2">{{ row.serial_number || '—' }}</td>
                            <td class="px-3 py-2">{{ row.snipe_it_hardware_id || row.snipe_it_accessory_id || '—' }}</td>
                            <td class="px-3 py-2 text-right">
                                <button
                                    v-if="row.is_pending && snipe.enabled"
                                    type="button"
                                    class="font-medium text-emerald-700"
                                    @click="openReceive(row)"
                                >
                                    {{ row.is_accessory ? 'Accessory Received' : 'Item Received' }}
                                </button>
                                <span v-else-if="row.is_pending" class="text-slate-400">—</span>
                            </td>
                        </tr>
                        <tr v-if="!receipts.length">
                            <td colspan="9" class="px-3 py-8 text-center text-slate-500">No Snipe-IT items for this PO.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Single receive modal -->
        <div v-if="showReceive && activeReceipt" class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 pt-12">
            <form class="w-full max-w-2xl space-y-4 rounded-xl bg-white p-6 shadow-xl" @submit.prevent="submitReceive">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">
                        {{ activeReceipt.is_accessory ? 'Accessory Received' : 'Item Received' }}
                        — {{ activeReceipt.item_name }}
                    </h3>
                    <button type="button" class="text-slate-500" @click="showReceive = false">Close</button>
                </div>

                <template v-if="activeReceipt.is_accessory">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Accessory Name</label>
                            <input v-model="receiveForm.name" type="text" required :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Category</label>
                            <select v-model="receiveForm.snipe_category_id" required :class="fieldClass">
                                <option v-for="o in snipe.options.categories" :key="o.id" :value="o.id">{{ o.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Quantity</label>
                            <input v-model="receiveForm.snipe_quantity" type="number" min="1" required :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Location</label>
                            <select v-model="receiveForm.snipe_location_id" :class="fieldClass">
                                <option :value="null">None</option>
                                <option v-for="o in snipe.options.locations" :key="o.id" :value="o.id">{{ o.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Order Number</label>
                            <input v-model="receiveForm.order_number" type="text" :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Purchase Date</label>
                            <input v-model="receiveForm.purchase_date" type="date" :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Purchase Cost</label>
                            <input v-model="receiveForm.purchase_cost" type="number" step="0.01" :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Supplier</label>
                            <select v-model="receiveForm.snipe_supplier_id" :class="fieldClass">
                                <option :value="null">None</option>
                                <option v-for="o in snipe.options.suppliers" :key="o.id" :value="o.id">{{ o.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Model Number</label>
                            <input v-model="receiveForm.model_number" type="text" :class="fieldClass" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Notes</label>
                            <textarea v-model="receiveForm.notes" rows="2" :class="fieldClass" />
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2 text-xs text-slate-500">Asset tag is assigned automatically by Snipe-IT.</div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Status</label>
                            <select v-model="receiveForm.snipe_status_id" required :class="fieldClass">
                                <option v-for="o in snipe.options.statuses" :key="o.id" :value="o.id">{{ o.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Model</label>
                            <select v-model="receiveForm.snipe_model_id" required :class="fieldClass">
                                <option value="" disabled>Select…</option>
                                <option v-for="o in snipe.options.models" :key="o.id" :value="o.id">{{ o.label }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Asset Name</label>
                            <input v-model="receiveForm.name" type="text" required :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Serial</label>
                            <div class="flex gap-2">
                                <input v-model="receiveForm.serial_number" type="text" :class="fieldClass" />
                                <button type="button" class="shrink-0 rounded-md border border-slate-200 px-3 text-sm" @click="checkSerial">Check</button>
                            </div>
                            <p v-if="serialMessage" class="mt-1 text-xs" :class="serialOk ? 'text-emerald-600' : 'text-amber-700'">{{ serialMessage }}</p>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Location</label>
                            <select v-model="receiveForm.snipe_location_id" :class="fieldClass">
                                <option :value="null">None</option>
                                <option v-for="o in snipe.options.locations" :key="o.id" :value="o.id">{{ o.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Order Number</label>
                            <input v-model="receiveForm.order_number" type="text" :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Purchase Date</label>
                            <input v-model="receiveForm.purchase_date" type="date" :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Purchase Cost</label>
                            <input v-model="receiveForm.purchase_cost" type="number" step="0.01" :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Supplier</label>
                            <select v-model="receiveForm.snipe_supplier_id" :class="fieldClass">
                                <option :value="null">None</option>
                                <option v-for="o in snipe.options.suppliers" :key="o.id" :value="o.id">{{ o.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">CAO Asset Code</label>
                            <input v-model="receiveForm.cao_asset_code" type="text" required :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Finance Old Asset Tag</label>
                            <input v-model="receiveForm.finance_old_asset_tag" type="text" :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Asset Class</label>
                            <input v-model="receiveForm.asset_class" type="text" :class="fieldClass" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">MAC Address</label>
                            <input v-model="receiveForm.mac_address" type="text" placeholder="AA:BB:CC:DD:EE:FF" :class="fieldClass" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="mb-1 block text-sm font-medium">Notes</label>
                            <textarea v-model="receiveForm.notes" rows="2" :class="fieldClass" />
                        </div>
                    </div>
                </template>

                <p v-if="receiveForm.errors.snipe" class="text-sm text-rose-600">{{ receiveForm.errors.snipe }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="showReceive = false">Cancel</button>
                    <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="receiveForm.processing">
                        Mark received
                    </button>
                </div>
            </form>
        </div>

        <!-- Bulk receive modal -->
        <div v-if="showBulk" class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4 pt-12">
            <form class="w-full max-w-3xl space-y-4 rounded-xl bg-white p-6 shadow-xl" @submit.prevent="submitBulk">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold">
                        Bulk receive {{ bulkType === 'accessory' ? 'accessories' : 'assets' }}
                    </h3>
                    <button type="button" class="text-slate-500" @click="showBulk = false">Close</button>
                </div>

                <div v-if="bulkType === 'asset'" class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Status</label>
                        <select v-model="bulkForm.snipe_status_id" required :class="fieldClass">
                            <option v-for="o in snipe.options.statuses" :key="o.id" :value="o.id">{{ o.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Model</label>
                        <select v-model="bulkForm.snipe_model_id" required :class="fieldClass">
                            <option v-for="o in snipe.options.models" :key="o.id" :value="o.id">{{ o.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Location</label>
                        <select v-model="bulkForm.snipe_location_id" :class="fieldClass">
                            <option :value="null">None</option>
                            <option v-for="o in snipe.options.locations" :key="o.id" :value="o.id">{{ o.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">CAO Asset Code</label>
                        <input v-model="bulkForm.cao_asset_code" type="text" required :class="fieldClass" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Order Number</label>
                        <input v-model="bulkForm.order_number" type="text" :class="fieldClass" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Purchase Date</label>
                        <input v-model="bulkForm.purchase_date" type="date" :class="fieldClass" />
                    </div>
                    <div class="sm:col-span-2 space-y-3">
                        <h4 class="text-sm font-semibold">Units</h4>
                        <div v-for="(row, i) in bulkForm.assets" :key="row.asset_receipt_id" class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-3">
                            <p class="sm:col-span-3 text-xs font-medium text-slate-500">{{ row.unit_label }}</p>
                            <input v-model="row.name" type="text" required placeholder="Name" :class="fieldClass" />
                            <input v-model="row.serial_number" type="text" required placeholder="Serial" :class="fieldClass" />
                            <input v-model="row.mac_address" type="text" placeholder="MAC" :class="fieldClass" />
                        </div>
                    </div>
                </div>

                <div v-else class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Category</label>
                        <select v-model="bulkForm.snipe_category_id" required :class="fieldClass">
                            <option v-for="o in snipe.options.categories" :key="o.id" :value="o.id">{{ o.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Location</label>
                        <select v-model="bulkForm.snipe_location_id" :class="fieldClass">
                            <option :value="null">None</option>
                            <option v-for="o in snipe.options.locations" :key="o.id" :value="o.id">{{ o.label }}</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2 space-y-3">
                        <h4 class="text-sm font-semibold">Lines</h4>
                        <div v-for="row in bulkForm.accessories" :key="row.asset_receipt_id" class="grid gap-2 rounded-lg border border-slate-200 p-3 sm:grid-cols-3">
                            <p class="sm:col-span-3 text-xs font-medium text-slate-500">{{ row.line_label }}</p>
                            <input v-model="row.name" type="text" required placeholder="Name" :class="fieldClass" />
                            <input v-model="row.snipe_quantity" type="number" min="1" required placeholder="Qty" :class="fieldClass" />
                            <input v-model="row.purchase_cost" type="number" step="0.01" placeholder="Cost" :class="fieldClass" />
                        </div>
                    </div>
                </div>

                <p v-if="bulkForm.errors.bulk" class="text-sm text-rose-600">{{ bulkForm.errors.bulk }}</p>
                <div class="flex justify-end gap-2">
                    <button type="button" class="rounded-md border px-3 py-2 text-sm" @click="showBulk = false">Cancel</button>
                    <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" :disabled="bulkForm.processing">
                        Receive selected
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
