import { ref, onBeforeUnmount } from 'vue'
import axios from 'axios'

/**
 * Presence-channel wiring for the team chat room.
 *
 * Owned by ChatWidget (always mounted) so messages are heard even when the
 * drawer is closed — that's what powers the unread badge + toast. ChatPanel
 * consumes this same state as a prop-like object.
 *
 * Assumes window.Echo is bootstrapped with Pusher (same as NotificationBell)
 * and axios carries the session cookie.
 */
export function useChatRoom({ currentUserId }) {
  const messages = ref([])
  const members = ref([])
  const typingUsers = ref({})
  const features = ref({ edit: false, delete: false, read_receipts: false })
  const loading = ref(false)
  const nextCursor = ref(null)

  // First unread message id — where the "New messages" divider renders.
  const firstUnreadId = ref(null)

  // Fired whenever a message arrives from someone else. The widget listens
  // to decide whether to bump the badge / toast (drawer closed) or mark
  // read (drawer open).
  const onIncoming = ref(null)

  let channel = null
  const typingTimers = {}

  async function loadHistory() {
    loading.value = true
    try {
      const url = nextCursor.value
        ? `/chat/messages?cursor=${encodeURIComponent(nextCursor.value)}`
        : '/chat/messages'
      const { data } = await axios.get(url)
      const batch = [...data.data].reverse()
      messages.value = nextCursor.value ? [...batch, ...messages.value] : batch
      nextCursor.value = data.next_cursor
      if (data.features) features.value = data.features
    } finally {
      loading.value = false
    }
  }

  function connect() {
    if (channel) return
    channel = window.Echo.join('chat.room')
      .here((users) => { members.value = users })
      .joining((user) => { members.value = [...members.value, user] })
      .leaving((user) => {
        members.value = members.value.filter((m) => m.id !== user.id)
        const next = { ...typingUsers.value }; delete next[user.id]; typingUsers.value = next
      })
      .listen('.message.sent', (e) => {
        messages.value.push(e.message)
        if (e.message.user_id !== currentUserId && typeof onIncoming.value === 'function') {
          onIncoming.value(e.message)
        }
      })
      .listen('.message.updated', (e) => {
        const i = messages.value.findIndex((m) => m.id === e.message.id)
        if (i !== -1) messages.value.splice(i, 1, e.message)
      })
      .listen('.message.deleted', (e) => {
        const i = messages.value.findIndex((m) => m.id === e.id)
        if (i !== -1) messages.value.splice(i, 1, { ...messages.value[i], deleted: true })
      })
      .listenForWhisper('typing', (e) => {
        if (e.id === currentUserId) return
        typingUsers.value = { ...typingUsers.value, [e.id]: { name: e.name } }
        clearTimeout(typingTimers[e.id])
        typingTimers[e.id] = setTimeout(() => {
          const next = { ...typingUsers.value }; delete next[e.id]; typingUsers.value = next
        }, 3000)
      })
  }

  let lastWhisper = 0
  function sendTyping(name) {
    const now = Date.now()
    if (now - lastWhisper < 2500) return
    lastWhisper = now
    channel?.whisper('typing', { id: currentUserId, name })
  }

  async function sendMessage(body) {
    const { data } = await axios.post('/chat/messages', { body })
    messages.value.push(data.message)
    return data.message
  }

  async function editMessage(id, body) {
    const { data } = await axios.put(`/chat/messages/${id}`, { body })
    const i = messages.value.findIndex((m) => m.id === id)
    if (i !== -1) messages.value.splice(i, 1, data.message)
  }

  async function deleteMessage(id) {
    await axios.delete(`/chat/messages/${id}`)
    const i = messages.value.findIndex((m) => m.id === id)
    if (i !== -1) messages.value.splice(i, 1, { ...messages.value[i], deleted: true })
  }

  async function markRead() {
    try { await axios.post('/chat/read') } catch (e) { /* non-fatal */ }
  }

  // Set the divider to the earliest unread message (first from someone else
  // after the last one the user has already seen). Called when opening.
  function markFirstUnread() {
    const list = messages.value
    for (let i = 0; i < list.length; i += 1) {
      if (list[i].user_id !== currentUserId) {
        firstUnreadId.value = list[i].id
        return
      }
    }
    firstUnreadId.value = null
  }
  function clearUnreadDivider() { firstUnreadId.value = null }

  function disconnect() {
    if (channel) { window.Echo.leave('chat.room'); channel = null }
    Object.values(typingTimers).forEach(clearTimeout)
  }

  onBeforeUnmount(disconnect)

  return {
    messages, members, typingUsers, features, loading, nextCursor,
    firstUnreadId, onIncoming,
    loadHistory, connect, disconnect, sendMessage, editMessage, deleteMessage,
    sendTyping, markRead, markFirstUnread, clearUnreadDivider,
  }
}