<template>
  <div class="chat">
    <!-- Header -->
    <header class="chat__head">
      <div class="chat__id">
        <span class="chat__pulse" aria-hidden="true"></span>
        <div class="chat__idtext">
          <span class="chat__name">Team chat</span>
          <span class="chat__meta">{{ members.length }} {{ members.length === 1 ? 'person' : 'people' }} online</span>
        </div>
      </div>
      <button
        class="chat__memberbtn" type="button"
        :class="{ 'is-active': showMembers }"
        :aria-expanded="showMembers" aria-label="Show members"
        @click="showMembers = !showMembers"
      >
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" width="19" height="19">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13A4 4 0 0 1 16 11" />
        </svg>
      </button>
    </header>

    <!-- Members -->
    <transition name="drop">
      <div v-if="showMembers" class="chat__members">
        <div v-for="m in members" :key="m.id" class="chat__memberchip">
          <span class="avatar avatar--sm" :style="avatarStyle(m.name)">{{ initial(m.name) }}</span>
          <span class="chat__membername">{{ m.name }}</span>
          <RoleBadge :role="m.role" />
        </div>
      </div>
    </transition>

    <!-- Timeline -->
    <div ref="scroller" class="chat__scroll" @scroll="onScroll">
      <button
        v-if="nextCursor" class="chat__more" type="button" :disabled="loading"
        @click="$emit('load-more')"
      >{{ loading ? 'Loading…' : 'Load earlier messages' }}</button>

      <div v-if="!messages.length && !loading" class="chat__empty">
        <div class="chat__empty-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="26" height="26">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5Z" />
          </svg>
        </div>
        <p class="chat__empty-title">No messages yet</p>
        <p class="chat__empty-sub">Say hello to the team to get things started.</p>
      </div>

      <div class="stream">
        <template v-for="(m, i) in messages" :key="m.id">
          <div v-if="showDateDivider(i)" class="divider">
            <span class="divider__label">{{ dateLabel(m.created_at) }}</span>
          </div>

          <div v-if="m.id === firstUnreadId" class="unread">
            <span class="unread__label">New messages</span>
          </div>

          <div
            class="row"
            :class="[
              mine(m) ? 'row--mine' : 'row--theirs',
              startsGroup(i) ? 'row--first' : 'row--cont',
            ]"
          >
            <!-- avatar gutter: theirs only, rendered on last of group so it
                 sits at the visual anchor; spacer keeps alignment otherwise -->
            <div v-if="!mine(m)" class="row__gutter">
              <span
                v-if="endsGroup(i)"
                class="avatar"
                :style="avatarStyle(m.author)"
                :title="m.author"
              >{{ initial(m.author) }}</span>
            </div>

            <div class="row__main">
              <div v-if="!mine(m) && startsGroup(i)" class="row__sender">
                <span class="row__name">{{ m.author }}</span>
                <RoleBadge :role="m.role" />
              </div>

              <div class="bubble-wrap">
                <div v-if="m.deleted" class="bubble bubble--deleted" :class="tail(i)">Message deleted</div>

                <template v-else-if="editingId === m.id">
                  <div class="bubble bubble--edit">
                    <textarea
                      v-model="editDraft" class="bubble__ta" rows="2"
                      @keydown.enter.exact.prevent="commitEdit(m)"
                      @keydown.esc="cancelEdit"
                    ></textarea>
                    <div class="bubble__editrow">
                      <button type="button" class="lk" @click="commitEdit(m)">Save</button>
                      <button type="button" class="lk lk--muted" @click="cancelEdit">Cancel</button>
                    </div>
                  </div>
                </template>

                <template v-else>
                  <div class="bubble" :class="tail(i)">
                    <span class="bubble__text">{{ m.body }}</span>
                    <span v-if="m.edited" class="bubble__edited">edited</span>
                  </div>
                  <div v-if="canModify(m)" class="row__actions">
                    <button v-if="features.edit && mine(m)" type="button" class="ib" aria-label="Edit" @click="startEdit(m)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </button>
                    <button v-if="features.delete" type="button" class="ib ib--danger" aria-label="Delete" @click="askDelete(m)">
                      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="13" height="13"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                    </button>
                  </div>
                </template>

                <div v-if="endsGroup(i) && !m.deleted" class="row__time">{{ time(m.created_at) }}</div>
              </div>
            </div>
          </div>
        </template>

        <div v-if="typingLabel" class="row row--theirs row--first">
          <div class="row__gutter"><span class="avatar avatar--ghost"></span></div>
          <div class="row__main">
            <div class="bubble bubble--typing"><span class="d"></span><span class="d"></span><span class="d"></span></div>
            <div class="row__time">{{ typingLabel }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Composer -->
    <footer class="chat__composer">
      <div class="composer">
        <textarea
          v-model="draft" ref="input" class="composer__ta" rows="1"
          placeholder="Write a message…"
          :disabled="!canSend"
          @input="onInput" @keydown.enter.exact.prevent="send"
        ></textarea>
        <button
          class="composer__send" type="button"
          :class="{ 'is-ready': canSend && draft.trim() }"
          :disabled="!canSend || !draft.trim()"
          aria-label="Send message"
          @click="send"
        >
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="m22 2-7 20-4-9-9-4Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M22 2 11 13"/></svg>
        </button>
      </div>
    </footer>
    <p v-if="!canSend" class="chat__readonly">You have read-only access to this chat.</p>

    <!-- Delete confirmation — teleported to body; unique class names and
         inline-critical styles so nothing can hide the card -->
    <Teleport to="body">
      <div
        v-if="deleting"
        class="cdel-backdrop"
        style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;"
        @click.self="deleting = null"
      >
        <div class="cdel-card" role="alertdialog" aria-modal="true" aria-labelledby="cdel-title">
          <div class="cdel-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
          </div>
          <h3 id="cdel-title" class="cdel-title">Delete this message?</h3>
          <p class="cdel-body">"{{ truncate(deleting.body, 80) }}"</p>
          <p class="cdel-sub">This can't be undone. A "message deleted" placeholder will remain.</p>
          <div class="cdel-actions">
            <button type="button" class="cdel-btn cdel-btn--ghost" @click="deleting = null">Cancel</button>
            <button type="button" class="cdel-btn cdel-btn--danger" @click="confirmDelete">Delete</button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue'
import RoleBadge from './RoleBadge.vue'

const props = defineProps({
  room: { type: Object, required: true },
  currentUserId: { type: Number, required: true },
  currentUserName: { type: String, required: true },
  canSend: { type: Boolean, default: true },
})
const emit = defineEmits(['load-more', 'sent', 'seen'])

const messages = computed(() => props.room.messages.value)
const members = computed(() => props.room.members.value)
const typingUsers = computed(() => props.room.typingUsers.value)
const features = computed(() => props.room.features.value)
const loading = computed(() => props.room.loading.value)
const nextCursor = computed(() => props.room.nextCursor.value)
const firstUnreadId = computed(() => props.room.firstUnreadId.value)

const scroller = ref(null)
const input = ref(null)
const draft = ref('')
const editingId = ref(null)
const editDraft = ref('')
const showMembers = ref(false)
const deleting = ref(null)

const mine = (m) => m.user_id === props.currentUserId

function sameGroup(a, b) {
  if (!a || !b || a.user_id !== b.user_id) return false
  if (!sameDay(a.created_at, b.created_at)) return false
  return Math.abs(new Date(b.created_at) - new Date(a.created_at)) < 5 * 60 * 1000
}
const startsGroup = (i) => !sameGroup(messages.value[i - 1], messages.value[i])
const endsGroup = (i) => !sameGroup(messages.value[i], messages.value[i + 1])
function tail(i) {
  const t = endsGroup(i)
  if (mine(messages.value[i])) return t ? 'bubble--mine bubble--tr' : 'bubble--mine'
  return t ? 'bubble--theirs bubble--tl' : 'bubble--theirs'
}

function sameDay(a, b) {
  const d1 = new Date(a), d2 = new Date(b)
  return d1.getFullYear() === d2.getFullYear() && d1.getMonth() === d2.getMonth() && d1.getDate() === d2.getDate()
}
function showDateDivider(i) {
  if (i === 0) return true
  return !sameDay(messages.value[i - 1].created_at, messages.value[i].created_at)
}
function dateLabel(iso) {
  const d = new Date(iso), now = new Date()
  const t = new Date(now); t.setHours(0, 0, 0, 0)
  const y = new Date(t); y.setDate(y.getDate() - 1)
  const dd = new Date(d); dd.setHours(0, 0, 0, 0)
  if (dd.getTime() === t.getTime()) return 'Today'
  if (dd.getTime() === y.getTime()) return 'Yesterday'
  return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: d.getFullYear() === now.getFullYear() ? undefined : 'numeric' })
}

const typingLabel = computed(() => {
  const names = Object.values(typingUsers.value).map((u) => u.name)
  if (!names.length) return ''
  if (names.length === 1) return `${names[0]} is typing…`
  if (names.length === 2) return `${names[0]} and ${names[1]} are typing…`
  return 'Several people are typing…'
})

const canModify = (m) => !m.deleted && (features.value.edit || features.value.delete)
function time(iso) { return iso ? new Date(iso).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '' }
function initial(name) { return (name || '?').charAt(0).toUpperCase() }
function truncate(s, n) { return s && s.length > n ? s.slice(0, n) + '…' : s }
function avatarStyle(name) {
  let h = 0; for (let i = 0; i < (name || '').length; i += 1) h = (h * 31 + name.charCodeAt(i)) % 360
  return {
    background: `linear-gradient(135deg, hsl(${h} 52% 52%), hsl(${(h + 28) % 360} 52% 44%))`,
    color: '#fff',
  }
}

function onInput() {
  if (props.canSend) props.room.sendTyping(props.currentUserName)
  autogrow()
}
function autogrow() {
  const el = input.value; if (!el) return
  el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 120) + 'px'
}
async function send() {
  const body = draft.value.trim()
  if (!body || !props.canSend) return
  draft.value = ''
  autogrow()
  await props.room.sendMessage(body)
  emit('sent')
  await nextTick(); scrollToBottom()
}
function startEdit(m) { editingId.value = m.id; editDraft.value = m.body }
function cancelEdit() { editingId.value = null; editDraft.value = '' }
async function commitEdit(m) {
  const body = editDraft.value.trim(); if (!body) return
  await props.room.editMessage(m.id, body); cancelEdit()
}
function askDelete(m) { deleting.value = m }
async function confirmDelete() {
  const m = deleting.value
  deleting.value = null
  if (m) await props.room.deleteMessage(m.id)
}

function onScroll() {
  const el = scroller.value; if (!el) return
  if (el.scrollTop < 40 && nextCursor.value && !loading.value) {
    const prev = el.scrollHeight
    emit('load-more', () => nextTick(() => { el.scrollTop = el.scrollHeight - prev }))
  }
  if (el.scrollHeight - el.scrollTop - el.clientHeight < 30) emit('seen')
}
function scrollToBottom() { const el = scroller.value; if (el) el.scrollTop = el.scrollHeight }

watch(() => messages.value.length, async () => {
  const el = scroller.value
  const nearBottom = el ? (el.scrollHeight - el.scrollTop - el.clientHeight < 120) : true
  await nextTick()
  if (nearBottom) scrollToBottom()
})

defineExpose({ scrollToBottom })
</script>

<style scoped>
.chat {
  --c-bg: var(--bg-canvas, #fff);
  --c-panel: var(--bg-elevated, rgba(15,23,42,0.04));
  --c-panel-2: var(--bg-elevated-2, rgba(15,23,42,0.07));
  --c-border: var(--border-soft, rgba(15,23,42,0.09));
  --c-border-faint: var(--border-faint, rgba(15,23,42,0.05));
  --c-text: var(--text-primary, #1f1f1d);
  --c-muted: var(--text-secondary, #6b6b66);
  --c-faint: var(--text-tertiary, #9a9a94);

  --c-send: #6366f1;
  --c-send-on: #fff;
  --c-divider: #6366f1;
  --c-danger: var(--danger, #e11d48);

  /* Bubble palette follows the LAYOUT, not the OS. Fallbacks are the
     light-theme values (used by the always-light admin). TeamLayout
     injects --chat-* vars for its dark and light user themes. */
  --c-mine: var(--chat-mine, #e7ebf3);
  --c-mine-text: var(--chat-mine-text, #1a2430);
  --c-them: var(--chat-them, #f4f6fa);
  --c-them-border: var(--chat-them-border, rgba(15,23,42,0.08));
  --c-them-text: var(--chat-them-text, #1a2430);

  display: flex; flex-direction: column; height: 100%; min-height: 0;
  background: var(--c-bg); color: var(--c-text);
  font-size: 14px; -webkit-font-smoothing: antialiased;
}

/* Header */
.chat__head {
  display: flex; align-items: center; justify-content: space-between;
  padding: 14px 52px 14px 16px; /* right padding clears the drawer close button */
  border-bottom: 1px solid var(--c-border-faint); flex-shrink: 0;
}
.chat__id { display: flex; align-items: center; gap: 10px; }
.chat__pulse {
  width: 9px; height: 9px; border-radius: 50%; background: var(--success, #22c55e);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--success, #22c55e) 20%, transparent);
}
.chat__idtext { display: flex; flex-direction: column; line-height: 1.2; }
.chat__name { font-size: 14px; font-weight: 600; letter-spacing: -0.01em; }
.chat__meta { font-size: 11.5px; color: var(--c-faint); margin-top: 1px; }
.chat__memberbtn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; border: none; border-radius: 10px; cursor: pointer;
  color: var(--c-muted); background: transparent; transition: background .15s, color .15s;
}
.chat__memberbtn:hover, .chat__memberbtn.is-active { background: var(--c-panel); color: var(--c-text); }

.chat__members {
  display: flex; flex-direction: column; gap: 2px;
  padding: 8px; margin: 8px; border-radius: 14px; background: var(--c-panel);
  max-height: 180px; overflow-y: auto; flex-shrink: 0;
}
.chat__memberchip { display: flex; align-items: center; gap: 9px; padding: 6px 8px; border-radius: 10px; }
.chat__memberchip:hover { background: var(--c-panel-2); }
.chat__membername { font-size: 13px; flex: 1; }

/* Avatars */
.avatar {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 50%;
  font-size: 11.5px; font-weight: 600; flex-shrink: 0; user-select: none;
  box-shadow: 0 1px 3px rgba(0,0,0,0.18);
}
.avatar--sm { width: 24px; height: 24px; font-size: 10.5px; }
.avatar--ghost { visibility: hidden; }

/* Scroll region */
.chat__scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 16px 14px 10px; scroll-behavior: smooth; }
.chat__scroll::-webkit-scrollbar { width: 6px; }
.chat__scroll::-webkit-scrollbar-thumb { background: var(--c-border); border-radius: 999px; }

.chat__more {
  display: block; margin: 0 auto 14px; padding: 6px 14px; font-size: 12.5px;
  color: var(--c-muted); background: var(--c-panel); border: 1px solid var(--c-border-faint);
  border-radius: 999px; cursor: pointer;
}

/* Empty state */
.chat__empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; text-align: center; padding: 0 24px; }
.chat__empty-icon {
  display: flex; align-items: center; justify-content: center; width: 56px; height: 56px;
  border-radius: 18px; background: var(--c-panel); color: var(--c-faint); margin-bottom: 14px;
}
.chat__empty-title { font-size: 15px; font-weight: 600; margin: 0; }
.chat__empty-sub { font-size: 13px; color: var(--c-faint); margin: 4px 0 0; }

/* Stream */
.stream { display: flex; flex-direction: column; }

.divider { display: flex; align-items: center; justify-content: center; margin: 18px 0 12px; }
.divider__label {
  font-size: 11px; font-weight: 500; color: var(--c-faint);
  background: var(--c-panel); padding: 3px 12px; border-radius: 999px;
}

.unread { display: flex; align-items: center; gap: 10px; margin: 14px 2px; }
.unread::before, .unread::after { content: ''; flex: 1; height: 1px; background: color-mix(in srgb, var(--c-divider) 30%, transparent); }
.unread__label { font-size: 11px; font-weight: 600; color: var(--c-divider); letter-spacing: 0.02em; }

/* Rows — rhythm: tight inside a group, roomy between groups/speakers */
.row { display: flex; max-width: 86%; }
.row--theirs { align-self: flex-start; }
.row--mine { align-self: flex-end; flex-direction: row-reverse; }
.row--cont { margin-top: 2px; }
.row--first { margin-top: 14px; }
.stream > .row:first-child, .divider + .row, .unread + .row { margin-top: 4px; }

.row__gutter { width: 36px; flex-shrink: 0; display: flex; align-items: flex-end; padding-bottom: 20px; }
.row__main { display: flex; flex-direction: column; min-width: 0; }
.row--mine .row__main { align-items: flex-end; }

.row__sender { display: flex; align-items: center; gap: 6px; margin: 0 0 4px 12px; }
.row__name { font-size: 12px; font-weight: 600; color: var(--c-muted); }

.bubble-wrap { position: relative; display: flex; flex-direction: column; }
.row--mine .bubble-wrap { align-items: flex-end; }

/* Bubbles — subtle depth via layered shadow + faint top-light gradient */
.bubble {
  font-size: 14px; line-height: 1.5; padding: 9px 13px; border-radius: 16px;
  word-break: break-word; white-space: pre-wrap; max-width: 100%; min-width: 44px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.10), 0 2px 8px rgba(0,0,0,0.06);
}
.bubble--theirs {
  background: linear-gradient(180deg, color-mix(in srgb, var(--c-them) 92%, white 8%), var(--c-them));
  color: var(--c-them-text);
  border: 1px solid var(--c-them-border);
}
.bubble--mine {
  background: linear-gradient(180deg, color-mix(in srgb, var(--c-mine) 90%, white 10%), var(--c-mine));
  color: var(--c-mine-text);
}
.bubble--tl { border-bottom-left-radius: 5px; }
.bubble--tr { border-bottom-right-radius: 5px; }
.bubble__edited { font-size: 10px; opacity: .65; margin-left: 6px; }
.bubble--deleted {
  background: transparent; border: 1px dashed var(--c-border);
  color: var(--c-faint); font-style: italic; box-shadow: none; min-width: 0;
}

.bubble--edit { background: var(--c-panel); padding: 8px; border-radius: 14px; box-shadow: none; }
.bubble__ta { width: 220px; max-width: 60vw; resize: vertical; font: inherit; border: 1px solid var(--c-border); border-radius: 10px; padding: 6px 8px; background: var(--c-bg); color: var(--c-text); }
.bubble__editrow { display: flex; gap: 12px; margin-top: 6px; }
.lk { background: none; border: none; cursor: pointer; color: var(--c-send); font-size: 12px; padding: 0; }
.lk--muted { color: var(--c-muted); }

.row__actions { display: none; gap: 4px; margin-top: 4px; }
.bubble-wrap:hover .row__actions { display: flex; }
.ib { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border: none; border-radius: 7px; cursor: pointer; color: var(--c-faint); background: var(--c-panel); }
.ib:hover { color: var(--c-send); background: var(--c-panel-2); }
.ib--danger:hover { color: var(--c-danger); }

.row__time { font-size: 10.5px; color: var(--c-faint); margin: 4px 4px 0; }

/* Typing */
.bubble--typing {
  display: inline-flex; gap: 4px; align-items: center; padding: 12px 14px; min-width: 0;
  background: var(--c-them); border: 1px solid var(--c-them-border);
  border-radius: 16px 16px 16px 5px;
}
.d { width: 6px; height: 6px; border-radius: 50%; background: var(--c-faint); animation: blink 1.4s infinite both; }
.d:nth-child(2) { animation-delay: .2s; } .d:nth-child(3) { animation-delay: .4s; }
@keyframes blink { 0%,60%,100% { opacity: .25; transform: translateY(0); } 30% { opacity: 1; transform: translateY(-2px); } }

/* Composer — floating card, not a bare bar */
.chat__composer { padding: 10px 12px 12px; flex-shrink: 0; }
.composer {
  display: flex; align-items: flex-end; gap: 6px;
  background: var(--c-panel);
  border: 1px solid var(--c-border-faint);
  border-radius: 16px; padding: 5px 5px 5px 8px;
  box-shadow: 0 1px 2px rgba(0,0,0,0.05), 0 4px 14px rgba(0,0,0,0.05);
  transition: border-color .15s, box-shadow .15s;
}
.composer:focus-within {
  border-color: color-mix(in srgb, var(--c-send) 50%, transparent);
  box-shadow: 0 1px 2px rgba(0,0,0,0.05), 0 0 0 3px color-mix(in srgb, var(--c-send) 12%, transparent);
}
.composer__ta {
  flex: 1; resize: none; font: inherit; line-height: 1.45; max-height: 120px;
  padding: 8px 6px; border: none; background: transparent; color: var(--c-text); outline: none;
}
.composer__ta::placeholder { color: var(--c-faint); }
.composer__send {
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
  width: 36px; height: 36px; border: none; border-radius: 12px; cursor: pointer;
  background: transparent; color: var(--c-faint);
  transition: background .15s, color .15s, transform .1s;
}
.composer__send.is-ready { background: var(--c-send); color: var(--c-send-on); box-shadow: 0 2px 8px color-mix(in srgb, var(--c-send) 40%, transparent); }
.composer__send.is-ready:active { transform: scale(0.92); }
.composer__send:disabled { cursor: not-allowed; }
.chat__readonly { margin: 0; padding: 0 16px 10px; font-size: 12px; color: var(--c-faint); }

/* Delete modal (teleported; classes unique to avoid any collision) */
.cdel-backdrop { background: rgba(0,0,0,0.46); backdrop-filter: blur(3px); padding: 24px; }
.cdel-card {
  width: 100%; max-width: 320px;
  background: #fff; color: #1a2430;
  border: 1px solid rgba(15,23,42,0.10);
  border-radius: 18px; padding: 20px;
  box-shadow: 0 24px 60px rgba(0,0,0,0.32);
  text-align: center; opacity: 1;
}
.cdel-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 42px; height: 42px; border-radius: 14px; margin-bottom: 10px;
  background: rgba(225,29,72,0.12); color: #e11d48;
}
.cdel-title { margin: 0; font-size: 15px; font-weight: 600; }
.cdel-body { margin: 8px 0 0; font-size: 13px; opacity: 0.75; overflow-wrap: break-word; }
.cdel-sub { margin: 6px 0 0; font-size: 11.5px; opacity: 0.55; }
.cdel-actions { display: flex; gap: 8px; margin-top: 16px; }
.cdel-btn {
  flex: 1; padding: 9px 0; border: none; border-radius: 11px;
  font-size: 13px; font-weight: 600; cursor: pointer; transition: filter .12s, transform .1s;
}
.cdel-btn:active { transform: scale(0.97); }
.cdel-btn--ghost { background: rgba(120,130,150,0.16); color: inherit; }
.cdel-btn--danger { background: #e11d48; color: #fff; }
.cdel-btn--danger:hover { filter: brightness(1.06); }

/* transitions */
.drop-enter-active, .drop-leave-active { transition: opacity .18s, transform .18s; }
.drop-enter-from, .drop-leave-to { opacity: 0; transform: translateY(-6px); }
.modal-enter-active, .modal-leave-active { transition: opacity .18s; }
.modal-enter-active .modal, .modal-leave-active .modal { transition: transform .18s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal, .modal-leave-to .modal { transform: scale(0.96) translateY(4px); }

@media (prefers-reduced-motion: reduce) {
  .chat__scroll { scroll-behavior: auto; }
  .d { animation: none; }
}
</style>