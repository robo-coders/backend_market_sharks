<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import Swal from 'sweetalert2'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const roleNames = computed(() => page.props.auth?.role_names ?? [])
const isSuperAdmin = computed(() => roleNames.value.includes('super_admin'))

const sidebarOpen = ref(false)
const userMenuOpen = ref(false)
const lastFlashText = ref(null)

watch(
    () => page.props.flash?.status,
    (status) => {
        if (!status?.text) return
        if (lastFlashText.value === status.text) return

        lastFlashText.value = status.text

        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: status.type || 'success',
            title: status.title || 'Success',
            text: status.text || '',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true,
        })
    },
    { immediate: true }
)

const menuLinks = computed(() => {
    const links = [
        {
            label: 'Dashboard',
            href: route('admin.dashboard'),
            active: route().current('admin.dashboard'),
            icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        },
    ]

    if (isSuperAdmin.value) {
        links.push(
            {
                label: 'Admins',
                href: route('admin.admins.index'),
                active: route().current('admin.admins.*'),
                icon: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
            },
            {
                label: 'Teams',
                href: route('admin.teams.index'),
                active: route().current('admin.teams.*'),
                icon: 'M17 20h5v-2a4 4 0 00-5.196-3.796M9 20H4v-2a4 4 0 015.196-3.796M15 7a4 4 0 11-8 0 4 4 0 018 0z',
            }
        )
    }

    links.push({
        label: 'Users',
        href: route('admin.users.index'),
        active: route().current('admin.users.*'),
        icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
    })

    return links
})

const tradingLinks = computed(() => [
    {
        label: 'Signals',
        href: route('admin.signals.index'),
        active: route().current('admin.signals.*'),
        icon: 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
    },
])
</script>

<template>
    <div class="min-h-screen bg-slate-50 flex">
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-20 bg-slate-900/50 backdrop-blur-sm lg:hidden"
            @click="sidebarOpen = false"
        />

        <aside
            :class="[
                'fixed inset-y-0 left-0 z-30 w-64 bg-white border-r border-slate-200 flex flex-col transition-transform duration-300 lg:translate-x-0 lg:static lg:z-auto',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full'
            ]"
        >
            <div class="h-16 flex items-center gap-3 px-5 border-b border-slate-100 shrink-0">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <Link :href="route('admin.dashboard')" class="text-sm font-bold text-slate-900 tracking-tight">
                    Market Sharks
                </Link>
                <button
                    class="ml-auto lg:hidden text-slate-400 hover:text-slate-600"
                    @click="sidebarOpen = false"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-4">
                <div>
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest px-3 mb-2">Menu</p>

                    <Link
                        v-for="link in menuLinks"
                        :key="link.label"
                        :href="link.href"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all group"
                        :class="link.active
                            ? 'bg-indigo-50 text-indigo-700'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        @click="sidebarOpen = false"
                    >
                        <svg
                            class="w-4 h-4 shrink-0 transition-colors"
                            :class="link.active ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600'"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
                        </svg>
                        {{ link.label }}
                        <span v-if="link.active" class="ml-auto w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    </Link>
                </div>

                <div>
                    <p class="text-[10px] font-semibold text-slate-400 uppercase tracking-widest px-3 mb-2">Trading</p>

                    <Link
                        v-for="link in tradingLinks"
                        :key="link.label"
                        :href="link.href"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all group"
                        :class="link.active
                            ? 'bg-indigo-50 text-indigo-700'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900'"
                        @click="sidebarOpen = false"
                    >
                        <svg
                            class="w-4 h-4 shrink-0 transition-colors"
                            :class="link.active ? 'text-indigo-600' : 'text-slate-400 group-hover:text-slate-600'"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" :d="link.icon" />
                        </svg>
                        {{ link.label }}
                        <span v-if="link.active" class="ml-auto w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                    </Link>
                </div>
            </nav>

            <div class="p-3 border-t border-slate-100 shrink-0">
                <div class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-slate-50">
                    <div class="w-7 h-7 rounded-lg bg-indigo-100 flex items-center justify-center text-indigo-700 font-bold text-xs shrink-0">
                        {{ user?.name?.charAt(0)?.toUpperCase() ?? 'A' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-slate-800 truncate">{{ user?.name ?? 'Admin' }}</p>
                        <p class="text-[10px] text-slate-400 truncate">{{ user?.email ?? '' }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 bg-white border-b border-slate-200 flex items-center gap-4 px-4 sm:px-6 shrink-0 sticky top-0 z-10">
                <button
                    class="lg:hidden text-slate-500 hover:text-slate-700 transition"
                    @click="sidebarOpen = true"
                >
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="flex-1" />

                <div class="flex items-center gap-3">
                    <span class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-semibold border border-indigo-100">
                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span>
                        {{ isSuperAdmin ? 'Super Admin' : 'Admin' }}
                    </span>

                    <div class="relative">
                        <button
                            class="flex items-center gap-2.5 pl-2 pr-3 py-1.5 rounded-xl hover:bg-slate-50 transition"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-xs shrink-0">
                                {{ user?.name?.charAt(0)?.toUpperCase() ?? 'A' }}
                            </div>
                            <span class="hidden sm:block text-sm font-medium text-slate-700">{{ user?.name?.split(' ')[0] }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 mt-1.5 w-52 bg-white rounded-2xl shadow-lg border border-slate-200 py-1.5 z-50"
                            @click.stop
                        >
                            <div class="px-4 py-2.5 border-b border-slate-100">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ user?.name }}</p>
                                <p class="text-xs text-slate-400 truncate mt-0.5">{{ user?.email }}</p>
                            </div>

                            <div class="pt-1.5 px-1.5">
                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    class="flex items-center gap-2.5 w-full px-3 py-2 rounded-xl text-sm text-rose-600 hover:bg-rose-50 transition font-medium"
                                    @click="userMenuOpen = false"
                                >
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                    </svg>
                                    Log Out
                                </Link>
                            </div>
                        </div>

                        <div
                            v-if="userMenuOpen"
                            class="fixed inset-0 z-40"
                            @click="userMenuOpen = false"
                        />
                    </div>
                </div>
            </header>

            <main class="flex-1 p-4 sm:p-6">
                <slot />
            </main>

            <footer class="px-6 py-4 border-t border-slate-100 bg-white">
                <div class="flex items-center justify-between">
                    <p class="text-xs text-slate-400">
                        © {{ new Date().getFullYear() }} Market Sharks. All rights reserved.
                    </p>
                    <p class="text-xs text-slate-400">
                        Powered and designed by
                        <a
                            href="https://www.robocoders.dev/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="font-medium text-slate-500 hover:text-indigo-600 transition underline underline-offset-2 decoration-slate-300"
                        >
                            Robo Coders
                        </a>
                    </p>
                </div>
            </footer>
        </div>
    </div>
</template>