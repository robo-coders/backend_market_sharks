<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  teams: Array,
  filters: Object,
})

const notifyToast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 2200,
  timerProgressBar: true,
})

const search = ref(props.filters?.search ?? '')

let t = null
watch(search, (val) => {
  clearTimeout(t)
  t = setTimeout(() => {
    router.get(
      route('admin.teams.index'),
      { search: val || undefined },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }, 300)
})

const AVATAR_TONES = [
  { bg: '#eef2ff', fg: '#4f46e5' },
  { bg: '#f0f4f8', fg: '#475569' },
  { bg: '#f5f3ff', fg: '#7c3aed' },
]

const avatarTone = (item) => AVATAR_TONES[(item.id ?? 0) % AVATAR_TONES.length]

const initials = (name) => {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}

const STATUS_META = {
  pending: { label: 'Pending', tier: 'neutral', dot: '#94a3b8' },
  active:  { label: 'Active',  tier: 'neutral', dot: '#10b981' },
  blocked: { label: 'Blocked', tier: 'blocked', dot: '#64748b' },
}

const statusMeta = (status) => STATUS_META[status] ?? { label: status, tier: 'neutral', dot: '#94a3b8' }

const formatDate = (value) => {
  if (!value) return '—'
  return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

const formatDateTime = (value) => {
  if (!value) return ''
  return new Date(value).toLocaleString()
}

const destroyTeam = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.delete(route('admin.teams.destroy', id), {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'Team deleted' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not delete team' }),
    })
  })
}

const blockTeam = (id) => {
  Swal.fire({
    title: 'Block team?',
    text: 'They will not be able to access the team panel.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, block it!',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.patch(route('admin.teams.block', id), {}, {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'Team blocked' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not block team' }),
    })
  })
}

const unblockTeam = (id) => {
  Swal.fire({
    title: 'Unblock team?',
    text: 'They will regain access to the team panel.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, unblock',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.patch(route('admin.teams.unblock', id), {}, {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'Team unblocked' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not unblock team' }),
    })
  })
}
</script>

<template>
  <Head title="Teams" />

  <AdminLayout>
    <div class="ms-card">
      <div class="ms-toolbar">
        <div class="ms-toolbar-left">
          <h5 class="ms-title">Teams</h5>
          <span class="ms-title-count">{{ teams.length }}</span>
        </div>

        <div class="ms-search">
          <i class="bx bx-search ms-search-icon"></i>
          <input
            v-model="search"
            type="text"
            class="ms-search-input"
            placeholder="Search teams…"
            aria-label="Search teams"
          />
          <button
            v-if="search"
            type="button"
            class="ms-search-clear"
            aria-label="Clear search"
            @click="search = ''"
          >
            <i class="bx bx-x"></i>
          </button>
        </div>

        <Link :href="route('admin.teams.create')" class="ms-add-btn">
          <i class="bx bx-plus"></i>
          Add Team
        </Link>
      </div>

      <div class="d-none d-md-block">
        <table class="ms-table" aria-label="Teams">
          <thead>
            <tr>
              <th scope="col" class="ms-th-name">Name</th>
              <th scope="col">WhatsApp</th>
              <th scope="col">Status</th>
              <th scope="col">Created</th>
              <th scope="col" class="ms-th-actions"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="teams.length === 0">
              <td colspan="5">
                <div class="ms-empty">
                  <div class="ms-empty-icon"><i class="bx bx-group"></i></div>
                  <p class="ms-empty-title">No teams found</p>
                  <p class="ms-empty-text">
                    {{ search ? 'Try a different search term.' : 'Add your first team to get started.' }}
                  </p>
                </div>
              </td>
            </tr>

            <tr v-for="team in teams" :key="team.id" class="ms-row">
              <td>
                <div class="ms-user">
                  <span
                    class="ms-avatar"
                    :style="{ background: avatarTone(team).bg, color: avatarTone(team).fg }"
                    aria-hidden="true"
                  >
                    {{ initials(team.name) }}
                  </span>
                  <div class="ms-user-meta">
                    <span class="ms-user-name" :title="team.name">{{ team.name }}</span>
                    <span class="ms-user-email" :title="team.email">{{ team.email }}</span>
                  </div>
                </div>
              </td>

              <td>
                <span v-if="team.whatsapp_number" class="ms-cell-text">{{ team.whatsapp_number }}</span>
                <span v-else class="ms-muted">—</span>
              </td>

              <td>
                <span class="ms-badge" :class="`tier-${statusMeta(team.status).tier}`">
                  <span class="ms-badge-dot" :style="{ background: statusMeta(team.status).dot }"></span>
                  {{ statusMeta(team.status).label }}
                </span>
              </td>

              <td>
                <span class="ms-date" :title="formatDateTime(team.created_at)">
                  {{ formatDate(team.created_at) }}
                </span>
              </td>

              <td class="ms-td-actions">
                <div class="ms-actions">
                  <Link
                    v-if="team.status !== 'blocked'"
                    :href="route('admin.teams.edit', team.id)"
                    class="ms-icon-btn"
                    title="Edit"
                  >
                    <i class="bx bx-edit-alt"></i>
                  </Link>
                  <button
                    v-else
                    type="button"
                    class="ms-icon-btn is-disabled"
                    title="Blocked teams can't be edited"
                    disabled
                  >
                    <i class="bx bx-edit-alt"></i>
                  </button>

                  <button
                    v-if="team.status !== 'blocked'"
                    type="button"
                    class="ms-icon-btn"
                    title="Block"
                    @click.prevent="blockTeam(team.id)"
                  >
                    <i class="bx bx-block"></i>
                  </button>
                  <button
                    v-else
                    type="button"
                    class="ms-icon-btn is-accent"
                    title="Unblock"
                    @click.prevent="unblockTeam(team.id)"
                  >
                    <i class="bx bx-lock-open"></i>
                  </button>

                  <button
                    type="button"
                    class="ms-icon-btn is-danger"
                    title="Delete"
                    @click.prevent="destroyTeam(team.id)"
                  >
                    <i class="bx bx-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile cards -->
      <div class="d-md-none ms-mobile">
        <div v-if="teams.length === 0" class="ms-empty">
          <div class="ms-empty-icon"><i class="bx bx-group"></i></div>
          <p class="ms-empty-title">No teams found</p>
          <p class="ms-empty-text">
            {{ search ? 'Try a different search term.' : 'Add your first team to get started.' }}
          </p>
        </div>

        <div v-else v-for="team in teams" :key="`m-${team.id}`" class="ms-mobile-card">
          <div class="ms-mobile-top">
            <span
              class="ms-avatar"
              :style="{ background: avatarTone(team).bg, color: avatarTone(team).fg }"
              aria-hidden="true"
            >
              {{ initials(team.name) }}
            </span>
            <div class="ms-user-meta">
              <span class="ms-user-name" :title="team.name">{{ team.name }}</span>
              <span class="ms-user-email" :title="team.email">{{ team.email }}</span>
            </div>
          </div>

          <div class="ms-mobile-bottom">
            <span class="ms-badge" :class="`tier-${statusMeta(team.status).tier}`">
              <span class="ms-badge-dot" :style="{ background: statusMeta(team.status).dot }"></span>
              {{ statusMeta(team.status).label }}
            </span>

            <div class="ms-actions is-static">
              <Link
                v-if="team.status !== 'blocked'"
                :href="route('admin.teams.edit', team.id)"
                class="ms-icon-btn"
                title="Edit"
              >
                <i class="bx bx-edit-alt"></i>
              </Link>
              <button
                v-if="team.status !== 'blocked'"
                type="button"
                class="ms-icon-btn"
                title="Block"
                @click.prevent="blockTeam(team.id)"
              >
                <i class="bx bx-block"></i>
              </button>
              <button
                v-else
                type="button"
                class="ms-icon-btn is-accent"
                title="Unblock"
                @click.prevent="unblockTeam(team.id)"
              >
                <i class="bx bx-lock-open"></i>
              </button>
              <button
                type="button"
                class="ms-icon-btn is-danger"
                title="Delete"
                @click.prevent="destroyTeam(team.id)"
              >
                <i class="bx bx-trash"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.ms-card {
  background: #fff;
  border: 1px solid #e9ecf1;
  border-radius: 16px;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 8px 24px -12px rgba(15, 23, 42, 0.06);
  overflow: hidden;
}

.ms-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 18px 24px;
  border-bottom: 1px solid #edf0f4;
  box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
}

.ms-toolbar-left {
  display: flex;
  align-items: baseline;
  gap: 9px;
}

.ms-title {
  margin: 0;
  font-size: 17px;
  font-weight: 650;
  letter-spacing: -0.014em;
  color: #0b1220;
}

.ms-title-count {
  font-size: 13px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  color: #9aa4b2;
}

.ms-search {
  position: relative;
  width: 280px;
  margin-left: auto;
}

.ms-search-icon {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 16px;
  color: #9aa4b2;
  pointer-events: none;
}

.ms-search-input {
  width: 100%;
  height: 36px;
  padding: 0 32px 0 36px;
  font-size: 13.5px;
  color: #0b1220;
  background: #f7f9fb;
  border: 1px solid #e7eaef;
  border-radius: 10px;
  outline: none;
  transition: border-color 0.14s ease, background 0.14s ease, box-shadow 0.14s ease;
}

.ms-search-input::placeholder { color: #a6b0be; }
.ms-search-input:hover { border-color: #dbe0e8; }
.ms-search-input:focus {
  background: #fff;
  border-color: #a5b4fc;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.ms-search-clear {
  position: absolute;
  right: 7px;
  top: 50%;
  transform: translateY(-50%);
  display: flex;
  align-items: center;
  justify-content: center;
  width: 23px;
  height: 23px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  color: #9aa4b2;
  cursor: pointer;
  transition: background 0.12s ease, color 0.12s ease;
}

.ms-search-clear:hover { background: #edf0f4; color: #46536a; }

.ms-add-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  height: 36px;
  padding: 0 14px;
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  background: #696cff;
  border-radius: 10px;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: 0 4px 12px rgba(105, 108, 255, 0.28);
  transition: background 0.14s ease, box-shadow 0.14s ease, transform 0.14s ease;
}

.ms-add-btn:hover {
  background: #5f61e6;
  color: #fff;
  box-shadow: 0 6px 16px rgba(105, 108, 255, 0.34);
}

.ms-add-btn i { font-size: 16px; }

.ms-table { width: 100%; border-collapse: collapse; }

.ms-table thead th {
  padding: 10px 24px;
  font-size: 11px;
  font-weight: 620;
  letter-spacing: 0.09em;
  text-transform: uppercase;
  color: #8a94a6;
  text-align: left;
  background: #fafbfc;
  border-bottom: 1px solid #edf0f4;
  box-shadow: inset 0 1px 0 #f4f6f9;
}

.ms-th-name { width: 38%; }
.ms-th-actions { width: 130px; }

.ms-row { transition: background 0.1s ease; }
.ms-row + .ms-row td { border-top: 1px solid #f3f5f8; }
.ms-row:hover { background: #f8fafc; }
.ms-row td { padding: 11px 24px; vertical-align: middle; }

.ms-user { display: flex; align-items: center; gap: 13px; min-width: 0; }

.ms-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: 10px;
  font-size: 12.5px;
  font-weight: 650;
  letter-spacing: 0.02em;
  user-select: none;
  box-shadow: inset 0 0 0 1px rgba(15, 23, 42, 0.04);
}

.ms-user-meta { display: flex; flex-direction: column; gap: 1px; min-width: 0; }

.ms-user-name {
  font-size: 14.5px;
  font-weight: 600;
  color: #0b1220;
  letter-spacing: -0.01em;
  line-height: 1.35;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ms-user-email {
  font-size: 12.5px;
  color: #94a0b0;
  line-height: 1.35;
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ms-cell-text { font-size: 13.5px; color: #46536a; }

.ms-badge {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 4px 11px;
  border-radius: 999px;
  font-size: 12.5px;
  font-weight: 560;
  white-space: nowrap;
  line-height: 1.4;
}

.ms-badge-dot { width: 6.5px; height: 6.5px; border-radius: 999px; flex-shrink: 0; }

.ms-badge.tier-neutral { color: #5b6779; background: transparent; border: 1px solid #e7eaef; }
.ms-badge.tier-blocked { color: #334155; background: #f1f5f9; border: 1px solid #cbd5e1; }

.ms-date { font-size: 13.5px; font-variant-numeric: tabular-nums; color: #46536a; }
.ms-muted { color: #c6cdd8; }

.ms-td-actions { text-align: right; }

.ms-actions {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  opacity: 0;
  transition: opacity 0.12s ease;
}

.ms-actions.is-static { opacity: 1; }

.ms-row:hover .ms-actions,
.ms-row:focus-within .ms-actions { opacity: 1; }

.ms-icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #8a94a6;
  font-size: 17px;
  cursor: pointer;
  text-decoration: none;
  transition: background 0.12s ease, color 0.12s ease;
}

.ms-icon-btn:hover { background: #edf0f4; color: #334155; }
.ms-icon-btn.is-danger:hover { background: #fef2f2; color: #dc2626; }
.ms-icon-btn.is-accent { color: #6366f1; }
.ms-icon-btn.is-accent:hover { background: #eef2ff; color: #4f46e5; }
.ms-icon-btn.is-disabled { opacity: 0.4; cursor: not-allowed; }

.ms-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 56px 24px;
  text-align: center;
}

.ms-empty-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 46px;
  height: 46px;
  border-radius: 13px;
  background: #f4f6f9;
  color: #a6b0be;
  font-size: 22px;
}

.ms-empty-title { margin: 15px 0 0; font-size: 14px; font-weight: 600; color: #334155; }
.ms-empty-text { margin: 4px 0 0; font-size: 13px; color: #94a0b0; }

.ms-mobile { padding: 14px; }

.ms-mobile-card {
  padding: 14px 15px;
  border: 1px solid #e9ecf1;
  border-radius: 14px;
  background: #fff;
}

.ms-mobile-card + .ms-mobile-card { margin-top: 10px; }
.ms-mobile-top { display: flex; align-items: center; gap: 12px; }

.ms-mobile-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #f3f5f8;
}

@media (max-width: 767.98px) {
  .ms-toolbar { padding: 14px 16px; }
  .ms-search { width: 100%; order: 3; margin-left: 0; }
}
</style>