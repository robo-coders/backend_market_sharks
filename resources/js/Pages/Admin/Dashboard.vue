<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Swal from 'sweetalert2';

const props = defineProps({
    role: String,
    whatsappLink: String,
    stats: Object,
});

const page = usePage();
const auth = computed(() => page.props.auth?.user);

const form = useForm({
    whatsapp_link: props.whatsappLink ?? '',
});

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    },
});

const submit = () => {
    form.post(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            toast.fire({ icon: 'success', title: 'WhatsApp link updated!' });
        },
        onError: () => {
            toast.fire({ icon: 'error', title: 'Failed to save link.' });
        },
    });
};

const statsConfig = computed(() => [
    {
        label: 'Total Users',
        value: props.stats?.total ?? 0,
        icon: 'M17 20h5v-2a4 4 0 00-5.196-3.796M9 20H4v-2a4 4 0 015.196-3.796M15 7a4 4 0 11-8 0 4 4 0 018 0z',
        bg: 'bg-slate-100',
        iconColor: 'text-slate-600',
        badge: null,
    },
    {
        label: 'Active Members',
        value: props.stats?.active ?? 0,
        icon: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        bg: 'bg-emerald-50',
        iconColor: 'text-emerald-600',
        badge: { label: 'Live', color: 'bg-emerald-100 text-emerald-700' },
    },
    {
        label: 'Pending Review',
        value: props.stats?.payment_review ?? 0,
        icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        bg: 'bg-amber-50',
        iconColor: 'text-amber-500',
        badge: { label: 'Needs action', color: 'bg-amber-100 text-amber-700' },
    },
    {
        label: 'Blocked',
        value: props.stats?.blocked ?? 0,
        icon: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
        bg: 'bg-rose-50',
        iconColor: 'text-rose-500',
        badge: null,
    },
]);
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="space-y-6">
            <div class="relative overflow-hidden rounded-2xl px-6 py-5 flex items-center justify-between" style="isolation: isolate; z-index: 0;">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(79,70,229,0.45),transparent_50%),radial-gradient(circle_at_bottom_right,rgba(14,165,233,0.25),transparent_50%),linear-gradient(135deg,#0f172a,#1e1b4b)]"></div>
                <div class="absolute inset-0 opacity-20 [background-image:linear-gradient(to_right,rgba(148,163,184,0.1)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.1)_1px,transparent_1px)] [background-size:32px_32px]"></div>

                <div class="relative">
                    <h1 class="text-white font-bold text-lg">
                        Welcome back, {{ auth?.name?.split(' ')[0] ?? 'Admin' }} 👋
                    </h1>
                    <p class="text-slate-400 text-sm mt-0.5">
                        Here's what's happening with Market Sharks today.
                    </p>
                </div>

                <span class="relative hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-white/10 text-white text-xs font-semibold border border-white/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" aria-hidden="true"></span>
                    {{ role === 'super_admin' ? 'Super Admin' : role === 'admin' ? 'Admin' : role }}
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <div
                    v-for="stat in statsConfig"
                    :key="stat.label"
                    class="bg-white rounded-2xl border border-gray-100 p-5 shadow-sm hover:shadow-[0_8px_24px_-8px_rgba(15,23,42,0.12)] transition-shadow"
                >
                    <div class="flex items-start justify-between mb-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center" :class="stat.bg">
                            <svg class="w-4 h-4" :class="stat.iconColor" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="stat.icon" />
                            </svg>
                        </div>
                        <span v-if="stat.badge" class="text-[10px] font-semibold px-2 py-0.5 rounded-full" :class="stat.badge.color">
                            {{ stat.badge.label }}
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-slate-900 tabular-nums">{{ stat.value }}</p>
                    <p class="text-xs text-gray-400 font-medium mt-0.5">{{ stat.label }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-6 pt-5 pb-4 border-b border-gray-50 flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0" style="background:#25D366;">
                            <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">WhatsApp Group Link</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Shown to all active members on their dashboard</p>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="px-6 py-5 space-y-4">
                        <div>
                            <label for="whatsapp_link" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Invite Link</label>
                            <div class="flex gap-3">
                                <input
                                    id="whatsapp_link"
                                    v-model="form.whatsapp_link"
                                    type="url"
                                    placeholder="https://chat.whatsapp.com/..."
                                    class="flex-1 px-4 py-2.5 text-sm border border-gray-200 rounded-xl bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition"
                                />
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="px-5 py-2.5 text-sm font-semibold text-white rounded-xl transition-all disabled:opacity-60 disabled:cursor-not-allowed focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500"
                                    style="background:#25D366;"
                                >
                                    {{ form.processing ? 'Saving…' : 'Save' }}
                                </button>
                            </div>
                            <p v-if="form.errors.whatsapp_link" class="text-xs text-rose-500 mt-1.5">
                                {{ form.errors.whatsapp_link }}
                            </p>
                        </div>

                        <div v-if="whatsappLink" class="flex items-center gap-2 px-3 py-2 bg-gray-50 rounded-lg border border-gray-100">
                            <svg class="w-3.5 h-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101M10.172 13.828a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            <span class="text-xs text-gray-500 truncate">{{ whatsappLink }}</span>
                            <a
                                :href="whatsappLink"
                                target="_blank"
                                class="text-xs text-emerald-600 hover:underline ml-auto shrink-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-500 rounded"
                            >Test ↗</a>
                        </div>
                        <div v-else class="flex items-center gap-2 px-3 py-2 bg-amber-50 rounded-lg border border-amber-100">
                            <svg class="w-3.5 h-3.5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span class="text-xs text-amber-600">No link set, active users won't see a join button until this is saved.</span>
                        </div>
                    </form>
                </div>

                <div class="space-y-4">
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
                        <h3 class="text-sm font-semibold text-slate-900 mb-4">Member Breakdown</h3>
                        <div class="space-y-3.5">
                            <div
                                v-for="item in [
                                    { label: 'Active', value: stats?.active ?? 0, total: stats?.total ?? 1, color: 'bg-emerald-500' },
                                    { label: 'Pending', value: stats?.payment_review ?? 0, total: stats?.total ?? 1, color: 'bg-amber-400' },
                                    { label: 'Blocked', value: stats?.blocked ?? 0, total: stats?.total ?? 1, color: 'bg-rose-400' },
                                ]"
                                :key="item.label"
                            >
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-xs font-medium text-gray-500">{{ item.label }}</span>
                                    <span class="text-xs font-bold text-slate-700 tabular-nums">{{ item.value }}</span>
                                </div>
                                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        class="h-full rounded-full transition-all duration-700"
                                        :class="item.color"
                                        :style="{ width: item.total > 0 ? `${Math.round((item.value / item.total) * 100)}%` : '0%' }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                        <div class="px-5 pt-4 pb-3 border-b border-gray-50">
                            <h3 class="text-sm font-semibold text-slate-900">Quick Actions</h3>
                        </div>
                        <div class="p-3 space-y-1">
                            <a
                                v-for="action in [
                                    { label: 'All Users', sub: 'View & manage users', href: route('admin.users.index'), icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z' },
                                    { label: 'Pending Reviews', sub: 'Approve payments', href: route('admin.users.index'), icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
                                ]"
                                :key="action.label"
                                :href="action.href"
                                class="flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-gray-50 transition group focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500"
                            >
                                <div class="w-8 h-8 rounded-lg bg-slate-100 group-hover:bg-slate-200 flex items-center justify-center transition shrink-0">
                                    <svg class="w-3.5 h-3.5 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" :d="action.icon" />
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-slate-700 group-hover:text-slate-900 transition">{{ action.label }}</p>
                                    <p class="text-xs text-gray-400">{{ action.sub }}</p>
                                </div>
                                <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-gray-400 transition shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>