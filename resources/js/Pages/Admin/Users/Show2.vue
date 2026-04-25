<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  user: { type: Object, required: true },
})

const user = computed(() => props.user)
const isAnon  = computed(() => user.value.is_anonymous)
const status  = computed(() => user.value.status)

const statusLabel = computed(() => {
  const map = {
    active:         'ACTIVE',
    pending:        'PENDING',
    payment_review: 'PAYMENT REVIEW',
    blocked:        'BLOCKED',
    rejected:       'REJECTED',
  }
  return map[status.value] ?? 'UNKNOWN'
})

const statusBadgeClass = computed(() => {
  if (status.value === 'active')                                 return 'bg-label-success'
  if (status.value === 'payment_review')                         return 'bg-label-info'
  if (status.value === 'pending')                                return 'bg-label-warning'
  if (status.value === 'blocked' || status.value === 'rejected') return 'bg-label-danger'
  return 'bg-label-secondary'
})

const showVerified = computed(() => status.value === 'active')

const planLabel = computed(() => {
  const plan = status.value === 'active'
    ? user.value.subscription?.plan
    : user.value.paymentRequest?.plan

  if (!plan) return null
  return plan.charAt(0).toUpperCase() + plan.slice(1)
})

const roleLabel = computed(() => {
  const map = {
    super_admin: 'Super Admin',
    admin:       'Admin',
    user:        'User',
  }
  return map[user.value.role] ?? user.value.role
})

const roleBadgeClass = computed(() => {
  if (user.value.role === 'super_admin') return 'bg-label-danger'
  if (user.value.role === 'admin')       return 'bg-label-warning'
  return 'bg-label-secondary'
})

const formatDate  = (value) => (value ? new Date(value).toLocaleDateString() : null)
const joinedDate  = computed(() => formatDate(user.value.created_at))

const expires_at = computed(() => {
  if (status.value !== 'active') return null
  return formatDate(user.value.subscription?.expires_at)
})

const showRenewsSoon = computed(() => {
  if (status.value !== 'active' || !user.value.subscription?.expires_at) return false
  const days = (new Date(user.value.subscription.expires_at) - new Date()) / (1000 * 60 * 60 * 24)
  return days <= 7 && days > 0
})

const whatsappLabel = computed(() => user.value.whatsapp_number ?? null)

/* actions */
const approveUser = () => router.patch(route('admin.users.approve', user.value.id))

const blockUser = () => {
  Swal.fire({
    title: 'Block this user?',
    text: 'Their subscription will be canceled.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, block',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.patch(route('admin.users.block', user.value.id), {}, {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'User Blocked',
          text: 'The user has been blocked.',
          timer: 1500,
          showConfirmButton: false,
        })
      },
    })
  })
}

const unblockUser = () => {
  Swal.fire({
    title: 'Unblock this user?',
    text: 'They will regain access to the platform.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, unblock',
  }).then((result) => {
    if (!result.isConfirmed) return
    router.patch(route('admin.users.unblock', user.value.id), {}, {
      onSuccess: () => {
        Swal.fire({
          icon: 'success',
          title: 'User Unblocked',
          text: 'The user has been unblocked.',
          timer: 1500,
          showConfirmButton: false,
        })
      },
    })
  })
}
</script>

<template>
  <Head title="User Profile" />
  <AdminLayout>
    <div class="row">
      <div class="col-12">

        <!-- Banner -->
        <div class="card mb-4 overflow-hidden">
          <div class="ms-banner">
            <div class="ms-banner__overlay"></div>
            <div class="ms-banner__content">
              <div class="ms-badges">
                <span v-if="isAnon" class="badge bg-label-secondary">Anonymous</span>
                <span class="badge px-3 py-1 fw-semibold" :class="statusBadgeClass">
                  {{ statusLabel }}
                </span>
              </div>

              <div class="ms-title">
                <h4 class="mb-1 text-white">{{ user.display_name }}</h4>
                <div class="text-white-50 small d-flex flex-wrap gap-3">
                  <span><i class="bx bx-envelope me-1"></i> {{ user.email }}</span>
                  <span v-if="joinedDate"><i class="bx bx-calendar me-1"></i> Joined: {{ joinedDate }}</span>
                  <span v-if="whatsappLabel"><i class="bx bx-phone me-1"></i> WhatsApp: {{ whatsappLabel }}</span>
                  <span><i class="bx bx-map me-1"></i> Region: UAE</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Avatar strip -->
          <div class="card-body pb-0">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div class="ms-avatar">
                <img src="/admin/assets/img/avatars/1.png" class="rounded-circle" />
              </div>

              <div class="flex-grow-1 min-w-0">
                <h5 class="mb-0">{{ user.display_name }}</h5>
                <div class="text-muted small">
                  Trading Signals • Smart Trading • Risk-managed entries
                </div>
              </div>

              <div class="d-flex gap-2 ms-auto">
                <!-- Approve: only when payment submitted and awaiting review -->
                <button
                  v-if="status === 'payment_review'"
                  class="btn btn-primary"
                  @click="approveUser"
                >
                  <i class="bx bx-check me-1"></i> Approve
                </button>

                <!-- Block: only active users -->
                <button
                  v-else-if="status === 'active'"
                  class="btn btn-danger"
                  @click="blockUser"
                >
                  <i class="bx bx-shield-quarter me-1"></i> Block
                </button>

                <!-- Unblock -->
                <button
                  v-else-if="status === 'blocked'"
                  class="btn btn-warning"
                  @click="unblockUser"
                >
                  <i class="bx bx-refresh me-1"></i> Unblock
                </button>
              </div>
            </div>
          </div>

          <!-- Summary stats -->
          <div class="card-body pt-3">
            <div class="ms-stats">

              <!-- Status stat -->
              <div class="ms-stat">
                <div class="ms-stat__icon"><i class="bx bx-check-circle"></i></div>
                <div>
                  <div class="ms-stat__label">User Status</div>
                  <div class="ms-stat__value">
                    {{ statusLabel }}
                    <span v-if="showVerified" class="badge bg-label-success ms-2">Verified</span>
                  </div>
                </div>
              </div>

              <!-- Plan stat -->
              <div class="ms-stat">
                <div class="ms-stat__icon"><i class="bx bx-wallet"></i></div>
                <div>
                  <div class="ms-stat__label">Plan</div>
                  <div class="ms-stat__value">
                    <template v-if="planLabel">
                      {{ planLabel }}
                      <span v-if="user.subscription" class="badge bg-label-primary ms-2">Active</span>
                    </template>
                    <template v-else>
                      <span class="text-muted fw-normal fst-italic" style="font-size:13px;">No active plan</span>
                    </template>
                  </div>
                </div>
              </div>

              <!-- Expires stat — only shown when subscription exists -->
              <div class="ms-stat" v-if="user.subscription">
                <div class="ms-stat__icon"><i class="bx bx-time-five"></i></div>
                <div>
                  <div class="ms-stat__label">Expires</div>
                  <div class="ms-stat__value">
                    {{ expires_at ?? '—' }}
                    <span v-if="showRenewsSoon" class="badge bg-label-warning ms-2">Renews soon</span>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <!-- Bottom row -->
        <div class="row">
          <div class="col-lg-4 d-flex">
            <div class="card mb-4 h-100 w-100">
              <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-user me-2"></i> About</h5>
              </div>
              <div class="card-body">
                <ul class="list-unstyled mb-0">
                  <li class="mb-3">
                    Full Name: <strong>{{ user.display_name }}</strong>
                  </li>
                  <li class="mb-3">
                    Role:
                    <span class="badge ms-1" :class="roleBadgeClass">{{ roleLabel }}</span>
                  </li>
                  <li v-if="whatsappLabel" class="mb-3">
                    WhatsApp: <strong>{{ whatsappLabel }}</strong>
                  </li>
                  <li v-if="user.subscription">
                    Expires: <strong>{{ expires_at ?? '—' }}</strong>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <div class="col-lg-8 d-flex">
            <div class="card mb-4 h-100 w-100">
              <div class="card-header d-flex justify-content-between">
                <h5 class="mb-0"><i class="bx bx-line-chart me-2"></i> Recent Activity</h5>
                <button class="btn btn-sm btn-label-secondary" disabled>
                  <i class="bx bx-filter-alt"></i>
                </button>
              </div>
              <div class="card-body d-flex align-items-center">
                <div class="w-100 p-3 rounded bg-label-info">
                  <div class="d-flex align-items-start gap-2">
                    <i class="bx bx-info-circle fs-5 mt-1"></i>
                    <div>
                      <div class="fw-semibold">Coming in Version 2</div>
                      <div class="small text-muted">
                        Activity feed will show signals history, admin actions, and risk alerts.
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.ms-banner {
  position: relative;
  height: 190px;
  border-radius: 0.5rem 0.5rem 0 0;
  overflow: hidden;
  background: radial-gradient(1100px 300px at 20% 20%, rgba(105,108,255,.55), transparent 60%),
              radial-gradient(900px 260px at 80% 30%, rgba(3,195,236,.35), transparent 60%),
              linear-gradient(135deg, #0b1220, #111a2e 50%, #0b1220);
}

.ms-banner__overlay {
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    120deg,
    rgba(255,255,255,0.06),
    rgba(255,255,255,0.06) 1px,
    transparent 1px,
    transparent 14px
  );
  opacity: 0.55;
}

.ms-banner__content {
  position: absolute;
  inset: 0;
  padding: 1.25rem 1.25rem 1.75rem;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  gap: 0.75rem;
}

.ms-title h4 { letter-spacing: 0.2px; }
.ms-badges { display: flex; flex-wrap: wrap; gap: 0.5rem; }

.ms-avatar {
  width: 54px;
  height: 54px;
  border-radius: 999px;
  overflow: hidden;
  border: 3px solid #fff;
  margin-top: -32px;
  background: #fff;
  flex: 0 0 auto;
}
.ms-avatar img { width: 100%; height: 100%; object-fit: cover; }

.ms-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.ms-stat {
  display: flex;
  gap: 12px;
  padding: 14px;
  border: 1px solid rgba(67,89,113,0.18);
  border-radius: 10px;
  background: #fff;
  transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.ms-stat:hover { transform: translateY(-1px); box-shadow: 0 8px 22px rgba(15,23,42,0.06); }

.ms-stat__icon {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(105,108,255,0.10);
  color: rgba(105,108,255,1);
  flex: 0 0 auto;
}
.ms-stat__icon i { font-size: 20px; }

.ms-stat__label { font-size: 12px; color: rgba(67,89,113,0.75); line-height: 1.2; }
.ms-stat__value { margin-top: 2px; font-weight: 600; color: rgba(67,89,113,1); display: flex; flex-wrap: wrap; align-items: center; }

@media (max-width: 991.98px) {
  .ms-stats { grid-template-columns: 1fr; }
}
</style>