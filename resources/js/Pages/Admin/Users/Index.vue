<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import Swal from 'sweetalert2'


/* props */
const props = defineProps({
  users: Array,
  counts: Object, // { all, pending, active, expiring, expired, blocked }
})

/* filters */
const activeFilter = ref('all')
const setFilter = (key) => (activeFilter.value = key)

const filteredUsers = computed(() => {
  if (activeFilter.value === 'all') return props.users
  return props.users.filter(
    (u) => u.subscription_status === activeFilter.value
  )
})

/* helpers */
const formatDate = (value) =>
  value ? new Date(value).toLocaleDateString() : '—'

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
    if (result.isConfirmed === false) {
      return
    }

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
        Swal.fire({
          title: 'Error',
          text: 'Could not delete user.',
          icon: 'error',
        })
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
              All
              <span class="badge rounded-pill bg-label-secondary ms-2">
                {{ props.counts.all }}
              </span>
            </button>
          </li>

          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'pending' }" @click="setFilter('pending')">
              Pending
              <span class="badge rounded-pill bg-label-secondary ms-2">
                {{ props.counts.pending }}
              </span>
            </button>
          </li>

          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'active' }" @click="setFilter('active')">
              Active
              <span class="badge rounded-pill bg-label-success ms-2">
                {{ props.counts.active }}
              </span>
            </button>
          </li>

          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'expiring' }" @click="setFilter('expiring')">
              Expiring Soon
              <span class="badge rounded-pill bg-label-warning ms-2">
                {{ props.counts.expiring }}
              </span>
            </button>
          </li>

          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'expired' }" @click="setFilter('expired')">
              Expired
              <span class="badge rounded-pill bg-label-danger ms-2">
                {{ props.counts.expired }}
              </span>
            </button>
          </li>

          <li class="nav-item">
            <button class="nav-link" :class="{ active: activeFilter === 'blocked' }" @click="setFilter('blocked')">
              Blocked
              <span class="badge rounded-pill bg-label-dark ms-2">
                {{ props.counts.blocked }}
              </span>
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
              <th>Picture</th>
              <th>Plan</th>
              <th>Status</th>
              <th>Expires</th>
              <th>Actions</th>
            </tr>
          </thead>

          <tbody class="table-border-bottom-0">
            <!-- Empty state -->
            <tr v-if="filteredUsers.length === 0">
              <td colspan="7" class="text-center py-5">
                <div class="fw-semibold">No users found</div>
                <div class="text-muted small mt-1">
                  No users match this filter yet.
                </div>
              </td>
            </tr>

            <!-- Rows -->
            <tr
              v-else
              v-for="user in filteredUsers"
              :key="user.id"
              :class="{ 'table-danger': user.subscription_status === 'expired' }"
            >
              <td>
                <div class="d-flex align-items-center gap-2">
                  <strong>{{ user.display_name }}</strong>

                  <i
                    v-if="user.is_anonymous"
                    class="bx bx-mask text-muted"
                    title="Anonymous user"
                  ></i>
                </div>
              </td>

              <td>{{ user.email }}</td>

              <td>
                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                  <li class="avatar avatar-xs pull-up">
                    <img
                      src="/admin/assets/img/avatars/5.png"
                      class="rounded-circle"
                      alt="Avatar"
                    />
                  </li>
                </ul>
              </td>

              <td>{{ user.subscription?.plan ?? '-' }}</td>

              <td>
                <span v-if="user.subscription_status === 'pending'" class="badge bg-label-secondary">Pending</span>
                <span v-else-if="user.subscription_status === 'blocked'" class="badge bg-label-dark">Blocked</span>
                <span v-else-if="user.subscription_status === 'expired'" class="badge bg-label-danger">Expired</span>
                <span v-else-if="user.subscription_status === 'expiring'" class="badge bg-label-warning">Expiring Soon</span>
                <span v-else class="badge bg-label-success">Active</span>
              </td>

              <td>{{ formatDate(user.subscription?.expires_at) }}</td>

              <td>
                <div class="d-flex align-items-center gap-2">
                  <!-- View (primary action) -->
                  <a
                    :href="route('admin.users.show', user.id)"
                    class="btn btn-sm btn-icon"
                    title="View"
                  >
                    <i class="bx bx-show"></i>
                  </a>

                  <!-- More actions -->
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
                        v-if="user.subscription_status === 'pending'"
                        class="dropdown-item"
                        @click.prevent="approveUser(user)"
                      >
                        <i class="bx bx-check me-1"></i> Approve
                      </button>

                      <button class="dropdown-item">
                        <i class="bx bx-edit-alt me-1"></i> Edit
                      </button>

                      <button class="dropdown-item text-danger"
                        @click.prevent="destroyUser(user.id)"
                      >
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
