<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePage } from '@inertiajs/vue3'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  user: { type: Object, required: true },
})

const page = usePage()

// TEST CHECK
const isSuperAdmin = computed(() =>
  page.props.auth?.role_names?.includes('super_admin')
)

const user    = computed(() => props.user)
const isAnon  = computed(() => user.value.is_anonymous)
const status  = computed(() => user.value.status)

const statusLabel = computed(() => {
  if (status.value === 'active') {
    if (user.value.subscription?.status === 'expired') return 'Expired'
    return 'Active'
  }
  return {
    pending:        'Pending',
    payment_review: 'Payment Review',
    blocked:        'Blocked',
    rejected:       'Rejected',
  }[status.value] ?? 'Unknown'
})
const statusBadgeClass = computed(() => {
  if (status.value === 'active' && user.value.subscription?.status === 'expired') return 'bg-label-danger'
  if (status.value === 'active')         return 'bg-label-success'
  if (status.value === 'payment_review') return 'bg-label-info'
  if (status.value === 'pending')        return 'bg-label-warning'
  if (status.value === 'blocked' || status.value === 'rejected') return 'bg-label-danger'
  return 'bg-label-secondary'
})
const planLabel = computed(() => {
  const plan = status.value === 'active'
    ? user.value.subscription?.plan
    : user.value.payment_request?.plan
  if (!plan) return null
  return plan.charAt(0).toUpperCase() + plan.slice(1)
})

const roleLabel = computed(() => ({
  super_admin: 'Super Admin',
  admin:       'Admin',
  user:        'User',
}[user.value.role] ?? user.value.role))

const roleBadgeClass = computed(() => {
  if (user.value.role === 'super_admin') return 'bg-label-danger'
  if (user.value.role === 'admin')       return 'bg-label-warning'
  return 'bg-label-secondary'
})

const formatDate     = (v) => v ? new Date(v).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : null
const joinedDate     = computed(() => formatDate(user.value.created_at))
const whatsappLabel  = computed(() => user.value.whatsapp_number ?? null)

const expires_at = computed(() => {
  if (status.value !== 'active') return null
  return formatDate(user.value.subscription?.expires_at)
})

const showRenewsSoon = computed(() => {
  if (status.value !== 'active' || !user.value.subscription?.expires_at) return false
  const days = (new Date(user.value.subscription.expires_at) - new Date()) / (1000 * 60 * 60 * 24)
  return days <= 7 && days > 0
})

const prStatus = computed(() => user.value.payment_request?.status ?? null)

const prStatusClass = computed(() => ({
  pending:  'ms-pr-badge--pending',
  approved: 'ms-pr-badge--approved',
  rejected: 'ms-pr-badge--rejected',
}[prStatus.value] ?? 'ms-pr-badge--pending'))

const prStatusLabel = computed(() => prStatus.value
  ? prStatus.value.charAt(0).toUpperCase() + prStatus.value.slice(1)
  : null
)

/* actions */
const approveUser = () => router.patch(route('admin.users.approve', user.value.id))

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
      onSuccess: () => Swal.fire({ icon: 'success', title: 'Rejected', timer: 1500, showConfirmButton: false }),
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
      onSuccess: () => Swal.fire({ icon:'success', title:'Blocked', timer:1500, showConfirmButton:false }),
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
      onSuccess: () => Swal.fire({ icon:'success', title:'Unblocked', timer:1500, showConfirmButton:false }),
    })
  })
}
</script>

<template>
  <Head title="User Profile" />
  <AdminLayout>
    <div class="ms-page">

      <!-- ── HERO BANNER ─────────────────────────────────── -->
      <div class="ms-hero mb-4">
        <div class="ms-hero__bg">
          <div class="ms-hero__grid"></div>
        </div>

        <div class="ms-hero__body">
          <!-- Top row: badge + actions -->
          <div class="ms-hero__topbar">
            <div class="d-flex align-items-center gap-2">
              <span v-if="isAnon" class="ms-chip ms-chip--muted">Anonymous</span>
              <span class="ms-chip" :class="statusBadgeClass">{{ statusLabel }}</span>
            </div>
            <div class="d-flex gap-2">
              <button v-if="status === 'payment_review'" class="btn btn-primary btn-sm px-3" @click="approveUser">
                <i class="bx bx-check me-1"></i> Approve
              </button>
              <button v-if="status === 'payment_review'" class="btn btn-danger btn-sm px-3" @click="rejectUser">
                <i class="bx bx-x me-1"></i> Reject
              </button>
              <button v-else-if="status === 'active'" class="btn btn-danger btn-sm px-3" @click="blockUser">
                <i class="bx bx-shield-quarter me-1"></i> Block
              </button>
              <button v-else-if="status === 'blocked'" class="btn btn-warning btn-sm px-3" @click="unblockUser">
                <i class="bx bx-refresh me-1"></i> Unblock
              </button>
            </div>
          </div>

          <!-- Identity -->
          <div class="ms-hero__identity">
            <div class="ms-hero__avatar">
              <img src="/admin/assets/img/avatars/1.png" alt="avatar" />
            </div>
            <div>
              <h4 class="ms-hero__name">{{ user.display_name }}</h4>
              <div class="ms-hero__meta">
                <span v-if="isSuperAdmin"><i class="bx bx-envelope"></i> {{ user.email }}</span>
                <span v-if="joinedDate"><i class="bx bx-calendar"></i> Joined {{ joinedDate }}</span>
                <span v-if="whatsappLabel && isSuperAdmin"><i class="bx bx-phone"></i> {{ whatsappLabel }}</span>
                <span><i class="bx bx-map"></i> UAE</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Stats strip -->
        <div class="ms-stats-strip">
          <div class="ms-stat-pill">
            <span class="ms-stat-pill__label">Status</span>
            <span class="ms-stat-pill__value">{{ statusLabel }}</span>
          </div>
          <div class="ms-stat-pill__divider"></div>
          <div class="ms-stat-pill">
            <span class="ms-stat-pill__label">Plan</span>
            <span class="ms-stat-pill__value">
              <template v-if="planLabel">{{ planLabel }}</template>
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

      <!-- ── BOTTOM ROW ───────────────────────────────────── -->
      <div class="row g-4">

        <!-- About -->
        <div class="col-lg-4">
          <div class="ms-card h-100">
            <div class="ms-card__header">
              <i class="bx bx-user"></i>
              <span>About</span>
            </div>
            <div class="ms-card__body">
              <div class="ms-info-list">
                <div class="ms-info-row">
                  <span class="ms-info-row__label">Full Name</span>
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
                <div class="ms-info-row" v-if="user.subscription">
                  <span class="ms-info-row__label">Expires</span>
                  <span class="ms-info-row__value">{{ expires_at ?? '—' }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment Request -->
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

              <!-- Empty -->
              <template v-if="!user.payment_request">
                <div class="ms-empty">
                  <i class="bx bx-receipt ms-empty__icon"></i>
                  <p class="ms-empty__title">No payment request</p>
                  <p class="ms-empty__sub">This user hasn't submitted a payment yet.</p>
                </div>
              </template>

              <!-- Has request -->
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
/* ── Page ─────────────────────────────────────────────── */
.ms-page { display: flex; flex-direction: column; gap: 0; }

/* ── Hero ─────────────────────────────────────────────── */
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
  border-radius: 50%;
  overflow: hidden;
  border: 2.5px solid rgba(255,255,255,0.25);
  flex-shrink: 0;
  background: #1e2a40;
}
.ms-hero__avatar img { width: 100%; height: 100%; object-fit: cover; }

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

/* Stats strip */
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
  gap: 2px;
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

/* Chips */
.ms-chip {
  display: inline-flex;
  align-items: center;
  padding: 3px 10px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  letter-spacing: 0.3px;
}
.ms-chip--muted  { background: rgba(255,255,255,0.12); color: rgba(255,255,255,0.7); }
.ms-chip--warn   { background: rgba(255,159,67,0.2);   color: #ff9f43; }
.ms-chip--xs     { font-size: 10px; padding: 2px 7px; }
.ms-ms-1         { margin-left: 4px; }
.ms-ms-auto      { margin-left: auto; }

/* ── Cards ────────────────────────────────────────────── */
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

/* ── Info list ────────────────────────────────────────── */
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

/* ── Payment Request badge ────────────────────────────── */
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
.ms-pr-badge--pending  { background: rgba(255,159,67,0.12);  color: #e08c00; }
.ms-pr-badge--approved { background: rgba(40,199,111,0.12);  color: #1a7a44; }
.ms-pr-badge--rejected { background: rgba(234,84,85,0.12);   color: #c0392b; }

/* ── Proof image ──────────────────────────────────────── */
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

/* ── Empty state ──────────────────────────────────────── */
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

/* ── Responsive ───────────────────────────────────────── */
@media (max-width: 767px) {
  .ms-stats-strip { flex-wrap: wrap; }
  .ms-stat-pill { flex: 1 1 50%; }
  .ms-stat-pill__divider { display: none; }
}
</style>