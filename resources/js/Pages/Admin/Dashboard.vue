<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { Head } from '@inertiajs/vue3'
</script>

<template>
  <Head title="Admin Dashboard" />

  <AdminLayout>
    <p>Welcom to dashboard Role: {{ $page.props.auth.user.role }}</p>
  </AdminLayout>
</template>
