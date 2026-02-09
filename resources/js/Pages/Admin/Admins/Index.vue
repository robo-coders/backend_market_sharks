<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  admins: Array,
  filters: Object,
})

const search = ref(props.filters?.search ?? '')

// When user types, update URL + fetch fresh data from controller
let t = null
watch(search, (val) => {
  clearTimeout(t)
  t = setTimeout(() => {
    router.get(
      route('admin.admins.index'),
      { search: val || undefined },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }, 300)
})

const destroyAdmin = (id) => {
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

    router.delete(route('admin.admins.destroy', id), {
      onSuccess: () => {
        Swal.fire({
          title: 'Deleted',
          text: 'Admin deleted successfully.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        })
      },

      onError: () => {
        Swal.fire({
          title: 'Error',
          text: 'Could not delete admin.',
          icon: 'error',
        })
      },
    })
  })
}

const blockAdmin = (id) => {
  Swal.fire({
    title: 'Block admin?',
    text: "They will not be able to access the admin panel.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, block it!',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (result.isConfirmed === false) {
      return
    }

    router.patch(route('admin.admins.block', id), {}, {
      onSuccess: () => {
        Swal.fire({
          title: 'Blocked',
          text: 'Admin blocked successfully.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        })
      },

      onError: () => {
        Swal.fire({
          title: 'Error',
          text: 'Could not block admin.',
          icon: 'error',
        })
      },
    })
  })
}

const unblockAdmin = (id) => {
  Swal.fire({
    title: 'Unblock admin?',
    text: 'They will regain access to the admin panel.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, unblock',
    cancelButtonText: 'Cancel',
  }).then((result) => {
    if (result.isConfirmed === false) {
      return
    }

    router.patch(route('admin.admins.unblock', id), {}, {
      onSuccess: () => {
        Swal.fire({
          title: 'Unblocked',
          text: 'Admin unblocked successfully.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        })
      },
      onError: () => {
        Swal.fire({
          title: 'Error',
          text: 'Could not unblock admin.',
          icon: 'error',
        })
      },
    })
  })
}


const formatDateTime = (value) => {
  if (!value) return '—'
  return new Date(value).toLocaleString()
}

const statusBadge = (status) => {
  if (status === 'pending') return 'bg-label-warning'
  if (status === 'active') return 'bg-label-success'
  return 'bg-label-secondary'
}
</script>

<template>
  <Head title="Admins" />

  <AdminLayout>
    <div class="card">
      <!-- Header -->
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h5 class="mb-0">Admins</h5>
          <div class="text-muted small mt-1">Manage admin accounts</div>
        </div>

        <div class="d-flex align-items-center gap-2">
          <!-- Working Search -->
          <!-- <div class="input-group input-group-sm" style="width: 260px;"> -->
          <div class="input-group input-group-sm w-100 w-sm-auto" style="max-width: 260px;">

            <span class="input-group-text">
              <i class="bx bx-search"></i>
            </span>
            <input
              v-model="search"
              type="text"
              class="form-control"
              placeholder="Search admins..."
            />
          </div>

          <!-- Add admin -->
          <Link
            :href="route('admin.admins.create')"
            class="btn btn-primary btn-sm text-nowrap"
          >
            <i class="bx bx-plus me-1"></i>
            Add Admin
          </Link>
        </div>
      </div>

      <!-- Table -->
      <div class="table-responsive text-nowrap">
        <table class="table table-hover">
          <thead>
            <tr>
              <th style="width: 90px;">ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone / Whats App</th>
              <th style="width: 140px;">Status</th>
              <th style="width: 220px;">Created</th>
              <th style="width: 120px;">Actions</th>
            </tr>
          </thead>

          <tbody class="table-border-bottom-0">
            <!-- Empty state -->
            <tr v-if="admins.length === 0">
              <td colspan="7" class="text-center py-5">
                <div class="fw-semibold">No admins found</div>
                <div class="text-muted small mt-1">
                  Try a different search term.
                </div>
              </td>
            </tr>

            <!-- Rows -->
            <tr v-for="( admin, index) in admins" :key="admin.id">
              <td class="text-muted">{{ index + 1 }}</td>
              <td><strong>{{ admin.name }}</strong></td>
              <td>{{ admin.email }}</td>
              <td>{{ admin.whatsapp_number }}</td>

              <td>
                <span class="badge" :class="statusBadge(admin.status)">
                  {{ admin.status }}
                </span>
              </td>

              <td class="text-muted">
                {{ formatDateTime(admin.created_at) }}
              </td>

             <td>
              <div class="d-flex align-items-center gap-1">

                <Link
                  v-if="admin.status !== 'blocked'"
                  :href="route('admin.admins.edit', admin.id)"
                  class="btn btn-sm btn-icon"
                  title="Edit"
                >
                  <i class="bx bx-edit-alt"></i>
                </Link>
                <button
                  v-else
                  type="button"
                  class="btn btn-sm btn-icon"
                  title="Blocked admins can’t be edited"
                  disabled
                  style="opacity: 0.5; cursor: not-allowed;"
                >
                  <i class="bx bx-edit-alt"></i>
                </button>

                <button
                  v-if="admin.status !== 'blocked'"
                  class="btn btn-sm btn-icon text-black"
                  title="Block"
                  @click.prevent="blockAdmin(admin.id)"
                  >
                    <i class="bx bx-block"></i>
                </button>

                <button
                    v-else
                    class="btn btn-sm btn-icon text-info"
                    title="Unblock"
                    @click.prevent="unblockAdmin(admin.id)"
                  >
                    <i class="bx bx-check-circle"></i>
                </button>


                <button
                  class="btn btn-sm btn-icon text-danger"
                  title="Delete"
                  @click.prevent="destroyAdmin(admin.id)"
                >
                  <i class="bx bx-trash"></i>
                </button>
              </div>
            </td>

            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<style>

.btn.btn-icon:hover {
  background-color: rgba(0, 0, 0, 0.04) !important;
}

</style>