<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    budgets: { type: Array, required: true },
});

const form = useForm({
    from_budget_id: '',
    to_budget_id: '',
    amount: '',
    description: '',
});

const submit = () => form.post(route('app.budget-transfers.store'));
</script>

<template>
    <AppLayout>
        <template #header>Create budget transfer</template>

        <form class="mx-auto max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">From budget</label>
                <select v-model="form.from_budget_id" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="" disabled>Select…</option>
                    <option v-for="b in budgets" :key="b.id" :value="b.id">{{ b.label }}</option>
                </select>
                <p v-if="form.errors.from_budget_id" class="mt-1 text-sm text-rose-600">{{ form.errors.from_budget_id }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">To budget</label>
                <select v-model="form.to_budget_id" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="" disabled>Select…</option>
                    <option v-for="b in budgets" :key="b.id" :value="b.id">{{ b.label }}</option>
                </select>
                <p v-if="form.errors.to_budget_id" class="mt-1 text-sm text-rose-600">{{ form.errors.to_budget_id }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Amount</label>
                <input v-model="form.amount" type="number" min="0.01" step="0.01" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <p v-if="form.errors.amount" class="mt-1 text-sm text-rose-600">{{ form.errors.amount }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Reason</label>
                <textarea v-model="form.description" rows="3" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <p v-if="form.errors.description" class="mt-1 text-sm text-rose-600">{{ form.errors.description }}</p>
            </div>
            <div class="flex justify-between pt-2">
                <Link :href="route('app.budget-transfers.index')" class="text-sm text-slate-500">Cancel</Link>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">
                    Transfer
                </button>
            </div>
        </form>
    </AppLayout>
</template>
