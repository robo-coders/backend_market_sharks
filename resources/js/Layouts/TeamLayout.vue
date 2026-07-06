<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import Swal from 'sweetalert2'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)

const navigation = computed(() => [
    {
        label: 'Dashboard',
        href: route('team.dashboard'),
        active: route().current('team.dashboard'),
        icon: 'M3 13.2L12 5l9 8.2v6.3a1.5 1.5 0 0 1-1.5 1.5h-4.2a1.5 1.5 0 0 1-1.5-1.5v-3.9h-3.6v3.9a1.5 1.5 0 0 1-1.5 1.5H4.5A1.5 1.5 0 0 1 3 19.5v-6.3Z',
    },
])

// On mobile, the slide-out overlay should always show the full nav
// width regardless of the desktop-only collapsed preference — there's
// no reason to cram an icon-only sidebar into a temporary overlay.
const sidebarWidth = computed(() => {
    if (sidebarOpen.value) return '288px'
    return sidebarCollapsed.value ? '88px' : '288px'
})

const themeVars = {
    '--bg-app': '#071019',
    '--bg-canvas': '#0a1420',
    '--bg-elevated': 'rgba(255,255,255,0.04)',
    '--bg-elevated-2': 'rgba(255,255,255,0.06)',
    '--bg-hover': 'rgba(255,255,255,0.08)',
    '--bg-sidebar': 'rgba(7,13,22,0.82)',
    '--border-soft': 'rgba(255,255,255,0.07)',
    '--border-faint': 'rgba(255,255,255,0.04)',
    '--text-primary': 'rgba(244,247,251,0.96)',
    '--text-secondary': 'rgba(244,247,251,0.66)',
    '--text-tertiary': 'rgba(244,247,251,0.36)',
    '--text-faint': 'rgba(244,247,251,0.22)',
    '--accent': '#5cc8ff',
    '--accent-strong': '#2f90ff',
    '--success': '#3ddc97',
    '--danger': '#ff6b81',
    '--warning': '#f7c66b',
    '--shadow-lg': '0 24px 90px rgba(0,0,0,0.34)',
    '--shadow-md': '0 14px 40px rgba(0,0,0,0.22)',
    '--radius-xl': '30px',
    '--radius-lg': '22px',
    '--radius-md': '16px',
}

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
</script>

<template>
    <div
        class="min-h-screen bg-[var(--bg-app)] text-[var(--text-primary)]"
        :style="themeVars"
    >
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(71,163,255,0.16),transparent_24%),radial-gradient(circle_at_top_right,rgba(92,200,255,0.08),transparent_20%),radial-gradient(circle_at_bottom_left,rgba(64,122,255,0.07),transparent_24%),linear-gradient(180deg,#071019_0%,#0a1220_46%,#061019_100%)]"></div>
            <div class="absolute inset-0 opacity-[0.045] [background-image:linear-gradient(to_right,rgba(148,163,184,0.16)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.16)_1px,transparent_1px)] [background-size:28px_28px]"></div>
            <div
                class="absolute inset-y-0 hidden w-px bg-white/5 transition-all duration-300 lg:block"
                :style="{ left: sidebarWidth }"
            ></div>
        </div>

        <div class="relative flex min-h-screen">
            <div
                v-if="sidebarOpen"
                class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"
                @click="sidebarOpen = false"
            />

            <aside
                :class="[
                    'fixed inset-y-0 left-0 z-50 border-r border-[var(--border-faint)] bg-[var(--bg-sidebar)] backdrop-blur-2xl transition-all duration-300 lg:translate-x-0',
                    sidebarOpen ? 'translate-x-0' : '-translate-x-full'
                ]"
                :style="{ width: sidebarWidth }"
            >
                <div
                    class="flex h-full flex-col pb-4 pt-4 transition-all duration-300"
                    :class="sidebarCollapsed ? 'px-3' : 'px-4'"
                >
                    <div
                        class="flex h-14 items-center"
                        :class="sidebarCollapsed ? 'justify-center px-0' : 'gap-3 px-2'"
                    >
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[18px] bg-[linear-gradient(135deg,#69d2ff,#2f90ff_60%,#2b63ff)] shadow-[0_10px_34px_rgba(47,144,255,0.35)]">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 15l4-4 4 3 7-8" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6h2v2" />
                            </svg>
                        </div>

                        <div v-if="!sidebarCollapsed" class="min-w-0">
                            <p class="truncate text-sm font-semibold text-[var(--text-primary)]">Market Sharks</p>
                            <p class="mt-0.5 truncate text-[11px] uppercase tracking-[0.22em] text-[var(--text-faint)]">Smart Trading</p>
                        </div>
                    </div>

                    <div
                        class="mt-5 rounded-[26px] bg-[var(--bg-elevated)] shadow-[inset_0_1px_0_rgba(255,255,255,0.03)] transition-all duration-300"
                        :class="sidebarCollapsed ? 'p-3' : 'p-4'"
                    >
                        <div
                            class="flex gap-3"
                            :class="sidebarCollapsed ? 'justify-center' : 'items-center justify-between'"
                        >
                            <template v-if="!sidebarCollapsed">
                                <div>
                                    <p class="text-[11px] uppercase tracking-[0.24em] text-[var(--text-faint)]">Session</p>
                                    <p class="mt-2 text-sm font-medium text-[var(--text-primary)]">Dashboard</p>
                                </div>

                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-400/10 px-2.5 py-1 text-[11px] font-medium text-emerald-300">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-300 shadow-[0_0_10px_rgba(61,220,151,0.8)]"></span>
                                    Live
                                </span>
                            </template>

                            <template v-else>
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-emerald-400/10 text-emerald-300">
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-300 shadow-[0_0_10px_rgba(61,220,151,0.8)]"></span>
                                </span>
                            </template>
                        </div>
                    </div>

                    <div class="mt-7">
                        <p
                            v-if="!sidebarCollapsed"
                            class="px-3 text-[11px] uppercase tracking-[0.24em] text-[var(--text-faint)]"
                        >
                            Navigation
                        </p>

                        <div class="mt-3 space-y-1.5">
                            <Link
                                v-for="item in navigation"
                                :key="item.label"
                                :href="item.href"
                                class="group relative flex items-center rounded-2xl text-sm font-medium transition"
                                :class="[
                                    item.active
                                        ? 'bg-[var(--bg-elevated-2)] text-[var(--text-primary)] shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]'
                                        : 'text-[var(--text-secondary)] hover:bg-[var(--bg-elevated)] hover:text-[var(--text-primary)]',
                                    sidebarCollapsed
                                        ? 'justify-center px-0 py-3.5'
                                        : 'justify-between px-3.5 py-3'
                                ]"
                                @click="sidebarOpen = false"
                            >
                                <div
                                    class="flex items-center"
                                    :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                                >
                                    <span class="flex h-5 w-5 items-center justify-center">
                                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                            <path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                    </span>

                                    <span v-if="!sidebarCollapsed">{{ item.label }}</span>
                                </div>

                                <span
                                    v-if="item.active && !sidebarCollapsed"
                                    class="h-2 w-2 rounded-full bg-[var(--accent)] shadow-[0_0_12px_rgba(92,200,255,0.85)]"
                                />

                                <span
                                    v-if="item.active && sidebarCollapsed"
                                    class="absolute right-2 h-2 w-2 rounded-full bg-[var(--accent)] shadow-[0_0_12px_rgba(92,200,255,0.85)]"
                                />
                            </Link>
                        </div>
                    </div>

                    <div class="mt-auto space-y-3">
                        <button
                            type="button"
                            class="hidden w-full items-center rounded-2xl bg-[var(--bg-elevated)] text-[var(--text-secondary)] transition hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)] lg:flex"
                            :class="sidebarCollapsed ? 'justify-center px-0 py-3' : 'justify-between px-3.5 py-3'"
                            @click="sidebarCollapsed = !sidebarCollapsed"
                        >
                            <div class="flex items-center" :class="sidebarCollapsed ? 'justify-center' : 'gap-3'">
                                <span class="flex h-5 w-5 items-center justify-center">
                                    <svg
                                        class="h-4.5 w-4.5 transition-transform duration-300"
                                        :class="sidebarCollapsed ? 'rotate-180' : ''"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                    </svg>
                                </span>

                                <span v-if="!sidebarCollapsed" class="text-sm font-medium">
                                    Collapse sidebar
                                </span>
                            </div>
                        </button>

                        <div
                            class="rounded-[26px] bg-[var(--bg-elevated)] shadow-[inset_0_1px_0_rgba(255,255,255,0.03)] transition-all duration-300"
                            :class="sidebarCollapsed ? 'p-3' : 'p-3.5'"
                        >
                            <div
                                class="flex items-center"
                                :class="sidebarCollapsed ? 'justify-center' : 'gap-3'"
                            >
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[18px] bg-[linear-gradient(135deg,#69d2ff,#2f90ff)] text-sm font-semibold text-white shadow-[0_10px_24px_rgba(47,144,255,0.3)]">
                                    {{ user?.name?.charAt(0)?.toUpperCase() ?? 'T' }}
                                </div>

                                <div v-if="!sidebarCollapsed" class="min-w-0">
                                    <p class="truncate text-sm font-medium text-[var(--text-primary)]">{{ user?.name ?? 'Team User' }}</p>
                                    <p class="truncate text-xs text-[var(--text-tertiary)]">{{ user?.email ?? 'team@marketsharks.local' }}</p>
                                </div>
                            </div>

                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="mt-3 flex w-full items-center rounded-2xl bg-[var(--bg-elevated-2)] text-sm font-medium text-[var(--text-secondary)] transition hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]"
                                :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'justify-center px-3 py-2.5'"
                            >
                                <svg
                                    v-if="sidebarCollapsed"
                                    class="h-4.5 w-4.5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0l4-4m-4 4l4 4M13 5h4a2 2 0 012 2v10a2 2 0 01-2 2h-4" />
                                </svg>
                                <span v-else>Sign out</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </aside>

            <div
                class="flex min-w-0 flex-1 flex-col transition-all duration-300"
                :style="{ paddingLeft: `min(${sidebarWidth}, 100vw)` }"
            >
                <header class="sticky top-0 z-30 border-b border-[var(--border-faint)] bg-[rgba(7,13,22,0.68)] backdrop-blur-2xl">
                    <div class="flex h-[72px] items-center gap-3 px-4 sm:px-6 lg:px-8">
                        <button
                            class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-[var(--bg-elevated)] text-[var(--text-secondary)] transition hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)] lg:hidden"
                            @click="sidebarOpen = true"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16" />
                            </svg>
                        </button>

                        <div class="min-w-0">
                            <p class="text-[11px] uppercase tracking-[0.26em] text-[var(--text-faint)]">Team Terminal</p>
                            <h1 class="truncate text-[15px] font-medium text-[var(--text-primary)]">Operations Dashboard</h1>
                        </div>

                        <div class="ml-auto flex items-center gap-2 sm:gap-3">
                            <button
                                type="button"
                                class="hidden items-center gap-2 rounded-full bg-[var(--bg-elevated)] px-3 py-2 text-[12px] font-medium text-[var(--text-secondary)] transition hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)] lg:inline-flex"
                                @click="sidebarCollapsed = !sidebarCollapsed"
                            >
                                <svg
                                    class="h-4 w-4 transition-transform duration-300"
                                    :class="sidebarCollapsed ? 'rotate-180' : ''"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                                {{ sidebarCollapsed ? 'Expand nav' : 'Focus mode' }}
                            </button>

                            <div class="hidden items-center gap-2 rounded-full bg-[var(--bg-elevated)] px-3 py-2 text-[12px] font-medium text-[var(--text-secondary)] sm:inline-flex">
                                <span class="h-2 w-2 rounded-full bg-[var(--accent)] shadow-[0_0_10px_rgba(92,200,255,0.8)]"></span>
                                Market connected
                            </div>

                            <NotificationBell />

                            <div class="inline-flex items-center gap-2 rounded-full bg-emerald-400/10 px-3 py-2 text-[12px] font-medium text-emerald-300">
                                <span class="h-2 w-2 rounded-full bg-emerald-300 animate-pulse"></span>
                                Live session
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex min-h-0 flex-1 flex-col px-4 py-4 sm:px-6 sm:py-6 lg:px-8">
                    <div class="flex-1">
                        <slot />
                    </div>

                    <footer class="mt-8 border-t border-[var(--border-faint)] pt-4">
                        <p class="text-center text-xs text-[var(--text-tertiary)]">
                            Developed by
                            <a
                                href="https://robocoders.dev/"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="ml-1 text-[var(--text-secondary)] underline decoration-white/10 underline-offset-4 transition hover:text-[var(--text-primary)] hover:decoration-white/30"
                            >
                                Robo Coders
                            </a>
                        </p>
                    </footer>
                </main>
            </div>
        </div>
    </div>
</template>