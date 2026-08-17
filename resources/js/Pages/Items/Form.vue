<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    item: { type: Object, default: null },
    types: { type: Array, required: true },
});

const isEdit = !!props.item;

const form = useForm({
    item_code: props.item?.item_code ?? '',
    name: props.item?.name ?? '',
    type: props.item?.type ?? 'other',
});

const submit = () => {
    if (isEdit) {
        form.put(route('app.items.update', props.item.id));
    } else {
        form.post(route('app.items.store'));
    }
};
</script>

<template>
    <AppLayout>
        <template #header>{{ isEdit ? 'Edit item' : 'Create item' }}</template>

        <form class="mx-auto max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="item_code">Item code</label>
                <input
                    id="item_code"
                    v-model="form.item_code"
                    type="text"
                    required
                    class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <p v-if="form.errors.item_code" class="mt-1 text-sm text-rose-600">{{ form.errors.item_code }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="name">Name</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    required
                    class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700" for="type">Type</label>
                <select
                    id="type"
                    v-model="form.type"
                    required
                    class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                >
                    <option v-for="type in types" :key="type.value" :value="type.value">
                        {{ type.label }}
                    </option>
                </select>
                <p v-if="form.errors.type" class="mt-1 text-sm text-rose-600">{{ form.errors.type }}</p>
            </div>

            <div class="flex items-center justify-between gap-3 pt-2">
                <Link :href="route('app.items.index')" class="text-sm text-slate-500 hover:text-slate-800">Cancel</Link>
                <button
                    type="submit"
                    class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Saving…' : isEdit ? 'Save changes' : 'Create item' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
