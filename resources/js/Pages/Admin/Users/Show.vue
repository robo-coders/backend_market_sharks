<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePage, Link } from '@inertiajs/vue3'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  user: { type: Object, required: true },
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

const user = computed(() => props.user)
const isAnon = computed(() => user.value.is_anonymous)
const status = computed(() => user.value.status)

const isExpired = (expiresAt) => {
  if (!expiresAt) return false
  return new Date(expiresAt) < new Date()
}

const isExpiringSoon = (expiresAt) => {
  if (!expiresAt || isExpired(expiresAt)) return false
  const days = (new Date(expiresAt) - new Date()) / (1000 * 60 * 60 * 24)
  return days <= 7
}

const effectiveStatus = computed(() => {
  if (status.value === 'active') {
    if (isExpired(user.value.subscription?.expires_at)) return 'expired'
    if (isExpiringSoon(user.value.subscription?.expires_at)) return 'expiring'
    return 'active'
  }

  return status.value
})

const statusLabel = computed(() => {
  if (effectiveStatus.value === 'active') return 'Active'
  if (effectiveStatus.value === 'expiring') return 'Expiring Soon'

  return {
    pending: 'Pending',
    payment_review: 'Payment Review',
    blocked: 'Blocked',
    rejected: 'Rejected',
    expired: 'Expired',
  }[effectiveStatus.value] ?? 'Unknown'
})

// Chip variants designed for the dark hero background — the light
// bg-label-* tints from the admin theme go muddy on #0d1525.
const statusChipClass = computed(() => ({
  active: 'ms-chip--success',
  expiring: 'ms-chip--warn',
  payment_review: 'ms-chip--info',
  pending: 'ms-chip--neutral',
  expired: 'ms-chip--danger',
  rejected: 'ms-chip--danger',
  blocked: 'ms-chip--slate',
}[effectiveStatus.value] ?? 'ms-chip--neutral'))

const planLabel = computed(() => {
  let plan = null

  if (effectiveStatus.value === 'active' || effectiveStatus.value === 'expiring' || effectiveStatus.value === 'expired') {
    plan = user.value.subscription?.plan
  } else if (effectiveStatus.value === 'payment_review') {
    plan = user.value.payment_request?.plan
  }

  if (!plan) return null
  return plan.charAt(0).toUpperCase() + plan.slice(1)
})

const roleLabel = computed(() => ({
  super_admin: 'Super Admin',
  admin: 'Admin',
  user: 'User',
}[user.value.role] ?? user.value.role ?? 'User'))

const roleBadgeClass = computed(() => {
  if (user.value.role === 'super_admin') return 'bg-label-danger'
  if (user.value.role === 'admin') return 'bg-label-warning'
  return 'bg-label-secondary'
})

// "Full Name" is a contradiction for anonymous accounts, whose display
// name is actually a chosen nickname — the label now reflects which one
// is genuinely being shown, instead of asserting it's a real name.
const nameFieldLabel = computed(() => (isAnon.value ? 'Nickname' : 'Full Name'))

// Same deterministic 3-tone avatar system as the Users list — a user
// keeps the same tile color when navigating from list to detail.
const AVATAR_TONES = [
  { bg: '#eef2ff', fg: '#4f46e5' },
  { bg: '#f0f4f8', fg: '#475569' },
  { bg: '#f5f3ff', fg: '#7c3aed' },
]

const avatarTone = computed(() => AVATAR_TONES[(user.value.id ?? 0) % AVATAR_TONES.length])

const initials = computed(() => {
  const name = user.value.display_name
  if (!name) return '?'
  const parts = name.trim().split(/\s+/)
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase()
  return (parts[0][0] + parts[1][0]).toUpperCase()
})

const formatDate = (v) =>
  v ? new Date(v).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) : null

const joinedDate = computed(() => formatDate(user.value.created_at))
const whatsappLabel = computed(() => user.value.whatsapp_number ?? null)

const expires_at = computed(() => {
  if (!user.value.subscription?.expires_at) return null
  return formatDate(user.value.subscription.expires_at)
})

const showRenewsSoon = computed(() => {
  if (effectiveStatus.value !== 'active' && effectiveStatus.value !== 'expiring') return false
  return isExpiringSoon(user.value.subscription?.expires_at)
})

const prStatus = computed(() => user.value.payment_request?.status ?? null)

const prStatusClass = computed(() => ({
  pending: 'ms-pr-badge--pending',
  approved: 'ms-pr-badge--approved',
  rejected: 'ms-pr-badge--rejected',
}[prStatus.value] ?? 'ms-pr-badge--pending'))

const prStatusLabel = computed(() => prStatus.value
  ? prStatus.value.charAt(0).toUpperCase() + prStatus.value.slice(1)
  : null
)

const approveUser = () => {
  Swal.fire({
    title: 'Approve this payment?',
    text: 'A subscription will be created and the user will be notified.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, approve',
  }).then((r) => {
    if (!r.isConfirmed) return
    router.patch(route('admin.users.approve', user.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'User approved' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not approve user' }),
    })
  })
}

const rejectUser = () => {
  Swal.fire({
    title: 'Reject this payment?',
    text: 'The user will be notified their payment was rejected.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, reject',
    confirmButtonColor: '#ea5455',
  }).then((r) => {
    if (!r.isConfirmed) return
    router.patch(route('admin.users.reject', user.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'Payment rejected' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not reject payment' }),
    })
  })
}

const blockUser = () => {
  Swal.fire({
    title: 'Block this user?',
    text: 'Their subscription will be canceled.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, block',
  }).then((r) => {
    if (!r.isConfirmed) return
    router.patch(route('admin.users.block', user.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'User blocked' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not block user' }),
    })
  })
}

const unblockUser = () => {
  Swal.fire({
    title: 'Unblock this user?',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, unblock',
  }).then((r) => {
    if (!r.isConfirmed) return
    router.patch(route('admin.users.unblock', user.value.id), {}, {
      preserveScroll: true,
      onSuccess: () => notifyToast.fire({ icon: 'success', title: 'User unblocked' }),
      onError: () => notifyToast.fire({ icon: 'error', title: 'Could not unblock user' }),
    })
  })
}
</script>

<template>
  <Head title="User Profile" />
  <AdminLayout>
    <div class="ms-page">
      <Link :href="route('admin.users.index')" class="ms-back">
        <i class="bx bx-arrow-back"></i>
        Back to users
      </Link>

      <div class="ms-hero mb-4">
        <div class="ms-hero__bg">
          <div class="ms-hero__grid"></div>
        </div>

        <div class="ms-hero__body">
          <div class="ms-hero__topbar">
            <div class="d-flex align-items-center gap-2">
              <span v-if="isAnon" class="ms-chip ms-chip--muted">Anonymous</span>
              <span class="ms-chip" :class="statusChipClass">{{ statusLabel }}</span>
            </div>
            <div class="d-flex gap-2">
              <button v-if="effectiveStatus === 'payment_review'" class="btn btn-primary btn-sm px-3" @click="approveUser">
                <i class="bx bx-check me-1"></i> Approve
              </button>
              <button v-if="effectiveStatus === 'payment_review'" class="btn btn-danger btn-sm px-3" @click="rejectUser">
                <i class="bx bx-x me-1"></i> Reject
              </button>
              <button v-else-if="effectiveStatus === 'active' || effectiveStatus === 'expiring' || effectiveStatus === 'expired'" class="btn btn-danger btn-sm px-3" @click="blockUser">
                <i class="bx bx-shield-quarter me-1"></i> Block
              </button>
              <button v-else-if="effectiveStatus === 'blocked'" class="btn btn-warning btn-sm px-3" @click="unblockUser">
                <i class="bx bx-refresh me-1"></i> Unblock
              </button>
            </div>
          </div>

          <div class="ms-hero__identity">
            <div
              class="ms-hero__avatar"
              :style="{ background: avatarTone.bg, color: avatarTone.fg }"
            >
              {{ initials }}
            </div>
            <div>
              <h4 class="ms-hero__name">{{ user.display_name }}</h4>
              <div class="ms-hero__meta">
                <span v-if="isSuperAdmin"><i class="bx bx-envelope"></i> {{ user.email }}</span>
                <span v-if="joinedDate"><i class="bx bx-calendar"></i> Joined {{ joinedDate }}</span>
                <span v-if="whatsappLabel && isSuperAdmin"><i class="bx bx-phone"></i> {{ whatsappLabel }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="ms-stats-strip">
          <div class="ms-stat-pill">
            <span class="ms-stat-pill__label">Plan</span>
            <span class="ms-stat-pill__value">
              <span v-if="planLabel" class="ms-chip ms-chip--sm ms-chip--plan">
                <i class="bx bxs-zap ms-chip__icon"></i>
                {{ planLabel }}
              </span>
              <span v-else class="ms-stat-pill__empty">No plan</span>
            </span>
          </div>
          <div class="ms-stat-pill__divider"></div>
          <div class="ms-stat-pill">
            <span class="ms-stat-pill__label">Role</span>
            <span class="ms-stat-pill__value">{{ roleLabel }}</span>
          </div>
          <div class="ms-stat-pill__divider"></div>
          <div class="ms-stat-pill">
            <span class="ms-stat-pill__label">Expires</span>
            <span class="ms-stat-pill__value">
              <template v-if="expires_at">
                {{ expires_at }}
                <span v-if="showRenewsSoon" class="ms-chip ms-chip--warn ms-chip--xs ms-ms-1">Soon</span>
              </template>
              <span v-else class="ms-stat-pill__empty">—</span>
            </span>
          </div>
        </div>
      </div>

      <div class="row g-4">
        <div class="col-lg-4">
          <div class="ms-card h-100">
            <div class="ms-card__header">
              <i class="bx bx-user"></i>
              <span>About</span>
            </div>
            <div class="ms-card__body">
              <div class="ms-info-list">
                <div class="ms-info-row">
                  <span class="ms-info-row__label">{{ nameFieldLabel }}</span>
                  <span class="ms-info-row__value">{{ user.display_name }}</span>
                </div>
                <div class="ms-info-row">
                  <span class="ms-info-row__label">Role</span>
                  <span class="badge" :class="roleBadgeClass">{{ roleLabel }}</span>
                </div>
                <div class="ms-info-row" v-if="whatsappLabel && isSuperAdmin">
                  <span class="ms-info-row__label">WhatsApp</span>
                  <span class="ms-info-row__value">{{ whatsappLabel }}</span>
                </div>
                <div class="ms-info-row">
                  <span class="ms-info-row__label">Joined</span>
                  <span class="ms-info-row__value">{{ joinedDate ?? '—' }}</span>
                </div>
                <div class="ms-info-row" v-if="user.subscription?.expires_at">
                  <span class="ms-info-row__label">Expires</span>
                  <span class="ms-info-row__value">{{ expires_at ?? '—' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-lg-8">
          <div class="ms-card h-100">
            <div class="ms-card__header">
              <i class="bx bx-receipt"></i>
              <span>Payment Request</span>
              <span v-if="prStatusLabel" class="ms-pr-badge ms-ms-auto" :class="prStatusClass">
                {{ prStatusLabel }}
              </span>
            </div>

            <div class="ms-card__body">
              <template v-if="!user.payment_request">
                <div class="ms-empty">
                  <i class="bx bx-receipt ms-empty__icon"></i>
                  <p class="ms-empty__title">No payment request</p>
                  <p class="ms-empty__sub">This user hasn't submitted a payment yet.</p>
                </div>
              </template>

              <template v-else>
                <div class="row g-4">
                  <div class="col-sm-6">
                    <div class="ms-info-list">
                      <div class="ms-info-row">
                        <span class="ms-info-row__label">Plan</span>
                        <span class="ms-info-row__value text-capitalize fw-semibold">{{ user.payment_request.plan ?? '—' }}</span>
                      </div>
                      <div class="ms-info-row">
                        <span class="ms-info-row__label">Method</span>
                        <span class="ms-info-row__value text-capitalize">{{ user.payment_request.payment_method ?? '—' }}</span>
                      </div>
                      <div class="ms-info-row">
                        <span class="ms-info-row__label">Submitted</span>
                        <span class="ms-info-row__value">{{ formatDate(user.payment_request.created_at) ?? '—' }}</span>
                      </div>
                      <div class="ms-info-row" v-if="user.payment_request.reviewed_at">
                        <span class="ms-info-row__label">Reviewed</span>
                        <span class="ms-info-row__value">{{ formatDate(user.payment_request.reviewed_at) }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="col-sm-6">
                    <p class="ms-info-row__label mb-2">Payment Proof</p>
                    <template v-if="user.payment_request.proof_path">
                      <a :href="`/storage/${user.payment_request.proof_path}`" target="_blank" class="ms-proof">
                        <img :src="`/storage/${user.payment_request.proof_path}`" alt="Payment proof" class="ms-proof__img" />
                        <div class="ms-proof__overlay"><i class="bx bx-zoom-in me-1"></i> View full size</div>
                      </a>
                    </template>
                    <template v-else>
                      <div class="ms-proof ms-proof--empty">
                        <i class="bx bx-image"></i>
                        <span>No proof uploaded</span>
                      </div>
                    </template>
                  </div>
                </div>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.ms-page { display: flex; flex-direction: column; gap: 0; }

.ms-back {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  align-self: flex-start;
  margin-bottom: 14px;
  font-size: 13px;
  font-weight: 550;
  color: rgba(67, 89, 113, 0.65);
  text-decoration: none;
  border-radius: 8px;
  padding: 5px 9px 5px 6px;
  transition: background 0.12s ease, color 0.12s ease;
}
.ms-back:hover {
  background: rgba(67, 89, 113, 0.07);
  color: rgba(67, 89, 113, 0.95);
}
.ms-back i { font-size: 16px; }

.ms-hero {
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 4px 24px rgba(15,23,42,0.10);
  background: #0d1525;
  position: relative;
}

.ms-hero__bg {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(ellipse 900px 300px at 15% 50%, rgba(105,108,255,0.45), transparent 70%),
    radial-gradient(ellipse 700px 260px at 85% 30%, rgba(3,195,236,0.28), transparent 65%);
}

.ms-hero__grid {
  position: absolute;
  inset: 0;
  background-image: repeating-linear-gradient(
    0deg, rgba(255,255,255,0.04) 0px, rgba(255,255,255,0.04) 1px, transparent 1px, transparent 40px
  ), repeating-linear-gradient(
    90deg, rgba(255,255,255,0.04) 0px, rgba(255,255,255,0.04) 1px, transparent 1px, transparent 40px
  );
}

.ms-hero__body {
  position: relative;
  padding: 1.5rem 1.5rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.ms-hero__topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.ms-hero__identity {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.ms-hero__avatar {
  width: 56px;
  height: 56px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 0.02em;
  flex-shrink: 0;
  box-shadow: 0 0 0 2.5px rgba(255,255,255,0.22);
  user-select: none;
}

.ms-hero__name {
  color: #fff;
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0 0 4px;
  letter-spacing: 0.1px;
}

.ms-hero__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  font-size: 12px;
  color: rgba(255,255,255,0.55);
}
.ms-hero__meta i { margin-right: 4px; vertical-align: middle; }

.ms-stats-strip {
  position: relative;
  display: flex;
  align-items: stretch;
  background: rgba(255,255,255,0.05);
  border-top: 1px solid rgba(255,255,255,0.07);
  backdrop-filter: blur(4px);
}

.ms-stat-pill {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 12px 20px;
}

.ms-stat-pill__divider {
  width: 1px;
  background: rgba(255,255,255,0.08);
  align-self: stretch;
  margin: 8px 0;
}

.ms-stat-pill__label {
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: rgba(255,255,255,0.4);
}

.ms-stat-pill__value {
  font-size: 13px;
  font-weight: 600;
  color: rgba(255,255,255,0.9);
  display: flex;
  align-items: center;
  gap: 6px;
}

.ms-stat-pill__empty { color: rgba(255,255,255,0.3); font-weight: 400; }

.ms-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.ms-chip--muted   { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.7); }
.ms-chip--neutral { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.78); }
.ms-chip--success { background: rgba(61,220,151,0.18);  color: #5eead4; }
.ms-chip--info    { background: rgba(56,189,248,0.18);  color: #7dd3fc; }
.ms-chip--warn    { background: rgba(255,159,67,0.2);   color: #ff9f43; }
.ms-chip--danger  { background: rgba(248,113,113,0.18); color: #fca5a5; }
.ms-chip--slate   { background: rgba(148,163,184,0.18); color: #cbd5e1; }
.ms-chip--plan    { background: rgba(167,139,250,0.16); color: #c4b5fd; }
.ms-chip--sm      { font-size: 11px; padding: 3px 9px; }
.ms-chip--xs      { font-size: 10px; padding: 2px 7px; }
.ms-chip__icon    { font-size: 12px; }
.ms-ms-1          { margin-left: 4px; }
.ms-ms-auto       { margin-left: auto; }

.ms-card {
  background: #fff;
  border-radius: 12px;
  border: 1px solid rgba(67,89,113,0.12);
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(15,23,42,0.05);
}

.ms-card__header {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 14px 18px;
  border-bottom: 1px solid rgba(67,89,113,0.09);
  font-size: 13px;
  font-weight: 600;
  color: rgba(67,89,113,0.9);
}

.ms-card__header i {
  font-size: 16px;
  color: rgba(105,108,255,0.75);
}

.ms-card__body { padding: 18px; }

.ms-info-list { display: flex; flex-direction: column; }

.ms-info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 9px 0;
  border-bottom: 1px solid rgba(67,89,113,0.07);
}
.ms-info-row:last-child { border-bottom: none; }

.ms-info-row__label {
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: rgba(67,89,113,0.5);
}

.ms-info-row__value {
  font-size: 13px;
  font-weight: 500;
  color: rgba(67,89,113,0.9);
}

.ms-pr-badge {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}
.ms-pr-badge--pending  { background: rgba(255,159,67,0.12); color: #e08c00; }
.ms-pr-badge--approved { background: rgba(40,199,111,0.12); color: #1a7a44; }
.ms-pr-badge--rejected { background: rgba(234,84,85,0.12); color: #c0392b; }

.ms-proof {
  position: relative;
  display: block;
  border-radius: 10px;
  overflow: hidden;
  border: 1px solid rgba(67,89,113,0.13);
  background: #f7f7f7;
  text-decoration: none;
  cursor: pointer;
}

.ms-proof__img {
  width: 100%;
  max-height: 200px;
  object-fit: contain;
  display: block;
}

.ms-proof__overlay {
  position: absolute;
  inset: 0;
  background: rgba(13,21,37,0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #fff;
  font-size: 12px;
  font-weight: 600;
  opacity: 0;
  transition: opacity 0.2s ease;
  backdrop-filter: blur(2px);
}
.ms-proof:hover .ms-proof__overlay { opacity: 1; }

.ms-proof--empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 2.5rem 1rem;
  border: 1.5px dashed rgba(67,89,113,0.2);
  color: rgba(67,89,113,0.4);
  font-size: 12px;
  cursor: default;
}
.ms-proof--empty i { font-size: 1.75rem; }

.ms-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  text-align: center;
}
.ms-empty__icon { font-size: 2.5rem; color: rgba(67,89,113,0.2); margin-bottom: 12px; }
.ms-empty__title { font-weight: 600; color: rgba(67,89,113,0.7); margin: 0 0 4px; font-size: 14px; }
.ms-empty__sub { font-size: 12px; color: rgba(67,89,113,0.45); margin: 0; }

@media (max-width: 767px) {
  .ms-stats-strip { flex-wrap: wrap; }
  .ms-stat-pill { flex: 1 1 50%; }
  .ms-stat-pill__divider { display: none; }
}
</style>