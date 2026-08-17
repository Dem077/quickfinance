<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    user: { type: Object, default: null },
    departments: Array,
    locations: Array,
    roles: Array,
    availableHodDepartments: Array,
});

const isEdit = !!props.user;

const form = useForm({
    name: props.user?.name ?? '',
    designation: props.user?.designation ?? '',
    department_id: props.user?.department_id ?? null,
    location_id: props.user?.location_id ?? null,
    email: props.user?.email ?? '',
    mobile: props.user?.mobile ?? '',
    view_all_pr: props.user?.view_all_pr ?? false,
    bank_account_name: props.user?.bank_account_name ?? '',
    bank_account_no: props.user?.bank_account_no ?? '',
    pettycashassigned: props.user?.bank_account_name || props.user?.bank_account_no ? 'Yes' : 'No',
    roles: props.user?.roles ?? [],
    password: '',
    password_confirmation: '',
});

const showBank = computed(() => form.pettycashassigned === 'Yes' || !!form.bank_account_name || !!form.bank_account_no);

const submit = () => {
    if (isEdit) {
        form.put(route('app.users.update', props.user.id));
    } else {
        form.post(route('app.users.store'));
    }
};

const hodForm = useForm({ department_id: null });
const selectedHod = ref([]);

const associate = () => {
    hodForm.post(route('app.users.hod.associate', props.user.id), { preserveScroll: true });
};

const dissociate = (departmentId) => {
    router.delete(route('app.users.hod.dissociate', [props.user.id, departmentId]), { preserveScroll: true });
};

const dissociateMany = () => {
    if (! selectedHod.value.length) return;
    router.delete(route('app.users.hod.dissociate-many', props.user.id), {
        data: { ids: selectedHod.value },
        preserveScroll: true,
        onSuccess: () => { selectedHod.value = []; },
    });
};
</script>

<template>
    <AppLayout>
        <template #header>{{ isEdit ? 'Edit user' : 'Create user' }}</template>

        <div class="mx-auto max-w-3xl space-y-6">
            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Name</label>
                        <input v-model="form.name" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Designation</label>
                        <input v-model="form.designation" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Department</label>
                        <select v-model="form.department_id" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option :value="null" disabled>Select</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Location</label>
                        <select v-model="form.location_id" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option :value="null" disabled>Select</option>
                            <option v-for="l in locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Email</label>
                        <input v-model="form.email" type="email" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Mobile</label>
                        <input v-model="form.mobile" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">View all PRs</label>
                        <select v-model="form.view_all_pr" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option :value="true">Yes</option>
                            <option :value="false">No</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Petty cash assigned</label>
                        <select v-model="form.pettycashassigned" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="No">No</option>
                            <option value="Yes">Yes</option>
                        </select>
                    </div>
                    <template v-if="showBank">
                        <div>
                            <label class="mb-1 block text-sm font-medium">Bank account name</label>
                            <input v-model="form.bank_account_name" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium">Bank account no</label>
                            <input v-model="form.bank_account_no" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                        </div>
                    </template>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Password{{ isEdit ? ' (optional)' : '' }}</label>
                        <input v-model="form.password" type="password" :required="!isEdit" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Confirm password</label>
                        <input v-model="form.password_confirmation" type="password" :required="!isEdit" class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium">Roles</label>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="role in roles" :key="role" class="inline-flex items-center gap-2 text-sm">
                                <input v-model="form.roles" type="checkbox" :value="role" class="rounded border-slate-300 text-brand-600" />
                                {{ role }}
                            </label>
                        </div>
                        <p v-if="form.errors.roles" class="mt-1 text-sm text-rose-600">{{ form.errors.roles }}</p>
                    </div>
                </div>
                <div class="flex justify-between pt-2">
                    <Link :href="route('app.users.index')" class="text-sm text-slate-500">Cancel</Link>
                    <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white" :disabled="form.processing">Save</button>
                </div>
            </form>

            <div v-if="isEdit" class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-slate-900">HOD of departments</h2>
                    <button
                        v-if="selectedHod.length"
                        type="button"
                        class="text-sm font-medium text-rose-600"
                        @click="dissociateMany"
                    >
                        Dissociate selected
                    </button>
                </div>

                <ul class="mt-4 divide-y divide-slate-100">
                    <li v-for="dept in user.hod_of" :key="dept.id" class="flex items-center justify-between py-2 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="selectedHod" type="checkbox" :value="dept.id" class="rounded border-slate-300 text-brand-600" />
                            {{ dept.name }}
                        </label>
                        <button type="button" class="text-rose-600" @click="dissociate(dept.id)">Dissociate</button>
                    </li>
                    <li v-if="!user.hod_of?.length" class="py-4 text-sm text-slate-500">No departments associated.</li>
                </ul>

                <form class="mt-4 flex flex-col gap-2 sm:flex-row" @submit.prevent="associate">
                    <select v-model="hodForm.department_id" required class="w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                        <option :value="null" disabled>Select department without HOD</option>
                        <option v-for="d in availableHodDepartments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white" :disabled="hodForm.processing">
                        Associate
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
