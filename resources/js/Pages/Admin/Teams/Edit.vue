<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import Swal from 'sweetalert2'

const props = defineProps({
  team: Object,
})

const form = useForm({
  name: props.team.name,
  email: props.team.email,
  whatsapp_number: props.team.whatsapp_number ?? '',
})

const submit = () => {
  form.put(route('admin.teams.update', props.team.id), {
    onSuccess: () => {
      Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'success',
        title: 'Team updated successfully',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
      })
    },
  })
}
</script>

<template>
  <Head title="Edit Team" />

  <AdminLayout>
    <div class="card mb-4">
      <h5 class="card-header d-flex align-items-center justify-content-between">
        <span>Edit Team</span>

        <Link
          :href="route('admin.teams.index')"
          class="text-muted d-inline-flex align-items-center gap-1"
        >
          <i class="bx bx-arrow-back"></i>
          Back to Teams
        </Link>
      </h5>

      <div class="card-body">
        <form @submit.prevent="submit">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input
              v-model="form.name"
              type="text"
              class="form-control"
            />
            <div v-if="form.errors.name" class="text-danger small mt-1">
              {{ form.errors.name }}
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input
              v-model="form.email"
              type="email"
              class="form-control"
            />
            <div v-if="form.errors.email" class="text-danger small mt-1">
              {{ form.errors.email }}
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Phone number (optional)</label>
            <input
              v-model="form.whatsapp_number"
              type="tel"
              class="form-control"
              placeholder="+971 50 123 4567"
            />
            <div v-if="form.errors.whatsapp_number" class="text-danger small mt-1">
              {{ form.errors.whatsapp_number }}
            </div>
          </div>

          <div class="d-flex gap-2">
            <button
              class="btn btn-primary"
              type="submit"
              :disabled="form.processing"
            >
              <span v-if="form.processing">Saving…</span>
              <span v-else>Save Changes</span>
            </button>

            <Link
              :href="route('admin.teams.index')"
              class="btn btn-outline-secondary"
            >
              Cancel
            </Link>
          </div>
        </form>
      </div>
    </div>
  </AdminLayout>
</template>