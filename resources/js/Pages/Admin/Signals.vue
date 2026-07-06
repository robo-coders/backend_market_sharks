<script setup>
import axios from 'axios'
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import Swal from 'sweetalert2'

defineOptions({
    layout: AdminLayout,
})

const notifyToast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2200,
    timerProgressBar: true,
    didOpen: toastEl => {
        toastEl.addEventListener('mouseenter', Swal.stopTimer)
        toastEl.addEventListener('mouseleave', Swal.resumeTimer)
    },
})

const props = defineProps({
    signal: { type: Object, default: null },
    structure: { type: Object, default: null },
    trend: { type: Object, default: null },
    logs: { type: Array, default: () => [] },
    livePriceEndpoint: { type: String, default: '' },
    signalStoreUrl: { type: String, default: '' },
    // New — actually updates the existing open signal instead of trying
    // to create a second one (which the "already open" guard now blocks).
    signalUpdateUrl: { type: String, default: '' },
    structureUpdateUrl: { type: String, default: '' },
    trendUpdateUrl: { type: String, default: '' },
    logsExportUrl: { type: String, default: '' },
    closeSignalUrl: { type: String, default: '' },
})

// Whether we're editing an existing open signal (PUT to update it) or
// there's no open signal yet (POST to create one). Previously this page
// always POSTed to store(), which — now that duplicate-open signals are
// blocked server-side — meant editing an existing signal always failed.
const isEditingExisting = computed(() => Boolean(props.signal?.id) && props.signal?.status !== 'closed')

const initialSignal = {
    symbol: props.signal?.symbol ?? 'XAUUSD',
    side: props.signal?.side ?? props.signal?.signal_type ?? 'sell',
    updated_at: props.signal?.updated_at ?? '30 Jun 2026, 10:10 PM',
    entry_price: props.signal?.entry_price ?? '2345.10',
    take_profit: props.signal?.take_profit ?? '2358.90',
    stop_loss: props.signal?.stop_loss ?? '2338.40',
}

const initialStructure = {
    support_1: props.structure?.support_1 ?? '2340',
    support_2: props.structure?.support_2 ?? '2334.5',
    support_3: props.structure?.support_3 ?? '2328.2',
    resistance_1: props.structure?.resistance_1 ?? '2352.1',
    resistance_2: props.structure?.resistance_2 ?? '2358.9',
    resistance_3: props.structure?.resistance_3 ?? '2366.3',
}

const initialTrend = {
    gold: props.trend?.gold_trend ?? props.trend?.gold ?? 'buy',
    dollar: props.trend?.dollar_trend ?? props.trend?.dollar ?? 'neutral',
}

const signal = reactive({ ...initialSignal })
const structure = reactive({ ...initialStructure })
const trend = reactive({ ...initialTrend })

const trendView = ref('gold')

const goldLivePrice = ref(Number(props.signal?.gold_price_at_entry ?? props.signal?.gold_live_price ?? 2348.72))
const previousPrice = ref(goldLivePrice.value)
const priceLoading = ref(true)
const priceError = ref(false)
const priceStale = ref(false)
const priceUpdatedAt = ref(null)
let pollTimer = null

const priceDirection = computed(() => {
    if (goldLivePrice.value > previousPrice.value) return 'up'
    if (goldLivePrice.value < previousPrice.value) return 'down'
    return 'flat'
})

const formattedLivePrice = computed(() =>
    goldLivePrice.value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
)

const lastSyncedLabel = computed(() => {
    if (!priceUpdatedAt.value) return 'Syncing…'
    return priceUpdatedAt.value.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })
})

const feedStatus = computed(() => {
    if (priceError.value) {
        return { label: 'Feed offline', dot: 'bg-rose-500', pulse: false }
    }
    if (priceStale.value) {
        return { label: 'Feed delayed', dot: 'bg-amber-500', pulse: false }
    }
    return { label: 'Live feed active', dot: 'bg-emerald-500', pulse: true }
})

const fetchGoldPrice = async () => {
    priceError.value = false

    try {
        if (props.livePriceEndpoint) {
            const res = await fetch(props.livePriceEndpoint, {
                headers: { Accept: 'application/json' },
            })

            if (!res.ok) throw new Error('Bad response')

            const data = await res.json()
            const next = Number(data.price ?? data.gold_live_price ?? data.value)

            if (Number.isFinite(next)) {
                previousPrice.value = goldLivePrice.value
                goldLivePrice.value = next
            }

            priceStale.value = Boolean(data.stale)
        } else {
            previousPrice.value = goldLivePrice.value
            const drift = (Math.random() - 0.5) * 1.6
            goldLivePrice.value = Math.round((goldLivePrice.value + drift) * 100) / 100
            priceStale.value = false
        }

        priceUpdatedAt.value = new Date()
    } catch (e) {
        priceError.value = true
    } finally {
        priceLoading.value = false
    }
}

onMounted(() => {
    fetchGoldPrice()
    pollTimer = setInterval(fetchGoldPrice, 5000)
})

const sideLabel = computed(() => (signal.side === 'buy' ? 'Buy' : 'Sell'))
const sideTextClass = computed(() =>
    signal.side === 'buy' ? 'text-emerald-600' : 'text-rose-600'
)

const currentTrendValue = computed({
    get() {
        return trend[trendView.value]
    },
    set(value) {
        trend[trendView.value] = value
    },
})

const currentTrendLabel = computed(() =>
    trendView.value === 'gold' ? 'Gold' : 'Dollar'
)

const trendTone = value => {
    if (value === 'buy') return 'border-emerald-100 bg-emerald-50 text-emerald-700'
    if (value === 'sell') return 'border-rose-100 bg-rose-50 text-rose-700'
    return 'border-amber-100 bg-amber-50 text-amber-700'
}

const savingSignal = ref(false)
const savingStructure = ref(false)
const savingTrend = ref(false)
const closingSignal = ref(false)

const stampSignalUpdatedAt = () => {
    signal.updated_at = new Date().toLocaleString('en-US', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const updateSignal = () => {
    savingSignal.value = true

    const payload = {
        symbol: signal.symbol,
        signal_type: signal.side,
        entry_price: signal.entry_price,
        take_profit: signal.take_profit,
        stop_loss: signal.stop_loss,
        gold_price_at_entry: Number(goldLivePrice.value.toFixed(2)),
    }

    // Editing an existing open signal → PUT to the update endpoint.
    // Creating a fresh signal (none currently open) → POST to store.
    if (isEditingExisting.value) {
        if (!props.signalUpdateUrl) {
            savingSignal.value = false
            notifyToast.fire({ icon: 'error', title: 'Update endpoint not configured' })
            return
        }

        router.put(props.signalUpdateUrl, payload, {
            preserveScroll: true,
            onSuccess: () => {
                stampSignalUpdatedAt()
                notifyToast.fire({ icon: 'success', title: 'Signal updated' })
            },
            onError: () => {
                notifyToast.fire({ icon: 'error', title: 'Failed to update signal' })
            },
            onFinish: () => {
                savingSignal.value = false
            },
        })
        return
    }

    if (!props.signalStoreUrl) {
        savingSignal.value = false
        notifyToast.fire({ icon: 'error', title: 'No signal endpoint configured' })
        return
    }

    router.post(props.signalStoreUrl, payload, {
        preserveScroll: true,
        onSuccess: () => {
            stampSignalUpdatedAt()
            notifyToast.fire({ icon: 'success', title: 'Signal created' })
        },
        onError: (errors) => {
            const message = errors?.message ?? 'Failed to create signal'
            notifyToast.fire({ icon: 'error', title: message })
        },
        onFinish: () => {
            savingSignal.value = false
        },
    })
}

const updateStructure = () => {
    savingStructure.value = true

    const payload = {
        resistance_1: structure.resistance_1,
        resistance_2: structure.resistance_2,
        resistance_3: structure.resistance_3,
        support_1: structure.support_1,
        support_2: structure.support_2,
        support_3: structure.support_3,
    }

    if (!props.structureUpdateUrl) {
        savingStructure.value = false
        return
    }

    router.put(props.structureUpdateUrl, payload, {
        preserveScroll: true,
        onSuccess: () => {
            stampSignalUpdatedAt()
            notifyToast.fire({
                icon: 'success',
                title: 'Structure updated',
            })
        },
        onFinish: () => {
            savingStructure.value = false
        },
    })
}

const updateTrend = async () => {
    savingTrend.value = true

    if (!props.trendUpdateUrl) {
        savingTrend.value = false
        return
    }

    try {
        const payload = {
            gold_trend: trend.gold,
            dollar_trend: trend.dollar,
        }

        const { data } = await axios.put(props.trendUpdateUrl, payload)

        if (data?.data) {
            trend.gold = data.data.gold_trend ?? trend.gold
            trend.dollar = data.data.dollar_trend ?? trend.dollar
        }

        notifyToast.fire({
            icon: 'success',
            title: 'Trend updated',
        })
    } catch (error) {
        notifyToast.fire({
            icon: 'error',
            title: error?.response?.data?.message ?? 'Failed to update trend',
        })
    } finally {
        savingTrend.value = false
    }
}

const resetSignal = () => {
    Object.assign(signal, initialSignal)
}

const resetStructure = () => {
    Object.assign(structure, initialStructure)
}

const resetTrend = () => {
    Object.assign(trend, initialTrend)
    trendView.value = 'gold'
}

const downloadLogs = () => {
    if (!props.logsExportUrl) return
    window.open(props.logsExportUrl, '_blank')
}

const closeActiveSignal = () => {
    if (!props.closeSignalUrl) {
        notifyToast.fire({
            icon: 'error',
            title: 'No open signal to close',
        })
        return
    }

    Swal.fire({
        icon: 'warning',
        title: 'Close active signal?',
        text: 'Are you sure you want to close the active signal?',
        showCancelButton: true,
        confirmButtonText: 'Yes, close it',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        reverseButtons: true,
    }).then(result => {
        if (!result.isConfirmed) return

        closingSignal.value = true

        router.post(props.closeSignalUrl, {}, {
            preserveScroll: true,
            onSuccess: () => {
                notifyToast.fire({
                    icon: 'success',
                    title: 'Signal closed',
                })
            },
            onError: () => {
                notifyToast.fire({
                    icon: 'error',
                    title: 'Failed to close signal',
                })
            },
            onFinish: () => {
                closingSignal.value = false
            },
        })
    })
}

// Real counts derived from the logs the backend actually sent, instead
// of hardcoded "2 Profit / 1 Loss" text that never matched reality.
const profitCount = computed(() => props.logs.filter(log => log.result === 'Profit').length)
const lossCount = computed(() => props.logs.filter(log => log.result === 'Loss').length)
const breakevenCount = computed(() => props.logs.filter(log => log.result === 'Breakeven').length)

const logTone = value => {
    if (value === 'Profit') return { badge: 'bg-emerald-100 text-emerald-700', icon: 'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-100', ring: 'group-hover:border-emerald-100 group-hover:shadow-[0_4px_16px_rgba(16,185,129,0.08)]' }
    if (value === 'Loss') return { badge: 'bg-rose-100 text-rose-700', icon: 'bg-rose-50 text-rose-600 group-hover:bg-rose-100', ring: 'group-hover:border-rose-100 group-hover:shadow-[0_4px_16px_rgba(244,63,94,0.08)]' }
    return { badge: 'bg-amber-100 text-amber-700', icon: 'bg-amber-50 text-amber-600 group-hover:bg-amber-100', ring: 'group-hover:border-amber-100 group-hover:shadow-[0_4px_16px_rgba(245,158,11,0.08)]' }
}

const logIconPath = value => {
    if (value === 'Profit') return 'M7 17L17 7m0 0H9m8 0v8'
    if (value === 'Loss') return 'M7 7l10 10m0 0V9m0 8H9'
    return 'M4 12h16'
}

onBeforeUnmount(() => {
    if (pollTimer) clearInterval(pollTimer)
})
</script>

<template>
    <Head title="Signals" />

    <div class="mx-auto w-full max-w-[1180px] space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                    Trading
                </p>
                <h1 class="mt-1.5 text-[28px] font-bold tracking-tight text-slate-900">
                    Signals
                </h1>
                <p class="mt-1 text-sm text-slate-500">
                    Configure the live signal shown to your subscribers.
                </p>
            </div>

            <div class="inline-flex items-center gap-2 self-start rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 shadow-sm">
                <span class="relative flex h-2 w-2">
                    <span
                        v-if="feedStatus.pulse"
                        class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                        :class="feedStatus.dot"
                    ></span>
                    <span
                        class="relative inline-flex h-2 w-2 rounded-full"
                        :class="feedStatus.dot"
                    ></span>
                </span>
                {{ feedStatus.label }}
            </div>
        </section>

        <section class="space-y-4">
            <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-[minmax(0,0.92fr)_420px]">
                <div class="flex h-full flex-col overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_12px_34px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-indigo-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                    Signal
                                    <span v-if="isEditingExisting" class="ml-1 text-indigo-400">· Editing active signal</span>
                                    <span v-else class="ml-1 text-indigo-400">· No active signal</span>
                                </div>

                                <div class="mt-4 flex flex-wrap items-center gap-3">
                                    <h2
                                        class="text-[42px] font-bold leading-none tracking-tight transition-colors duration-300 sm:text-[48px]"
                                        :class="sideTextClass"
                                    >
                                        {{ sideLabel }}
                                    </h2>

                                    <span class="rounded-full border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-600">
                                        {{ signal.symbol }}
                                    </span>
                                </div>
                            </div>

                            <div class="grid w-full gap-3 sm:w-auto sm:min-w-[236px]">
                                <div class="flex items-stretch gap-2">
                                    <div class="flex-1 rounded-2xl border border-slate-200 bg-slate-50 p-1">
                                        <div class="grid grid-cols-2 gap-1">
                                            <button
                                                type="button"
                                                class="h-10 rounded-xl px-4 text-sm font-semibold transition"
                                                :class="signal.side === 'buy'
                                                    ? 'bg-white text-emerald-700 shadow-sm ring-1 ring-emerald-100'
                                                    : 'text-slate-500 hover:text-slate-900'"
                                                @click="signal.side = 'buy'"
                                            >
                                                Buy
                                            </button>
                                            <button
                                                type="button"
                                                class="h-10 rounded-xl px-4 text-sm font-semibold transition"
                                                :class="signal.side === 'sell'
                                                    ? 'bg-white text-rose-700 shadow-sm ring-1 ring-rose-100'
                                                    : 'text-slate-500 hover:text-slate-900'"
                                                @click="signal.side = 'sell'"
                                            >
                                                Sell
                                            </button>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        title="Emergency close active signal"
                                        class="group inline-flex shrink-0 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 px-3 text-rose-600 shadow-[0_0_0_3px_rgba(244,63,94,0.06)] transition hover:border-rose-300 hover:bg-rose-600 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                                        :disabled="closingSignal"
                                        @click="closeActiveSignal"
                                    >
                                        <svg v-if="!closingSignal" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                            <circle cx="12" cy="12" r="8.5" fill="currentColor" fill-opacity="0.12" />
                                            <circle cx="12" cy="12" r="6" fill="currentColor" />
                                        </svg>
                                        <svg v-else class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                            Last Updated
                                        </p>
                                    </div>
                                    <input
                                        :value="signal.updated_at"
                                        type="text"
                                        readonly
                                        class="mt-2 h-10 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-4 p-5 sm:p-6">
                        <div class="relative overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 px-5 py-4">
                            <div
                                class="pointer-events-none absolute inset-0 opacity-[0.07]"
                                style="background-image: radial-gradient(circle at 20% 20%, #fff 1px, transparent 1px); background-size: 22px 22px;"
                            ></div>

                            <div class="relative flex items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                            Gold live price
                                        </p>
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider"
                                            :class="priceError
                                                ? 'bg-rose-500/15 text-rose-400'
                                                : priceStale
                                                    ? 'bg-amber-500/15 text-amber-400'
                                                    : 'bg-emerald-500/15 text-emerald-400'"
                                        >
                                            <span
                                                class="h-1 w-1 rounded-full"
                                                :class="priceError ? 'bg-rose-400' : priceStale ? 'bg-amber-400' : 'bg-emerald-400'"
                                            ></span>
                                            {{ priceError ? 'Offline' : priceStale ? 'Delayed' : 'Live' }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-1.5 text-[11px] text-slate-500">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                        </svg>
                                        Read only · synced {{ lastSyncedLabel }}
                                    </div>
                                </div>

                                <div class="flex items-center gap-3">
                                    <div v-if="priceLoading" class="h-8 w-32 animate-pulse rounded-lg bg-slate-700/60"></div>

                                    <template v-else>
                                        <span
                                            class="text-[30px] font-bold leading-none tracking-tight tabular-nums transition-colors duration-300"
                                            :class="priceDirection === 'up' ? 'text-emerald-400' : priceDirection === 'down' ? 'text-rose-400' : 'text-white'"
                                        >
                                            {{ formattedLivePrice }}
                                        </span>
                                        <svg
                                            v-if="priceDirection !== 'flat'"
                                            class="h-5 w-5 transition"
                                            :class="priceDirection === 'up' ? 'text-emerald-400' : 'text-rose-400 rotate-180'"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                        >
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                        </svg>
                                    </template>

                                    <button
                                        type="button"
                                        class="rounded-lg border border-slate-700 p-2 text-slate-400 transition hover:border-slate-500 hover:text-white disabled:opacity-50"
                                        :disabled="priceLoading"
                                        title="Refresh price"
                                        @click="fetchGoldPrice"
                                    >
                                        <svg class="h-4 w-4" :class="priceLoading && 'animate-spin'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <label class="group cursor-text rounded-2xl border border-slate-200 bg-slate-50 p-4 transition focus-within:border-indigo-300 focus-within:bg-white focus-within:ring-4 focus-within:ring-indigo-100">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Entry</span>
                                    <svg class="h-3.5 w-3.5 text-slate-300 transition group-focus-within:text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="signal.entry_price"
                                    inputmode="decimal"
                                    class="mt-3 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-[26px] font-bold tracking-tight tabular-nums text-slate-900 outline-none transition focus:border-indigo-300 focus:ring-4 focus:ring-indigo-100"
                                />
                            </label>

                            <label class="group cursor-text rounded-2xl border border-emerald-100 bg-emerald-50 p-4 transition focus-within:border-emerald-300 focus-within:ring-4 focus-within:ring-emerald-100">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-emerald-700">Take profit</span>
                                    <svg class="h-3.5 w-3.5 text-emerald-300 transition group-focus-within:text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="signal.take_profit"
                                    inputmode="decimal"
                                    class="mt-3 h-11 w-full rounded-xl border border-emerald-200 bg-white px-3 text-[26px] font-bold tracking-tight tabular-nums text-emerald-700 outline-none transition focus:border-emerald-300 focus:ring-4 focus:ring-emerald-100"
                                />
                            </label>

                            <label class="group cursor-text rounded-2xl border border-rose-100 bg-rose-50 p-4 transition focus-within:border-rose-300 focus-within:ring-4 focus-within:ring-rose-100">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-rose-700">Stop loss</span>
                                    <svg class="h-3.5 w-3.5 text-rose-300 transition group-focus-within:text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="signal.stop_loss"
                                    inputmode="decimal"
                                    class="mt-3 h-11 w-full rounded-xl border border-rose-200 bg-white px-3 text-[26px] font-bold tracking-tight tabular-nums text-rose-700 outline-none transition focus:border-rose-300 focus:ring-4 focus:ring-rose-100"
                                />
                            </label>
                        </div>
                    </div>

                    <div class="mt-auto border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:px-6">
                        <div class="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:opacity-50"
                                :disabled="savingSignal"
                                @click="resetSignal"
                            >
                                Reset
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
                                :disabled="savingSignal"
                                @click="updateSignal"
                            >
                                <svg v-if="savingSignal" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                {{ savingSignal ? 'Saving…' : (isEditingExisting ? 'Update Signal' : 'Create Signal') }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex h-full flex-col overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_12px_34px_rgba(15,23,42,0.06)]">
                    <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-indigo-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                            Market structure
                        </div>
                    </div>

                    <div class="space-y-6 p-5 sm:p-6">
                        <div>
                            <p class="mb-4 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Resistance
                            </p>
                            <div class="grid grid-cols-3 gap-3.5">
                                <label
                                    v-for="key in ['resistance_1', 'resistance_2', 'resistance_3']"
                                    :key="key"
                                    class="cursor-text"
                                >
                                    <span class="mb-2 block text-[11px] font-semibold text-slate-500">
                                        {{ 'R' + key.slice(-1) }}
                                    </span>
                                    <input
                                        v-model="structure[key]"
                                        inputmode="decimal"
                                        class="h-[70px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-[26px] font-bold tracking-tight tabular-nums text-slate-900 outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                    />
                                </label>
                            </div>
                        </div>

                        <div>
                            <p class="mb-4 text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                Support
                            </p>
                            <div class="grid grid-cols-3 gap-3.5">
                                <label
                                    v-for="key in ['support_1', 'support_2', 'support_3']"
                                    :key="key"
                                    class="cursor-text"
                                >
                                    <span class="mb-2 block text-[11px] font-semibold text-slate-500">
                                        {{ 'S' + key.slice(-1) }}
                                    </span>
                                    <input
                                        v-model="structure[key]"
                                        inputmode="decimal"
                                        class="h-[70px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 text-[26px] font-bold tracking-tight tabular-nums text-slate-900 outline-none transition focus:border-indigo-300 focus:bg-white focus:ring-4 focus:ring-indigo-100"
                                    />
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto border-t border-slate-100 bg-slate-50/70 px-5 py-4 sm:px-6">
                        <div class="flex items-center justify-end gap-2">
                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50 disabled:opacity-50"
                                :disabled="savingStructure"
                                @click="resetStructure"
                            >
                                Reset
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
                                :disabled="savingStructure"
                                @click="updateStructure"
                            >
                                <svg v-if="savingStructure" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                </svg>
                                {{ savingStructure ? 'Saving…' : 'Update' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,0.78fr)_minmax(0,1.22fr)]">
            <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_12px_34px_rgba(15,23,42,0.06)]">
                <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="flex items-center justify-between gap-3">
                        <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-indigo-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                            Market trend
                        </div>
                        <span class="rounded-full border px-2.5 py-1 text-xs font-medium capitalize transition" :class="trendTone(currentTrendValue)">
                            {{ currentTrendValue }}
                        </span>
                    </div>
                </div>

                <div class="space-y-4 p-5 sm:p-6">
                    <div class="grid grid-cols-2 gap-1 rounded-2xl border border-slate-200 bg-slate-50 p-1">
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                            :class="trendView === 'gold'
                                ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                : 'text-slate-500 hover:text-slate-900'"
                            @click="trendView = 'gold'"
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold transition"
                                :class="trendView === 'gold' ? 'bg-amber-100 text-amber-700' : 'bg-slate-200 text-slate-500'"
                            >Au</span>
                            Gold
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                            :class="trendView === 'dollar'
                                ? 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200'
                                : 'text-slate-500 hover:text-slate-900'"
                            @click="trendView = 'dollar'"
                        >
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full text-[11px] font-bold transition"
                                :class="trendView === 'dollar' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-500'"
                            >$</span>
                            Dollar
                        </button>
                    </div>

                    <div class="rounded-[20px] border border-slate-200 bg-slate-50 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-base font-semibold text-slate-900">
                                    {{ currentTrendLabel }}
                                </p>
                                <p class="mt-1 text-[11px] text-slate-400">
                                    Current market bias
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                                    Last Updated
                                </p>
                                <p class="mt-1 text-xs font-medium text-slate-500">
                                    {{ trend.updated_at }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-3 gap-2">
                            <button
                                type="button"
                                class="rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                                :class="currentTrendValue === 'buy'
                                    ? 'border border-emerald-100 bg-emerald-50 text-emerald-700'
                                    : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                @click="currentTrendValue = 'buy'"
                            >
                                Buy
                            </button>
                            <button
                                type="button"
                                class="rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                                :class="currentTrendValue === 'neutral'
                                    ? 'border border-amber-100 bg-amber-50 text-amber-700'
                                    : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                @click="currentTrendValue = 'neutral'"
                            >
                                Neutral
                            </button>
                            <button
                                type="button"
                                class="rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                                :class="currentTrendValue === 'sell'
                                    ? 'border border-rose-100 bg-rose-50 text-rose-700'
                                    : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                                @click="currentTrendValue = 'sell'"
                            >
                                Sell
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                        :disabled="savingTrend"
                        @click="resetTrend"
                    >
                        Reset
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-70"
                        :disabled="savingTrend"
                        @click="updateTrend"
                    >
                        <svg v-if="savingTrend" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                        </svg>
                        {{ savingTrend ? 'Saving…' : 'Update' }}
                    </button>
                </div>
            </div>

            <div class="overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-[0_1px_2px_rgba(15,23,42,0.04),0_12px_34px_rgba(15,23,42,0.06)]">
                <div class="relative overflow-hidden border-b border-slate-100 bg-gradient-to-br from-slate-50 via-white to-white px-5 py-5 sm:px-6">
                    <div
                        class="pointer-events-none absolute -right-10 -top-16 h-40 w-40 rounded-full bg-indigo-100/50 blur-3xl"
                    ></div>

                    <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-indigo-50 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-indigo-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                                Market logs
                            </div>
                            <p class="mt-3 text-base font-semibold tracking-tight text-slate-900">
                                Last 30 days market logs
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Operational outcomes and trade events from the previous 30 days
                            </p>

                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span v-if="profitCount" class="inline-flex items-center gap-1.5 rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    {{ profitCount }} Profit
                                </span>
                                <span v-if="lossCount" class="inline-flex items-center gap-1.5 rounded-full border border-rose-100 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                    {{ lossCount }} Loss
                                </span>
                                <span v-if="breakevenCount" class="inline-flex items-center gap-1.5 rounded-full border border-amber-100 bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    {{ breakevenCount }} Breakeven
                                </span>
                                <span class="inline-flex shrink-0 items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-500">
                                    {{ logs.length }} events
                                </span>
                            </div>
                        </div>

                        <button
                            type="button"
                            title="Download Excel"
                            class="inline-flex shrink-0 items-center gap-2 self-start rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-[0_10px_24px_rgba(15,23,42,0.16)] transition hover:bg-slate-800 hover:shadow-[0_12px_28px_rgba(15,23,42,0.22)] disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none"
                            :disabled="!logsExportUrl"
                            @click="downloadLogs"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v1a2 2 0 002 2h12a2 2 0 002-2v-1" />
                            </svg>
                            Export
                        </button>
                    </div>
                </div>

                <div class="market-logs-scroll relative max-h-[420px] space-y-1 overflow-y-auto p-5 sm:p-6">
                    <div
                        v-for="(log, index) in logs"
                        :key="`${log.time}-${index}`"
                        class="group relative flex items-start gap-4 rounded-2xl p-3 transition hover:bg-slate-50"
                    >
                        <span
                            class="relative z-10 mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-4 ring-white transition"
                            :class="logTone(log.result).icon"
                        >
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25">
                                <path :d="logIconPath(log.result)" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>
                        <div
                            class="min-w-0 flex-1 rounded-2xl border border-slate-100 bg-white p-4 shadow-[0_1px_2px_rgba(15,23,42,0.03)] transition"
                            :class="logTone(log.result).ring"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="logTone(log.result).badge">
                                        {{ log.result }}
                                    </span>
                                    <span class="text-sm font-semibold text-slate-900">{{ log.signal_type }}</span>
                                    <span class="text-sm text-slate-400">hit</span>
                                    <span class="text-sm font-medium text-slate-600">{{ log.hit_level }}</span>
                                </div>
                                <span class="text-[11px] font-medium text-slate-400">{{ log.time }}</span>
                            </div>
                            <div class="mt-2.5 flex items-center gap-1.5 text-sm">
                                <span class="font-medium text-slate-400">Execution</span>
                                <span class="font-semibold tabular-nums text-slate-900">{{ log.price }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!logs.length" class="flex flex-col items-center justify-center px-6 py-14 text-center">
                        <p class="text-[13px] font-medium text-slate-500">No trade events in the last 30 days</p>
                        <p class="mt-1 max-w-[15rem] text-[12px] leading-relaxed text-slate-400">
                            Closed trades will appear here as they happen.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<style scoped>
.market-logs-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(100, 116, 139, 0.28) transparent;
}
.market-logs-scroll::-webkit-scrollbar {
    width: 6px;
}
.market-logs-scroll::-webkit-scrollbar-thumb {
    background: rgba(100, 116, 139, 0.28);
    border-radius: 999px;
}
.market-logs-scroll::-webkit-scrollbar-track {
    background: transparent;
}
</style>