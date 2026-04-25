<script setup>
import InputError from '@/Components/InputError.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({
    status: {
        type: String,
    },
})

const form = useForm({
    email: '',
})

const submit = () => {
    form.post(route('password.email'))
}
</script>

<template>
    <Head title="Forgot Password" />

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
                                Reset your password securely.
                            </h1>
                            <p class="mt-5 max-w-lg text-base leading-7 text-slate-600 dark:text-slate-300">
                                Enter your email address and we’ll send you a secure password reset link so you can regain access to your admin workspace.
                            </p>

                            <div class="mt-10 grid gap-4">
                                <div class="rounded-2xl border border-white/60 bg-white/60 p-4 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/50 flex items-start gap-3">
                                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500/20">
                                        <svg class="h-4 w-4 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4V4zm2 3l6 5 6-5" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-white">Secure reset link</p>
                                        <p class="mt-0.5 text-sm text-slate-400">A password reset email will be sent directly to your inbox.</p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-white/60 bg-white/60 p-4 shadow-sm backdrop-blur dark:border-slate-800 dark:bg-slate-900/50 flex items-start gap-3">
                                    <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500/20">
                                        <svg class="h-4 w-4 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-white">Trusted access recovery</p>
                                        <p class="mt-0.5 text-sm text-slate-400">Designed to help admins recover access safely and quickly.</p>
                                    </div>
                                </div>
                            </div>
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
                                Forgot password
                            </h2>
                            <p class="mt-1.5 text-sm leading-6 text-slate-500">
                                Enter your email and we’ll send you a reset link.
                            </p>
                        </div>

                        <div
                            v-if="status"
                            class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
                        >
                            {{ status }}
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
                                    placeholder="you@marketsharks.live"
                                    class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-900 caret-slate-900 outline-none transition placeholder:text-slate-400 focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/10"
                                />
                                <InputError class="mt-2" :message="form.errors.email" />
                            </div>

                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:-translate-y-0.5 hover:bg-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-500/20 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                {{ form.processing ? 'Sending link...' : 'Email Password Reset Link' }}
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