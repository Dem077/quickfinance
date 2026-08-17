<script setup>
import AppLayout from '../../Layouts/AppLayout.vue';
import UiSignaturePad from '../../Components/UiSignaturePad.vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    signature: { type: String, default: '' },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const form = useForm({
    signature: props.signature ?? '',
});

const submit = () => {
    form.post(route('app.profile.signature'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout description="Your account details and the signature used on generated PDFs.">
        <template #header>Profile</template>

        <div class="mx-auto max-w-2xl space-y-4">
            <section class="ui-panel p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Account</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            These details come from your user record.
                        </p>
                    </div>
                    <span :class="form.signature ? 'ui-badge-success' : 'ui-badge-warn'">
                        {{ form.signature ? 'Signature on file' : 'Signature missing' }}
                    </span>
                </div>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-surface-muted/40">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Name</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ user?.name }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 px-4 py-3 dark:bg-surface-muted/40">
                        <dt class="text-xs text-slate-500 dark:text-slate-400">Email</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-slate-100">{{ user?.email }}</dd>
                    </div>
                </dl>
            </section>

            <form class="ui-panel p-5" @submit.prevent="submit">
                <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Signature</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Draw your signature the same way as the old portal. It is printed on purchase requests and advance forms.
                </p>
                <div class="mt-4">
                    <UiSignaturePad
                        v-model="form.signature"
                        label="Draw signature"
                        :error="form.errors.signature"
                    />
                </div>
                <div class="mt-4 flex justify-end">
                    <button type="submit" class="ui-btn-primary min-w-36" :disabled="form.processing">
                        {{ form.processing ? 'Saving…' : 'Save signature' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
