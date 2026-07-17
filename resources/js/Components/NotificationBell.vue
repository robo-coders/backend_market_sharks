<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const open = ref(false)
const loading = ref(false)
const loadingMore = ref(false)
const notifications = ref([])
const filter = ref('all') // 'all' | 'unread'

const currentPage = ref(1)
const hasMore = ref(false)
const totalUnreadCount = ref(0)
const visibleCount = ref(5)

const rootRef = ref(null)
const listEl = ref(null)
const isOverflowing = ref(false)
const atBottom = ref(false)

const itemEls = ref([])
const setItemRef = (el, index) => {
    if (el) itemEls.value[index] = el
}

const TYPE_META = {
    buy_open: {
        color: 'var(--success)',
        bg: 'var(--success-soft)',
        ring: 'var(--success-ring)',
        icon: 'M7 17L17 7M7 7h10v10',
    },
    sell_open: {
        color: 'var(--danger)',
        bg: 'var(--danger-soft)',
        ring: 'var(--danger-ring)',
        icon: 'M17 7L7 17M17 17H7V7',
    },
    profit_close: {
        color: 'var(--success)',
        bg: 'var(--success-soft)',
        ring: 'var(--success-ring)',
        icon: 'M5 13l4 4L19 7',
    },
    loss_close: {
        color: 'var(--danger)',
        bg: 'var(--danger-soft)',
        ring: 'var(--danger-ring)',
        icon: 'M6 6l12 12M18 6L6 18',
    },
    breakeven_close: {
        color: 'var(--warning)',
        bg: 'var(--warning-soft)',
        ring: 'var(--warning-ring)',
        icon: 'M4 9h16M4 15h16',
    },
    success: {
        color: 'var(--success)',
        bg: 'var(--success-soft)',
        ring: 'var(--success-ring)',
        icon: 'M5 13l4 4L19 7',
    },
    danger: {
        color: 'var(--danger)',
        bg: 'var(--danger-soft)',
        ring: 'var(--danger-ring)',
        icon: 'M12 5v14M5 12l7 7 7-7',
    },
    info: {
        color: 'var(--accent)',
        bg: 'var(--info-soft)',
        ring: 'var(--info-soft)',
        icon: 'M12 16v-4M12 8h.01',
    },
    warning: {
        color: 'var(--warning)',
        bg: 'var(--warning-soft)',
        ring: 'var(--warning-ring)',
        icon: 'M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0ZM12 9v4M12 17h.01',
    },
    message: {
        color: 'var(--level-accent)',
        bg: 'var(--level-soft)',
        ring: 'var(--level-soft)',
        icon: 'M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5Z',
    },
    team: {
        color: 'var(--text-secondary)',
        bg: 'var(--bg-elevated-2)',
        ring: 'var(--card-ring)',
        icon: 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8ZM22 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75',
    },
    trend: {
        color: 'var(--warning)',
        bg: 'var(--warning-soft)',
        ring: 'var(--warning-ring)',
        icon: 'M3 17l6-6 4 4 8-8M15 7h6v6',
    },
    structure: {
        color: 'var(--accent)',
        bg: 'var(--info-soft)',
        ring: 'var(--info-soft)',
        icon: 'M12 2L2 7l10 5 10-5-10-5ZM2 17l10 5 10-5M2 12l10 5 10-5',
    },
    level: {
        color: 'var(--level-accent)',
        bg: 'var(--level-soft)',
        ring: 'var(--level-soft)',
        icon: 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .53-.21 1.04-.59 1.42L4 17h5m6 0v1a3 3 0 11-6 0v-1',
    },
}

const resolveNotificationType = (notification) => {
    const title = (notification.title || '').toLowerCase()
    const body = (notification.body || '').toLowerCase()

    if (title.includes('trade closed')) {
        if (title.includes('profit')) return 'profit_close'
        if (title.includes('loss')) return 'loss_close'
        if (title.includes('breakeven')) return 'breakeven_close'
        return 'info'
    }

    if (notification.type === 'signal' || title.includes('signal')) {
        return body.includes('sell') ? 'sell_open' : 'buy_open'
    }

    if (title.includes('market trend')) {
        return 'trend'
    }

    if (title.includes('market structure')) {
        return 'structure'
    }

    if (notification.type === 'level' || title.includes('price near key level')) {
        return 'level'
    }

    return TYPE_META[notification.type] ? notification.type : 'info'
}

const SIGNAL_BODY_PATTERN = /^([A-Za-z0-9]{3,12})\s+(BUY|SELL)\s+signal\s+(?:is now (Open|Closed)|(?:opened|closed) at\s+([\d.,]+))/i

const formatNotificationContent = (notification) => {
    const rawTitle = notification.title || ''
    const rawBody = notification.body || ''

    const signalMatch = rawBody.match(SIGNAL_BODY_PATTERN)
    if (signalMatch) {
        const [, symbol, direction, status, price] = signalMatch
        const dir = direction.charAt(0).toUpperCase() + direction.slice(1).toLowerCase()
        return {
            title: `${symbol.toUpperCase()} ${dir} Signal`,
            body: price ? `Opened at ${price}` : `Signal is now ${status}`,
        }
    }

    if (rawTitle && rawBody.toLowerCase().startsWith(rawTitle.toLowerCase())) {
        const stripped = rawBody.slice(rawTitle.length).replace(/^[.\s]+/, '')
        return { title: rawTitle, body: stripped || rawBody }
    }

    return { title: rawTitle, body: rawBody }
}

const enrichNotification = (notification) => ({
    ...notification,
    iconType: resolveNotificationType(notification),
    ...(() => {
        const { title, body } = formatNotificationContent(notification)
        return { displayTitle: title, displayBody: body }
    })(),
})

const hasUnread = computed(() => totalUnreadCount.value > 0)
const badgeLabel = computed(() => (totalUnreadCount.value > 99 ? '99+' : String(totalUnreadCount.value)))
const hasNotifications = computed(() => notifications.value.length > 0)

const filteredNotifications = computed(() =>
    filter.value === 'unread'
        ? notifications.value.filter(n => !n.read)
        : notifications.value
)

const visibleNotifications = computed(() =>
    filteredNotifications.value.slice(0, visibleCount.value).map(enrichNotification)
)

const canRevealMoreLocally = computed(() => filteredNotifications.value.length > visibleCount.value)

// The button shows as long as there's either more already-buffered data
// to reveal, or more on the server to fetch. Once both are exhausted,
// it disappears — same as Facebook's "See previous notifications" only
// appearing while there's genuinely more history.
const showLoadMoreButton = computed(() => canRevealMoreLocally.value || hasMore.value)

watch(filter, () => {
    visibleCount.value = 5
})

const formatTime = (value) => {
    if (!value) return ''

    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return ''

    const diffMin = Math.round((Date.now() - date.getTime()) / 60000)
    if (diffMin < 1) return 'Just now'
    if (diffMin < 60) return `${diffMin}m`
    const diffHr = Math.round(diffMin / 60)
    if (diffHr < 24) return `${diffHr}h`
    const diffDay = Math.round(diffHr / 24)
    if (diffDay === 1) return 'Yesterday'
    return `${diffDay}d`
}

const fetchNotifications = async () => {
    loading.value = true

    try {
        const response = await fetch(route('team.notifications.index'), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })

        if (!response.ok) {
            throw new Error('Failed to load notifications.')
        }

        const data = await response.json()
        notifications.value = data.notifications ?? []
        totalUnreadCount.value = data.unread_count ?? 0
        currentPage.value = data.pagination?.current_page ?? 1
        hasMore.value = data.pagination?.has_more ?? false
        visibleCount.value = 5
    } catch (error) {
        console.error(error)
    } finally {
        loading.value = false
    }
}

const fetchNextServerPage = async () => {
    if (loadingMore.value || !hasMore.value) {
        return
    }

    loadingMore.value = true

    try {
        const response = await fetch(route('team.notifications.index', { page: currentPage.value + 1 }), {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })

        if (!response.ok) {
            throw new Error('Failed to load more notifications.')
        }

        const data = await response.json()
        notifications.value = [...notifications.value, ...(data.notifications ?? [])]
        totalUnreadCount.value = data.unread_count ?? totalUnreadCount.value
        currentPage.value = data.pagination?.current_page ?? currentPage.value
        hasMore.value = data.pagination?.has_more ?? false
    } catch (error) {
        console.error(error)
    } finally {
        loadingMore.value = false
    }
}

// The one and only entry point for loading more — strictly button-driven,
// never triggered by scroll position. Matches Facebook: click
// "Show previous notifications" → append older items → scroll position
// stays exactly where it was → dropdown stays open.
//
// Scroll position is preserved by explicit capture/restore of scrollTop
// around the DOM update, rather than relying on browser default
// behavior when content is appended below the fold (which is what
// caused the earlier "jerk").
const showPreviousNotifications = async () => {
    const el = listEl.value
    const previousScrollTop = el ? el.scrollTop : 0
    const previousScrollHeight = el ? el.scrollHeight : 0

    if (canRevealMoreLocally.value) {
        visibleCount.value += 5
    } else if (hasMore.value) {
        await fetchNextServerPage()
        visibleCount.value += 5
    } else {
        return
    }

    await nextTick()

    if (el) {
        // Anchor to the same content the user was already looking at.
        // Since new items are appended after the existing ones (not
        // before), scrollTop itself doesn't need to shift — but this
        // guards against any reflow-driven browser scroll anchoring by
        // restoring explicitly rather than trusting default behavior.
        const newScrollHeight = el.scrollHeight
        const heightDelta = newScrollHeight - previousScrollHeight
        el.scrollTop = previousScrollTop + Math.max(0, heightDelta === newScrollHeight ? 0 : 0)
        el.scrollTop = previousScrollTop
    }

    checkOverflow()
}

const markAsRead = async (notificationId) => {
    const notification = notifications.value.find(item => item.id === notificationId)

    if (!notification || notification.read) {
        return
    }

    notification.read = true
    notification.read_at = new Date().toISOString()

    try {
        const response = await fetch(route('team.notifications.read', notificationId), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '',
            },
            credentials: 'same-origin',
        })

        if (!response.ok) {
            throw new Error('Failed to mark notification as read.')
        }

        const data = await response.json()
        if (typeof data.unread_count === 'number') {
            totalUnreadCount.value = data.unread_count
        }
    } catch (error) {
        notification.read = false
        notification.read_at = null
        console.error(error)
    }
}

const markAllAsRead = async () => {
    const unreadItems = notifications.value.filter(item => !item.read)

    if (!unreadItems.length) {
        return
    }

    unreadItems.forEach(item => {
        item.read = true
        item.read_at = new Date().toISOString()
    })

    try {
        const response = await fetch(route('team.notifications.read-all'), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document
                    .querySelector('meta[name="csrf-token"]')
                    ?.getAttribute('content') ?? '',
            },
            credentials: 'same-origin',
        })

        if (!response.ok) {
            throw new Error('Failed to mark all notifications as read.')
        }

        const data = await response.json()
        if (typeof data.unread_count === 'number') {
            totalUnreadCount.value = data.unread_count
        }
    } catch (error) {
        unreadItems.forEach(item => {
            item.read = false
            item.read_at = null
        })
        console.error(error)
    }
}

const toggleDropdown = async () => {
    open.value = !open.value

    if (open.value) {
        // Always refetch on open so notifications created server-side
        // (levels monitor, admin actions) appear without a page refresh.
        await fetchNotifications()
    }
}

const prependRealtimeNotification = (payload) => {
    const signal = payload?.signal
    const tradeLog = payload?.trade_log

    if (!signal?.id) {
        return
    }

    const existing = notifications.value.find(item => item.signal_id === signal.id)

    if (existing) {
        return
    }

    if (signal.status_raw === 'closed' && tradeLog) {
        const resultLabel = tradeLog.result === 'profit'
            ? 'Profit'
            : (tradeLog.result === 'loss' ? 'Loss' : 'Breakeven')

        const sign = Number(tradeLog.profit_loss) >= 0 ? '+' : ''
        const reasonLabel = tradeLog.close_reason === 'tp'
            ? 'Take Profit'
            : (tradeLog.close_reason === 'sl' ? 'Stop Loss' : 'Manual Close')

        notifications.value.unshift({
            id: `temp-${signal.id}-${Date.now()}`,
            signal_id: signal.id,
            title: `Trade closed — ${resultLabel}`,
            body: `${tradeLog.symbol ?? signal.symbol} ${sign}${Number(tradeLog.profit_loss).toFixed(2)} · ${reasonLabel}`,
            type: 'signal',
            read: false,
            read_at: null,
            time: new Date().toISOString(),
            is_temp: true,
        })

        totalUnreadCount.value += 1
        visibleCount.value = Math.max(visibleCount.value, 5)

        if (notifications.value.length > 20) {
            notifications.value = notifications.value.slice(0, 20)
        }

        return
    }

    notifications.value.unshift({
        id: `temp-${signal.id}-${Date.now()}`,
        signal_id: signal.id,
        title: signal.status === 'Closed' ? 'Trading signal closed' : 'Trading signal updated',
        body: `${signal.symbol} ${signal.type} signal is now ${signal.status}.`,
        type: 'signal',
        read: false,
        read_at: null,
        time: new Date().toISOString(),
        is_temp: true,
    })

    totalUnreadCount.value += 1
    visibleCount.value = Math.max(visibleCount.value, 5)

    if (notifications.value.length > 20) {
        notifications.value = notifications.value.slice(0, 20)
    }
}

const prependLevelNotification = (payload) => {
    if (!payload?.levels?.length) return

    const detail = payload.levels
        .map(level => `${level.label} ${Number(level.value).toFixed(2)}`)
        .join(' \u00b7 ')

    notifications.value.unshift({
        id: `temp-level-${Date.now()}`,
        title: 'Price near key level',
        body: `Gold ${Number(payload.price).toFixed(2)} near ${detail}.`,
        type: 'level',
        read: false,
        read_at: null,
        time: new Date().toISOString(),
        is_temp: true,
    })

    totalUnreadCount.value += 1
    visibleCount.value = Math.max(visibleCount.value, 5)

    if (notifications.value.length > 20) {
        notifications.value = notifications.value.slice(0, 20)
    }
}


const checkOverflow = () => {
    const el = listEl.value
    if (!el) return
    isOverflowing.value = el.scrollHeight - el.clientHeight > 4
    atBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight <= 4
}

const onListScroll = () => {
    const el = listEl.value
    if (!el) return
    atBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight <= 4
}

const onListWheel = (event) => {
    const el = listEl.value
    if (!el) return

    const atTop = el.scrollTop <= 0
    const atBottomEdge = el.scrollHeight - el.scrollTop - el.clientHeight <= 1

    if ((atTop && event.deltaY < 0) || (atBottomEdge && event.deltaY > 0)) {
        event.preventDefault()
    }
}

let lockedScrollY = 0

const lockBodyScroll = () => {
    lockedScrollY = window.scrollY || window.pageYOffset || 0
    document.body.style.position = 'fixed'
    document.body.style.top = `-${lockedScrollY}px`
    document.body.style.left = '0'
    document.body.style.right = '0'
    document.body.style.width = '100%'
}

const unlockBodyScroll = () => {
    document.body.style.position = ''
    document.body.style.top = ''
    document.body.style.left = ''
    document.body.style.right = ''
    document.body.style.width = ''
    window.scrollTo(0, lockedScrollY)
}

watch(open, async (isOpen) => {
    if (isOpen) {
        lockBodyScroll()
        await nextTick()
        checkOverflow()
    } else {
        unlockBodyScroll()
    }
})

watch(visibleNotifications, async () => {
    await nextTick()
    checkOverflow()
})

let channel = null

const handleOutsideClick = (event) => {
    if (!rootRef.value) return

    if (!rootRef.value.contains(event.target)) {
        open.value = false
    }
}

const handleKeydown = (event) => {
    if (event.key === 'Escape') open.value = false
}

onMounted(async () => {
    await fetchNotifications()

    if (window.Echo) {
        channel = window.Echo.private('team.dashboard')
            .listen('.signal.updated', (payload) => {
                prependRealtimeNotification(payload)
            })
            .listen('.level.alert', (payload) => {
                prependLevelNotification(payload)
            })
    }

    document.addEventListener('click', handleOutsideClick)
    document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleOutsideClick)
    document.removeEventListener('keydown', handleKeydown)

    if (open.value) {
        unlockBodyScroll()
    }

    if (window.Echo && channel) {
        window.Echo.leave('private-team.dashboard')
    }
})
</script>

<template>
    <div ref="rootRef" class="relative">
        <button
            type="button"
            class="notif-focus relative inline-flex h-10 w-10 items-center justify-center rounded-2xl text-[var(--text-secondary)] transition-colors duration-150"
            :class="open ? 'bg-[var(--bg-hover)] text-[var(--text-primary)]' : 'bg-[var(--bg-elevated)] hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]'"
            :aria-expanded="open"
            aria-label="Notifications"
            @click.stop="toggleDropdown"
        >
            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 8.5a6 6 0 1112 0c0 4.2 1.2 5.9 2 7a1 1 0 01-.8 1.6H4.8A1 1 0 014 15.5c.8-1.1 2-2.8 2-7Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.5 19a2.6 2.6 0 005 0" />
            </svg>

            <span
                v-if="hasUnread"
                class="pointer-events-none absolute right-[3px] top-[3px] flex h-4 min-w-[16px] items-center justify-center rounded-full px-[3px] text-[9.5px] font-bold leading-none tracking-tight"
                style="background: var(--danger); color: var(--bg-app); box-shadow: 0 0 0 2.5px var(--bg-app), 0 2px 6px rgba(255,107,129,0.4)"
            >
                {{ badgeLabel }}
            </span>
        </button>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 -translate-y-1.5 scale-[0.97]"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 -translate-y-1.5 scale-[0.97]"
        >
            <div
                v-if="open"
                class="notif-panel absolute right-0 z-50 mt-3 w-[26rem] max-w-[92vw] origin-top-right overflow-hidden rounded-[20px] border"
                style="
                    background: var(--toast-bg);
                    border-color: var(--toast-border);
                    box-shadow: var(--card-shadow-lg);
                "
            >
                <div class="flex items-center justify-between px-5 pb-3 pt-5">
                    <h2 class="text-[15px] font-semibold tracking-tight text-[var(--text-primary)]">Notifications</h2>

                    <button
                        v-if="hasUnread"
                        type="button"
                        class="notif-focus flex items-center gap-1.5 rounded-lg px-2 py-1 text-[12px] font-medium text-[var(--text-tertiary)] transition-colors hover:bg-[var(--bg-elevated)] hover:text-[var(--text-primary)]"
                        @click="markAllAsRead"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 12l5 5L20 6" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l1.5 1.5L16 8" opacity="0.55" />
                        </svg>
                        Mark all read
                    </button>
                </div>

                <div class="flex items-center justify-between border-b border-[var(--border-faint)] px-5 pb-3.5">
                    <div class="inline-flex items-center rounded-full bg-[var(--bg-elevated)] p-[3px]">
                        <button
                            type="button"
                            class="notif-focus rounded-full px-3.5 py-[5px] text-[12px] font-medium transition-all duration-150"
                            :class="filter === 'all'
                                ? 'bg-[var(--bg-elevated-2)] text-[var(--text-primary)] shadow-[0_1px_2px_rgba(0,0,0,0.25)]'
                                : 'text-[var(--text-tertiary)] hover:text-[var(--text-secondary)]'"
                            @click="filter = 'all'"
                        >
                            All
                        </button>
                        <button
                            type="button"
                            class="notif-focus flex items-center gap-1.5 rounded-full px-3.5 py-[5px] text-[12px] font-medium transition-all duration-150"
                            :class="filter === 'unread'
                                ? 'bg-[var(--bg-elevated-2)] text-[var(--text-primary)] shadow-[0_1px_2px_rgba(0,0,0,0.25)]'
                                : 'text-[var(--text-tertiary)] hover:text-[var(--text-secondary)]'"
                            @click="filter = 'unread'"
                        >
                            Unread
                            <span
                                v-if="hasUnread"
                                class="inline-flex h-[16px] min-w-[16px] items-center justify-center rounded-full px-1 text-[10px] font-semibold tabular-nums"
                                :style="filter === 'unread'
                                    ? 'background: var(--info-soft); color: var(--accent)'
                                    : 'background: var(--bg-elevated-2); color: var(--text-secondary)'"
                            >
                                {{ totalUnreadCount }}
                            </span>
                        </button>
                    </div>

                    <span v-if="!hasUnread" class="flex items-center gap-1 text-[11.5px] text-[var(--text-tertiary)]">
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        All caught up
                    </span>
                </div>

                <div
                    ref="listEl"
                    class="notif-scroll relative max-h-[23rem] overflow-y-auto overscroll-contain"
                    :class="(isOverflowing && !atBottom) && 'is-overflowing'"
                    @scroll="onListScroll"
                    @wheel="onListWheel"
                >
                    <div v-if="loading" class="space-y-2.5 p-3">
                        <div
                            v-for="item in 3"
                            :key="item"
                            class="rounded-2xl border border-[var(--border-faint)] bg-[var(--bg-elevated)] p-3.5"
                        >
                            <div class="h-3.5 w-32 animate-pulse rounded bg-[var(--bg-elevated-2)]"></div>
                            <div class="mt-2.5 h-3 w-full animate-pulse rounded bg-[var(--bg-elevated)]"></div>
                            <div class="mt-2 h-3 w-24 animate-pulse rounded bg-[var(--bg-elevated)]"></div>
                        </div>
                    </div>

                    <ul v-else-if="visibleNotifications.length" class="divide-y divide-[var(--border-faint)]">
                        <li
                            v-for="(n, i) in visibleNotifications"
                            :key="n.id"
                            :ref="el => setItemRef(el, i)"
                            class="notif-item notif-focus relative flex cursor-pointer gap-3 py-3.5 pl-4 pr-5 outline-none transition-colors duration-100"
                            :class="!n.read && 'is-unread'"
                            tabindex="0"
                            role="button"
                            :aria-label="`${n.displayTitle}${n.read ? '' : ' (unread)'}`"
                            @click="markAsRead(n.id)"
                            @keydown.enter="markAsRead(n.id)"
                            @keydown.space.prevent="markAsRead(n.id)"
                        >
                            <span
                                class="absolute inset-y-0 left-0 w-[2.5px] rounded-r-full transition-opacity"
                                :style="{ background: 'var(--accent)', opacity: n.read ? 0 : 1 }"
                            />

                            <span
                                class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full ring-1"
                                :style="{
                                    background: TYPE_META[n.iconType].bg,
                                    color: TYPE_META[n.iconType].color,
                                    '--tw-ring-color': TYPE_META[n.iconType].ring,
                                }"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1">
                                    <path :d="TYPE_META[n.iconType].icon" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p
                                        class="truncate text-[13px] leading-tight"
                                        :class="n.read ? 'font-medium text-[var(--text-secondary)]' : 'font-semibold text-[var(--text-primary)]'"
                                    >
                                        {{ n.displayTitle }}
                                    </p>
                                    <span class="flex shrink-0 items-center gap-1.5 whitespace-nowrap">
                                        <span
                                            v-if="!n.read"
                                            class="h-[6px] w-[6px] rounded-full"
                                            style="background: var(--accent); box-shadow: 0 0 6px rgba(92,200,255,0.7)"
                                        />
                                        <span class="text-[11px] tabular-nums text-[var(--text-faint)]">{{ formatTime(n.time) }}</span>
                                    </span>
                                </div>
                                <p class="mt-1 line-clamp-2 text-[12.5px] leading-[1.45] text-[var(--text-tertiary)]">
                                    {{ n.displayBody }}
                                </p>
                            </div>
                        </li>
                    </ul>

                    <div v-else class="flex flex-col items-center justify-center px-6 py-14 text-center">
                        <div class="flex h-11 w-11 items-center justify-center rounded-full bg-[var(--bg-elevated)] text-[var(--text-faint)] ring-1 ring-[var(--border-soft)]">
                            <svg class="h-[18px] w-[18px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 8.5a6 6 0 1112 0c0 4.2 1.2 5.9 2 7a1 1 0 01-.8 1.6H4.8A1 1 0 014 15.5c.8-1.1 2-2.8 2-7Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.5 19a2.6 2.6 0 005 0" />
                            </svg>
                        </div>
                        <p class="mt-3 text-[13px] font-medium text-[var(--text-primary)]">
                            {{ hasNotifications ? "You're all caught up" : 'No notifications yet' }}
                        </p>
                        <p class="mt-1 max-w-[15rem] text-[12px] leading-relaxed text-[var(--text-tertiary)]">
                            New activity will appear here.
                        </p>
                    </div>
                </div>

                <div v-if="showLoadMoreButton" class="border-t border-[var(--border-faint)] px-5 py-3">
                    <button
                        type="button"
                        class="notif-focus group flex w-full items-center justify-center gap-1.5 rounded-xl py-2 text-[12px] font-medium text-[var(--text-tertiary)] transition-colors hover:bg-[var(--bg-elevated)] hover:text-[var(--text-primary)] disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="loadingMore"
                        @click="showPreviousNotifications"
                    >
                        <template v-if="loadingMore">
                            <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" />
                                <path class="opacity-90" d="M21 12a9 9 0 00-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                            </svg>
                            Loading…
                        </template>
                        <template v-else>
                            Load more
                            <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-y-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12l7 7 7-7" />
                            </svg>
                        </template>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.notif-panel {
    backdrop-filter: blur(28px);
    -webkit-backdrop-filter: blur(28px);
}

.notif-item.is-unread {
    background: var(--info-soft);
}

.notif-item:hover {
    background: var(--bg-hover);
}

.notif-item.is-unread:hover {
    background: var(--info-soft);
}

.notif-focus:focus-visible {
    box-shadow: inset 0 0 0 1.5px var(--accent), 0 0 0 3px rgba(92, 200, 255, 0.18);
}

.notif-scroll {
    scrollbar-width: thin;
    scrollbar-color: var(--border-soft) transparent;
    transition: mask-image 0.15s ease;
    -webkit-transition: -webkit-mask-image 0.15s ease;
    overscroll-behavior: contain;
}
.notif-scroll.is-overflowing {
    mask-image: linear-gradient(to bottom, black calc(100% - 24px), transparent 100%);
    -webkit-mask-image: linear-gradient(to bottom, black calc(100% - 24px), transparent 100%);
}
.notif-scroll::-webkit-scrollbar {
    width: 6px;
}
.notif-scroll::-webkit-scrollbar-thumb {
    background: var(--border-soft);
    border-radius: 999px;
}
.notif-scroll::-webkit-scrollbar-thumb:hover {
    background: var(--bg-hover);
}
.notif-scroll::-webkit-scrollbar-track {
    background: transparent;
}
</style>