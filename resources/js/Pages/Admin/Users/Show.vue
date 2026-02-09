<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, Link } from '@inertiajs/vue3'

// Demo-only helper (static page)
const formatDate = (value) => (value ? new Date(value).toLocaleDateString() : '—')
</script>

<template>
  <Head title="User Details" />

  <AdminLayout>
    <!-- Header -->
    <div class="row">
      <div class="col-12">
        <div class="card mb-4">
          <!-- Banner -->
          <div class="user-profile-header-banner">
            <img
              src="/admin/assets/img/pages/profile-banner.png"
              alt="Banner image"
              class="rounded-top"
              style="width: 100%; height: 220px; object-fit: cover"
            />
          </div>

          <!-- Profile header -->
          <div class="user-profile-header d-flex flex-column flex-lg-row text-sm-start text-center mb-4">
            <div class="flex-shrink-0 mt-2 mx-sm-0 mx-auto">
              <img
                src="/admin/assets/img/avatars/5.png"
                alt="user image"
                class="d-block h-auto ms-0 ms-sm-4 rounded-3 user-profile-img"
                style="width: 110px; height: 110px; object-fit: cover"
              />
            </div>

            <div class="flex-grow-1 mt-3 mt-lg-4">
              <div
                class="d-flex align-items-md-end align-items-sm-start align-items-center justify-content-md-between justify-content-start mx-4 flex-md-row flex-column gap-3"
              >
                <div class="user-profile-info">
                  <div class="d-flex align-items-center justify-content-center justify-content-sm-start gap-2 flex-wrap">
                    <h4 class="mb-0">Anon_3</h4>

                    <!-- Anonymous -->
                    <span class="badge rounded-pill bg-label-secondary" title="User chose to hide their real name">
                      <i class="bx bx-mask me-1"></i> Anonymous
                    </span>

                    <!-- Subscription status -->
                    <span class="badge rounded-pill bg-label-warning">Expiring Soon</span>
                  </div>

                  <ul
                    class="list-inline mb-0 d-flex align-items-center flex-wrap justify-content-sm-start justify-content-center gap-3 mt-3"
                  >
                    <li class="list-inline-item">
                      <i class="bx bx-envelope me-1 align-top"></i>
                      <span class="fw-medium">anon3@test.com</span>
                    </li>

                    <li class="list-inline-item">
                      <i class="bx bxl-whatsapp me-1 align-top"></i>
                      <span class="fw-medium">+44 7700 900123</span>
                    </li>

                    <li class="list-inline-item">
                      <i class="bx bx-calendar me-1 align-top"></i>
                      <span class="fw-medium">Joined {{ formatDate('2025-10-01') }}</span>
                    </li>
                  </ul>
                </div>

                <!-- Actions -->
                <div class="d-flex gap-2">
                  <Link :href="route('admin.users.index')" class="btn btn-label-secondary">
                    <i class="bx bx-arrow-back me-1"></i> Back
                  </Link>

                  <!-- Demo approve button -->
                  <button type="button" class="btn btn-primary">
                    <i class="bx bx-check me-1"></i> Approve
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Quick overview cards -->
          <div class="px-4 pb-4">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="d-flex align-items-center gap-2 p-3 rounded border">
                  <i class="bx bx-check-circle"></i>
                  <div>
                    <div class="text-muted small">User Status (users.status)</div>
                    <div class="fw-semibold">active</div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="d-flex align-items-center gap-2 p-3 rounded border">
                  <i class="bx bx-package"></i>
                  <div>
                    <div class="text-muted small">Plan</div>
                    <div class="fw-semibold">Pro Monthly</div>
                  </div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="d-flex align-items-center gap-2 p-3 rounded border">
                  <i class="bx bx-time-five"></i>
                  <div>
                    <div class="text-muted small">Expires</div>
                    <div class="fw-semibold">{{ formatDate('2026-02-01') }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- /Quick overview -->
        </div>
      </div>
    </div>
    <!--/ Header -->

    <!-- Content -->
    <div class="row">
      <!-- Left -->
      <div class="col-xl-4 col-lg-5 col-md-5">
        <!-- About -->
        <div class="card mb-4">
          <div class="card-body">
            <small class="card-text text-uppercase text-body-secondary">About</small>

            <ul class="list-unstyled mt-3 mb-0">
              <li class="d-flex align-items-center mb-3">
                <i class="bx bx-user"></i>
                <span class="fw-medium mx-2">Display Name:</span>
                <span>Anon_3</span>
              </li>

              <li class="d-flex align-items-center mb-3">
                <i class="bx bx-mask"></i>
                <span class="fw-medium mx-2">Anonymous:</span>
                <span class="badge bg-label-secondary">Yes</span>
              </li>

              <li class="d-flex align-items-center mb-3">
                <i class="bx bx-rename"></i>
                <span class="fw-medium mx-2">Nickname:</span>
                <span>Anon_3</span>
              </li>

              <li class="d-flex align-items-center mb-3">
                <i class="bx bx-crown"></i>
                <span class="fw-medium mx-2">Role:</span>
                <span>User</span>
              </li>

              <li class="d-flex align-items-center mb-0">
                <i class="bx bx-check"></i>
                <span class="fw-medium mx-2">Computed Subscription:</span>
                <span class="badge bg-label-warning">expiring</span>
              </li>
            </ul>

            <hr class="my-3" />

            <small class="card-text text-uppercase text-body-secondary">Contacts</small>
            <ul class="list-unstyled mt-3 mb-0">
              <li class="d-flex align-items-center mb-3">
                <i class="bx bx-envelope"></i>
                <span class="fw-medium mx-2">Email:</span>
                <span>anon3@test.com</span>
              </li>

              <li class="d-flex align-items-center mb-0">
                <i class="bx bxl-whatsapp"></i>
                <span class="fw-medium mx-2">WhatsApp:</span>
                <span>+44 7700 900123</span>
              </li>
            </ul>
          </div>
        </div>

        <!-- Account Actions (demo) -->
        <div class="card mb-4">
          <div class="card-body">
            <small class="card-text text-uppercase text-body-secondary">Admin Actions</small>

            <div class="d-flex flex-wrap gap-2 mt-3">
              <button type="button" class="btn btn-label-dark">
                <i class="bx bx-block me-1"></i> Block User
              </button>

              <button type="button" class="btn btn-label-success">
                <i class="bx bx-user-check me-1"></i> Mark Active
              </button>

              <button type="button" class="btn btn-label-danger">
                <i class="bx bx-trash me-1"></i> Delete
              </button>
            </div>

            <div class="text-muted small mt-3">
              Demo buttons only — you can wire these later.
            </div>
          </div>
        </div>
      </div>

      <!-- Right -->
      <div class="col-xl-8 col-lg-7 col-md-7">
        <!-- Subscription -->
        <div class="card mb-4">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0">Subscription</h5>
            <span class="badge bg-label-warning">Expiring Soon</span>
          </div>

          <div class="card-body">
            <div class="row g-3">
              <div class="col-md-4">
                <div class="p-3 rounded border">
                  <div class="text-muted small">Plan</div>
                  <div class="fw-semibold mt-1">Pro Monthly</div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="p-3 rounded border">
                  <div class="text-muted small">Starts</div>
                  <div class="fw-semibold mt-1">{{ formatDate('2026-01-01') }}</div>
                </div>
              </div>

              <div class="col-md-4">
                <div class="p-3 rounded border">
                  <div class="text-muted small">Expires</div>
                  <div class="fw-semibold mt-1">{{ formatDate('2026-02-01') }}</div>
                </div>
              </div>
            </div>

            <div class="alert alert-warning mt-4 mb-0">
              <i class="bx bx-error-circle me-1"></i>
              Demo notice: subscription expiring within 7 days.
            </div>
          </div>
        </div>

        <!-- Activity (simple demo, not full timeline) -->
        <div class="card mb-4">
          <div class="card-header">
            <h5 class="mb-0">Recent Activity</h5>
          </div>

          <div class="card-body">
            <ul class="list-group list-group-flush">
              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <div class="d-flex align-items-center gap-2">
                  <i class="bx bx-log-in-circle text-muted"></i>
                  <div>
                    <div class="fw-semibold">Logged in</div>
                    <div class="text-muted small">IP: 127.0.0.1 (demo)</div>
                  </div>
                </div>
                <small class="text-muted">2 hours ago</small>
              </li>

              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <div class="d-flex align-items-center gap-2">
                  <i class="bx bx-credit-card text-muted"></i>
                  <div>
                    <div class="fw-semibold">Subscription renewed</div>
                    <div class="text-muted small">Plan: Pro Monthly (demo)</div>
                  </div>
                </div>
                <small class="text-muted">3 days ago</small>
              </li>

              <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                <div class="d-flex align-items-center gap-2">
                  <i class="bx bx-user-check text-muted"></i>
                  <div>
                    <div class="fw-semibold">Account approved</div>
                    <div class="text-muted small">By Admin (demo)</div>
                  </div>
                </div>
                <small class="text-muted">1 week ago</small>
              </li>
            </ul>
          </div>
        </div>

        <!-- Notes (demo) -->
        <div class="card">
          <div class="card-header">
            <h5 class="mb-0">Notes</h5>
          </div>
          <div class="card-body">
            <p class="text-muted mb-0">
              Static demo notes section. Later you can attach admin notes / internal remarks to a user.
            </p>
          </div>
        </div>
      </div>
    </div>
    <!--/ Content -->
  </AdminLayout>
</template>
