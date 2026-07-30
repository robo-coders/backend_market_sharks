<script setup>
import TeamLayout from '@/Layouts/TeamLayout.vue'
import { Head, usePage } from '@inertiajs/vue3'
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

const page = usePage()
const isDark = computed(() => (page.props.auth?.user?.theme ?? 'dark') !== 'light')

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

// Structure-change highlight state.
//
// When a level changes we record not just THAT it changed but which
// DIRECTION (up/down) — so the highlight can carry meaning, not just
// attention. Each entry: { dir: 'up'|'down'|'flat' }. The box shows a
// crisp directional edge + a persistent pip for ~18s, then clears.
// Values of 0 / empty are treated as "not set" and never highlighted —
// clearing a level or an untouched zero shouldn't flash.
const highlightedLevels = ref({}) // { 'sup-0': { dir }, 'res-1': { dir } }
const highlightTimers = {}

const isMeaningful = v =>
    !(v === null || v === undefined || v === '' || Number(v) === 0)

const flashLevel = (key, dir) => {
    highlightedLevels.value = { ...highlightedLevels.value, [key]: { dir } }

    if (highlightTimers[key]) {
        clearTimeout(highlightTimers[key])
    }

    highlightTimers[key] = setTimeout(() => {
        const next = { ...highlightedLevels.value }
        delete next[key]
        highlightedLevels.value = next
        delete highlightTimers[key]
    }, 18000)
}

// Diffs incoming structure against current levels. Flashes only boxes
// whose MEANINGFUL value changed, records direction, and returns the
// changed levels so the toast reports exactly what changed.
const diffAndFlashLevels = incoming => {
    const changed = []

    const rows = [
        ['sup', 'S', [incoming.support_1, incoming.support_2, incoming.support_3], liveLevels.value.supports],
        ['res', 'R', [incoming.resistance_1, incoming.resistance_2, incoming.resistance_3], liveLevels.value.resistances],
    ]

    rows.forEach(([prefix, label, nextVals, prevVals]) => {
        nextVals.forEach((val, i) => {
            const prev = prevVals[i]
            const prevNum = Number(prev)
            const nextNum = Number(val)

            const prevSet = isMeaningful(prev)
            const nextSet = isMeaningful(val)

            // Only highlight when the new value is meaningful AND actually
            // different. Clearing a level (→ 0) doesn't flash.
            if (nextSet && String(val) !== String(prev)) {
                let dir = 'flat'
                if (prevSet && Number.isFinite(prevNum) && Number.isFinite(nextNum)) {
                    dir = nextNum > prevNum ? 'up' : nextNum < prevNum ? 'down' : 'flat'
                }

                flashLevel(`${prefix}-${i}`, dir)
                changed.push({ label: `${label}${i + 1}`, value: val, dir })
            }
        })
    })

    return changed
}

const highlightDir = key => highlightedLevels.value[key]?.dir ?? null
const isHighlighted = key => key in highlightedLevels.value
const liveTrendUpdatedAt = ref(props.trend?.updated_at || null)

// Raw machine status drives the "Active signal" vs "Active trade" label.
// props.signal.status_raw is 'pending' | 'open' | 'closed' | 'cancelled'.
const signalStatusRaw = ref(
    props.signal?.status_raw ?? (props.signal?.status === 'Active' ? 'open' : 'closed')
)

// A signal occupies the card while it's pending OR open (live trade).
const hasActiveSignal = computed(() => ['pending', 'open'].includes(signalStatusRaw.value))

// Card headline: pending = still waiting for entry, open = live position.
const isLiveTrade = computed(() => signalStatusRaw.value === 'open')
const signalStateLabel = computed(() => (isLiveTrade.value ? 'Active trade' : 'Active signal'))

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

// --- Support/Resistance proximity alerts -------------------------------
// Fires a toast + beep + browser notification when the live price comes
// within LEVEL_ALERT_MARGIN dollars of any support or resistance level
// (an exact match is also within the margin). Alerts fire only when the
// price ENTERS the range for a given level — not on every poll while it
// stays there — and re-arm once the price leaves the range or the level
// value changes.
const LEVEL_ALERT_MARGIN = 1

let activeLevelAlerts = new Set()

const parseLevelValue = value => {
    if (value === null || value === undefined || value === '') return null
    const parsed = Number(String(value).replace(/,/g, ''))
    return Number.isFinite(parsed) ? parsed : null
}

const checkLevelProximity = price => {
    if (!Number.isFinite(price) || price <= 0) return

    const entries = []

    liveLevels.value.supports.forEach((level, index) => {
        entries.push({ label: `S${index + 1}`, value: parseLevelValue(level) })
    })

    liveLevels.value.resistances.forEach((level, index) => {
        entries.push({ label: `R${index + 1}`, value: parseLevelValue(level) })
    })

    const inRange = new Set()
    const newlyInRange = []

    entries.forEach(entry => {
        if (entry.value === null) return

        if (Math.abs(price - entry.value) <= LEVEL_ALERT_MARGIN) {
            const key = `${entry.label}:${entry.value}`
            inRange.add(key)

            if (!activeLevelAlerts.has(key)) {
                newlyInRange.push(entry)
            }
        }
    })

    activeLevelAlerts = inRange

    if (newlyInRange.length) {
        triggerLevelAlert(price, newlyInRange)
    }
}

const sendLevelNotification = (price, detail) => {
    try {
        if (!('Notification' in window)) return

        if (Notification.permission === 'granted') {
            // eslint-disable-next-line no-new
            new Notification('Price near key level', {
                body: `${liveMarket.symbol} · Live ${formatPrice(price)} · ${detail}`,
                tag: 'level-alert',
            })
        }
    } catch (error) {
        console.error('Level notification failed:', error)
    }
}

const triggerLevelAlert = async (price, entries) => {
    const detail = entries
        .map(entry => `${entry.label} ${formatPrice(entry.value)}`)
        .join(' · ')

    showLevelToast(price, detail)
    sendLevelNotification(price, detail)
    await playBeep('Level')
}
// -----------------------------------------------------------------------

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

            checkLevelProximity(next)
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
            dot: 'bg-[var(--text-faint)]',
            surface: 'from-slate-400/[0.05] to-transparent',
        }
    }

    return liveSignal.value.type !== 'Sell'
        ? {
            glow: 'shadow-[0_0_22px_rgba(61,220,151,0.3)]',
            dot: 'bg-[var(--success)]',
            surface: 'from-emerald-400/[0.09] to-transparent',
        }
        : {
            glow: 'shadow-[0_0_22px_rgba(255,107,129,0.26)]',
            dot: 'bg-[var(--danger)]',
            surface: 'from-rose-400/[0.09] to-transparent',
        }
})

const trendTone = value => {
    if (value === 'Buy') return 'bg-[var(--success-soft)] text-[var(--success-text)] ring-1 ring-[var(--success-ring)]'
    if (value === 'Sell') return 'bg-[var(--danger-soft)] text-[var(--danger-text)] ring-1 ring-[var(--danger-ring)]'
    return 'bg-[var(--warning-soft)] text-[var(--warning-text)] ring-1 ring-[var(--warning-ring)]'
}

const trendDot = value => {
    if (value === 'Buy') return 'bg-[var(--success)] shadow-[0_0_14px_rgba(61,220,151,0.45)]'
    if (value === 'Sell') return 'bg-[var(--danger)] shadow-[0_0_14px_rgba(255,107,129,0.45)]'
    return 'bg-[var(--warning)] shadow-[0_0_14px_rgba(247,198,107,0.4)]'
}

const logTone = value => {
    if (value === 'Profit') return 'bg-[var(--success-soft)] text-[var(--success-text)]'
    if (value === 'Loss') return 'bg-[var(--danger-soft)] text-[var(--danger-text)]'
    return 'bg-[var(--warning-soft)] text-[var(--warning-text)]'
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

// Renders a structure level as a dash when it hasn't been set (null),
// instead of dropping it from the array — which would shift every
// subsequent level up a slot and mislabel it (e.g. S3's value showing
// under the "S2" label).
const formatLevel = value => {
    if (value === null || value === undefined || value === '') return '—'
    return value
}

const toastTone = computed(() => {
    if (toast.value.kind === 'structure') {
        return {
            accent: 'var(--info-dot)',
            badgeBg: 'var(--info-soft)',
            badgeRing: 'var(--info-soft)',
            iconBg: 'var(--info-soft)',
            iconRing: 'var(--info-soft)',
            icon: 'M12 16v-4M12 8h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 3c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z',
        }
    }

    if (toast.value.kind === 'trend') {
        return {
            accent: 'var(--warning)',
            badgeBg: 'var(--warning-soft)',
            badgeRing: 'var(--warning-ring)',
            iconBg: 'var(--warning-soft)',
            iconRing: 'var(--warning-ring)',
            icon: 'M3 17l6-6 4 4 7-7',
        }
    }

    if (toast.value.kind === 'level') {
        return {
            accent: 'var(--level-accent)',
            badgeBg: 'var(--level-soft)',
            badgeRing: 'var(--level-soft)',
            iconBg: 'var(--level-soft)',
            iconRing: 'var(--level-soft)',
            icon: 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .53-.21 1.04-.59 1.42L4 17h5m6 0v1a3 3 0 11-6 0v-1',
        }
    }

    if (toast.value.kind === 'cancelled') {
        return {
            accent: 'var(--text-secondary)',
            badgeBg: 'var(--bg-elevated-2)',
            badgeRing: 'var(--card-ring)',
            iconBg: 'var(--bg-elevated-2)',
            iconRing: 'var(--card-ring)',
            icon: 'M6 6l12 12M18 6L6 18',
        }
    }

    if (toast.value.kind === 'closed') {
        return toast.value.type === 'Loss'
            ? {
                accent: 'var(--danger)',
                badgeBg: 'var(--danger-soft)',
                badgeRing: 'var(--danger-ring)',
                iconBg: 'var(--danger-soft)',
                iconRing: 'var(--danger-ring)',
                icon: 'M6 18L18 6M6 6l12 12',
            }
            : {
                accent: 'var(--success)',
                badgeBg: 'var(--success-soft)',
                badgeRing: 'var(--success-ring)',
                iconBg: 'var(--success-soft)',
                iconRing: 'var(--success-ring)',
                icon: 'M5 13l4 4L19 7',
            }
    }

    return toast.value.type === 'Sell'
        ? {
            accent: 'var(--danger)',
            badgeBg: 'var(--danger-soft)',
            badgeRing: 'var(--danger-ring)',
            iconBg: 'var(--danger-soft)',
            iconRing: 'var(--danger-ring)',
            icon: 'M12 5v14M5 12l7 7 7-7',
        }
        : {
            accent: 'var(--success)',
            badgeBg: 'var(--success-soft)',
            badgeRing: 'var(--success-ring)',
            iconBg: 'var(--success-soft)',
            iconRing: 'var(--success-ring)',
            icon: 'M12 19V5M5 12l7-7 7 7',
        }
})

const toastBadgeLabel = computed(() => {
    if (toast.value.kind === 'structure') return 'Update'
    if (toast.value.kind === 'trend') return 'Trend'
    if (toast.value.kind === 'level') return 'Level'
    if (toast.value.kind === 'cancelled') return 'Cancelled'
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
    // Respect the persisted per-user preference (Team Settings → Alert sounds).
    if (usePage().props.auth?.user?.alert_sounds_muted) return

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
            } else if (type === 'Level') {
                playTone(base, 987.77, { peak: 0.6, duration: 0.3 })
                playTone(base + 0.14, 1244.51, { peak: 0.64, duration: 0.32 })
                playTone(base + 0.28, 987.77, { peak: 0.6, duration: 0.34 })
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

// Signal PLACED (pending) — waiting for price to reach entry. No trade yet.
const showSignalToast = type => {
    resetToast()

    toast.value = {
        visible: true,
        kind: 'signal',
        type,
        title: type === 'Sell' ? 'Sell signal posted' : 'Buy signal posted',
        symbol: liveMarket.symbol,
        detail: `Waiting for entry ${liveSignal.value.entry_price}`,
        seq: toastSeq,
    }

    startToastTimer(TOAST_DURATION_MS)
}

// Signal ACTIVATED — price reached entry, it's now a live trade.
const showActivatedToast = type => {
    resetToast()

    toast.value = {
        visible: true,
        kind: 'signal',
        type,
        title: type === 'Sell' ? 'Sell trade live' : 'Buy trade live',
        symbol: liveMarket.symbol,
        detail: `Entry ${liveSignal.value.entry_price} reached`,
        seq: toastSeq,
    }

    startToastTimer(TOAST_DURATION_MS)
}

// Signal CANCELLED — pending signal closed before entry was ever hit.
const showCancelledToast = type => {
    resetToast()

    toast.value = {
        visible: true,
        kind: 'cancelled',
        type: 'Cancelled',
        title: 'Signal cancelled',
        symbol: liveMarket.symbol,
        detail: `${type} · entry never reached`,
        seq: toastSeq,
    }

    startToastTimer(TOAST_DURATION_MS)
}

const showStructureToast = (structure, changed = []) => {
    resetToast()

    // Report exactly what changed, with a direction arrow — matches the
    // box that highlights. Only meaningful changes reach here (the diff
    // already filtered out zero/cleared levels), so no misleading padding.
    let detail
    if (changed.length) {
        detail = changed
            .slice(0, 3)
            .map(({ label, value, dir }) => {
                const arrow = dir === 'up' ? '↑' : dir === 'down' ? '↓' : ''
                return `${label} ${value}${arrow ? ' ' + arrow : ''}`
            })
            .join('  ·  ')
    } else {
        // Nothing meaningful changed (e.g. a level was cleared). Keep it
        // honest and generic rather than listing zeros.
        detail = 'Levels updated'
    }

    toast.value = {
        visible: true,
        kind: 'structure',
        type: 'Update',
        title: 'Market structure updated',
        symbol: liveMarket.symbol,
        detail,
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

const showLevelToast = (price, detail) => {
    resetToast()

    toast.value = {
        visible: true,
        kind: 'level',
        type: 'Level',
        title: 'Price near key level',
        symbol: liveMarket.symbol,
        detail: `Live ${formatPrice(price)} · ${detail}`,
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
    unlockHandler = () => {
        unlockAudio()

        // Ask for browser notification permission on the same first
        // user gesture that unlocks audio, so level alerts can also
        // fire system notifications.
        try {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission().catch(() => {})
            }
        } catch (error) {
            console.error('Notification permission request failed:', error)
        }
    }
    window.addEventListener('click', unlockHandler, { once: true })

    fetchLivePrice()
    pricePollTimer = setInterval(fetchLivePrice, 5000)
    clockTimer = setInterval(() => { clockTick.value = Date.now() }, 1000)

    if (!window.Echo) return

    window.Echo.private('team.dashboard')
        .listen('.signal.updated', async event => {
            if (!event?.signal) return

            const raw = event.signal.status_raw ?? event.signal.status

            // --- Cancelled: pending signal closed before entry was hit.
            // No trade existed → no log row, no P/L toast. Just clear card.
            if (raw === 'cancelled') {
                signalStatusRaw.value = 'cancelled'
                const type = event.signal?.type || liveSignal.value.type || 'Buy'
                showCancelledToast(type)
                return
            }

            // --- Closed with a real trade log → P/L outcome.
            if (raw === 'closed' && event.trade_log) {
                liveLogs.value.unshift({
                    result: event.trade_log.result === 'profit' ? 'Profit' : (event.trade_log.result === 'loss' ? 'Loss' : 'Breakeven'),
                    signal_type: event.signal.signal_type === 'buy' ? 'Buy' : 'Sell',
                    hit_level: event.trade_log.close_reason === 'tp' ? 'Take Profit' : (event.trade_log.close_reason === 'sl' ? 'Stop Loss' : 'Manual Close'),
                    price: event.trade_log.close_price,
                    time: event.trade_log.closed_at,
                })

                signalStatusRaw.value = 'closed'

                showClosedToast(event.trade_log)
                await playBeep(event.trade_log.result === 'loss' ? 'Loss' : 'Profit')
                return
            }

            // --- Otherwise it's a live pending/open signal update.
            liveSignal.value = {
                ...liveSignal.value,
                ...event.signal,
            }

            signalStatusRaw.value = raw === 'pending' ? 'pending' : 'open'

            const type = event.signal?.type || liveSignal.value.type || 'Buy'

            // Distinct toast: activation (went live) vs a new placement.
            if (raw === 'open' && event.signal.just_activated) {
                showActivatedToast(type)
            } else {
                showSignalToast(type)
            }
            await playBeep(type)
        })
        .listen('.market-structure.updated', async event => {
            if (event?.structure) {
                // Flash only the boxes whose value changed — must run
                // BEFORE we overwrite liveLevels so the diff sees the old
                // values. Returns the changed levels so the toast reports
                // exactly what changed (matching the box that glows).
                const changedLevels = diffAndFlashLevels(event.structure)

                // Positions are preserved even when a value is null — this
                // is what keeps S1/S2/S3 and R1/R2/R3 correctly labeled.
                // Filtering nulls out here would shift later values into
                // earlier slots and mislabel them.
                liveLevels.value = {
                    supports: [
                        event.structure.support_1,
                        event.structure.support_2,
                        event.structure.support_3,
                    ],
                    resistances: [
                        event.structure.resistance_1,
                        event.structure.resistance_2,
                        event.structure.resistance_3,
                    ],
                }

                liveStructureUpdatedAt.value = event.structure.updated_at || null

                showStructureToast(event.structure, changedLevels)
                await playBeep('Structure')

                // New levels may already be within the alert margin of the
                // current price — check immediately instead of waiting for
                // the next price poll.
                checkLevelProximity(previousPrice.value)
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
        .listen('.level.alert', async event => {
            if (!event?.levels?.length) return

            // Server-side levels:monitor detected a level hit. Skip any
            // level the local 5-second poll already alerted on, and
            // register the rest so the local poll won't double-alert.
            const fresh = event.levels.filter(level => {
                const key = `${level.label}:${Number(level.value)}`
                if (activeLevelAlerts.has(key)) return false
                activeLevelAlerts.add(key)
                return true
            })

            if (!fresh.length) return

            const detail = fresh
                .map(level => `${level.label} ${formatPrice(Number(level.value))}`)
                .join(' · ')

            showLevelToast(Number(event.price), detail)
            sendLevelNotification(Number(event.price), detail)
            await playBeep('Level')
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

    // Clear any pending structure-highlight fade timers.
    Object.values(highlightTimers).forEach(clearTimeout)

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
        <div class="space-y-4 overflow-x-hidden">
            <section class="grid items-start grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.68fr)_290px]">
                <div class="rounded-[24px] bg-[var(--card-bg)] p-3.5 shadow-[var(--card-shadow-sm)] ring-1 ring-[var(--card-ring)]">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-[10px] uppercase tracking-[0.24em] text-[var(--text-faint)]">Market intelligence</p>
                            <p class="mt-0.5 text-[12px] leading-[1.15] text-[var(--text-tertiary)]">Context for fast decisions</p>
                        </div>

                        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-[var(--info-soft)] px-2 py-1 text-[10px] font-medium leading-none text-[var(--info-text)]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[var(--info-dot)]"></span>
                            Feed
                        </span>
                    </div>

                    <div class="mt-3 space-y-1.5">
                        <article
                            v-for="(item, index) in news.slice(0, 4)"
                            :key="`${item.title}-${index}`"
                            class="rounded-[16px] bg-[var(--news-bg)] px-3.5 py-2.5 transition hover:bg-[var(--bg-hover)]"
                        >
                            <div class="flex items-start gap-2.5">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-[var(--info-dot)]"></span>
                                <div class="min-w-0">
                                    <p class="text-[13px] leading-[1.35] text-[var(--text-primary)]">{{ item.title }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px] leading-none text-[var(--text-tertiary)]">
                                        <span>{{ item.source }}</span>
                                        <span>•</span>
                                        <span>{{ item.time }}</span>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="self-stretch rounded-[24px] bg-[var(--card-bg)] p-3.5 shadow-[var(--card-shadow)] ring-1 ring-[var(--card-ring)]">
                    <div class="flex h-full flex-col">
                        <p class="text-[10px] uppercase tracking-[0.24em] text-[var(--text-faint)]">Gold live price</p>

                        <div class="flex flex-1 flex-col items-center justify-center text-center">
                            <p class="text-[28px] sm:text-[34px] font-semibold leading-none tracking-[-0.05em] text-[var(--text-primary)] tabular-nums">
                                {{ liveMarket.live_price }}
                            </p>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-2">
                            <span class="text-[10px] font-medium uppercase tracking-[0.08em] text-[var(--text-tertiary)]">{{ liveMarket.symbol }}</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--chip-bg)] px-2 py-1 text-[10px] font-medium tabular-nums text-[var(--text-tertiary)]">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--success)] shadow-[0_0_6px_rgba(61,220,151,0.6)]"></span>
                                <Transition name="price-label-fade" mode="out-in">
                                    <span :key="displayedPriceLabel" class="inline-block min-w-[54px] text-right">{{ displayedPriceLabel }}</span>
                                </Transition>
                            </span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(0,1.46fr)_minmax(340px,0.92fr)]">
                <div class="relative overflow-hidden rounded-[32px] bg-[var(--card-bg)] p-4 shadow-[var(--card-shadow-lg)] ring-1 ring-[var(--card-ring)] sm:p-5 lg:p-6">
                    <div :class="['absolute inset-0 bg-gradient-to-br opacity-100', signalAccent.surface]"></div>
                    <template v-if="isDark">
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(92,200,255,0.14),transparent_24%),radial-gradient(circle_at_bottom_right,rgba(47,144,255,0.08),transparent_22%)]"></div>
                        <div class="absolute inset-0 opacity-[0.04] [background-image:linear-gradient(to_right,rgba(148,163,184,0.16)_1px,transparent_1px),linear-gradient(to_bottom,rgba(148,163,184,0.16)_1px,transparent_1px)] [background-size:24px_24px]"></div>
                        <div class="absolute inset-x-10 top-0 h-px bg-gradient-to-r from-transparent via-white/12 to-transparent"></div>
                    </template>

                    <div class="relative">
                        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_220px] lg:items-start">
                            <!-- Active state: headline + symbol pill -->
                            <div v-if="hasActiveSignal" class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--chip-bg)] px-3 py-1.5 text-[11px] uppercase tracking-[0.22em] text-[var(--text-tertiary)] ring-1 ring-[var(--card-ring)] backdrop-blur-sm">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="[signalAccent.dot, signalAccent.glow]"></span>
                                        {{ signalStateLabel }}
                                    </span>
                                </div>

                                <div class="mt-5 flex flex-wrap items-end gap-3">
                                    <h2 class="text-[36px] sm:text-[44px] md:text-[56px] lg:text-[64px] font-semibold leading-[0.92] tracking-[-0.065em] text-[var(--text-primary)]">
                                        {{ liveSignal.type }}
                                    </h2>

                                    <div class="pb-1.5">
                                        <span class="inline-flex items-center gap-2 rounded-full bg-[var(--chip-bg)] px-3.5 py-2 text-sm font-medium text-[var(--text-secondary)] ring-1 ring-[var(--card-ring)] backdrop-blur-sm">
                                            <span class="h-2 w-2 rounded-full bg-[var(--info-dot)] shadow-[0_0_12px_rgba(92,200,255,0.6)]"></span>
                                            {{ market.symbol }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div v-if="hasActiveSignal" class="lg:pl-3 lg:justify-self-end">
                                <div class="min-w-[180px] rounded-[22px] bg-[var(--inset-bg)] px-4 py-3 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                                    <p class="text-[11px] uppercase tracking-[0.24em] text-[var(--text-faint)]">Updated</p>
                                    <p class="mt-2 text-sm font-medium leading-5 text-[var(--text-secondary)]">{{ liveSignal.updated_at }}</p>
                                </div>
                            </div>

                            <!-- Empty state: pill top-left, then a full-width centered
                                 block below, spanning both grid columns so it's
                                 centered on the whole card, not squeezed into the
                                 left column next to a phantom right box. -->
                            <div v-if="!hasActiveSignal" class="col-span-full">
                                <div class="flex flex-wrap items-center gap-2.5">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-[var(--chip-bg)] px-3 py-1.5 text-[11px] uppercase tracking-[0.22em] text-[var(--text-tertiary)] ring-1 ring-[var(--card-ring)] backdrop-blur-sm">
                                        <span class="h-1.5 w-1.5 rounded-full" :class="[signalAccent.dot, signalAccent.glow]"></span>
                                        No active signal
                                    </span>
                                </div>

                                <div class="flex min-h-[150px] sm:min-h-[190px] w-full flex-col items-center justify-center text-center">
                                    <span class="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-full bg-[var(--info-soft)] ring-1 ring-[var(--card-ring)]">
                                        <svg class="h-4 w-4 sm:h-4.5 sm:w-4.5 text-[var(--info-text)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                            <circle cx="12" cy="12" r="9" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5v5l3.2 1.9" />
                                        </svg>
                                    </span>

                                    <p class="mt-3 text-[15px] sm:text-[16px] font-medium text-[var(--text-secondary)]">
                                        Waiting for the next signal
                                    </p>
                                    <p class="mt-0.5 text-[12px] text-[var(--text-tertiary)]">
                                        {{ market.symbol }} · Last signal closed
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="mt-8 sm:mt-12 grid auto-rows-fr grid-cols-1 gap-3 md:grid-cols-3 transition-opacity duration-300"
                            :class="!hasActiveSignal && 'opacity-60'"
                        >
                            <div class="flex min-h-[92px] sm:min-h-[130px] md:min-h-[220px] h-full flex-col justify-between rounded-[24px] border border-[var(--panel-entry-ring)] [background-image:var(--panel-entry-bg)] px-4 py-3.5 shadow-[var(--panel-shadow)] sm:px-5 sm:py-5">
                                <div class="flex min-h-[26px] items-start justify-between gap-3 sm:min-h-[42px]">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-[0.24em] text-[var(--text-tertiary)]">Entry</p>
                                        <p class="mt-1 text-[11px] font-medium text-[var(--info-text)]">Execution</p>
                                    </div>
                                </div>

                                <div class="flex flex-1 items-end pt-2 sm:pt-6">
                                    <p class="text-[24px] sm:text-[26px] md:text-[32px] font-semibold leading-none tracking-[-0.05em] text-[var(--text-primary)] tabular-nums">
                                        {{ liveSignal.entry_price }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex min-h-[92px] sm:min-h-[130px] md:min-h-[220px] h-full flex-col justify-between rounded-[24px] border border-[var(--panel-tp-ring)] [background-image:var(--panel-tp-bg)] px-4 py-3.5 shadow-[var(--panel-shadow)] sm:px-5 sm:py-5">
                                <div class="flex min-h-[26px] items-start justify-between gap-3 sm:min-h-[42px]">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-[0.24em] text-[var(--success-text)] opacity-70">Take profit</p>
                                        <p class="mt-1 text-[11px] font-medium text-[var(--success-text)]">Reward</p>
                                    </div>
                                </div>

                                <div class="flex flex-1 items-end pt-2 sm:pt-6">
                                    <p class="text-[24px] sm:text-[26px] md:text-[32px] font-semibold leading-none tracking-[-0.05em] text-[var(--success-text)] tabular-nums">
                                        {{ liveSignal.take_profit }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex min-h-[92px] sm:min-h-[130px] md:min-h-[220px] h-full flex-col justify-between rounded-[24px] border border-[var(--panel-sl-ring)] [background-image:var(--panel-sl-bg)] px-4 py-3.5 shadow-[var(--panel-shadow)] sm:px-5 sm:py-5">
                                <div class="flex min-h-[26px] items-start justify-between gap-3 sm:min-h-[42px]">
                                    <div>
                                        <p class="text-[10px] uppercase tracking-[0.24em] text-[var(--danger-text)] opacity-70">Stop loss</p>
                                        <p class="mt-1 text-[11px] font-medium text-[var(--danger-text)]">Risk</p>
                                    </div>
                                </div>

                                <div class="flex flex-1 items-end pt-2 sm:pt-6">
                                    <p class="text-[24px] sm:text-[26px] md:text-[32px] font-semibold leading-none tracking-[-0.05em] text-[var(--danger-text)] tabular-nums">
                                        {{ liveSignal.stop_loss }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <div class="rounded-[28px] bg-[var(--card-bg)] p-4 shadow-[var(--card-shadow)] ring-1 ring-[var(--card-ring)]">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-[11px] uppercase tracking-[0.24em] text-[var(--text-faint)]">Market structure</p>
                            <div class="max-w-full rounded-[16px] bg-[var(--inset-bg)] px-3 py-2 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                                <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--text-faint)]">Updated</p>
                                <p class="mt-1 text-[11px] font-medium leading-4 text-[var(--text-secondary)]">{{ formatUpdatedAt(liveStructureUpdatedAt) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 sm:gap-3">
                            <div class="space-y-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[var(--text-faint)]">Support</p>
                                <div
                                    v-for="(level, index) in liveLevels.supports"
                                    :key="`sup-${index}`"
                                    class="level-box flex items-center justify-between gap-2 rounded-2xl bg-[var(--success-softer)] px-3 py-3 sm:px-4"
                                    :class="[
                                        isHighlighted(`sup-${index}`) && 'is-highlight',
                                        highlightDir(`sup-${index}`) === 'up' && 'dir-up',
                                        highlightDir(`sup-${index}`) === 'down' && 'dir-down',
                                    ]"
                                >
                                    <span class="flex items-center gap-1.5 text-sm text-[var(--text-tertiary)]">
                                        <span
                                            v-if="isHighlighted(`sup-${index}`)"
                                            class="level-pip"
                                            :class="[
                                                highlightDir(`sup-${index}`) === 'up' && 'pip-up',
                                                highlightDir(`sup-${index}`) === 'down' && 'pip-down',
                                            ]"
                                        ></span>
                                        S{{ index + 1 }}
                                    </span>
                                    <Transition name="level-swap" mode="out-in">
                                        <span
                                            :key="formatLevel(level)"
                                            class="truncate text-[13px] sm:text-base font-semibold text-[var(--text-primary)] tabular-nums"
                                        >{{ formatLevel(level) }}</span>
                                    </Transition>
                                </div>
                            </div>

                            <div class="space-y-2.5">
                                <p class="text-[11px] uppercase tracking-[0.2em] text-[var(--text-faint)]">Resistance</p>
                                <div
                                    v-for="(level, index) in liveLevels.resistances"
                                    :key="`res-${index}`"
                                    class="level-box flex items-center justify-between gap-2 rounded-2xl bg-[var(--danger-softer)] px-3 py-3 sm:px-4"
                                    :class="[
                                        isHighlighted(`res-${index}`) && 'is-highlight',
                                        highlightDir(`res-${index}`) === 'up' && 'dir-up',
                                        highlightDir(`res-${index}`) === 'down' && 'dir-down',
                                    ]"
                                >
                                    <span class="flex items-center gap-1.5 text-sm text-[var(--text-tertiary)]">
                                        <span
                                            v-if="isHighlighted(`res-${index}`)"
                                            class="level-pip"
                                            :class="[
                                                highlightDir(`res-${index}`) === 'up' && 'pip-up',
                                                highlightDir(`res-${index}`) === 'down' && 'pip-down',
                                            ]"
                                        ></span>
                                        R{{ index + 1 }}
                                    </span>
                                    <Transition name="level-swap" mode="out-in">
                                        <span
                                            :key="formatLevel(level)"
                                            class="truncate text-[13px] sm:text-base font-semibold text-[var(--text-primary)] tabular-nums"
                                        >{{ formatLevel(level) }}</span>
                                    </Transition>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[28px] bg-[var(--card-bg)] p-4 shadow-[var(--card-shadow)] ring-1 ring-[var(--card-ring)]">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <p class="text-[11px] uppercase tracking-[0.24em] text-[var(--text-faint)]">Market Trend</p>
                            <div class="max-w-full rounded-[16px] bg-[var(--inset-bg)] px-3 py-2 text-center shadow-[inset_0_1px_0_rgba(255,255,255,0.04)]">
                                <p class="text-[9px] uppercase tracking-[0.2em] text-[var(--text-faint)]">Updated</p>
                                <p class="mt-1 text-[11px] font-medium leading-4 text-[var(--text-secondary)]">{{ formatUpdatedAt(liveTrendUpdatedAt) }}</p>
                            </div>
                        </div>

                        <div class="mt-3 grid grid-cols-2 gap-2 sm:gap-3">
                            <div class="rounded-[22px] bg-[var(--bg-elevated)] px-3 py-3 ring-1 ring-[var(--border-faint)] sm:px-4">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-[var(--text-primary)]">Gold</p>
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="trendDot(liveTrend.gold)"></span>
                                </div>

                                <div class="mt-4">
                                    <span
                                        class="inline-flex w-full justify-center rounded-full px-2 py-1.5 text-[13px] sm:text-sm font-semibold tracking-[0.02em] sm:min-w-[88px] sm:w-auto"
                                        :class="trendTone(liveTrend.gold)"
                                    >
                                        {{ liveTrend.gold }}
                                    </span>
                                </div>
                            </div>

                            <div class="rounded-[22px] bg-[var(--bg-elevated)] px-3 py-3 ring-1 ring-[var(--border-faint)] sm:px-4">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-medium text-[var(--text-primary)]">Dollar</p>
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="trendDot(liveTrend.dollar)"></span>
                                </div>

                                <div class="mt-4">
                                    <span
                                        class="inline-flex w-full justify-center rounded-full px-2 py-1.5 text-[13px] sm:text-sm font-semibold tracking-[0.02em] sm:min-w-[88px] sm:w-auto"
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

            <section class="rounded-[28px] bg-[var(--card-bg)] p-4 shadow-[var(--card-shadow)] ring-1 ring-[var(--card-ring)] sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[11px] uppercase tracking-[0.24em] text-[var(--text-faint)]">Market Logs</p>
                        <p class="mt-1 text-sm text-[var(--text-tertiary)]">Operational outcomes and trade events</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--chip-bg)] px-3 py-1.5 text-[11px] font-medium text-[var(--text-tertiary)]">
                            <span class="h-1.5 w-1.5 rounded-full bg-[var(--info-dot)]"></span>
                            {{ liveLogs.length }} events
                        </span>

                        <span class="rounded-full bg-[var(--chip-bg)] px-3 py-1.5 text-[11px] font-medium text-[var(--text-tertiary)]">
                            Last 30 days
                        </span>

                        <button
                            type="button"
                            title="Download as Excel"
                            class="inline-flex items-center gap-2 rounded-xl bg-[var(--bg-elevated-2)] px-3.5 py-2 text-[12px] font-semibold text-[var(--text-primary)] shadow-[var(--card-shadow-sm)] transition hover:bg-[var(--bg-hover)] disabled:cursor-not-allowed disabled:opacity-40"
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
                        class="grid grid-cols-[auto_1fr] items-start gap-3 rounded-[22px] px-3 py-3 transition hover:bg-[var(--bg-hover)]"
                    >
                        <span
                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-1"
                            :class="logTone(log.result)"
                            style="--tw-ring-color: var(--card-ring)"
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
                                <span class="text-sm font-medium text-[var(--text-primary)]">{{ log.signal_type }}</span>
                                <span class="text-sm text-[var(--text-faint)]">hit</span>
                                <span class="text-sm text-[var(--text-secondary)]">{{ log.hit_level }}</span>
                            </div>

                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-[var(--text-tertiary)]">
                                <span>Execution <span class="ml-1 font-medium text-[var(--text-primary)] tabular-nums">{{ log.price }}</span></span>
                                <span>{{ log.time }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!liveLogs.length" class="flex flex-col items-center justify-center px-6 py-14 text-center">
                        <p class="text-[13px] font-medium text-[var(--text-secondary)]">No trade events in the last 30 days</p>
                        <p class="mt-1 max-w-[15rem] text-[12px] leading-relaxed text-[var(--text-tertiary)]">
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
                    :style="{
                        background: 'var(--toast-bg)',
                        borderColor: 'var(--toast-border)',
                        boxShadow: 'var(--card-shadow-lg)',
                    }"
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

                    <div class="relative h-[2.5px] w-full" :style="{ background: 'var(--toast-track)' }">
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
/* Market-structure change highlight — directional, readable in both themes.
 *
 * Design rationale (senior UX):
 *  - A soft background wash alone is nearly invisible on dark cards, so we
 *    lead with a CRISP directional EDGE (a solid 3px inset bar) that reads
 *    instantly regardless of theme.
 *  - Colour carries MEANING: green = level moved up, red = moved down,
 *    neutral accent = newly set / no prior value. The team learns intent at
 *    a glance, not just "something changed".
 *  - The NUMBER itself animates on change (level-swap transition) — the eye
 *    is already on the value, so that's the strongest change signal.
 *  - A small persistent PIP by the label keeps a quiet marker alive for the
 *    full ~18s hold, so a late glance still catches the box.
 *
 * Timeline (18s): edge + wash pulse in (~0.4s) → hold steady → ease out
 * over the last ~2s. Directionless default uses --accent. */
.level-box {
    position: relative;
    overflow: hidden;
    transition: box-shadow 0.5s ease, background-color 0.5s ease;
}

/* The directional inset edge, drawn as a pseudo-element so it sits above
 * the box's own background and rounded corners cleanly. */
.level-box::before {
    content: '';
    position: absolute;
    inset: 0 auto 0 0;
    width: 3px;
    border-radius: 3px;
    background: var(--edge-color, var(--accent));
    opacity: 0;
    transition: opacity 0.4s ease;
}

.level-box.is-highlight {
    --edge-color: var(--accent);
    animation: level-highlight 18s ease-out forwards;
}
.level-box.is-highlight::before {
    animation: level-edge 18s ease-out forwards;
}

/* Directional colour overrides. --success-text / --danger-text are the
 * theme's strong, high-contrast semantic colours — legible in light & dark. */
.level-box.dir-up {
    --edge-color: var(--success-text);
}
.level-box.dir-down {
    --edge-color: var(--danger-text);
}

@keyframes level-highlight {
    0% {
        background-color: color-mix(in srgb, var(--edge-color) 0%, transparent);
    }
    3% {
        background-color: color-mix(in srgb, var(--edge-color) 20%, transparent);
    }
    88% {
        background-color: color-mix(in srgb, var(--edge-color) 14%, transparent);
    }
    100% {
        background-color: color-mix(in srgb, var(--edge-color) 0%, transparent);
    }
}

@keyframes level-edge {
    0% { opacity: 0; }
    3% { opacity: 1; }
    88% { opacity: 1; }
    100% { opacity: 0; }
}

/* Persistent directional pip next to the label. */
.level-pip {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: var(--accent);
    box-shadow: 0 0 8px color-mix(in srgb, var(--accent) 60%, transparent);
    animation: pip-in 0.35s ease-out;
}
.level-pip.pip-up {
    background: var(--success-text);
    box-shadow: 0 0 8px color-mix(in srgb, var(--success-text) 60%, transparent);
}
.level-pip.pip-down {
    background: var(--danger-text);
    box-shadow: 0 0 8px color-mix(in srgb, var(--danger-text) 60%, transparent);
}
@keyframes pip-in {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}

/* Number swap — old value eases up/out, new value eases in. Fast and
 * subtle so it registers as "the number just updated". */
.level-swap-enter-active,
.level-swap-leave-active {
    transition: opacity 0.28s ease, transform 0.28s ease;
}
.level-swap-enter-from {
    opacity: 0;
    transform: translateY(6px);
}
.level-swap-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

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
    scrollbar-color: var(--border-soft) transparent;
}
.market-logs-scroll::-webkit-scrollbar {
    width: 6px;
}
.market-logs-scroll::-webkit-scrollbar-thumb {
    background: var(--border-soft);
    border-radius: 999px;
}
.market-logs-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--bg-hover);
}
.market-logs-scroll::-webkit-scrollbar-track {
    background: transparent;
}
</style>