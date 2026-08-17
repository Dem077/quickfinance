<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    role: { type: Object, default: null },
    permissionGroups: { type: Array, required: true },
});

const isEdit = !!props.role;

const form = useForm({
    name: props.role?.name ?? '',
    guard_name: props.role?.guard_name ?? 'web',
    permissions: props.role?.permissions ? [...props.role.permissions] : [],
});

const allPermissionNames = computed(() =>
    props.permissionGroups.flatMap((g) => g.permissions.map((p) => p.name)),
);

const allSelected = computed({
    get: () => allPermissionNames.value.length > 0
        && allPermissionNames.value.every((name) => form.permissions.includes(name)),
    set: (value) => {
        form.permissions = value ? [...allPermissionNames.value] : [];
    },
});

const groupSelected = (group) => {
    const names = group.permissions.map((p) => p.name);
    return {
        get value() {
            return names.length > 0 && names.every((n) => form.permissions.includes(n));
        },
        set value(checked) {
            if (checked) {
                form.permissions = [...new Set([...form.permissions, ...names])];
            } else {
                form.permissions = form.permissions.filter((n) => ! names.includes(n));
            }
        },
    };
};

const submit = () => {
    if (isEdit) {
        form.put(route('app.roles.update', props.role.id));
        return;
    }
    form.post(route('app.roles.store'));
};
</script>

<template>
    <AppLayout>
        <template #header>{{ isEdit ? `Edit role: ${role.name}` : 'Create role' }}</template>

        <form class="mx-auto max-w-4xl space-y-6" @submit.prevent="submit">
            <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Name</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            :disabled="role?.is_super_admin"
                            class="w-full rounded-md border-slate-300 shadow-sm disabled:bg-slate-50"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-rose-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Guard</label>
                        <input v-model="form.guard_name" type="text" class="w-full rounded-md border-slate-300 shadow-sm" />
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm font-medium">
                    <input v-model="allSelected" type="checkbox" class="rounded border-slate-300 text-brand-600" />
                    Select all permissions
                </label>
            </div>

            <div class="space-y-4">
                <div v-for="group in permissionGroups" :key="group.label" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <label class="mb-3 flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                        <span class="font-semibold text-slate-900">{{ group.label }}</span>
                        <input
                            type="checkbox"
                            class="rounded border-slate-300 text-brand-600"
                            :checked="groupSelected(group).value"
                            @change="groupSelected(group).value = $event.target.checked"
                        />
                    </label>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="perm in group.permissions" :key="perm.name" class="flex items-start gap-2 text-sm text-slate-700">
                            <input v-model="form.permissions" type="checkbox" :value="perm.name" class="mt-0.5 rounded border-slate-300 text-brand-600" />
                            <span>{{ perm.label }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-between">
                <Link :href="route('app.roles.index')" class="text-sm text-slate-500">Cancel</Link>
                <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">Save</button>
            </div>
        </form>
    </AppLayout>
</template>
