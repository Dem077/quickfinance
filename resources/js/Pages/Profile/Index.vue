<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const form = useForm({
    signature: user.value?.has_signature ? '•••• saved' : '',
});

const submit = () => {
    form.post(route('app.profile.signature'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout>
        <template #header>Profile</template>

        <div class="mx-auto max-w-xl space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="font-semibold text-slate-900">Account</h2>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Name</dt>
                        <dd class="font-medium text-slate-900">{{ user?.name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="font-medium text-slate-900">{{ user?.email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Signature</dt>
                        <dd class="font-medium text-slate-900">
                            {{ user?.has_signature ? 'On file' : 'Missing' }}
                        </dd>
                    </div>
                </dl>
            </div>

            <form class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm" @submit.prevent="submit">
                <h2 class="font-semibold text-slate-900">Update signature</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Paste a signature image path/value as stored today, or leave blank to clear. Full pad UI comes
                    with the profile polish pass.
                </p>
                <textarea
                    v-model="form.signature"
                    rows="4"
                    class="mt-4 w-full rounded-md border-slate-300 shadow-sm focus:border-brand-500 focus:ring-brand-500"
                    placeholder="Signature data"
                />
                <p v-if="form.errors.signature" class="mt-1 text-sm text-rose-600">{{ form.errors.signature }}</p>
                <button
                    type="submit"
                    class="mt-4 rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Save signature
                </button>
            </form>
        </div>
    </AppLayout>
</template>
