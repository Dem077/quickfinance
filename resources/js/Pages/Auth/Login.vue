<script setup>
import { useForm } from '@inertiajs/vue3';
import AppBrandMark from '../../Components/AppBrandMark.vue';
import ThemeToggle from '../../Components/ThemeToggle.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('app.login.store'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="relative flex min-h-screen bg-canvas">
        <div class="absolute right-4 top-4 z-10 sm:right-6 sm:top-6">
            <ThemeToggle />
        </div>

        <div class="relative hidden w-1/2 overflow-hidden bg-sidebar lg:flex lg:flex-col lg:items-center lg:justify-center lg:gap-4">
            <div class="absolute inset-0 bg-gradient-to-br from-brand-500/20 via-transparent to-slate-900/5 dark:from-brand-600/25 dark:to-black/40" />
            <AppBrandMark size="lg" />
            <div class="relative text-center">
                <p class="text-2xl font-semibold text-slate-900 dark:text-white">Finance</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Control center</p>
            </div>
        </div>

        <div class="flex w-full items-center justify-center px-4 py-10 pb-[max(2.5rem,env(safe-area-inset-bottom))] lg:w-1/2">
            <div class="w-full max-w-md">
                <div class="mb-8 flex flex-col items-center lg:hidden">
                    <AppBrandMark size="lg" />
                    <p class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">Finance</p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-surface p-6 shadow-xl shadow-slate-200/60 dark:border-slate-800 dark:shadow-black/30 sm:p-8">
                    <div class="mb-8 text-center lg:text-left">
                        <h2 class="text-2xl font-semibold text-slate-900 dark:text-white">Sign in</h2>
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            Sign in with your work email to continue.
                        </p>
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <label class="ui-label" for="email">Email</label>
                            <input id="email" v-model="form.email" type="email" required autofocus class="ui-input" />
                            <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label class="ui-label" for="password">Password</label>
                            <input id="password" v-model="form.password" type="password" required class="ui-input" />
                            <p v-if="form.errors.password" class="mt-1.5 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="rounded border-slate-300 bg-white text-brand-600 focus:ring-brand-500/30 dark:border-slate-600 dark:bg-surface-elevated"
                            />
                            Remember me
                        </label>

                        <button type="submit" class="ui-btn-primary w-full" :disabled="form.processing">
                            {{ form.processing ? 'Signing in…' : 'Sign in' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
