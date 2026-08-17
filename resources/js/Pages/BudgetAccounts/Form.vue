<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    account: { type: Object, default: null },
});

const isEdit = !!props.account;
const form = useForm({
    expenditure_type: props.account?.expenditure_type ?? '',
    account: props.account?.account ?? '',
    name: props.account?.name ?? '',
});

const submit = () => {
    if (isEdit) {
        form.put(route('app.budget-accounts.update', props.account.id));
        return;
    }
    form.post(route('app.budget-accounts.store'));
};
</script>

<template>
    <AppLayout>
        <template #header>{{ isEdit ? 'Edit budget account' : 'Create budget account' }}</template>

        <form class="mx-auto max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Expenditure type</label>
                <input v-model="form.expenditure_type" type="text" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <p v-if="form.errors.expenditure_type" class="mt-1 text-sm text-rose-600">{{ form.errors.expenditure_type }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Account</label>
                <input v-model="form.account" type="text" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <p v-if="form.errors.account" class="mt-1 text-sm text-rose-600">{{ form.errors.account }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                <input v-model="form.name" type="text" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
            </div>
            <div class="flex justify-between pt-2">
                <Link :href="route('app.budget-accounts.index')" class="text-sm text-slate-500">Cancel</Link>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">
                    Save
                </button>
            </div>
        </form>
    </AppLayout>
</template>
