<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import RoleBadge from './RoleBadge.vue'

const V2_LOCKED = true

const features = ref({ edit: false, delete: false, read_receipts: false })
const roles = ref([])
const users = ref([])
const loading = ref(true)
const savingFeature = ref(null)

const featureRows = [
  { key: 'edit', label: 'Edit messages', desc: 'Let people edit their own messages within a short window.' },
  { key: 'delete', label: 'Delete messages', desc: 'Let people delete their own messages; admins can moderate any.' },
  { key: 'read_receipts', label: 'Read receipts', desc: 'Show when messages have been seen by others.' },
]

// Same toast pattern as Admin/Dashboard.vue
const toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 2500,
  timerProgressBar: true,
})

onMounted(load)

async function load() {
  loading.value = true
  try {
    const { data } = await axios.get('/chat/admin')
    features.value = data.features
    roles.value = data.roles
    users.value = data.users
  } finally {
    loading.value = false
  }
}

async function toggleFeature(key) {
  if (V2_LOCKED) return
  savingFeature.value = key
  try {
    const { data } = await axios.post('/chat/admin/features', { feature: key, enabled: !features.value[key] })
    features.value = data.features
    toast.fire({ icon: 'success', title: `${key.replace('_', ' ')} ${data.features[key] ? 'enabled' : 'disabled'}` })
  } catch (e) {
    toast.fire({ icon: 'error', title: 'Could not update feature.' })
  } finally {
    savingFeature.value = null
  }
}

async function setUser(u, permission, granted) {
  if (V2_LOCKED) return
  try {
    const { data } = await axios.post(`/chat/admin/users/${u.id}/access`, { permission, granted })
    u.can_send = data.can_send
    u.can_view = data.can_view
    const what = permission === 'chat.send' ? 'posting' : 'viewing'
    toast.fire({ icon: 'success', title: `${u.name}: ${what} ${granted ? 'allowed' : 'revoked'}` })
  } catch (e) {
    toast.fire({ icon: 'error', title: 'Could not update access.' })
  }
}

async function setRole(role, granted) {
  if (V2_LOCKED) return
  try {
    await axios.post('/chat/admin/roles/access', { role, permission: 'chat.send', granted })
    await load()
    toast.fire({ icon: 'success', title: `${role}: posting ${granted ? 'allowed' : 'revoked'} for the whole role` })
  } catch (e) {
    toast.fire({ icon: 'error', title: 'Could not update role access.' })
  }
}
</script>

<template>
  <div class="space-y-8">
    <!-- Feature flags -->
    <section :class="V2_LOCKED ? 'opacity-60' : ''">
      <div class="mb-4 flex items-center gap-2">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Chat features</h3>
          <p class="text-xs text-slate-400 mt-0.5">Advanced features unlock with the v2 upgrade.</p>
        </div>
        <span v-if="V2_LOCKED" class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 shrink-0">Coming in v2</span>
      </div>

      <div class="divide-y divide-slate-100 rounded-xl border border-slate-100 overflow-hidden">
        <div v-for="f in featureRows" :key="f.key" class="flex items-center justify-between gap-4 px-4 py-4 bg-white">
          <div class="min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium text-slate-800">{{ f.label }}</span>
              <span v-if="V2_LOCKED || !features[f.key]" class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600">
                Coming in v2
              </span>
              <span v-else class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-600">
                Enabled
              </span>
            </div>
            <p class="text-xs text-slate-400 mt-0.5">{{ f.desc }}</p>
          </div>

          <button
            type="button"
            role="switch"
            :aria-checked="features[f.key]"
            :aria-label="f.label"
            :disabled="V2_LOCKED || savingFeature === f.key || loading"
            class="relative shrink-0 w-11 h-6 rounded-full transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            :class="features[f.key] && !V2_LOCKED ? 'bg-indigo-600' : 'bg-slate-200'"
            @click="toggleFeature(f.key)"
          >
            <span
              class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform"
              :class="features[f.key] && !V2_LOCKED ? 'translate-x-5' : ''"
            />
          </button>
        </div>
      </div>
    </section>

    <!-- Access by role -->
    <section :class="V2_LOCKED ? 'opacity-60 pointer-events-none select-none' : ''" :aria-disabled="V2_LOCKED">
      <div class="mb-4 flex items-center gap-2">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Access by role</h3>
          <p class="text-xs text-slate-400 mt-0.5">Revoke or restore posting for an entire role at once. Takes effect immediately.</p>
        </div>
        <span v-if="V2_LOCKED" class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 shrink-0">Coming in v2</span>
      </div>

      <div class="divide-y divide-slate-100 rounded-xl border border-slate-100 overflow-hidden">
        <div v-for="role in roles" :key="role" class="flex items-center justify-between gap-4 px-4 py-3.5 bg-white">
          <RoleBadge :role="role" />
          <div class="flex items-center gap-2">
            <button
              type="button"
              :disabled="V2_LOCKED"
              class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition disabled:opacity-50 disabled:cursor-not-allowed"
              @click="setRole(role, true)"
            >Allow posting</button>
            <button
              type="button"
              :disabled="V2_LOCKED"
              class="text-xs font-semibold px-3 py-1.5 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition disabled:opacity-50 disabled:cursor-not-allowed"
              @click="setRole(role, false)"
            >Revoke posting</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Access by person -->
    <section :class="V2_LOCKED ? 'opacity-60 pointer-events-none select-none' : ''" :aria-disabled="V2_LOCKED">
      <div class="mb-4 flex items-center gap-2">
        <div>
          <h3 class="text-sm font-semibold text-slate-900">Access by person</h3>
          <p class="text-xs text-slate-400 mt-0.5">Revoking <span class="font-medium">Post</span> leaves them read only. Revoking <span class="font-medium">View</span> removes them from the room entirely.</p>
        </div>
        <span v-if="V2_LOCKED" class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-600 shrink-0">Coming in v2</span>
      </div>

      <div class="overflow-hidden rounded-xl border border-slate-100">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-slate-50 text-left">
              <th class="px-4 py-2.5 text-xs font-semibold text-slate-500">Name</th>
              <th class="px-4 py-2.5 text-xs font-semibold text-slate-500">Role</th>
              <th class="px-4 py-2.5 text-xs font-semibold text-slate-500">Post</th>
              <th class="px-4 py-2.5 text-xs font-semibold text-slate-500">View</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 bg-white">
            <tr v-for="u in users" :key="u.id">
              <td class="px-4 py-2.5 text-slate-700">{{ u.name }}</td>
              <td class="px-4 py-2.5"><RoleBadge :role="u.role" /></td>
              <td class="px-4 py-2.5">
                <button
                  type="button"
                  :disabled="V2_LOCKED"
                  class="text-xs font-semibold px-2.5 py-1 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="u.can_send ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-rose-50 text-rose-600 hover:bg-rose-100'"
                  @click="setUser(u, 'chat.send', !u.can_send)"
                >{{ u.can_send ? 'Allowed' : 'Revoked' }}</button>
              </td>
              <td class="px-4 py-2.5">
                <button
                  type="button"
                  :disabled="V2_LOCKED"
                  class="text-xs font-semibold px-2.5 py-1 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
                  :class="u.can_view ? 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'bg-rose-50 text-rose-600 hover:bg-rose-100'"
                  @click="setUser(u, 'chat.view', !u.can_view)"
                >{{ u.can_view ? 'Allowed' : 'Revoked' }}</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>