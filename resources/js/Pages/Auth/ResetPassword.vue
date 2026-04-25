<script setup>
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
})

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Reset Password" />

    <div class="min-h-screen bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-white">
        <div class="grid min-h-screen lg:grid-cols-2">
            <aside class="relative hidden overflow-hidden lg:flex">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(79,70,229,0.18),transparent_35%),radial-gradient(circle_at_bottom_right,rgba(14,165,233,0.12),transparent_35%),linear-gradient(180deg,#ffffff,#f8fafc)] dark:bg-[radial-gradient(circle_at_top_left,rgba(99,102,241,0.22),transparent_35%),radial-gradient(circle_at_bottom_right,rgba(59,130,246,0.14),transparent_35%),linear-gradient(180deg,#020617,#0f172a)]"></div>
                <div class="absolute inset-0 opacity-40 [background-image:linear-gradient(to_right,rgba(148,163,184,0.08)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.08)_1px,transparent_1px)] [background-size:48px_48px]"></div>

                <div class="relative z-10 flex w-full flex-col justify-between p-12">
                    <div>
                        <div class="inline-flex items-center gap-3 rounded-full border border-slate-200/70 bg-white/70 px-4 py-2 text-sm font-medium text-slate-700 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-200">
                            <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                            MarketSharks Admin
                        </div>

                        <div class="mt-16 max-w-xl">
                            <h1 class="text-4xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                Create your new password.
                            </h1>
                            <p class="mt-5 max-w-lg text-base leading-7 text-slate-600 dark:text-slate-300">
                                Choose a strong new password to restore access to your admin workspace.
                            </p>
                        </div>
                    </div>

                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Designed and developed by
                        <a
                            href="https://www.robocoders.dev/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-slate-700 underline decoration-slate-300 underline-offset-4 transition hover:text-indigo-600 dark:text-slate-200 dark:decoration-slate-600 dark:hover:text-indigo-400"
                        >
                            Robo Coders
                        </a>
                    </p>
                </div>
            </aside>

            <main class="flex items-center justify-center px-6 py-10 sm:px-8 lg:px-12">
                <div class="w-full max-w-md">
                    <div class="mb-8 lg:hidden">
                        <div class="inline-flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200">
                            <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                            MarketSharks Admin
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-[0_20px_60px_rgba(15,23,42,0.12)]">
                        <div>
                            <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                                Reset password
                            </h2>
                            <p class="mt-1.5 text-sm leading-6 text-slate-500">
                                Enter your new password below.
                            </p>
                        </div>

                        <form @submit.prevent="submit" novalidate class="mt-6 space-y-5">
                            <div>
                                <label for="email" class="mb-2 block text-sm font-semibold text-slate-800">
                                    Email
                                </label>
                                <input
                                    id="email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-900 caret-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10"
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <div>
                                <label for="password" class="mb-2 block text-sm font-semibold text-slate-800">
                                    New Password
                                </label>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Enter your new password"
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-900 caret-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10"
                                />
                                <InputError class="mt-2" :message="form.errors.password" />
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-800">
                                    Confirm Password
                                </label>
                                <input
                                    id="password_confirmation"
                                    v-model="form.password_confirmation"
                                    type="password"
                                    required
                                    autocomplete="new-password"
                                    placeholder="Confirm your new password"
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-900 caret-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10"
                                />
                                <InputError class="mt-2" :message="form.errors.password_confirmation" />
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:-translate-y-0.5 hover:bg-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/20 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {{ form.processing ? 'Resetting...' : 'Reset Password' }}
                            </button>

                            <Link
                                :href="route('login')"
                                class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Back to login
                            </Link>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>