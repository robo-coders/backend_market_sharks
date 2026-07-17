<script setup>
import TeamLayout from '@/Layouts/TeamLayout.vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

const props = defineProps({
    updateUrl: { type: String, default: '' },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)

const theme = ref(user.value?.theme ?? 'dark')
const soundsOn = ref(!user.value?.alert_sounds_muted)
const saving = ref(false)

const persist = payload => {
    if (!props.updateUrl) return

    saving.value = true

    router.patch(props.updateUrl, payload, {
        preserveScroll: true,
        onFinish: () => {
            saving.value = false
        },
    })
}

const setTheme = value => {
    if (theme.value === value) return
    theme.value = value
    persist({ theme: value })
}

const toggleSounds = () => {
    soundsOn.value = !soundsOn.value
    persist({ alert_sounds_muted: !soundsOn.value })
}
</script>

<template>
    <Head title="Settings" />

    <TeamLayout>
        <div class="mx-auto w-full max-w-[720px] space-y-4">
            <section>
                <p class="text-[11px] uppercase tracking-[0.24em] text-[var(--text-faint)]">Preferences</p>
                <h1 class="mt-1 text-[22px] font-semibold tracking-tight text-[var(--text-primary)]">Settings</h1>
                <p class="mt-1 text-sm text-[var(--text-tertiary)]">Personal preferences for your terminal. Changes save automatically.</p>
            </section>

            <section class="rounded-[28px] bg-[var(--bg-elevated)] shadow-[var(--shadow-md)] ring-1 ring-[var(--border-soft)]">
                <!-- Theme -->
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-[var(--text-primary)]">Theme</p>
                        <p class="mt-1 text-[13px] leading-relaxed text-[var(--text-tertiary)]">
                            Applies across the whole terminal.
                        </p>
                    </div>

                    <div
                        class="grid w-full shrink-0 grid-cols-2 gap-1 rounded-2xl bg-[var(--bg-elevated-2)] p-1 ring-1 ring-[var(--border-faint)] sm:w-[220px]"
                        role="radiogroup"
                        aria-label="Theme"
                    >
                        <button
                            type="button"
                            role="radio"
                            :aria-checked="theme === 'light'"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-xl px-3 text-sm font-medium transition"
                            :class="theme === 'light'
                                ? 'bg-[var(--bg-canvas)] text-[var(--text-primary)] shadow-sm ring-1 ring-[var(--border-soft)]'
                                : 'text-[var(--text-tertiary)] hover:text-[var(--text-primary)]'"
                            :disabled="saving"
                            @click="setTheme('light')"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <circle cx="12" cy="12" r="4" />
                                <path stroke-linecap="round" d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4" />
                            </svg>
                            Light
                        </button>

                        <button
                            type="button"
                            role="radio"
                            :aria-checked="theme === 'dark'"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-xl px-3 text-sm font-medium transition"
                            :class="theme === 'dark'
                                ? 'bg-[var(--bg-canvas)] text-[var(--text-primary)] shadow-sm ring-1 ring-[var(--border-soft)]'
                                : 'text-[var(--text-tertiary)] hover:text-[var(--text-primary)]'"
                            :disabled="saving"
                            @click="setTheme('dark')"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.8A8.5 8.5 0 1 1 11.2 3a6.6 6.6 0 0 0 9.8 9.8Z" />
                            </svg>
                            Dark
                        </button>
                    </div>
                </div>

                <div class="h-px bg-[var(--border-faint)]" />

                <!-- Alert sounds -->
                <div class="flex items-center justify-between gap-4 p-5 sm:p-6">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-[var(--text-primary)]">Alert sounds</p>
                        <p class="mt-1 text-[13px] leading-relaxed text-[var(--text-tertiary)]">
                            Play a beep when signals, levels, and market updates arrive.
                        </p>
                    </div>

                    <button
                        type="button"
                        role="switch"
                        :aria-checked="soundsOn"
                        aria-label="Alert sounds"
                        class="relative inline-flex h-[26px] w-[46px] shrink-0 items-center rounded-full transition-colors duration-200"
                        :class="soundsOn
                            ? 'bg-[var(--accent-strong)]'
                            : 'bg-[var(--bg-elevated-2)] ring-1 ring-[var(--border-soft)]'"
                        :disabled="saving"
                        @click="toggleSounds"
                    >
                        <span
                            class="inline-block h-[20px] w-[20px] rounded-full bg-white shadow-[0_1px_3px_rgba(0,0,0,0.35)] transition-transform duration-200"
                            :class="soundsOn ? 'translate-x-[23px]' : 'translate-x-[3px]'"
                        />
                    </button>
                </div>
            </section>
        </div>
    </TeamLayout>
</template>