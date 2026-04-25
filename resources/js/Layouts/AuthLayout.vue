<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen bg-slate-100 flex items-center justify-center p-6">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <Link href="/" class="inline-flex items-center justify-center">
                    <ApplicationLogo class="h-14 w-14 fill-current text-indigo-600" />
                </Link>
                <h1 class="mt-4 text-2xl font-bold text-slate-900">
                    Welcome back
                </h1>
                <p class="mt-2 text-sm text-slate-500">
                    Sign in to continue to MarketSharks Admin
                </p>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white px-8 py-8 shadow-xl shadow-slate-200/50">
                <slot />
            </div>
        </div>
    </div>
</template>