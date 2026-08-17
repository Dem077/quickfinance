<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiSearchableSelect from '../../Components/UiSearchableSelect.vue';
import UiSignaturePad from '../../Components/UiSignaturePad.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    user: { type: Object, default: null },
    departments: { type: Array, required: true },
    locations: { type: Array, required: true },
    roles: { type: Array, required: true },
    availableHodDepartments: { type: Array, required: true },
});

const isEdit = !!props.user;

const form = useForm({
    name: props.user?.name ?? '',
    designation: props.user?.designation ?? '',
    department_id: props.user?.department_id ?? '',
    location_id: props.user?.location_id ?? '',
    email: props.user?.email ?? '',
    mobile: props.user?.mobile ?? '',
    view_all_pr: props.user?.view_all_pr ?? false,
    bank_account_name: props.user?.bank_account_name ?? '',
    bank_account_no: props.user?.bank_account_no ?? '',
    pettycashassigned: props.user?.bank_account_name || props.user?.bank_account_no ? 'Yes' : 'No',
    roles: props.user?.roles ?? [],
    password: '',
    password_confirmation: '',
    signature: props.user?.signature ?? '',
});

const showBank = computed(() => form.pettycashassigned === 'Yes');

const departmentOptions = computed(() =>
    props.departments.map((dept) => ({ value: dept.id, label: dept.name })),
);

const locationOptions = computed(() =>
    props.locations.map((location) => ({ value: location.id, label: location.name })),
);

const hodOptions = computed(() =>
    props.availableHodDepartments.map((dept) => ({ value: dept.id, label: dept.name })),
);

const roleLabel = (name) => (name || '').replaceAll('_', ' ');

const fieldError = (key) => form.errors[key] || '';

const toggleRole = (name) => {
    if (form.roles.includes(name)) {
        form.roles = form.roles.filter((role) => role !== name);
        return;
    }
    form.roles = [...form.roles, name];
};

const setToggle = (key, value) => {
    form[key] = value;
    if (key === 'pettycashassigned' && value === 'No') {
        form.bank_account_name = '';
        form.bank_account_no = '';
    }
};

const submit = () => {
    if (isEdit) {
        form.put(route('app.users.update', props.user.id));
        return;
    }
    form.post(route('app.users.store'));
};

const hodForm = useForm({ department_id: '' });
const selectedHod = ref([]);

const associate = () => {
    hodForm.post(route('app.users.hod.associate', props.user.id), {
        preserveScroll: true,
        onSuccess: () => hodForm.reset(),
    });
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

        <form class="mx-auto max-w-5xl space-y-4 pb-28" @submit.prevent="submit">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <Link :href="route('app.users.index')" class="ui-link inline-flex items-center gap-1 text-sm">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                        Back
                    </Link>
                    <h1 class="mt-1.5 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">
                        {{ isEdit ? user.name : 'Create user' }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ isEdit
                            ? 'Update account details, roles, and the signature used on PDFs.'
                            : 'Set up access, department, and an optional signature for documents.' }}
                    </p>
                </div>
                <span
                    v-if="isEdit"
                    :class="form.signature ? 'ui-badge-success' : 'ui-badge-warn'"
                >
                    {{ form.signature ? 'Signature on file' : 'Signature missing' }}
                </span>
            </div>

            <section class="ui-panel p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Profile</h2>
                <div class="grid gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-6">
                        <label class="ui-label">Name</label>
                        <input v-model="form.name" type="text" required class="ui-input" />
                        <p v-if="fieldError('name')" class="mt-1 text-xs text-red-600">{{ fieldError('name') }}</p>
                    </div>
                    <div class="lg:col-span-6">
                        <label class="ui-label">Designation</label>
                        <input v-model="form.designation" type="text" required class="ui-input" />
                        <p v-if="fieldError('designation')" class="mt-1 text-xs text-red-600">{{ fieldError('designation') }}</p>
                    </div>
                    <div class="lg:col-span-6">
                        <UiSearchableSelect
                            id="user-department"
                            v-model="form.department_id"
                            label="Department"
                            :options="departmentOptions"
                            placeholder="Search departments…"
                            required
                            :error="fieldError('department_id')"
                        />
                    </div>
                    <div class="lg:col-span-6">
                        <UiSearchableSelect
                            id="user-location"
                            v-model="form.location_id"
                            label="Location"
                            :options="locationOptions"
                            placeholder="Search locations…"
                            required
                            :error="fieldError('location_id')"
                        />
                    </div>
                    <div class="lg:col-span-6">
                        <label class="ui-label">Email</label>
                        <input v-model="form.email" type="email" required class="ui-input" />
                        <p v-if="fieldError('email')" class="mt-1 text-xs text-red-600">{{ fieldError('email') }}</p>
                    </div>
                    <div class="lg:col-span-6">
                        <label class="ui-label">Mobile</label>
                        <input v-model="form.mobile" type="text" required class="ui-input" />
                        <p v-if="fieldError('mobile')" class="mt-1 text-xs text-red-600">{{ fieldError('mobile') }}</p>
                    </div>
                </div>
            </section>

            <section class="ui-panel p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Access</h2>
                <div class="grid gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-6">
                        <label class="ui-label">Password{{ isEdit ? ' (optional)' : '' }}</label>
                        <input v-model="form.password" type="password" :required="!isEdit" autocomplete="new-password" class="ui-input" />
                        <p v-if="fieldError('password')" class="mt-1 text-xs text-red-600">{{ fieldError('password') }}</p>
                    </div>
                    <div class="lg:col-span-6">
                        <label class="ui-label">Confirm password</label>
                        <input v-model="form.password_confirmation" type="password" :required="!isEdit" autocomplete="new-password" class="ui-input" />
                    </div>
                    <div class="lg:col-span-6">
                        <label class="ui-label">View all PRs</label>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition"
                                :class="!form.view_all_pr
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'"
                                @click="setToggle('view_all_pr', false)"
                            >
                                No
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition"
                                :class="form.view_all_pr
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'"
                                @click="setToggle('view_all_pr', true)"
                            >
                                Yes
                            </button>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">
                            Allows this user to see every purchase request. Use for procurement HOD.
                        </p>
                    </div>
                    <div class="lg:col-span-12">
                        <label class="ui-label">Roles</label>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <button
                                v-for="name in roles"
                                :key="name"
                                type="button"
                                class="rounded-xl border px-3 py-1.5 text-sm font-medium capitalize transition"
                                :class="form.roles.includes(name)
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'"
                                @click="toggleRole(name)"
                            >
                                {{ roleLabel(name) }}
                            </button>
                        </div>
                        <p v-if="fieldError('roles')" class="mt-1.5 text-xs text-red-600">{{ fieldError('roles') }}</p>
                    </div>
                </div>
            </section>

            <section class="ui-panel p-4">
                <h2 class="mb-3 text-sm font-semibold text-slate-900 dark:text-white">Petty cash</h2>
                <div class="grid gap-3 lg:grid-cols-12">
                    <div class="lg:col-span-12">
                        <label class="ui-label">Petty cash assigned</label>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition"
                                :class="form.pettycashassigned === 'No'
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'"
                                @click="setToggle('pettycashassigned', 'No')"
                            >
                                No
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium transition"
                                :class="form.pettycashassigned === 'Yes'
                                    ? 'border-brand-300 bg-brand-50 text-brand-800 dark:border-brand-700 dark:bg-brand-950/40 dark:text-brand-200'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300'"
                                @click="setToggle('pettycashassigned', 'Yes')"
                            >
                                Yes
                            </button>
                        </div>
                    </div>
                    <template v-if="showBank">
                        <div class="lg:col-span-6">
                            <label class="ui-label">Bank account name</label>
                            <input v-model="form.bank_account_name" type="text" class="ui-input" />
                            <p v-if="fieldError('bank_account_name')" class="mt-1 text-xs text-red-600">{{ fieldError('bank_account_name') }}</p>
                        </div>
                        <div class="lg:col-span-6">
                            <label class="ui-label">Bank account no</label>
                            <input v-model="form.bank_account_no" type="text" class="ui-input" />
                            <p v-if="fieldError('bank_account_no')" class="mt-1 text-xs text-red-600">{{ fieldError('bank_account_no') }}</p>
                        </div>
                    </template>
                </div>
            </section>

            <section class="ui-panel p-4">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Signature</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Used on purchase request, advance form, and other generated PDFs.
                        </p>
                    </div>
                </div>
                <UiSignaturePad
                    v-model="form.signature"
                    label="Draw signature"
                    :error="fieldError('signature')"
                />
            </section>

            <section v-if="isEdit" class="ui-panel p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">HOD of departments</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            Departments this user approves as head of department.
                        </p>
                    </div>
                    <button
                        v-if="selectedHod.length"
                        type="button"
                        class="text-sm font-medium text-rose-600"
                        @click="dissociateMany"
                    >
                        Remove selected
                    </button>
                </div>

                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    <li v-for="dept in user.hod_of" :key="dept.id" class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <label class="flex min-w-0 items-center gap-2">
                            <input v-model="selectedHod" type="checkbox" :value="dept.id" class="rounded border-slate-300 text-brand-600" />
                            <span class="truncate text-slate-800 dark:text-slate-200">{{ dept.name }}</span>
                        </label>
                        <button type="button" class="shrink-0 text-rose-600 hover:text-rose-700" @click="dissociate(dept.id)">
                            Remove
                        </button>
                    </li>
                    <li v-if="!user.hod_of?.length" class="py-4 text-sm text-slate-500">
                        No departments associated.
                    </li>
                </ul>

                <div class="mt-4 grid gap-2 sm:grid-cols-[1fr_auto]">
                    <UiSearchableSelect
                        id="hod-department"
                        v-model="hodForm.department_id"
                        :options="hodOptions"
                        placeholder="Select department without HOD…"
                        :error="hodForm.errors.department_id"
                    />
                    <button
                        type="button"
                        class="ui-btn-secondary"
                        :disabled="hodForm.processing || !hodForm.department_id"
                        @click="associate"
                    >
                        Associate
                    </button>
                </div>
            </section>

            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/90 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-surface/95">
                <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                    <p class="hidden text-xs text-slate-500 sm:block dark:text-slate-400">
                        {{ form.roles.length || 0 }} role{{ form.roles.length === 1 ? '' : 's' }}
                        · {{ form.signature ? 'Signature ready' : 'No signature' }}
                    </p>
                    <div class="ml-auto flex items-center gap-2">
                        <Link :href="route('app.users.index')" class="ui-btn-ghost">Cancel</Link>
                        <button type="submit" class="ui-btn-primary min-w-36" :disabled="form.processing">
                            {{ form.processing ? 'Saving…' : (isEdit ? 'Save changes' : 'Create user') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </AppLayout>
</template>
