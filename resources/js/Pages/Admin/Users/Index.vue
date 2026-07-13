<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePage, Link } from '@inertiajs/vue3'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  users: Array,
  counts: Object,
})

const page = usePage()

const isSuperAdmin = computed(() =>
  page.props.auth?.role_names?.includes('super_admin')
)

const notifyToast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 2200,
  timerProgressBar: true,
})

const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString() : '—'

const isExpired = (expiresAt) => {
  if (!expiresAt) return false
  return new Date(expiresAt) <= new Date()
}

const isExpiringSoon = (expiresAt) => {
  if (!expiresAt || isExpired(expiresAt)) return false
  const days = (new Date(expiresAt) - new Date()) / (1000 * 60 * 60 * 24)
  return days <= 7
}

const displayStatus = (user) => {
  if (user.status === 'pending') return 'pending'
  if (user.status === 'payment_review') return 'payment_review'
  if (user.status === 'blocked') return 'blocked'
  if (user.status === 'rejected') return 'rejected'

  if (user.status === 'active') {
    if (isExpired(user.subscription?.expires_at)) return 'expired'
    if (isExpiringSoon(user.subscription?.expires_at)) return 'expiring'
    return 'active'
  }

  if (user.status === 'expired') return 'expired'

  return 'pending'
}

const STATUS_META = {
  pending:        { label: 'Pending',        tier: 'neutral',   dot: '#94a3b8' },
  active:         { label: 'Active',         tier: 'neutral',   dot: '#10b981' },
  payment_review: { label: 'Payment Review', tier: 'attention', dot: '#0284c7' },
  expiring:       { label: 'Expiring Soon',  tier: 'attention', dot: '#d97706' },
  rejected:       { label: 'Rejected',       tier: 'critical',  dot: '#dc2626' },
  expired:        { label: 'Expired',        tier: 'critical',  dot: '#dc2626' },
  blocked:        { label: 'Blocked',        tier: 'blocked',   dot: '#64748b' },
}

const statusMeta = (user) => STATUS_META[displayStatus(user)] ?? STATUS_META.pending

const AVATAR_TONES = [
  { bg: '#eef2ff', fg: '#4f46e5' }, // indigo
  { bg: '#f0f4f8', fg: '#475569' }, // slate
  { bg: '#f5f3ff', fg: '#7c3aed' }, // violet
]

const avatarTone = (user) => AVATAR_TONES[(user.id ?? 0) % AVATAR_TONES.length]

const initials = (name) => {
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
}

const planLabel = (user) => {
  if (user.status === 'active' && user.subscription?.plan) return user.subscription.plan
  if (user.status === 'payment_review' && user.payment_request?.plan) return user.payment_request.plan
  return null
}

const activeFilter = ref('all')
const setFilter = (key) => {
  activeFilter.value = key
  statusMenuOpen.value = false
}

const needsReviewCount = computed(
  () => (props.counts.pending ?? 0) + (props.counts.payment_review ?? 0)
)

const statusMenuOpen = ref(false)
const statusMenuRef = ref(null)

const STATUS_OPTIONS = [
  { key: 'all',            label: 'All statuses' },
  { key: 'needs_review',   label: 'Needs review' },
  { key: 'pending',        label: 'Pending' },
  { key: 'payment_review', label: 'Payment review' },
  { key: 'active',         label: 'Active' },
  { key: 'expiring',       label: 'Expiring soon' },
  { key: 'expired',        label: 'Expired' },
  { key: 'blocked',        label: 'Blocked' },
  { key: 'rejected',       label: 'Rejected' },
]

const optionCount = (key) => {
  if (key === 'all') return props.counts.all
  if (key === 'needs_review') return needsReviewCount.value
  return props.counts[key] ?? 0
}

const activeFilterLabel = computed(
  () => STATUS_OPTIONS.find((o) => o.key === activeFilter.value)?.label ?? 'All statuses'
)

const handleOutsideClick = (event) => {
  if (statusMenuRef.value && !statusMenuRef.value.contains(event.target)) {
    statusMenuOpen.value = false
  }
}

const handleEscape = (event) => {
  if (event.key === 'Escape') statusMenuOpen.value = false
}

onMounted(() => {
  document.addEventListener('click', handleOutsideClick)
  document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleOutsideClick)
  document.removeEventListener('keydown', handleEscape)
})

const searchQuery = ref('')

const filteredUsers = computed(() => {
  let list = props.users

  if (activeFilter.value === 'needs_review') {
    list = list.filter((u) => ['pending', 'payment_review'].includes(displayStatus(u)))
  } else if (activeFilter.value !== 'all') {
    list = list.filter((u) => displayStatus(u) === activeFilter.value)
  }

  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    list = list.filter((u) =>
      (u.display_name || '').toLowerCase().includes(q) ||
      (u.email || '').toLowerCase().includes(q)
    )
  }

  return list
})

const openUser = (user) => {
  router.visit(route('admin.users.show', user.id))
}

const approveUser = (user) => {
  router.patch(route('admin.users.approve', user.id), {}, {
    preserveScroll: true,
    onSuccess: () => notifyToast.fire({ icon: 'success', title: 'User approved' }),
    onError: () => notifyToast.fire({ icon: 'error', title: 'Could not approve user' }),
  })
}

const rejectUser = (user) => {
  Swal.fire({
    title: 'Reject this user?',
    text: 'Their payment request will be marked as rejected.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, reject',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.patch(route('admin.users.reject', user.id), {}, {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'User rejected' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not reject user' }),
    })
  })
}

const blockUser = (user) => {
  Swal.fire({
    title: 'Block this user?',
    text: 'They will lose access until unblocked.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, block',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.patch(route('admin.users.block', user.id), {}, {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'User blocked' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not block user' }),
    })
  })
}

const unblockUser = (user) => {
  router.patch(route('admin.users.unblock', user.id), {}, {
    preserveScroll: true,
    onSuccess: () => notifyToast.fire({ icon: 'success', title: 'User unblocked' }),
    onError: () => notifyToast.fire({ icon: 'error', title: 'Could not unblock user' }),
  })
}

const destroyUser = (id) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.delete(route('admin.users.destroy', id), {
      onSuccess: () => {
        Swal.fire({
          title: 'Deleted',
          text: 'User deleted successfully.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        })
      },
      onError: () => {
        Swal.fire({ title: 'Error', text: 'Could not delete user.', icon: 'error' })
      },
    })
  })
}
</script>

<template>
  <Head title="Users" />
  <AdminLayout>
    <div class="ms-card">
      <!-- Toolbar -->
      <div class="ms-toolbar">
        <div class="ms-toolbar-left">
          <h5 class="ms-title">Users</h5>
          <span class="ms-title-count">{{ props.counts.all }}</span>
        </div>

        <div class="ms-search">
          <i class="bx bx-search ms-search-icon"></i>
          <input
            v-model="searchQuery"
            type="text"
            class="ms-search-input"
            placeholder="Search name or email…"
            aria-label="Search users"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="ms-search-clear"
            aria-label="Clear search"
            @click="searchQuery = ''"
          >
            <i class="bx bx-x"></i>
          </button>
        </div>

        <div class="ms-toolbar-right">
          <div class="ms-segment" role="group" aria-label="Quick filters">
            <button
              type="button"
              class="ms-segment-btn"
              :class="{ 'is-active': activeFilter === 'all' }"
              @click="setFilter('all')"
            >
              All <span class="ms-segment-count">{{ props.counts.all }}</span>
            </button>
            <button
              type="button"
              class="ms-segment-btn"
              :class="{ 'is-active': activeFilter === 'needs_review' }"
              @click="setFilter('needs_review')"
            >
              Needs review <span class="ms-segment-count">{{ needsReviewCount }}</span>
            </button>
            <button
              type="button"
              class="ms-segment-btn"
              :class="{ 'is-active': activeFilter === 'active' }"
              @click="setFilter('active')"
            >
              Active <span class="ms-segment-count">{{ props.counts.active }}</span>
            </button>
          </div>

          <!-- Crafted status dropdown -->
          <div ref="statusMenuRef" class="ms-dd">
            <button
              type="button"
              class="ms-dd-trigger"
              :class="{ 'is-open': statusMenuOpen }"
              aria-haspopup="listbox"
              :aria-expanded="statusMenuOpen"
              @click="statusMenuOpen = !statusMenuOpen"
            >
              <span class="ms-dd-label">{{ activeFilterLabel }}</span>
              <i class="bx bx-chevron-down ms-dd-chevron" :class="{ 'is-open': statusMenuOpen }"></i>
            </button>

            <Transition name="ms-dd-pop">
              <div v-if="statusMenuOpen" class="ms-dd-menu" role="listbox" aria-label="Filter by status">
                <button
                  v-for="option in STATUS_OPTIONS"
                  :key="option.key"
                  type="button"
                  class="ms-dd-item"
                  :class="{ 'is-selected': activeFilter === option.key }"
                  role="option"
                  :aria-selected="activeFilter === option.key"
                  @click="setFilter(option.key)"
                >
                  <span class="ms-dd-check">
                    <i v-if="activeFilter === option.key" class="bx bx-check"></i>
                  </span>
                  <span class="ms-dd-item-label">{{ option.label }}</span>
                  <span class="ms-dd-item-count">{{ optionCount(option.key) }}</span>
                </button>
              </div>
            </Transition>
          </div>
        </div>
      </div>

      <!-- Desktop table -->
      <div class="d-none d-md-block">
        <table class="ms-table" aria-label="Users">
          <thead>
            <tr>
              <th scope="col" class="ms-th-name">Name</th>
              <th scope="col" class="ms-th-plan">Plan</th>
              <th scope="col" class="ms-th-status">Status</th>
              <th scope="col" class="ms-th-expires">Expires</th>
              <th scope="col" class="ms-th-actions"><span class="visually-hidden">Actions</span></th>
            </tr>
          </thead>

          <tbody>
            <tr v-if="filteredUsers.length === 0">
              <td colspan="5">
                <div class="ms-empty">
                  <div class="ms-empty-icon">
                    <i class="bx bx-user-x"></i>
                  </div>
                  <p class="ms-empty-title">No users found</p>
                  <p class="ms-empty-text">
                    {{ searchQuery ? 'Try a different name or email.' : 'No users match this filter yet.' }}
                  </p>
                </div>
              </td>
            </tr>

            <tr
              v-else
              v-for="user in filteredUsers"
              :key="user.id"
              class="ms-row"
              tabindex="0"
              role="button"
              :aria-label="`View ${user.display_name}`"
              @click="openUser(user)"
              @keydown.enter="openUser(user)"
            >
              <td>
                <div class="ms-user">
                  <span
                    class="ms-avatar"
                    :style="{ background: avatarTone(user).bg, color: avatarTone(user).fg }"
                    aria-hidden="true"
                  >
                    {{ initials(user.display_name) }}
                  </span>

                  <div class="ms-user-meta">
                    <span class="ms-user-name" :title="user.display_name">
                      {{ user.display_name }}
                      <i v-if="user.is_anonymous" class="bx bx-mask ms-anon" title="Anonymous user — real name hidden"></i>
                    </span>
                    <span
                      v-if="isSuperAdmin"
                      class="ms-user-email"
                      :title="user.email"
                    >
                      {{ user.email }}
                    </span>
                  </div>
                </div>
              </td>

              <td>
                <span v-if="planLabel(user)" class="ms-plan-chip">
                  <i class="bx bxs-zap ms-plan-icon"></i>
                  {{ planLabel(user) }}
                </span>
                <span v-else class="ms-muted">—</span>
              </td>

              <td>
                <span class="ms-badge" :class="`tier-${statusMeta(user).tier}`">
                  <span class="ms-badge-dot" :style="{ background: statusMeta(user).dot }"></span>
                  {{ statusMeta(user).label }}
                </span>
              </td>

              <td>
                <span v-if="user.subscription?.expires_at" class="ms-date">
                  {{ formatDate(user.subscription.expires_at) }}
                </span>
                <span v-else class="ms-muted">—</span>
              </td>

              <td class="ms-td-actions" @click.stop>
                <div class="dropdown ms-actions">
                  <button
                    type="button"
                    class="ms-kebab dropdown-toggle hide-arrow"
                    data-bs-toggle="dropdown"
                    :aria-label="`Actions for ${user.display_name}`"
                  >
                    <i class="bx bx-dots-horizontal-rounded"></i>
                  </button>

                  <div class="dropdown-menu dropdown-menu-end ms-menu">
                    <Link class="dropdown-item" :href="route('admin.users.show', user.id)">
                      <i class="bx bx-show me-1"></i> View details
                    </Link>

                    <template v-if="user.status === 'payment_review'">
                      <div class="dropdown-divider"></div>
                      <button class="dropdown-item" @click.prevent="approveUser(user)">
                        <i class="bx bx-check me-1"></i> Approve
                      </button>
                      <button class="dropdown-item" @click.prevent="rejectUser(user)">
                        <i class="bx bx-x-circle me-1"></i> Reject
                      </button>
                    </template>

                    <div class="dropdown-divider"></div>

                    <button
                      v-if="user.status !== 'blocked'"
                      class="dropdown-item"
                      @click.prevent="blockUser(user)"
                    >
                      <i class="bx bx-block me-1"></i> Block
                    </button>

                    <button
                      v-if="user.status === 'blocked'"
                      class="dropdown-item"
                      @click.prevent="unblockUser(user)"
                    >
                      <i class="bx bx-lock-open me-1"></i> Unblock
                    </button>

                    <button class="dropdown-item text-danger" @click.prevent="destroyUser(user.id)">
                      <i class="bx bx-trash me-1"></i> Delete
                    </button>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Mobile card list -->
      <div class="d-md-none ms-mobile">
        <div v-if="filteredUsers.length === 0" class="ms-empty">
          <div class="ms-empty-icon"><i class="bx bx-user-x"></i></div>
          <p class="ms-empty-title">No users found</p>
          <p class="ms-empty-text">
            {{ searchQuery ? 'Try a different name or email.' : 'No users match this filter yet.' }}
          </p>
        </div>

        <div
          v-else
          v-for="user in filteredUsers"
          :key="`m-${user.id}`"
          class="ms-mobile-card"
          role="button"
          tabindex="0"
          :aria-label="`View ${user.display_name}`"
          @click="openUser(user)"
          @keydown.enter="openUser(user)"
        >
          <div class="ms-mobile-top">
            <span
              class="ms-avatar"
              :style="{ background: avatarTone(user).bg, color: avatarTone(user).fg }"
              aria-hidden="true"
            >
              {{ initials(user.display_name) }}
            </span>

            <div class="ms-user-meta">
              <span class="ms-user-name" :title="user.display_name">
                {{ user.display_name }}
                <i v-if="user.is_anonymous" class="bx bx-mask ms-anon" title="Anonymous user — real name hidden"></i>
              </span>
              <span
                v-if="isSuperAdmin"
                class="ms-user-email"
                :title="user.email"
              >
                {{ user.email }}
              </span>
            </div>

            <div class="dropdown ms-mobile-kebab" @click.stop>
              <button
                type="button"
                class="ms-kebab dropdown-toggle hide-arrow"
                data-bs-toggle="dropdown"
                :aria-label="`Actions for ${user.display_name}`"
              >
                <i class="bx bx-dots-horizontal-rounded"></i>
              </button>

              <div class="dropdown-menu dropdown-menu-end ms-menu">
                <Link class="dropdown-item" :href="route('admin.users.show', user.id)">
                  <i class="bx bx-show me-1"></i> View details
                </Link>

                <template v-if="user.status === 'payment_review'">
                  <div class="dropdown-divider"></div>
                  <button class="dropdown-item" @click.prevent="approveUser(user)">
                    <i class="bx bx-check me-1"></i> Approve
                  </button>
                  <button class="dropdown-item" @click.prevent="rejectUser(user)">
                    <i class="bx bx-x-circle me-1"></i> Reject
                  </button>
                </template>

                <div class="dropdown-divider"></div>

                <button
                  v-if="user.status !== 'blocked'"
                  class="dropdown-item"
                  @click.prevent="blockUser(user)"
                >
                  <i class="bx bx-block me-1"></i> Block
                </button>

                <button
                  v-if="user.status === 'blocked'"
                  class="dropdown-item"
                  @click.prevent="unblockUser(user)"
                >
                  <i class="bx bx-lock-open me-1"></i> Unblock
                </button>

                <button class="dropdown-item text-danger" @click.prevent="destroyUser(user.id)">
                  <i class="bx bx-trash me-1"></i> Delete
                </button>
              </div>
            </div>
          </div>

          <div class="ms-mobile-bottom">
            <div class="d-flex align-items-center gap-2">
              <span class="ms-badge" :class="`tier-${statusMeta(user).tier}`">
                <span class="ms-badge-dot" :style="{ background: statusMeta(user).dot }"></span>
                {{ statusMeta(user).label }}
              </span>
              <span v-if="planLabel(user)" class="ms-plan-chip">
                <i class="bx bxs-zap ms-plan-icon"></i>
                {{ planLabel(user) }}
              </span>
            </div>

            <span class="ms-date-sm">
              <template v-if="user.subscription?.expires_at">
                {{ formatDate(user.subscription.expires_at) }}
              </template>
              <template v-else>—</template>
            </span>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
/* ─── Card shell ────────────────────────────────────────────────── */
.ms-card {
  background: #fff;
  border: 1px solid #e9ecf1;
  border-radius: 16px;
  box-shadow:
    0 1px 2px rgba(15, 23, 42, 0.04),
    0 8px 24px -12px rgba(15, 23, 42, 0.06);
  overflow: visible;
}

/* ─── Toolbar ───────────────────────────────────────────────────── */
.ms-toolbar {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 12px;
  padding: 18px 24px;
  background: #fff;
  border-bottom: 1px solid #edf0f4;
  box-shadow: 0 1px 0 rgba(15, 23, 42, 0.02);
  position: relative;
  z-index: 5;
  border-radius: 16px 16px 0 0;
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

.ms-toolbar-right {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-left: auto;
  flex-wrap: wrap;
}

/* ─── Search ────────────────────────────────────────────────────── */
.ms-search {
  position: relative;
  width: 280px;
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

.ms-search-input::placeholder {
  color: #a6b0be;
}

.ms-search-input:hover {
  border-color: #dbe0e8;
}

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

.ms-search-clear:hover {
  background: #edf0f4;
  color: #46536a;
}

/* ─── Segmented control ─────────────────────────────────────────── */
.ms-segment {
  display: inline-flex;
  padding: 3px;
  gap: 2px;
  background: #f1f4f8;
  border: 1px solid #eceff4;
  border-radius: 10px;
}

.ms-segment-btn {
  border: 0;
  background: transparent;
  padding: 6px 13px;
  font-size: 13px;
  font-weight: 550;
  color: #64748b;
  border-radius: 7px;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.14s ease, background 0.14s ease, box-shadow 0.14s ease;
}

.ms-segment-btn:hover {
  color: #334155;
}

.ms-segment-btn.is-active {
  background: #fff;
  color: #0b1220;
  box-shadow:
    0 1px 2.5px rgba(15, 23, 42, 0.1),
    0 0 0 1px rgba(15, 23, 42, 0.035);
}

.ms-segment-btn:focus-visible {
  outline: 2px solid #6366f1;
  outline-offset: 1px;
}

.ms-segment-count {
  margin-left: 5px;
  font-variant-numeric: tabular-nums;
  color: #9aa4b2;
  font-weight: 500;
  font-size: 12px;
}

.ms-segment-btn.is-active .ms-segment-count {
  color: #6366f1;
}

/* ─── Crafted status dropdown ───────────────────────────────────── */
.ms-dd {
  position: relative;
}

.ms-dd-trigger {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  height: 36px;
  padding: 0 11px 0 13px;
  font-size: 13px;
  font-weight: 550;
  color: #334155;
  background: #fff;
  border: 1px solid #e7eaef;
  border-radius: 10px;
  cursor: pointer;
  outline: none;
  transition: border-color 0.14s ease, box-shadow 0.14s ease, background 0.14s ease;
}

.ms-dd-trigger:hover {
  border-color: #dbe0e8;
}

.ms-dd-trigger.is-open,
.ms-dd-trigger:focus-visible {
  border-color: #a5b4fc;
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.ms-dd-chevron {
  font-size: 17px;
  color: #9aa4b2;
  transition: transform 0.16s ease;
}

.ms-dd-chevron.is-open {
  transform: rotate(180deg);
}

.ms-dd-menu {
  position: absolute;
  right: 0;
  top: calc(100% + 6px);
  z-index: 50;
  min-width: 214px;
  padding: 5px;
  background: #fff;
  border: 1px solid #e9ecf1;
  border-radius: 12px;
  box-shadow:
    0 12px 32px rgba(15, 23, 42, 0.1),
    0 2px 8px rgba(15, 23, 42, 0.05);
}

.ms-dd-item {
  display: flex;
  align-items: center;
  gap: 7px;
  width: 100%;
  padding: 7px 9px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  font-size: 13px;
  font-weight: 500;
  color: #334155;
  cursor: pointer;
  text-align: left;
  transition: background 0.1s ease;
}

.ms-dd-item:hover {
  background: #f4f6f9;
}

.ms-dd-item.is-selected {
  color: #0b1220;
  font-weight: 600;
}

.ms-dd-item:focus-visible {
  outline: 2px solid #6366f1;
  outline-offset: -2px;
}

.ms-dd-check {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  flex-shrink: 0;
  font-size: 15px;
  color: #6366f1;
}

.ms-dd-item-label {
  flex: 1;
}

.ms-dd-item-count {
  font-size: 11.5px;
  font-weight: 550;
  font-variant-numeric: tabular-nums;
  color: #9aa4b2;
  background: #f4f6f9;
  border-radius: 999px;
  padding: 1px 7px;
}

.ms-dd-item.is-selected .ms-dd-item-count {
  color: #6366f1;
  background: #eef2ff;
}

.ms-dd-pop-enter-active {
  transition: opacity 0.14s ease, transform 0.14s ease;
}

.ms-dd-pop-leave-active {
  transition: opacity 0.1s ease, transform 0.1s ease;
}

.ms-dd-pop-enter-from,
.ms-dd-pop-leave-to {
  opacity: 0;
  transform: translateY(-4px) scale(0.98);
}

/* ─── Table ─────────────────────────────────────────────────────── */
.ms-table {
  width: 100%;
  border-collapse: collapse;
}

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

.ms-th-name { width: 34%; }
.ms-th-plan { width: 16%; }
.ms-th-status { width: 22%; }
.ms-th-expires { width: 18%; }
.ms-th-actions { width: 56px; }

.ms-row {
  cursor: pointer;
  transition: background 0.1s ease;
}

.ms-row + .ms-row td {
  border-top: 1px solid #f3f5f8;
}

.ms-row:hover {
  background: #f8fafc;
}

.ms-row:focus-visible {
  outline: 2px solid #6366f1;
  outline-offset: -2px;
  border-radius: 6px;
}

.ms-row td {
  padding: 11px 24px;
  vertical-align: middle;
}

/* ─── User cell ─────────────────────────────────────────────────── */
.ms-user {
  display: flex;
  align-items: center;
  gap: 13px;
  min-width: 0;
}

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

.ms-user-meta {
  display: flex;
  flex-direction: column;
  gap: 1px;
  min-width: 0;
}

.ms-user-name {
  display: inline-flex;
  align-items: center;
  font-size: 14.5px;
  font-weight: 600;
  color: #0b1220;
  letter-spacing: -0.01em;
  line-height: 1.35;
  max-width: 340px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ms-anon {
  margin-left: 6px;
  font-size: 14px;
  color: #a6b0be;
  flex-shrink: 0;
}

.ms-user-email {
  font-size: 12.5px;
  color: #94a0b0;
  line-height: 1.35;
  max-width: 340px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ─── Plan chip ─────────────────────────────────────────────────── */
.ms-plan-chip {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 13px;
  font-weight: 570;
  color: #6d28d9;
  text-transform: capitalize;
}

.ms-plan-icon {
  font-size: 13px;
  color: #8b5cf6;
}

/* ─── Status badges — four visual tiers ─────────────────────────── */
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

.ms-badge-dot {
  width: 6.5px;
  height: 6.5px;
  border-radius: 999px;
  flex-shrink: 0;
}

.ms-badge.tier-neutral {
  color: #5b6779;
  background: transparent;
  border: 1px solid #e7eaef;
}

.ms-badge.tier-attention {
  color: #075985;
  background: #f0f9ff;
  border: 1px solid #bae6fd;
}

.ms-badge.tier-critical {
  color: #991b1b;
  background: #fef2f2;
  border: 1px solid #fecaca;
}

.ms-badge.tier-blocked {
  color: #334155;
  background: #f1f5f9;
  border: 1px solid #cbd5e1;
}

/* ─── Dates ─────────────────────────────────────────────────────── */
.ms-date {
  font-size: 13.5px;
  font-variant-numeric: tabular-nums;
  color: #46536a;
}

.ms-date-sm {
  font-size: 12.5px;
  font-variant-numeric: tabular-nums;
  color: #94a0b0;
}

.ms-muted {
  color: #c6cdd8;
}

/* ─── Actions ───────────────────────────────────────────────────── */
.ms-td-actions {
  text-align: right;
}

.ms-actions {
  opacity: 0;
  transition: opacity 0.12s ease;
}

.ms-row:hover .ms-actions,
.ms-row:focus-within .ms-actions,
.ms-row:focus-visible .ms-actions {
  opacity: 1;
}

.ms-kebab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: #8a94a6;
  font-size: 19px;
  cursor: pointer;
  transition: background 0.12s ease, color 0.12s ease;
}

.ms-kebab:hover {
  background: #edf0f4;
  color: #334155;
}

.ms-kebab:focus-visible {
  outline: 2px solid #6366f1;
  outline-offset: 1px;
}

.ms-menu {
  border: 1px solid #e9ecf1;
  border-radius: 12px;
  box-shadow:
    0 12px 32px rgba(15, 23, 42, 0.1),
    0 2px 8px rgba(15, 23, 42, 0.05);
  padding: 5px;
  min-width: 180px;
}

.ms-menu .dropdown-item {
  border-radius: 8px;
  font-size: 13.5px;
  padding: 7px 10px;
}

.ms-menu .dropdown-divider {
  margin: 5px 4px;
  opacity: 0.6;
}

/* ─── Empty state ───────────────────────────────────────────────── */
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

.ms-empty-title {
  margin: 15px 0 0;
  font-size: 14px;
  font-weight: 600;
  color: #334155;
}

.ms-empty-text {
  margin: 4px 0 0;
  font-size: 13px;
  color: #94a0b0;
}

/* ─── Mobile cards ──────────────────────────────────────────────── */
.ms-mobile {
  padding: 14px;
}

.ms-mobile-card {
  padding: 14px 15px;
  border: 1px solid #e9ecf1;
  border-radius: 14px;
  background: #fff;
  cursor: pointer;
  transition: border-color 0.12s ease, box-shadow 0.12s ease;
}

.ms-mobile-card + .ms-mobile-card {
  margin-top: 10px;
}

.ms-mobile-card:active {
  border-color: #dbe0e8;
}

.ms-mobile-card:focus-visible {
  outline: 2px solid #6366f1;
  outline-offset: 2px;
}

.ms-mobile-top {
  display: flex;
  align-items: center;
  gap: 12px;
}

.ms-mobile-kebab {
  margin-left: auto;
  flex-shrink: 0;
}

.ms-mobile .ms-kebab {
  opacity: 1;
}

.ms-mobile-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: 12px;
  padding-top: 12px;
  border-top: 1px solid #f3f5f8;
}

/* ─── Responsive toolbar ────────────────────────────────────────── */
@media (max-width: 767.98px) {
  .ms-toolbar {
    padding: 14px 16px;
  }

  .ms-search {
    width: 100%;
    order: 3;
  }

  .ms-toolbar-right {
    width: 100%;
    justify-content: space-between;
    margin-left: 0;
  }

  .ms-segment {
    flex: 1;
  }

  .ms-segment-btn {
    flex: 1;
    text-align: center;
  }
}
</style>