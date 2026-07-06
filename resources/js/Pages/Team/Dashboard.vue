<script setup>
import TeamLayout from '@/Layouts/TeamLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

const props = defineProps({
    market: { type: Object, required: true },
    trend: { type: Object, required: true },
    signal: { type: Object, required: true },
    levels: { type: Object, required: true },
    news: { type: Array, default: () => [] },
    logs: { type: Array, default: () => [] },
    livePriceEndpoint: { type: String, default: '' },
    logsExportUrl: { type: String, default: '' },
})

const normalizeTrendValue = value => {
    if (!value) return 'Neutral'
    const normalized = String(value).toLowerCase()
    if (normalized === 'buy') return 'Buy'
    if (normalized === 'sell') return 'Sell'
    return 'Neutral'
}

const mapTrendPayload = trend => ({
    gold: normalizeTrendValue(trend?.gold ?? trend?.gold_trend),
    dollar: normalizeTrendValue(trend?.dollar ?? trend?.dollar_trend),
    updated_at: trend?.updated_at ?? null,
})

const liveTrend = ref(mapTrendPayload(props.trend))
const liveSignal = ref({ ...props.signal })
const liveLevels = ref({
    supports: Array.isArray(props.levels?.supports) ? [...props.levels.supports] : [],
    resistances: Array.isArray(props.levels?.resistances) ? [...props.levels.resistances] : [],
})
const liveStructureUpdatedAt = ref(props.levels?.updated_at || null)
const liveTrendUpdatedAt = ref(props.trend?.updated_at || null)

const hasActiveSignal = ref(props.signal?.status === 'Active')

const liveLogs = ref([...props.logs])

const liveMarket = reactive({ ...props.market })
const previousPrice = ref(Number(String(props.market.live_price).replace(/,/g, '')) || 0)
let pricePollTimer = null

const lastPriceUpdate = ref(new Date())
const clockTick = ref(Date.now())
let clockTimer = null
let unlockHandler = null

const priceUpdatedLabel = computed(() => {
    const diffSec = Math.max(0, Math.round((clockTick.value - lastPriceUpdate.value.getTime()) / 1000))
    if (diffSec < 5) return 'Just now'
    if (diffSec < 60) return `${diffSec}s ago`
    const diffMin = Math.round(diffSec / 60)
    if (diffMin < 60) return `${diffMin}m ago`
    const diffHr = Math.round(diffMin / 60)
    return `${diffHr}h ago`
})

const displayedPriceLabel = ref(priceUpdatedLabel.value)
watch(priceUpdatedLabel, (next) => {
    if (next !== displayedPriceLabel.value) {
        displayedPriceLabel.value = next
    }
})

const formatPrice = value =>
    value.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })

const fetchLivePrice = async () => {
    if (!props.livePriceEndpoint) return

    try {
        const res = await fetch(props.livePriceEndpoint, {
            headers: { Accept: 'application/json' },
        })

        if (!res.ok) throw new Error('Bad response')

        const data = await res.json()
        const next = Number(data.price)

        if (Number.isFinite(next) && next > 0) {
            const prev = previousPrice.value
            const diff = prev ? next - prev : 0
            const diffPct = prev ? (diff / prev) * 100 : 0

            liveMarket.live_price = formatPrice(next)
            liveMarket.price_change = `${diff >= 0 ? '+' : ''}${diff.toFixed(2)}`
            liveMarket.price_change_percent = `${diff >= 0 ? '+' : ''}${diffPct.toFixed(2)}%`
            lastPriceUpdate.value = new Date()

            previousPrice.value = next
        }
    } catch (error) {
        console.error('Live gold price fetch failed:', error)
    }
}

const TOAST_DURATION_MS = 5600

const toast = ref({
    visible: false,
    kind: 'signal',
    type: 'Buy',
    title: '',
    symbol: '',
    detail: '',
    seq: 0,
})

const toastPaused = ref(false)
let toastTimeout = null
let toastRemaining = TOAST_DURATION_MS
let toastStartedAt = 0
let toastSeq = 0
let audioCtx = null

const signalAccent = computed(() => {
    if (!hasActiveSignal.value) {
        return {
            glow: 'shadow-[0_0_22px_rgba(148,163,184,0.18)]',
            pill: 'bg-white/6 text-white/48',
            dot: 'bg-white/30',
            text: 'text-white/40',
            surface: 'from-white/[0.04] to-transparent',
        }
    }

    return liveSignal.value.type !== 'Sell'
        ? {
            glow: 'shadow-[0_0_22px_rgba(61,220,151,0.3)]',
            pill: 'bg-emerald-400/12 text-emerald-200',
            dot: 'bg-emerald-300',
            text: 'text-emerald-300',
            surface: 'from-emerald-400/[0.11] to-transparent',
        }
        : {
            glow: 'shadow-[0_0_22px_rgba(255,107,129,0.26)]',
            pill: 'bg-rose-400/12 text-rose-200',
            dot: 'bg-rose-300',
            text: 'text-rose-300',
            surface: 'from-rose-400/[0.11] to-transparent',
        }
})

const trendTone = value => {
    if (value === 'Buy') return 'bg-emerald-400/12 text-emerald-300 ring-1 ring-emerald-400/20'
    if (value === 'Sell') return 'bg-rose-400/12 text-rose-300 ring-1 ring-rose-400/20'
    return 'bg-amber-400/12 text-amber-300 ring-1 ring-amber-300/20'
}

const trendDot = value => {
    if (value === 'Buy') return 'bg-emerald-300 shadow-[0_0_14px_rgba(110,231,183,0.45)]'
    if (value === 'Sell') return 'bg-rose-300 shadow-[0_0_14px_rgba(253,164,175,0.45)]'
    return 'bg-amber-300 shadow-[0_0_14px_rgba(252,211,77,0.4)]'
}

const logTone = value => {
    if (value === 'Profit') return 'bg-emerald-400/10 text-emerald-300'
    if (value === 'Loss') return 'bg-rose-400/10 text-rose-300'
    return 'bg-amber-400/10 text-amber-300'
}

const logIconPath = value => {
    if (value === 'Profit') return 'M5 13l4 4L19 7'
    if (value === 'Loss') return 'M6 6l12 12M18 6L6 18'
    return 'M4 9h16M4 15h16'
}

const formatUpdatedAt = value => {
    if (!value || value === '—') return 'Not yet updated'
    return value
}

const toastTone = computed(() => {
    if (toast.value.kind === 'structure') {
        return {
            accent: 'var(--info, #5cc8ff)',
            badgeBg: 'rgba(92,200,255,0.16)',
            badgeRing: 'rgba(92,200,255,0.28)',
            iconBg: 'rgba(92,200,255,0.14)',
            iconRing: 'rgba(92,200,255,0.24)',
            icon: 'M12 16v-4M12 8h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 3c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z',
        }
    }

    if (toast.value.kind === 'trend') {
        return {
            accent: 'var(--warning, #f7c948)',
            badgeBg: 'rgba(247,201,72,0.16)',
            badgeRing: 'rgba(247,201,72,0.28)',
            iconBg: 'rgba(247,201,72,0.14)',
            iconRing: 'rgba(247,201,72,0.24)',
            icon: 'M3 17l6-6 4 4 7-7',
        }
    }

    if (toast.value.kind === 'closed') {
        return toast.value.type === 'Loss'
            ? {
                accent: 'var(--danger)',
                badgeBg: 'rgba(255,107,129,0.16)',
                badgeRing: 'rgba(255,107,129,0.28)',
                iconBg: 'rgba(255,107,129,0.14)',
                iconRing: 'rgba(255,107,129,0.24)',
                icon: 'M6 18L18 6M6 6l12 12',
            }
            : {
                accent: 'var(--success)',
                badgeBg: 'rgba(61,220,151,0.16)',
                badgeRing: 'rgba(61,220,151,0.28)',
                iconBg: 'rgba(61,220,151,0.14)',
                iconRing: 'rgba(61,220,151,0.24)',
                icon: 'M5 13l4 4L19 7',
            }
    }

    return toast.value.type === 'Sell'
        ? {
            accent: 'var(--danger)',
            badgeBg: 'rgba(255,107,129,0.16)',
            badgeRing: 'rgba(255,107,129,0.28)',
            iconBg: 'rgba(255,107,129,0.14)',
            iconRing: 'rgba(255,107,129,0.24)',
            icon: 'M12 5v14M5 12l7 7 7-7',
        }
        : {
            accent: 'var(--success)',
            badgeBg: 'rgba(61,220,151,0.16)',
            badgeRing: 'rgba(61,220,151,0.28)',
            iconBg: 'rgba(61,220,151,0.14)',
            iconRing: 'rgba(61,220,151,0.24)',
            icon: 'M12 19V5M5 12l7-7 7 7',
        }
})

const toastBadgeLabel = computed(() => {
    if (toast.value.kind === 'structure') return 'Update'
    if (toast.value.kind === 'trend') return 'Trend'
    if (toast.value.kind === 'closed') return toast.value.type
    return toast.value.type
})

const unlockAudio = async () => {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext
        if (!AudioContextClass) return

        if (!audioCtx) {
            audioCtx = new AudioContextClass()
        }

        if (audioCtx.state === 'suspended') {
            await audioCtx.resume()
        }
    } catch (error) {
        console.error('Audio unlock failed:', error)
    }
}

const playTone = (startTime, frequency, { peak = 0.6, duration = 0.24 } = {}) => {
    const osc = audioCtx.createOscillator()
    const shimmer = audioCtx.createOscillator()
    const gain = audioCtx.createGain()
    const shimmerGain = audioCtx.createGain()

    osc.type = 'sine'
    osc.frequency.setValueAtTime(frequency, startTime)

    shimmer.type = 'sine'
    shimmer.frequency.setValueAtTime(frequency * 2, startTime)

    gain.gain.setValueAtTime(0.0001, startTime)
    gain.gain.exponentialRampToValueAtTime(peak, startTime + 0.012)
    gain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration)

    shimmerGain.gain.setValueAtTime(0.0001, startTime)
    shimmerGain.gain.exponentialRampToValueAtTime(peak * 0.25, startTime + 0.012)
    shimmerGain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration * 0.7)

    osc.connect(gain)
    gain.connect(audioCtx.destination)
    shimmer.connect(shimmerGain)
    shimmerGain.connect(audioCtx.destination)

    osc.start(startTime)
    osc.stop(startTime + duration + 0.02)
    shimmer.start(startTime)
    shimmer.stop(startTime + duration + 0.02)
}

const playBeep = async (type = 'Buy') => {
    try {
        await unlockAudio()
        if (!audioCtx) return

        const now = audioCtx.currentTime
        const REPEATS = 3
        const REPEAT_GAP = 0.9

        for (let i = 0; i < REPEATS; i += 1) {
            const base = now + i * REPEAT_GAP

            if (type === 'Sell' || type === 'Loss') {
                playTone(base, 659.25, { peak: 0.64, duration: 0.42 })
                playTone(base + 0.22, 523.25, { peak: 0.68, duration: 0.46 })
            } else if (type === 'Structure') {
                playTone(base, 783.99, { peak: 0.52, duration: 0.28 })
                playTone(base + 0.18, 987.77, { peak: 0.56, duration: 0.32 })
            } else if (type === 'Trend') {
                playTone(base, 739.99, { peak: 0.5, duration: 0.26 })
                playTone(base + 0.16, 932.33, { peak: 0.54, duration: 0.3 })
            } else {
                playTone(base, 880, { peak: 0.62, duration: 0.4 })
                playTone(base + 0.22, 1108.73, { peak: 0.66, duration: 0.44 })
            }
        }
    } catch (error) {
        console.error('Beep playback failed:', error)
    }
}

const startToastTimer = duration => {
    toastStartedAt = Date.now()
    toastRemaining = duration
    toastTimeout = setTimeout(() => {
        toast.value.visible = false
    }, duration)
}

const pauseToastTimer = () => {
    if (!toastTimeout) return
    clearTimeout(toastTimeout)
    toastTimeout = null
    toastRemaining -= Date.now() - toastStartedAt
    toastPaused.value = true
}

const resumeToastTimer = () => {
    if (!toast.value.visible || toastTimeout) return
    toastPaused.value = false
    startToastTimer(Math.max(toastRemaining, 400))
}

const resetToast = () => {
    if (toastTimeout) {
        clearTimeout(toastTimeout)
        toastTimeout = null
    }
    toastSeq += 1
    toastPaused.value = false
}

const showSignalToast = type => {
    resetToast()

    toast.value = {
        visible: true,
        kind: 'signal',
        type,
        title: type === 'Sell' ? 'Sell signal posted' : 'Buy signal posted',
        symbol: liveMarket.symbol,
        detail: `Entry ${liveSignal.value.entry_price}`,
        seq: toastSeq,
    }

    startToastTimer(TOAST_DURATION_MS)
}

const showStructureToast = structure => {
    resetToast()

    toast.value = {
        visible: true,
        kind: 'structure',
        type: 'Update',
        title: 'Market structure updated',
        symbol: liveMarket.symbol,
        detail: `S1 ${structure?.support_1 ?? '-'} · R1 ${structure?.resistance_1 ?? '-'}`,
        seq: toastSeq,
    }

    startToastTimer(TOAST_DURATION_MS)
}

const showTrendToast = trend => {
    resetToast()

    toast.value = {
        visible: true,
        kind: 'trend',
        type: 'Trend',
        title: 'Market trend updated',
        symbol: liveMarket.symbol,
        detail: `Gold ${trend.gold} · Dollar ${trend.dollar}`,
        seq: toastSeq,
    }

    startToastTimer(TOAST_DURATION_MS)
}

const showClosedToast = tradeLog => {
    resetToast()

    const isProfit = tradeLog.result === 'profit'
    const sign = Number(tradeLog.profit_loss) >= 0 ? '+' : ''

    toast.value = {
        visible: true,
        kind: 'closed',
        type: isProfit ? 'Profit' : (tradeLog.result === 'loss' ? 'Loss' : 'Breakeven'),
        title: isProfit ? 'Trade closed — Profit' : (tradeLog.result === 'loss' ? 'Trade closed — Loss' : 'Trade closed — Breakeven'),
        symbol: tradeLog.symbol ?? liveMarket.symbol,
        detail: `${sign}${Number(tradeLog.profit_loss).toFixed(2)} · ${tradeLog.close_reason?.toUpperCase() ?? ''}`,
        seq: toastSeq,
    }

    startToastTimer(TOAST_DURATION_MS)
}

const downloadLogs = () => {
    if (!props.logsExportUrl) return
    window.open(props.logsExportUrl, '_blank')
}

onMounted(() => {
    unlockHandler = () => unlockAudio()
    window.addEventListener('click', unlockHandler, { once: true })

    fetchLivePrice()
    pricePollTimer = setInterval(fetchLivePrice, 5000)
    clockTimer = setInterval(() => { clockTick.value = Date.now() }, 1000)

    if (!window.Echo) return

    window.Echo.private('team.dashboard')
        .listen('.signal.updated', async event => {
            if (!event?.signal) return

            if (event.signal.status_raw === 'closed' && event.trade_log) {
                liveLogs.value.unshift({
                    result: event.trade_log.result === 'profit' ? 'Profit' : (event.trade_log.result === 'loss' ? 'Loss' : 'Breakeven'),
                    signal_type: event.signal.signal_type === 'buy' ? 'Buy' : 'Sell',
                    hit_level: event.trade_log.close_reason === 'tp' ? 'Take Profit' : (event.trade_log.close_reason === 'sl' ? 'Stop Loss' : 'Manual Close'),
                    price: event.trade_log.close_price,
                    time: event.trade_log.closed_at,
                })

                hasActiveSignal.value = false

                showClosedToast(event.trade_log)
                await playBeep(event.trade_log.result === 'loss' ? 'Loss' : 'Profit')
                return
            }

            liveSignal.value = {
                ...liveSignal.value,
                ...event.signal,
            }

            hasActiveSignal.value = event.signal.status_raw !== 'closed'

            const type = event.signal?.type || liveSignal.value.type || 'Buy'
            showSignalToast(type)
            await playBeep(type)
        })
        .listen('.market-structure.updated', async event => {
            if (event?.structure) {
                liveLevels.value = {
                    supports: [
                        event.structure.support_1,
                        event.structure.support_2,
                        event.structure.support_3,
                    ].filter(value => value !== null && value !== undefined),
                    resistances: [
                        event.structure.resistance_1,
                        event.structure.resistance_2,
                        event.structure.resistance_3,
                    ].filter(value => value !== null && value !== undefined),
                }

                liveStructureUpdatedAt.value = event.structure.updated_at || null

                showStructureToast(event.structure)
                await playBeep('Structure')
            }
        })
        .listen('.market-trend.updated', async event => {
            if (event?.trend) {
                const mergedTrend = {
                    gold: event.trend.gold ?? event.trend.gold_trend ?? liveTrend.value.gold,
                    dollar: event.trend.dollar ?? event.trend.dollar_trend ?? liveTrend.value.dollar,
                    updated_at: event.trend.updated_at ?? liveTrendUpdatedAt.value,
                }

                const normalizedTrend = mapTrendPayload(mergedTrend)

                liveTrend.value = normalizedTrend
                liveTrendUpdatedAt.value = normalizedTrend.updated_at || null

                showTrendToast(normalizedTrend)
                await playBeep('Trend')
            }
        })
})

onBeforeUnmount(() => {
    if (toastTimeout) {
        clearTimeout(toastTimeout)
    }

    if (pricePollTimer) {
        clearInterval(pricePollTimer)
    }

    if (clockTimer) {
        clearInterval(clockTimer)
    }

    if (unlockHandler) {
        window.removeEventListener('click', unlockHandler)
    }

    if (!window.Echo) return
    window.Echo.leave('team.dashboard')
})
</script>

<template>
    <Head title="Team Dashboard" />

    <TeamLayout>
        <div class="space-y-4">
            <section class="grid items-start grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.68fr)_290px]">
                <div class="rounded-[24px] bg-white/[0.03] p-3.5 shadow-[0_16px_42px_rgba(0,0,0,0.22)] ring-1 ring-white/6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.24em] text-white/24">Market intelligence</p>
                            <p class="mt-0.5 text-[12px] leading-[1.15] text-white/42">Context for fast decisions</p>
                        </div>

                        <span class="inline-flex items-center gap-1.5 rounded-full bg-cyan-400/10 px-2 py-1 text-[10px] font-medium leading-none text-cyan-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-cyan-300 shadow-[0_0_8px_rgba(92,200,255,0.8)]"></span>
                            Feed
                        </span>
                    </div>

                    <div class="mt-3 space-y-1.5">
                        <article
                            v-for="(item, index) in news.slice(0, 2)"
                            :key="`${item.title}-${index}`"
                            class="rounded-[16px] bg-black/14 px-3.5 py-2.5 transition hover:bg-white/[0.03]"
                        >
                            <div class="flex items-start gap-2.5">
                                <span class="mt-1 h-2 w-2 rounded-full bg-cyan-300 shadow-[0_0_8px_rgba(92,200,255,0.8)]"></span>
                                <div class="min-w-0">
                                    <p class="text-[13px] leading-[1.35] text-white/88">{{ item.title }}</p>
                                    <div class="mt-1 flex items-center gap-1.5 text-[10px] leading-none text-white/36">
                                        <span>{{ item.source }}</span>
                                        <span>•</span>
                                        <span>{{ item.time }}</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="self-stretch rounded-[24px] bg-white/[0.03] p-3.5 shadow-[0_16px_42px_rgba(0,0,0,0.22)] ring-1 ring-white/6">
                    <div class="flex h-full flex-col">
                        <p class="text-[10px] uppercase tracking-[0.24em] text-white/24">Gold live price</p>

                        <div class="flex flex-1 flex-col items-center justify-center text-center">
                            <p class="text-[28px] sm:text-[34px] font-semibold leading-none tracking-[-0.05em] text-white tabular-nums">
                                {{ liveMarket.live_price }}
                            </p>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-2">
                            <span class="text-[10px] font-medium uppercase tracking-[0.08em] text-white/40">{{ liveMarket.symbol }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/[0.04] px-2 py-1 text-[10px] font-medium tabular-nums text-white/44">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-300 shadow-[0_0_6px_rgba(110,231,183,0.7)]"></span>
                                <Transition name="price-label-fade" mode="out-in">
                                    <span :key="displayedPriceLabel" class="inline-block min-w-[54px] text-right">{{ displayedPriceLabel }}</span>
                                </Transition>
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.46fr)_minmax(340px,0.92fr)]">
                <div class="relative overflow-hidden rounded-[32px] bg-white/[0.035] p-4 shadow-[0_28px_90px_rgba(0,0,0,0.34)] ring-1 ring-white/6 sm:p-5 lg:p-6">
                    <div :class="['absolute inset-0 bg-gradient-to-br opacity-100', signalAccent.surface]"></div>
                    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(92,200,255,0.14),transparent_24%),radial-gradient(circle_at_bottom_right,rgba(47,144,255,0.08),transparent_22%)]"></div>
                    <div class="absolute inset-0 opacity-[0.04] [background-image:linear-gradient(to_right,rgba(148,163,184,0.16)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.16)_1px,transparent_1px)] [background-size:24px_24px]"></div>
                    <div class="absolute inset-x-10 top-0 h-px bg-gradient-to-r from-transparent via-white/12 to-transparent"></div>

                    <div class="relative">
                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-start">
                            <!-- Active state: headline + symbol pill -->
                            <div v-if="hasActiveSignal" class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-white/[0.06] px-3 py-1.5 text-[11px] uppercase tracking-[0.22em] text-white/48 ring-1 ring-white/8 backdrop-blur-sm">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="[signalAccent.dot, signalAccent.glow]"></span>
                                        Active signal
                                    </span>
                                </div>

                                <div class="mt-5 flex flex-wrap items-end gap-3">
                                    <h2 class="text-[44px] sm:text-[56px] lg:text-[64px] font-semibold leading-[0.92] tracking-[-0.065em] text-white">
                                        {{ liveSignal.type }}
                                    </h2>

                                    <div class="pb-1.5">
                                        <span class="inline-flex items-center gap-2 rounded-full bg-white/[0.06] px-3.5 py-2 text-sm font-medium text-white/70 ring-1 ring-white/8 backdrop-blur-sm">
                                            <span class="h-2 w-2 rounded-full bg-cyan-300 shadow-[0_0_12px_rgba(92,200,255,0.9)]"></span>
                                            {{ market.symbol }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="hasActiveSignal" class="lg:pl-3 lg:justify-self-end">
                                <div class="min-w-[180px] rounded-[22px] bg-black/18 px-4 py-3 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                                    <p class="text-[11px] uppercase tracking-[0.24em] text-white/24">Updated</p>
                                    <p class="mt-2 text-sm font-medium leading-5 text-white/88">{{ liveSignal.updated_at }}</p>
                                </div>
                            </div>

                            <!-- Empty state: pill top-left, then a full-width centered
                                 block below, spanning both grid columns so it's
                                 centered on the whole card, not squeezed into the
                                 left column next to a phantom right box. -->
                            <div v-if="!hasActiveSignal" class="col-span-full">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-white/[0.06] px-3 py-1.5 text-[11px] uppercase tracking-[0.22em] text-white/48 ring-1 ring-white/8 backdrop-blur-sm">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="[signalAccent.dot, signalAccent.glow]"></span>
                                        No active signal
                                    </span>
                                </div>

                                <div class="flex min-h-[150px] sm:min-h-[190px] w-full flex-col items-center justify-center text-center">
                                    <span class="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-full bg-cyan-400/[0.08] ring-1 ring-cyan-300/20">
                                        <svg class="h-4 w-4 sm:h-4.5 sm:w-4.5 text-cyan-300/60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <circle cx="12" cy="12" r="9" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5v5l3.2 1.9" />
                                        </svg>
                                    </span>

                                    <p class="mt-3 text-[15px] sm:text-[16px] font-medium text-white/75">
                                        Waiting for the next signal
                                    </p>
                                    <p class="mt-0.5 text-[12px] text-white/32">
                                        {{ market.symbol }} · Last signal closed
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-8 sm:mt-12 grid auto-rows-fr grid-cols-1 gap-3 md:grid-cols-3 transition-opacity duration-300"
                            :class="!hasActiveSignal && 'opacity-45'"
                        >
                            <div class="flex min-h-[180px] sm:min-h-[220px] h-full flex-col justify-between rounded-[24px] border border-white/8 bg-[linear-gradient(180deg,rgba(6,14,28,0.96),rgba(10,16,28,0.78))] px-5 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.05),0_18px_40px_rgba(0,0,0,0.18)]">
                                <div class="flex min-h-[42px] items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-[0.24em] text-white/34">Entry</p>
                                        <p class="mt-1 text-[11px] font-medium text-cyan-300/72">Execution</p>
                                    </div>
                                </div>

                                <div class="flex flex-1 items-end pt-6">
                                    <p class="text-[26px] sm:text-[32px] font-semibold leading-none tracking-[-0.05em] text-white tabular-nums">
                                        {{ liveSignal.entry_price }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex min-h-[180px] sm:min-h-[220px] h-full flex-col justify-between rounded-[24px] border border-emerald-400/10 bg-[linear-gradient(180deg,rgba(7,36,27,0.84),rgba(7,22,18,0.82))] px-5 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.04),0_18px_40px_rgba(0,0,0,0.18)]">
                                <div class="flex min-h-[42px] items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-[0.24em] text-emerald-200/48">Take profit</p>
                                        <p class="mt-1 text-[11px] font-medium text-emerald-300/72">Reward</p>
                                    </div>
                                </div>

                                <div class="flex flex-1 items-end pt-6">
                                    <p class="text-[26px] sm:text-[32px] font-semibold leading-none tracking-[-0.05em] text-emerald-300 tabular-nums">
                                        {{ liveSignal.take_profit }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex min-h-[180px] sm:min-h-[220px] h-full flex-col justify-between rounded-[24px] border border-rose-400/10 bg-[linear-gradient(180deg,rgba(43,13,20,0.84),rgba(17,10,15,0.82))] px-5 py-5 shadow-[inset_0_1px_0_rgba(255,255,255,0.04),0_18px_40px_rgba(0,0,0,0.18)]">
                                <div class="flex min-h-[42px] items-start justify-between gap-3">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-[0.24em] text-rose-200/48">Stop loss</p>
                                        <p class="mt-1 text-[11px] font-medium text-rose-300/72">Risk</p>
                                    </div>
                                </div>

                                <div class="flex flex-1 items-end pt-6">
                                    <p class="text-[26px] sm:text-[32px] font-semibold leading-none tracking-[-0.05em] text-rose-300 tabular-nums">
                                        {{ liveSignal.stop_loss }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div class="rounded-[28px] bg-white/[0.03] p-4 shadow-[0_20px_60px_rgba(0,0,0,0.32)] ring-1 ring-white/6">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-[11px] uppercase tracking-[0.24em] text-white/24">Market structure</p>
                            <div class="rounded-[16px] bg-black/18 px-3 py-2 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                                <p class="text-[9px] uppercase tracking-[0.2em] text-white/24">Updated</p>
                                <p class="mt-1 text-[11px] font-medium leading-4 text-white/80 whitespace-nowrap">{{ formatUpdatedAt(liveStructureUpdatedAt) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="space-y-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-white/24">Support</p>
                                <div
                                    v-for="(level, index) in liveLevels.supports"
                                    :key="`sup-${index}`"
                                    class="flex items-center justify-between rounded-2xl bg-emerald-400/[0.04] px-4 py-3"
                                >
                                    <span class="text-sm text-white/44">S{{ index + 1 }}</span>
                                    <span class="text-base font-semibold text-white tabular-nums">{{ level }}</span>
                                </div>
                            </div>

                            <div class="space-y-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-white/24">Resistance</p>
                                <div
                                    v-for="(level, index) in liveLevels.resistances"
                                    :key="`res-${index}`"
                                    class="flex items-center justify-between rounded-2xl bg-rose-400/[0.04] px-4 py-3"
                                >
                                    <span class="text-sm text-white/44">R{{ index + 1 }}</span>
                                    <span class="text-base font-semibold text-white tabular-nums">{{ level }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[28px] bg-white/[0.03] p-4 shadow-[0_20px_60px_rgba(0,0,0,0.32)] ring-1 ring-white/6">
                        <div class="flex items-start justify-between gap-3">
                            <p class="text-[11px] uppercase tracking-[0.24em] text-white/24">Market Trend</p>
                            <div class="rounded-[16px] bg-black/18 px-3 py-2 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                                <p class="text-[9px] uppercase tracking-[0.2em] text-white/24">Updated</p>
                                <p class="mt-1 text-[11px] font-medium leading-4 text-white/80 whitespace-nowrap">{{ formatUpdatedAt(liveTrendUpdatedAt) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="rounded-[22px] bg-white/[0.03] px-4 py-3 ring-1 ring-white/6">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-white/78">Gold</p>
                                    <span class="h-2.5 w-2.5 rounded-full" :class="trendDot(liveTrend.gold)"></span>
                                </div>

                                <div class="mt-4">
                                    <span
                                        class="inline-flex min-w-[88px] justify-center rounded-full px-3 py-1.5 text-sm font-semibold tracking-[0.02em]"
                                        :class="trendTone(liveTrend.gold)"
                                    >
                                        {{ liveTrend.gold }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-[22px] bg-white/[0.03] px-4 py-3 ring-1 ring-white/6">
                                <div class="flex items-center justify-between">
                                    <p class="text-sm font-medium text-white/78">Dollar</p>
                                    <span class="h-2.5 w-2.5 rounded-full" :class="trendDot(liveTrend.dollar)"></span>
                                </div>

                                <div class="mt-4">
                                    <span
                                        class="inline-flex min-w-[88px] justify-center rounded-full px-3 py-1.5 text-sm font-semibold tracking-[0.02em]"
                                        :class="trendTone(liveTrend.dollar)"
                                    >
                                        {{ liveTrend.dollar }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="rounded-[28px] bg-white/[0.03] p-4 shadow-[0_24px_80px_rgba(0,0,0,0.32)] ring-1 ring-white/6 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.24em] text-white/24">Market Logs</p>
                        <p class="mt-1 text-sm text-white/42">Operational outcomes and trade events</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/[0.05] px-3 py-1.5 text-[11px] font-medium text-white/48">
                            <span class="h-1.5 w-1.5 rounded-full bg-cyan-300/70"></span>
                            {{ liveLogs.length }} events
                        </span>

                        <span class="rounded-full bg-white/[0.05] px-3 py-1.5 text-[11px] font-medium text-white/48">
                            Last 30 days
                        </span>

                        <button
                            type="button"
                            title="Download as Excel"
                            class="inline-flex items-center gap-2 rounded-xl bg-white/[0.07] px-3.5 py-2 text-[12px] font-semibold text-white/85 shadow-[0_10px_24px_rgba(0,0,0,0.2)] transition hover:bg-white/[0.11] disabled:cursor-not-allowed disabled:opacity-40"
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

                <div class="market-logs-scroll mt-4 max-h-[340px] space-y-1.5 overflow-y-auto pr-1">
                    <div
                        v-for="(log, index) in liveLogs"
                        :key="`${log.time}-${index}`"
                        class="grid grid-cols-[auto_1fr] items-start gap-3 rounded-[22px] px-3 py-3 transition hover:bg-white/[0.04]"
                    >
                        <span
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-1"
                            :class="logTone(log.result)"
                            style="--tw-ring-color: rgba(255,255,255,0.08)"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path :d="logIconPath(log.result)" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-medium" :class="logTone(log.result)">
                                    {{ log.result }}
                                </span>
                                <span class="text-sm font-medium text-white/88">{{ log.signal_type }}</span>
                                <span class="text-sm text-white/30">hit</span>
                                <span class="text-sm text-white/60">{{ log.hit_level }}</span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/38">
                                <span>Execution <span class="ml-1 font-medium text-white/76 tabular-nums">{{ log.price }}</span></span>
                                <span>{{ log.time }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!liveLogs.length" class="flex flex-col items-center justify-center px-6 py-14 text-center">
                        <p class="text-[13px] font-medium text-white/70">No trade events in the last 30 days</p>
                        <p class="mt-1 max-w-[15rem] text-[12px] leading-relaxed text-white/32">
                            Closed trades will appear here as they happen.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <transition
            enter-active-class="transform-gpu transition duration-300 ease-out"
            enter-from-class="translate-x-4 opacity-0 scale-[0.985]"
            enter-to-class="translate-x-0 opacity-100 scale-100"
            leave-active-class="transform-gpu transition duration-200 ease-in"
            leave-from-class="translate-x-0 opacity-100 scale-100"
            leave-to-class="translate-x-3 opacity-0 scale-[0.99]"
        >
            <div
                v-if="toast.visible"
                class="pointer-events-none fixed inset-x-3 top-3 z-[140] sm:right-5 sm:top-1/2 sm:left-auto sm:inset-x-auto sm:w-[calc(100vw-2rem)] sm:max-w-[380px] sm:-translate-y-1/2"
                @mouseenter="pauseToastTimer"
                @mouseleave="resumeToastTimer"
            >
                <div
                    class="toast-shell pointer-events-auto relative overflow-hidden rounded-[22px] border"
                    style="
                        background: linear-gradient(180deg, #0d1621 0%, #0a121c 100%);
                        border-color: rgba(255,255,255,0.09);
                        box-shadow: 0 24px 64px -10px rgba(0,0,0,0.6), 0 8px 24px -6px rgba(0,0,0,0.4), inset 0 1px 0 rgba(255,255,255,0.04);
                    "
                >
                    <span
                        class="absolute inset-y-0 left-0 w-[3px]"
                        :style="{ background: toastTone.accent }"
                    />

                    <div class="relative flex items-start gap-3 py-4 pl-5 pr-3.5">
                        <span
                            class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-1"
                            :style="{ background: toastTone.iconBg, color: toastTone.accent, '--tw-ring-color': toastTone.iconRing }"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path :d="toastTone.icon" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </span>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.14em] ring-1"
                                    :style="{ background: toastTone.badgeBg, color: toastTone.accent, '--tw-ring-color': toastTone.badgeRing }"
                                >
                                    {{ toastBadgeLabel }}
                                </span>
                                <span class="text-[11px] font-medium text-[var(--text-faint)]">Just now</span>
                            </div>

                            <p class="mt-1.5 text-[14px] font-semibold leading-tight text-[var(--text-primary)]">
                                {{ toast.title }}
                            </p>

                            <p class="mt-1 text-[12px] leading-tight text-[var(--text-tertiary)]">
                                {{ toast.symbol }} <span class="text-[var(--text-faint)]">·</span>
                                <span class="font-medium tabular-nums text-[var(--text-secondary)]">{{ toast.detail }}</span>
                            </p>
                        </div>

                        <button
                            type="button"
                            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[var(--text-faint)] transition hover:bg-[var(--bg-elevated)] hover:text-[var(--text-primary)]"
                            aria-label="Dismiss notification"
                            @click="toast.visible = false"
                        >
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18" />
                            </svg>
                        </button>
                    </div>

                    <div class="relative h-[2.5px] w-full bg-white/[0.05]">
                        <div
                            :key="toast.seq"
                            class="toast-progress h-full"
                            :class="toastPaused && 'is-paused'"
                            :style="{ background: toastTone.accent }"
                        />
                    </div>
                </div>
            </div>
        </transition>
    </TeamLayout>
</template>

<style scoped>
.toast-progress {
    animation: toast-countdown 5.6s linear forwards;
    transform-origin: left;
}
.toast-progress.is-paused {
    animation-play-state: paused;
}
@keyframes toast-countdown {
    from {
        transform: scaleX(1);
    }
    to {
        transform: scaleX(0);
    }
}

.price-label-fade-enter-active,
.price-label-fade-leave-active {
    transition: opacity 0.25s ease;
}
.price-label-fade-enter-from,
.price-label-fade-leave-to {
    opacity: 0;
}

.market-logs-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.14) transparent;
}
.market-logs-scroll::-webkit-scrollbar {
    width: 6px;
}
.market-logs-scroll::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.14);
    border-radius: 999px;
}
.market-logs-scroll::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.22);
}
.market-logs-scroll::-webkit-scrollbar-track {
    background: transparent;
}
</style>