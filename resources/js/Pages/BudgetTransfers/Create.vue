<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    budgets: { type: Array, required: true },
});

const form = useForm({
    from_budget_id: '',
    to_budget_id: '',
    amount: '',
    description: '',
});

const formatMoney = (value) =>
    Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const fromOptions = computed(() =>
    props.budgets.map((budget) => ({
        value: budget.id,
        label: `${budget.label} — MVR ${formatMoney(budget.total_amount)}`,
    })),
);

const toOptions = computed(() =>
    props.budgets
        .filter((budget) => String(budget.id) !== String(form.from_budget_id))
        .map((budget) => ({
            value: budget.id,
            label: `${budget.label} — MVR ${formatMoney(budget.total_amount)}`,
        })),
);

const fromBudget = computed(() =>
    props.budgets.find((budget) => String(budget.id) === String(form.from_budget_id)) || null,
);

const toBudget = computed(() =>
    props.budgets.find((budget) => String(budget.id) === String(form.to_budget_id)) || null,
);

watch(() => form.from_budget_id, (fromId) => {
    if (String(form.to_budget_id) === String(fromId)) {
        form.to_budget_id = '';
    }
});

const amount = computed(() => Number(form.amount) || 0);
const fromAfter = computed(() => Number(fromBudget.value?.total_amount || 0) - amount.value);
const toAfter = computed(() => Number(toBudget.value?.total_amount || 0) + amount.value);
const exceedsBalance = computed(() => fromBudget.value && amount.value > Number(fromBudget.value.total_amount || 0));
const canSubmit = computed(() =>
    form.from_budget_id
    && form.to_budget_id
    && amount.value > 0
    && ! exceedsBalance.value
    && String(form.from_budget_id) !== String(form.to_budget_id),
);

const fieldError = (key) => form.errors[key] || '';

const submit = () => form.post(route('app.budget-transfers.store'));
</script>

<template>
    <AppLayout>
        <template #header>Create budget transfer</template>

        <form class="mx-auto max-w-3xl space-y-4 pb-28" @submit.prevent="submit">
            <div>
                <Link :href="route('app.budget-transfers.index')" class="ui-link inline-flex items-center gap-1 text-sm">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                    Back
                </Link>
                <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                    Transfer budget
                </h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Move funds from one sub-budget to another. The source account must have enough balance.
                </p>
            </div>

            <section class="ui-panel p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Accounts</h2>
                <div class="grid gap-3 lg:grid-cols-2">
                    <UiSearchableSelect
                        id="from-budget"
                        v-model="form.from_budget_id"
                        label="From budget"
                        :options="fromOptions"
                        placeholder="Search source budget…"
                        required
                        :error="fieldError('from_budget_id')"
                    />
                    <UiSearchableSelect
                        id="to-budget"
                        v-model="form.to_budget_id"
                        label="To budget"
                        :options="toOptions"
                        placeholder="Search destination budget…"
                        required
                        :error="fieldError('to_budget_id')"
                    />
                </div>
            </section>

            <section class="ui-panel p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Amount</h2>
                <div>
                    <label class="ui-label">Amount (MVR)</label>
                    <input v-model="form.amount" type="number" min="0.01" step="0.01" required class="ui-input" />
                    <p v-if="fieldError('amount')" class="mt-1 text-xs text-red-600">{{ fieldError('amount') }}</p>
                    <p v-else-if="exceedsBalance" class="mt-1 text-xs text-red-600">
                        Source budget only has MVR {{ formatMoney(fromBudget.total_amount) }}.
                    </p>
                </div>
                <div class="mt-4">
                    <label class="ui-label">Reason</label>
                    <textarea v-model="form.description" rows="3" class="ui-input" placeholder="Why is this transfer needed?" />
                    <p v-if="fieldError('description')" class="mt-1 text-xs text-red-600">{{ fieldError('description') }}</p>
                </div>
            </section>

            <section v-if="fromBudget || toBudget" class="ui-panel p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Preview</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-surface-muted/40">
                        <p class="text-xs text-slate-500">From</p>
                        <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ fromBudget?.label || 'Select a source' }}</p>
                        <p class="mt-2 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                            {{ fromBudget ? `MVR ${formatMoney(fromBudget.total_amount)}` : '—' }}
                            <span v-if="fromBudget && amount" class="text-rose-600"> → MVR {{ formatMoney(fromAfter) }}</span>
                        </p>
                    </div>
                    <div class="rounded-xl bg-emerald-50 px-4 py-3 dark:bg-emerald-950/30">
                        <p class="text-xs text-emerald-700 dark:text-emerald-300">To</p>
                        <p class="mt-1 font-medium text-slate-900 dark:text-white">{{ toBudget?.label || 'Select a destination' }}</p>
                        <p class="mt-2 text-sm tabular-nums text-slate-600 dark:text-slate-300">
                            {{ toBudget ? `MVR ${formatMoney(toBudget.total_amount)}` : '—' }}
                            <span v-if="toBudget && amount" class="text-emerald-700 dark:text-emerald-300"> → MVR {{ formatMoney(toAfter) }}</span>
                        </p>
                    </div>
                </div>
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95">
                <div class="mx-auto flex max-w-3xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <p class="hidden text-xs text-slate-500 sm:block">
                        {{ amount ? `MVR ${formatMoney(amount)}` : 'Enter an amount' }}
                    </p>
                    <div class="ml-auto flex items-center gap-2">
                        <Link :href="route('app.budget-transfers.index')" class="ui-btn-ghost">Cancel</Link>
                        <button type="submit" class="ui-btn-primary min-w-36" :disabled="form.processing || !canSubmit">
                            {{ form.processing ? 'Transferring…' : 'Transfer funds' }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
