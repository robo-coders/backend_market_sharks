<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  users: Array,
  counts: Object,
})

const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString() : '—'

const isExpiringSoon = (expiresAt) => {
  if (!expiresAt) return false
  const days = (new Date(expiresAt) - new Date()) / (1000 * 60 * 60 * 24)
  return days >= 0 && days <= 7
}

const displayStatus = (user) => {
  if (user.status === 'pending')        return 'pending'
  if (user.status === 'payment_review') return 'payment_review'
  if (user.status === 'blocked')        return 'blocked'
  if (user.status === 'rejected')       return 'rejected'
  if (user.status === 'active') {
    if (user.subscription?.status === 'expired')       return 'expired'
    if (isExpiringSoon(user.subscription?.expires_at)) return 'expiring'
    return 'active'
  }
  return 'pending'
}

const activeFilter = ref('all')
const setFilter = (key) => (activeFilter.value = key)

const filteredUsers = computed(() => {
  if (activeFilter.value === 'all') return props.users
  return props.users.filter((u) => displayStatus(u) === activeFilter.value)
})

const approveUser = (user) => {
  router.patch(route('admin.users.approve', user.id))
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
    if (result.isConfirmed === false) return
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
    <div class="card">
      <!-- Header + Filters -->
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <h5 class="mb-0 me-3">Users</h5>

        <ul class="nav nav-pills">
          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'all' }" @click="setFilter('all')">
              All <span class="badge rounded-pill bg-label-secondary ms-2">{{ props.counts.all }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'pending' }" @click="setFilter('pending')">
              Pending <span class="badge rounded-pill bg-label-secondary ms-2">{{ props.counts.pending }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'payment_review' }" @click="setFilter('payment_review')">
              Payment Review <span class="badge rounded-pill bg-label-info ms-2">{{ props.counts.payment_review }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'active' }" @click="setFilter('active')">
              Active <span class="badge rounded-pill bg-label-success ms-2">{{ props.counts.active }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'expiring' }" @click="setFilter('expiring')">
              Expiring Soon <span class="badge rounded-pill bg-label-warning ms-2">{{ props.counts.expiring }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'expired' }" @click="setFilter('expired')">
              Expired <span class="badge rounded-pill bg-label-danger ms-2">{{ props.counts.expired }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'blocked' }" @click="setFilter('blocked')">
              Blocked <span class="badge rounded-pill bg-label-dark ms-2">{{ props.counts.blocked }}</span>
            </button>
          </li>
          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'rejected' }" @click="setFilter('rejected')">
              Rejected <span class="badge rounded-pill bg-label-danger ms-2">{{ props.counts.rejected }}</span>
            </button>
          </li>
        </ul>
      </div>

      <!-- Table -->
      <div class="table-responsive text-nowrap">
        <table class="table table-hover">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Plan</th>
              <th>Status</th>
              <th>Expires</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody class="table-border-bottom-0">
            <tr v-if="filteredUsers.length === 0">
              <td colspan="7" class="text-center py-5">
                <div class="fw-semibold">No users found</div>
                <div class="text-muted small mt-1">No users match this filter yet.</div>
              </td>
            </tr>

            <tr
              v-else
              v-for="user in filteredUsers"
              :key="user.id"
              :class="{ 'table-danger': displayStatus(user) === 'expired' }"
            >
              <!-- Name -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <strong>{{ user.display_name }}</strong>
                  <i v-if="user.is_anonymous" class="bx bx-mask text-muted" title="Anonymous user"></i>
                </div>
              </td>

              <!-- Email -->
              <td>{{ user.email }}</td>

              <!-- Plan -->
              <td>
                <template v-if="user.status === 'active' && user.subscription?.plan">
                  <span class="fw-semibold text-capitalize">{{ user.subscription.plan }}</span>
                </template>
                <template v-else-if="user.status === 'payment_review' && user.payment_request?.plan">
                  <div class="d-flex align-items-center gap-2">
                    <span class="bg-warning rounded-circle d-inline-block flex-shrink-0" style="width:8px; height:8px;"></span>
                    <span class="fw-semibold text-capitalize">{{ user.payment_request.plan }}</span>
                  </div>
                </template>
                <template v-else>
                  <span class="text-muted">—</span>
                </template>
              </td>

              <!-- Status -->
              <td>
                <span v-if="displayStatus(user) === 'pending'"             class="badge bg-label-secondary">Pending</span>
                <span v-else-if="displayStatus(user) === 'payment_review'" class="badge bg-label-info">Payment Review</span>
                <span v-else-if="displayStatus(user) === 'blocked'"        class="badge bg-label-dark">Blocked</span>
                <span v-else-if="displayStatus(user) === 'rejected'"       class="badge bg-label-danger">Rejected</span>
                <span v-else-if="displayStatus(user) === 'expired'"        class="badge bg-label-danger">Expired</span>
                <span v-else-if="displayStatus(user) === 'expiring'"       class="badge bg-label-warning">Expiring Soon</span>
                <span v-else class="badge bg-label-success">Active</span>
              </td>

              <!-- Expires -->
              <td>
                <span v-if="user.status === 'active' && user.subscription?.expires_at">
                  {{ formatDate(user.subscription.expires_at) }}
                </span>
                <span v-else class="text-muted">—</span>
              </td>

              <!-- Actions -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <a :href="route('admin.users.show', user.id)" class="btn btn-sm btn-icon" title="View">
                    <i class="bx bx-show"></i>
                  </a>

                  <div class="dropdown">
                    <button
                      type="button"
                      class="btn btn-sm btn-icon dropdown-toggle hide-arrow"
                      data-bs-toggle="dropdown"
                    >
                      <i class="bx bx-dots-vertical-rounded"></i>
                    </button>

                    <div class="dropdown-menu dropdown-menu-end">
                      <button
                        v-if="user.status === 'payment_review'"
                        class="dropdown-item"
                        @click.prevent="approveUser(user)"
                      >
                        <i class="bx bx-check me-1"></i> Approve
                      </button>

                      <button class="dropdown-item">
                        <i class="bx bx-edit-alt me-1"></i> Edit
                      </button>

                      <button class="dropdown-item text-danger" @click.prevent="destroyUser(user.id)">
                        <i class="bx bx-trash me-1"></i> Delete
                      </button>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>