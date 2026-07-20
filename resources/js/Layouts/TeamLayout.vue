<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import NotificationBell from '@/Components/NotificationBell.vue'
import ChatWidget from '@/Components/Chat/ChatWidget.vue'
import Swal from 'sweetalert2'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const sidebarOpen = ref(false)
const sidebarCollapsed = ref(false)
const userMenuOpen = ref(false)

const isDark = computed(() => (user.value?.theme ?? 'dark') !== 'light')

const navigation = computed(() => [
    {
        label: 'Dashboard',
        href: route('team.dashboard'),
        active: route().current('team.dashboard'),
        icon: 'M3 13.2L12 5l9 8.2v6.3a1.5 1.5 0 0 1-1.5 1.5h-4.2a1.5 1.5 0 0 1-1.5-1.5v-3.9h-3.6v3.9a1.5 1.5 0 0 1-1.5 1.5H4.5A1.5 1.5 0 0 1 3 19.5v-6.3Z',
    },
    {
        label: 'Settings',
        href: route('team.settings.edit'),
        active: route().current('team.settings.edit'),
        icon: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 0 0-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 0 0-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 0 0-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 0 0-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 0 0 1.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    },
])

const sidebarWidth = computed(() => {
    if (sidebarOpen.value) return '288px'
    return sidebarCollapsed.value ? '88px' : '288px'
})

const darkThemeVars = {
    '--bg-app': '#071019',
    '--bg-canvas': '#0a1420',
    '--bg-elevated': 'rgba(255,255,255,0.04)',
    '--bg-elevated-2': 'rgba(255,255,255,0.06)',
    '--bg-hover': 'rgba(255,255,255,0.08)',
    '--bg-sidebar': 'rgba(7,13,22,0.82)',
    '--bg-header': 'rgba(7,13,22,0.68)',
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

    /* Card & surface tokens */
    '--card-bg': 'rgba(255,255,255,0.03)',
    '--card-ring': 'rgba(255,255,255,0.06)',
    '--card-shadow-sm': '0 16px 42px rgba(0,0,0,0.22)',
    '--card-shadow': '0 20px 60px rgba(0,0,0,0.32)',
    '--card-shadow-lg': '0 28px 90px rgba(0,0,0,0.34)',
    '--inset-bg': 'rgba(0,0,0,0.18)',
    '--chip-bg': 'rgba(255,255,255,0.05)',
    '--news-bg': 'rgba(0,0,0,0.14)',

    /* Semantic tints */
    '--success-soft': 'rgba(61,220,151,0.12)',
    '--success-softer': 'rgba(61,220,151,0.04)',
    '--success-ring': 'rgba(61,220,151,0.20)',
    '--success-text': '#6ee7b7',
    '--danger-soft': 'rgba(255,107,129,0.12)',
    '--danger-softer': 'rgba(255,107,129,0.04)',
    '--danger-ring': 'rgba(255,107,129,0.20)',
    '--danger-text': '#fda4af',
    '--warning-soft': 'rgba(247,198,107,0.12)',
    '--warning-ring': 'rgba(247,198,107,0.22)',
    '--warning-text': '#fcd34d',
    '--info-soft': 'rgba(92,200,255,0.10)',
    '--info-text': '#67d8ff',
    '--info-dot': '#5cc8ff',
    '--level-accent': '#c084fc',
    '--level-soft': 'rgba(192,132,252,0.14)',

    /* Signal price panels */
    '--panel-entry-bg': 'linear-gradient(180deg,rgba(6,14,28,0.96),rgba(10,16,28,0.78))',
    '--panel-entry-ring': 'rgba(255,255,255,0.08)',
    '--panel-tp-bg': 'linear-gradient(180deg,rgba(7,36,27,0.84),rgba(7,22,18,0.82))',
    '--panel-tp-ring': 'rgba(52,211,153,0.10)',
    '--panel-sl-bg': 'linear-gradient(180deg,rgba(43,13,20,0.84),rgba(17,10,15,0.82))',
    '--panel-sl-ring': 'rgba(251,113,133,0.10)',
    '--panel-shadow': 'inset 0 1px 0 rgba(255,255,255,0.05), 0 18px 40px rgba(0,0,0,0.18)',

    /* Toast / floating panels */
    '--toast-bg': 'linear-gradient(180deg,#0d1621 0%,#0a121c 100%)',
    '--toast-border': 'rgba(255,255,255,0.09)',
    '--toast-track': 'rgba(255,255,255,0.05)',
}

const lightThemeVars = {
    '--bg-app': '#f5f7fb',
    '--bg-canvas': '#ffffff',
    '--bg-elevated': 'rgba(15,23,42,0.04)',
    '--bg-elevated-2': 'rgba(15,23,42,0.07)',
    '--bg-hover': 'rgba(15,23,42,0.09)',
    '--bg-sidebar': 'rgba(255,255,255,0.86)',
    '--bg-header': 'rgba(255,255,255,0.78)',
    '--border-soft': 'rgba(15,23,42,0.09)',
    '--border-faint': 'rgba(15,23,42,0.05)',
    '--text-primary': 'rgba(15,23,42,0.95)',
    '--text-secondary': 'rgba(15,23,42,0.66)',
    '--text-tertiary': 'rgba(15,23,42,0.46)',
    '--text-faint': 'rgba(15,23,42,0.32)',
    '--accent': '#2f90ff',
    '--accent-strong': '#1f6fe0',
    '--success': '#0ea371',
    '--danger': '#e11d48',
    '--warning': '#d97706',
    '--shadow-lg': '0 24px 60px rgba(15,23,42,0.10)',
    '--shadow-md': '0 10px 30px rgba(15,23,42,0.08)',
    '--radius-xl': '30px',
    '--radius-lg': '22px',
    '--radius-md': '16px',

    /* Card & surface tokens — solid white cards, crisp hairlines, quiet shadows */
    '--card-bg': '#ffffff',
    '--card-ring': 'rgba(15,23,42,0.07)',
    '--card-shadow-sm': '0 1px 2px rgba(15,23,42,0.04), 0 10px 24px rgba(15,23,42,0.05)',
    '--card-shadow': '0 1px 2px rgba(15,23,42,0.04), 0 14px 34px rgba(15,23,42,0.07)',
    '--card-shadow-lg': '0 1px 2px rgba(15,23,42,0.05), 0 24px 54px rgba(15,23,42,0.09)',
    '--inset-bg': 'rgba(15,23,42,0.045)',
    '--chip-bg': 'rgba(15,23,42,0.05)',
    '--news-bg': 'rgba(15,23,42,0.03)',

    /* Semantic tints — saturated enough to read as deliberate on white */
    '--success-soft': 'rgba(14,163,113,0.10)',
    '--success-softer': 'rgba(14,163,113,0.06)',
    '--success-ring': 'rgba(14,163,113,0.22)',
    '--success-text': '#047857',
    '--danger-soft': 'rgba(225,29,72,0.09)',
    '--danger-softer': 'rgba(225,29,72,0.05)',
    '--danger-ring': 'rgba(225,29,72,0.20)',
    '--danger-text': '#be123c',
    '--warning-soft': 'rgba(217,119,6,0.10)',
    '--warning-ring': 'rgba(217,119,6,0.22)',
    '--warning-text': '#b45309',
    '--info-soft': 'rgba(2,132,199,0.09)',
    '--info-text': '#0369a1',
    '--info-dot': '#0284c7',
    '--level-accent': '#7c3aed',
    '--level-soft': 'rgba(124,58,237,0.10)',

    /* Signal price panels — tinted surfaces instead of dark gradients */
    '--panel-entry-bg': 'linear-gradient(180deg,#f8fafc,#eef2f7)',
    '--panel-entry-ring': 'rgba(15,23,42,0.08)',
    '--panel-tp-bg': 'linear-gradient(180deg,#effdf6,#e3f8ee)',
    '--panel-tp-ring': 'rgba(14,163,113,0.20)',
    '--panel-sl-bg': 'linear-gradient(180deg,#fff1f2,#fde8ea)',
    '--panel-sl-ring': 'rgba(225,29,72,0.16)',
    '--panel-shadow': 'inset 0 1px 0 rgba(255,255,255,0.7), 0 10px 24px rgba(15,23,42,0.06)',

    /* Toast / floating panels */
    '--toast-bg': '#ffffff',
    '--toast-border': 'rgba(15,23,42,0.09)',
    '--toast-track': 'rgba(15,23,42,0.06)',
}

const themeVars = computed(() => (isDark.value ? darkThemeVars : lightThemeVars))

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
            <div
                class="absolute inset-0"
                :class="isDark
                    ? 'bg-[radial-gradient(circle_at_top_left,rgba(71,163,255,0.16),transparent_24%),radial-gradient(circle_at_top_right,rgba(92,200,255,0.08),transparent_20%),radial-gradient(circle_at_bottom_left,rgba(64,122,255,0.07),transparent_24%),linear-gradient(180deg,#071019_0%,#0a1220_46%,#061019_100%)]'
                    : 'bg-[linear-gradient(180deg,#f8fafc_0%,#f4f6fb_52%,#eef2f8_100%)]'"
            ></div>
            <div
                v-if="isDark"
                class="absolute inset-0 opacity-[0.045] [background-image:linear-gradient(to_right,rgba(148,163,184,0.16)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.16)_1px,transparent_1px)] [background-size:28px_28px]"
            ></div>
            <div
                class="absolute inset-y-0 hidden w-px bg-[var(--border-faint)] transition-all duration-300 lg:block"
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
                    class="flex h-full flex-col pb-4 pt-5 transition-all duration-300"
                    :class="sidebarCollapsed ? 'px-3' : 'px-4'"
                >
                    <!-- Brand (collapse control lives here, not orphaned at the bottom) -->
                    <div
                        class="flex items-center"
                        :class="sidebarCollapsed ? 'flex-col gap-3' : 'gap-3 px-1'"
                    >
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[16px] bg-[linear-gradient(135deg,#69d2ff,#2f90ff_60%,#2b63ff)] shadow-[0_10px_30px_rgba(47,144,255,0.32)]">
                            <svg class="h-[18px] w-[18px] text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 15l4-4 4 3 7-8" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 6h2v2" />
                            </svg>
                        </div>

                        <div v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold leading-tight text-[var(--text-primary)]">Market Sharks</p>
                            <p class="mt-0.5 truncate text-[10px] uppercase tracking-[0.2em] text-[var(--text-faint)]">Smart Trading</p>
                        </div>

                        <button
                            type="button"
                            class="hidden h-8 w-8 shrink-0 items-center justify-center rounded-xl text-[var(--text-faint)] transition hover:bg-[var(--bg-elevated)] hover:text-[var(--text-secondary)] lg:flex"
                            :aria-label="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                            :title="sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
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
                        </button>
                    </div>

                    <!-- Navigation — grouped in the same card surface the dashboard uses -->
                    <nav
                        class="mt-8 rounded-[22px] bg-[var(--card-bg)] p-1.5 ring-1 ring-[var(--card-ring)]"
                    >
                        <Link
                            v-for="item in navigation"
                            :key="item.label"
                            :href="item.href"
                            class="flex items-center rounded-[16px] text-sm font-medium transition"
                            :class="[
                                item.active
                                    ? 'bg-[var(--info-soft)] text-[var(--text-primary)]'
                                    : 'text-[var(--text-secondary)] hover:bg-[var(--bg-elevated)] hover:text-[var(--text-primary)]',
                                sidebarCollapsed
                                    ? 'justify-center px-0 py-3'
                                    : 'gap-3 px-3 py-2.5'
                            ]"
                            @click="sidebarOpen = false"
                        >
                            <span
                                class="flex h-5 w-5 shrink-0 items-center justify-center"
                                :class="item.active ? 'text-[var(--accent)]' : ''"
                            >
                                <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                    <path :d="item.icon" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>

                            <span v-if="!sidebarCollapsed">{{ item.label }}</span>
                        </Link>
                    </nav>

                    <!-- User — anchored in a matching card; sign out reveals inside it -->
                    <div class="mt-auto rounded-[22px] bg-[var(--card-bg)] p-1.5 ring-1 ring-[var(--card-ring)]">
                        <Transition
                            enter-active-class="transition duration-150 ease-out"
                            enter-from-class="opacity-0 translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition duration-100 ease-in"
                            leave-from-class="opacity-100 translate-y-0"
                            leave-to-class="opacity-0 translate-y-1"
                        >
                            <div v-if="userMenuOpen" class="mb-1">
                                <Link
                                    :href="route('logout')"
                                    method="post"
                                    as="button"
                                    class="flex w-full items-center rounded-[16px] text-sm font-medium text-[var(--text-secondary)] transition hover:bg-[var(--danger-soft)] hover:text-[var(--danger-text)]"
                                    :class="sidebarCollapsed ? 'justify-center px-0 py-2.5' : 'gap-3 px-3 py-2.5'"
                                >
                                    <span class="flex h-5 w-5 shrink-0 items-center justify-center">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12H3m0 0l4-4m-4 4l4 4M13 5h4a2 2 0 012 2v10a2 2 0 01-2 2h-4" />
                                        </svg>
                                    </span>
                                    <span v-if="!sidebarCollapsed">Sign out</span>
                                </Link>
                            </div>
                        </Transition>

                        <button
                            type="button"
                            class="flex w-full items-center rounded-[16px] text-left transition hover:bg-[var(--bg-elevated)]"
                            :class="sidebarCollapsed ? 'justify-center px-0 py-2' : 'gap-3 px-2 py-2'"
                            :aria-expanded="userMenuOpen"
                            aria-label="Account menu"
                            @click="userMenuOpen = !userMenuOpen"
                        >
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[14px] bg-[linear-gradient(135deg,#69d2ff,#2f90ff)] text-[13px] font-semibold text-white shadow-[0_8px_20px_rgba(47,144,255,0.28)]">
                                {{ user?.name?.charAt(0)?.toUpperCase() ?? 'T' }}
                            </div>

                            <template v-if="!sidebarCollapsed">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[13px] font-medium leading-tight text-[var(--text-primary)]">{{ user?.name ?? 'Team User' }}</p>
                                    <p class="truncate text-[11.5px] text-[var(--text-tertiary)]">{{ user?.email ?? 'team@marketsharks.local' }}</p>
                                </div>

                                <svg
                                    class="mr-1 h-4 w-4 shrink-0 text-[var(--text-faint)] transition-transform duration-200"
                                    :class="userMenuOpen ? 'rotate-180' : ''"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 15l6-6 6 6" />
                                </svg>
                            </template>
                        </button>
                    </div>
                </div>
            </aside>

            <div
                class="flex min-w-0 flex-1 flex-col transition-all duration-300"
                :style="{ paddingLeft: `min(${sidebarWidth}, 100vw)` }"
            >
                <header class="sticky top-0 z-30 border-b border-[var(--border-faint)] bg-[var(--bg-header)] backdrop-blur-2xl">
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

                            <div class="inline-flex items-center gap-2 rounded-full bg-[var(--success-soft)] px-3 py-2 text-[12px] font-medium text-[var(--success)]">
                                <span class="h-2 w-2 rounded-full bg-[var(--success)] animate-pulse"></span>
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
                                class="ml-1 text-[var(--text-secondary)] underline decoration-current/10 underline-offset-4 transition hover:text-[var(--text-primary)] hover:decoration-current/30"
                            >
                                Robo Coders
                            </a>
                        </p>
                    </footer>
                </main>
            </div>
        </div>
        <ChatWidget />
    </div>
</template>