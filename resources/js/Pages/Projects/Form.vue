<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
const props = defineProps({ record: { type: Object, default: null } });
const isEdit = !!props.record;
const form = useForm({ name: props.record?.name ?? '' });
const submit = () => (isEdit ? form.put(route('app.projects.update', props.record.id)) : form.post(route('app.projects.store')));
</script>
<template>
    <AppLayout>
        <template #header>{{ isEdit ? 'Edit project' : 'Create project' }}</template>
        <form class="mx-auto max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                <input v-model="form.name" type="text" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
            </div>
            <div class="flex justify-between pt-2">
                <Link :href="route('app.projects.index')" class="text-sm text-slate-500">Cancel</Link>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">Save</button>
            </div>
        </form>
    </AppLayout>
</template>
