<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import Swal from 'sweetalert2'

const props = defineProps({
  teams: Array,
  filters: Object,
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

const destroyTeam = (id) => {
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

    router.delete(route('admin.teams.destroy', id), {
      onSuccess: () => {
        Swal.fire({
          title: 'Deleted',
          text: 'Team deleted successfully.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        })
      },

      onError: () => {
        Swal.fire({
          title: 'Error',
          text: 'Could not delete team.',
          icon: 'error',
        })
      },
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
    if (result.isConfirmed === false) {
      return
    }

    router.patch(route('admin.teams.block', id), {}, {
      onSuccess: () => {
        Swal.fire({
          title: 'Blocked',
          text: 'Team blocked successfully.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        })
      },

      onError: () => {
        Swal.fire({
          title: 'Error',
          text: 'Could not block team.',
          icon: 'error',
        })
      },
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
    if (result.isConfirmed === false) {
      return
    }

    router.patch(route('admin.teams.unblock', id), {}, {
      onSuccess: () => {
        Swal.fire({
          title: 'Unblocked',
          text: 'Team unblocked successfully.',
          icon: 'success',
          timer: 1500,
          showConfirmButton: false,
        })
      },
      onError: () => {
        Swal.fire({
          title: 'Error',
          text: 'Could not unblock team.',
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
  if (status === 'blocked') return 'bg-label-danger'
  return 'bg-label-secondary'
}
</script>

<template>
  <Head title="Teams" />

  <AdminLayout>
    <div class="card">
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
          <h5 class="mb-0">Teams</h5>
          <div class="text-muted small mt-1">Manage team accounts</div>
        </div>

        <div class="d-flex align-items-center gap-2">
          <div class="input-group input-group-sm w-100 w-sm-auto" style="max-width: 260px;">
            <span class="input-group-text">
              <i class="bx bx-search"></i>
            </span>
            <input
              v-model="search"
              type="text"
              class="form-control"
              placeholder="Search teams..."
            />
          </div>

          <Link
            :href="route('admin.teams.create')"
            class="btn btn-primary btn-sm text-nowrap"
          >
            <i class="bx bx-plus me-1"></i>
            Add Team
          </Link>
        </div>
      </div>

      <div class="table-responsive text-nowrap">
        <table class="table table-hover">
          <thead>
            <tr>
              <th style="width: 90px;">ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Phone / WhatsApp</th>
              <th style="width: 140px;">Status</th>
              <th style="width: 220px;">Created</th>
              <th style="width: 120px;">Actions</th>
            </tr>
          </thead>

          <tbody class="table-border-bottom-0">
            <tr v-if="teams.length === 0">
              <td colspan="7" class="text-center py-5">
                <div class="fw-semibold">No teams found</div>
                <div class="text-muted small mt-1">
                  Try a different search term.
                </div>
              </td>
            </tr>

            <tr v-for="(team, index) in teams" :key="team.id">
              <td class="text-muted">{{ index + 1 }}</td>
              <td><strong>{{ team.name }}</strong></td>
              <td>{{ team.email }}</td>
              <td>{{ team.whatsapp_number || '—' }}</td>

              <td>
                <span class="badge" :class="statusBadge(team.status)">
                  {{ team.status }}
                </span>
              </td>

              <td class="text-muted">
                {{ formatDateTime(team.created_at) }}
              </td>

              <td>
                <div class="d-flex align-items-center gap-1">
                  <Link
                    v-if="team.status !== 'blocked'"
                    :href="route('admin.teams.edit', team.id)"
                    class="btn btn-sm btn-icon"
                    title="Edit"
                  >
                    <i class="bx bx-edit-alt"></i>
                  </Link>

                  <button
                    v-else
                    type="button"
                    class="btn btn-sm btn-icon"
                    title="Blocked teams can’t be edited"
                    disabled
                    style="opacity: 0.5; cursor: not-allowed;"
                  >
                    <i class="bx bx-edit-alt"></i>
                  </button>

                  <button
                    v-if="team.status !== 'blocked'"
                    class="btn btn-sm btn-icon text-black"
                    title="Block"
                    @click.prevent="blockTeam(team.id)"
                  >
                    <i class="bx bx-block"></i>
                  </button>

                  <button
                    v-else
                    class="btn btn-sm btn-icon text-info"
                    title="Unblock"
                    @click.prevent="unblockTeam(team.id)"
                  >
                    <i class="bx bx-check-circle"></i>
                  </button>

                  <button
                    class="btn btn-sm btn-icon text-danger"
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
    </div>
  </AdminLayout>
</template>

<style>
.btn.btn-icon:hover {
  background-color: rgba(0, 0, 0, 0.04) !important;
}
</style>