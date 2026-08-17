<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    record: { type: Object, default: null },
    banks: { type: Array, required: true },
});

const isEdit = !!props.record;
const form = useForm({
    name: props.record?.name ?? '',
    address: props.record?.address ?? '',
    bank: props.record?.bank ?? props.banks[0],
    account_no: props.record?.account_no ?? '',
    mobile: props.record?.mobile ?? '',
    gst_no: props.record?.gst_no ?? '',
});

const submit = () => (isEdit ? form.put(route('app.vendors.update', props.record.id)) : form.post(route('app.vendors.store')));
</script>

<template>
    <AppLayout>
        <template #header>{{ isEdit ? 'Edit vendor' : 'Create vendor' }}</template>
        <form class="mx-auto max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div v-for="field in [
                { key: 'name', label: 'Name' },
                { key: 'address', label: 'Address' },
                { key: 'account_no', label: 'Account no' },
                { key: 'mobile', label: 'Mobile' },
                { key: 'gst_no', label: 'GST no' },
            ]" :key="field.key">
                <label class="mb-1 block text-sm font-medium text-slate-700">{{ field.label }}</label>
                <input v-model="form[field.key]" type="text" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <p v-if="form.errors[field.key]" class="mt-1 text-sm text-rose-600">{{ form.errors[field.key] }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Bank</label>
                <select v-model="form.bank" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option v-for="bank in banks" :key="bank" :value="bank">{{ bank }}</option>
                </select>
                <p v-if="form.errors.bank" class="mt-1 text-sm text-rose-600">{{ form.errors.bank }}</p>
            </div>
            <div class="flex justify-between pt-2">
                <Link :href="route('app.vendors.index')" class="text-sm text-slate-500">Cancel</Link>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">Save</button>
            </div>
        </form>
    </AppLayout>
</template>
