<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head } from '@inertiajs/vue3'
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
                <span class="badge bg-label-primary">PRO Trader</span>
                <span class="badge bg-label-secondary">ANON</span>
                <span class="badge bg-label-success">ACTIVE</span>
              </div>

              <div class="ms-title">
                <h4 class="mb-1 text-white">Anon_3</h4>
                <div class="text-white-50 small d-flex flex-wrap gap-3">
                  <span><i class="bx bx-envelope me-1"></i> user2@test.com</span>
                  <span><i class="bx bx-calendar me-1"></i> Joined: Apr 2026</span>
                  <span><i class="bx bx-map me-1"></i> Region: UK</span>
                </div>
              </div>
            </div>
          </div>

          <!-- Avatar strip -->
          <div class="card-body">
            <div class="d-flex align-items-center gap-3">
              <div class="ms-avatar">
                <img src="/admin/assets/img/avatars/1.png" class="rounded-circle" alt="Avatar" />
              </div>

              <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <h5 class="mb-0">Anon_3</h5>
                  <i class="bx bx-mask text-muted" title="Anonymous user"></i>
                </div>
                <div class="text-muted small">Trading Signals • Smart Trading • Risk-managed entries</div>
              </div>

              <div class="d-flex gap-2">
                <button class="btn btn-label-primary">
                  <i class="bx bx-message-square-dots me-1"></i> Message
                </button>
                <button class="btn btn-primary">
                  <i class="bx bx-user-check me-1"></i> Connected
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Content -->
        <div class="row">
          <!-- Left -->
          <div class="col-lg-4">
            <div class="card mb-4">
              <div class="card-body">
                <small class="text-uppercase text-muted">About</small>

                <ul class="list-unstyled mt-3 mb-0">
                  <li class="d-flex align-items-center mb-3">
                    <i class="bx bx-user"></i>
                    <span class="fw-semibold mx-2">Full Name:</span>
                    <span>Anon_3</span>
                  </li>

                  <li class="d-flex align-items-center mb-3">
                    <i class="bx bx-check-circle"></i>
                    <span class="fw-semibold mx-2">Status:</span>
                    <span>Active</span>
                  </li>

                  <li class="d-flex align-items-center mb-3">
                    <i class="bx bx-crown"></i>
                    <span class="fw-semibold mx-2">Role:</span>
                    <span>Admin</span>
                  </li>

                  <li class="d-flex align-items-center mb-3">
                    <i class="bx bx-wallet"></i>
                    <span class="fw-semibold mx-2">Plan:</span>
                    <span>Basic</span>
                  </li>

                  <li class="d-flex align-items-center">
                    <i class="bx bx-time"></i>
                    <span class="fw-semibold mx-2">Expires:</span>
                    <span>24/02/2026</span>
                  </li>
                </ul>
              </div>
            </div>

            <div class="card">
              <div class="card-body">
                <small class="text-uppercase text-muted">Trading Preferences</small>
                <div class="mt-3 d-flex flex-column gap-2">
                  <div class="d-flex justify-content-between">
                    <span class="text-muted">Style</span>
                    <span class="fw-semibold">Swing</span>
                  </div>
                  <div class="d-flex justify-content-between">
                    <span class="text-muted">Risk</span>
                    <span class="fw-semibold">Low–Medium</span>
                  </div>
                  <div class="d-flex justify-content-between">
                    <span class="text-muted">Assets</span>
                    <span class="fw-semibold">Crypto • FX</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right -->
          <div class="col-lg-8">
            <div class="card mb-4">
              <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">
                  <i class="bx bx-line-chart me-2 text-muted"></i>
                  Recent Activity
                </h5>

                <button class="btn btn-sm btn-label-secondary">
                  <i class="bx bx-filter-alt me-1"></i> Filter
                </button>
              </div>

              <div class="card-body">
                <ul class="timeline mb-0">
                  <li class="timeline-item timeline-item-transparent">
                    <span class="timeline-point timeline-point-primary"></span>
                    <div class="timeline-event">
                      <div class="d-flex justify-content-between mb-1">
                        <h6 class="mb-0">Signal posted: BTCUSDT</h6>
                        <small class="text-muted">12 min ago</small>
                      </div>
                      <p class="mb-0 text-muted">Entry zone shared with SL/TP and risk note.</p>
                    </div>
                  </li>

                  <li class="timeline-item timeline-item-transparent">
                    <span class="timeline-point timeline-point-success"></span>
                    <div class="timeline-event">
                      <div class="d-flex justify-content-between mb-1">
                        <h6 class="mb-0">Subscription approved</h6>
                        <small class="text-muted">2 hrs ago</small>
                      </div>
                      <p class="mb-0 text-muted">User moved from pending to active plan.</p>
                    </div>
                  </li>

                  <li class="timeline-item timeline-item-transparent">
                    <span class="timeline-point timeline-point-warning"></span>
                    <div class="timeline-event">
                      <div class="d-flex justify-content-between mb-1">
                        <h6 class="mb-0">Risk warning noted</h6>
                        <small class="text-muted">Yesterday</small>
                      </div>
                      <p class="mb-0 text-muted">High volatility session flagged.</p>
                    </div>
                  </li>
                </ul>
              </div>
            </div>

            <div class="card">
              <div class="card-header">
                <h5 class="mb-0">Admin Actions (Demo)</h5>
              </div>
              <div class="card-body d-flex flex-wrap gap-2">
                <button class="btn btn-label-success">
                  <i class="bx bx-check me-1"></i> Approve Subscription
                </button>
                <button class="btn btn-label-warning">
                  <i class="bx bx-shield-quarter me-1"></i> Block User
                </button>
                <button class="btn btn-label-danger">
                  <i class="bx bx-trash me-1"></i> Delete User
                </button>
              </div>
            </div>
          </div>
        </div>
        <!-- /Content -->
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
/* Banner */
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
    rgba(255, 255, 255, 0.06),
    rgba(255, 255, 255, 0.06) 1px,
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

.ms-badges {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.ms-title h4 {
  letter-spacing: 0.2px;
}

/* Avatar */
.ms-avatar {
  width: 54px;
  height: 54px;
  border-radius: 999px;
  overflow: hidden;
  border: 3px solid #fff;
  margin-top: -32px; /* overlap on banner like Sneat */
  background: #fff;
  flex: 0 0 auto;
}

.ms-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
