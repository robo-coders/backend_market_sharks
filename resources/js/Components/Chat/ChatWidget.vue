<template>
  <!-- Launcher -->
  <button v-if="!open" type="button" class="fab" aria-label="Open team chat" @click="openDrawer">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="23" height="23">
      <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z" />
    </svg>
    <span v-if="unread > 0" class="fab__badge">{{ unread > 99 ? '99+' : unread }}</span>
  </button>

  <transition name="toast">
    <button v-if="toast" type="button" class="toast" @click="openDrawer">
      <span class="toast__avatar">{{ toastInitial }}</span>
      <span class="toast__body">
        <span class="toast__name">{{ toast.author }}</span>
        <span class="toast__text">{{ toast.body }}</span>
      </span>
    </button>
  </transition>

  <transition name="fade">
    <div v-if="open" class="backdrop" @click="closeDrawer" />
  </transition>

  <transition name="slide">
    <aside v-if="open" class="drawer" role="dialog" aria-label="Team chat">
      <button type="button" class="drawer__close" aria-label="Close chat" @click="closeDrawer">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
      <ChatPanel
        v-if="user"
        ref="panel"
        :room="room"
        :current-user-id="user.id"
        :current-user-name="user.name ?? 'You'"
        :can-send="true"
        @load-more="onLoadMore"
        @sent="onSent"
        @seen="onSeen"
      />
    </aside>
  </transition>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { usePage } from '@inertiajs/vue3'
import axios from 'axios'
import ChatPanel from './ChatPanel.vue'
import { useChatRoom } from './useChatRoom.js'

const page = usePage()
const user = computed(() => page.props.auth?.user)

const open = ref(false)
const unread = ref(0)
const toast = ref(null)
const panel = ref(null)
let toastTimer = null

const room = useChatRoom({ currentUserId: user.value?.id })

const toastInitial = computed(() => (toast.value?.author || '?').charAt(0).toUpperCase())

/* Notification sound — synthesized soft two-note rise. Reuses the
 * dashboard's AudioContext-unlock pattern so it never throws under
 * autoplay policy, and respects alert_sounds_muted. */
let audioCtx = null
let audioUnlocked = false

function unlockAudio() {
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext
    if (!Ctx) return
    if (!audioCtx) audioCtx = new Ctx()
    if (audioCtx.state === 'suspended') audioCtx.resume()
    audioUnlocked = true
  } catch (e) { /* ignore */ }
}

function playNotification() {
  if (user.value?.alert_sounds_muted) return
  if (!audioUnlocked || !audioCtx) return
  try {
    const now = audioCtx.currentTime
    // Professional two-note chime (G5 -> D6): each note is a sine
    // fundamental plus a quiet octave harmonic for body, with a fast
    // attack and smooth decay. Louder (0.34 peak) but still refined.
    const notes = [{ f: 784.0, t: 0 }, { f: 1174.7, t: 0.11 }]
    notes.forEach(({ f, t }) => {
      const start = now + t

      const osc = audioCtx.createOscillator()
      const gain = audioCtx.createGain()
      osc.type = 'sine'
      osc.frequency.setValueAtTime(f, start)
      gain.gain.setValueAtTime(0.0001, start)
      gain.gain.exponentialRampToValueAtTime(0.34, start + 0.015)
      gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.42)
      osc.connect(gain); gain.connect(audioCtx.destination)
      osc.start(start); osc.stop(start + 0.45)

      const harm = audioCtx.createOscillator()
      const harmGain = audioCtx.createGain()
      harm.type = 'sine'
      harm.frequency.setValueAtTime(f * 2, start)
      harmGain.gain.setValueAtTime(0.0001, start)
      harmGain.gain.exponentialRampToValueAtTime(0.10, start + 0.015)
      harmGain.gain.exponentialRampToValueAtTime(0.0001, start + 0.30)
      harm.connect(harmGain); harmGain.connect(audioCtx.destination)
      harm.start(start); harm.stop(start + 0.32)
    })
  } catch (e) { /* ignore */ }
}

room.onIncoming.value = (msg) => {
  if (open.value) { room.markRead(); return }
  unread.value += 1
  playNotification()
  showToast(msg)
}

function showToast(msg) {
  toast.value = { author: msg.author, body: msg.body }
  clearTimeout(toastTimer)
  toastTimer = setTimeout(() => { toast.value = null }, 4500)
}

async function openDrawer() {
  unlockAudio()
  open.value = true
  toast.value = null
  if (!room.messages.value.length) await room.loadHistory()
  room.markFirstUnread()
  await nextTick()
  panel.value?.scrollToBottom()
  unread.value = 0
  room.markRead()
}
function closeDrawer() {
  open.value = false
  room.clearUnreadDivider()
}

function onSent() { unread.value = 0 }
function onSeen() { unread.value = 0; room.markRead() }
async function onLoadMore(after) {
  await room.loadHistory()
  if (typeof after === 'function') after()
}

async function fetchUnread() {
  try {
    const { data } = await axios.get('/chat/unread')
    if (!open.value) unread.value = data.unread ?? 0
  } catch (e) { /* silent */ }
}

function onKey(e) { if (e.key === 'Escape' && open.value) closeDrawer() }

function firstGestureUnlock() {
  unlockAudio()
  window.removeEventListener('click', firstGestureUnlock)
  window.removeEventListener('keydown', firstGestureUnlock)
}

onMounted(async () => {
  if (!user.value || !window.Echo) return
  room.connect()
  await room.loadHistory()
  fetchUnread()
  window.addEventListener('keydown', onKey)
  window.addEventListener('click', firstGestureUnlock, { once: true })
  window.addEventListener('keydown', firstGestureUnlock, { once: true })
})

onBeforeUnmount(() => {
  clearTimeout(toastTimer)
  window.removeEventListener('keydown', onKey)
  window.removeEventListener('click', firstGestureUnlock)
  window.removeEventListener('keydown', firstGestureUnlock)
  room.disconnect()
})
</script>

<style scoped>
.fab {
  position: fixed; right: 22px; bottom: 22px; z-index: 120;
  display: inline-flex; align-items: center; justify-content: center;
  width: 54px; height: 54px; border: none; border-radius: 50%; cursor: pointer;
  color: #fff; background: #6366f1;
  box-shadow: 0 10px 30px color-mix(in srgb, #6366f1 42%, transparent);
  transition: transform .16s ease, box-shadow .16s ease;
}
.fab:hover { transform: translateY(-2px) scale(1.03); }
.fab__badge {
  position: absolute; top: -3px; right: -3px; min-width: 20px; height: 20px; padding: 0 5px;
  border-radius: 10px; background: var(--danger, #ff5470); color: #fff;
  font-size: 11px; font-weight: 700; line-height: 20px; text-align: center;
  border: 2px solid var(--bg-canvas, #fff);
}

.toast {
  position: fixed; right: 22px; bottom: 88px; z-index: 121;
  display: flex; align-items: center; gap: 10px; max-width: 300px; text-align: left;
  padding: 10px 14px 10px 10px; border: none; border-radius: 14px; cursor: pointer;
  background: var(--card-bg, #fff); color: var(--text-primary, #1f1f1d);
  box-shadow: var(--card-shadow, 0 14px 34px rgba(15,23,42,0.16));
  border: 1px solid var(--border-soft, rgba(15,23,42,0.08));
}
.toast__avatar {
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
  width: 30px; height: 30px; border-radius: 50%; font-size: 12px; font-weight: 600;
  background: color-mix(in srgb, #6366f1 16%, transparent); color: #6366f1;
}
.toast__body { display: flex; flex-direction: column; min-width: 0; }
.toast__name { font-size: 12.5px; font-weight: 600; }
.toast__text { font-size: 12.5px; color: var(--text-secondary, #6b6b66); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.backdrop { position: fixed; inset: 0; z-index: 125; background: rgba(0,0,0,0.42); backdrop-filter: blur(2px); }

.drawer {
  position: fixed; top: 0; right: 0; bottom: 0; z-index: 130; width: 408px; max-width: 100vw;
  display: flex; flex-direction: column;
  background: var(--bg-canvas, #fff); border-left: 1px solid var(--border-soft, rgba(15,23,42,0.09));
  box-shadow: -24px 0 70px rgba(0,0,0,0.24);
}
.drawer__close {
  position: absolute; top: 14px; right: 14px; z-index: 2;
  display: inline-flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border: none; border-radius: 9px; cursor: pointer;
  color: var(--text-secondary, #6b6b66); background: transparent; transition: background .15s;
}
.drawer__close:hover { background: var(--bg-elevated, rgba(15,23,42,0.06)); }

.slide-enter-active, .slide-leave-active { transition: transform .28s cubic-bezier(.32,.72,0,1); }
.slide-enter-from, .slide-leave-to { transform: translateX(100%); }
.fade-enter-active, .fade-leave-active { transition: opacity .28s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.toast-enter-active, .toast-leave-active { transition: opacity .25s, transform .25s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translateY(8px); }

@media (max-width: 480px) { .drawer { width: 100vw; } }
@media (prefers-reduced-motion: reduce) {
  .slide-enter-active, .slide-leave-active, .fade-enter-active, .fade-leave-active, .toast-enter-active, .toast-leave-active { transition: none; }
  .fab:hover { transform: none; }
}
</style>