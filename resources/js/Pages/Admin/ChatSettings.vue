<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ChatSettingsPanel from '@/Components/Chat/ChatSettingsPanel.vue'
import { Head } from '@inertiajs/vue3'
</script>

<template>
  <Head title="Chat Settings" />

  <AdminLayout>
    <div class="space-y-6">
      <div>
        <h1 class="text-lg font-bold text-slate-900">Chat settings</h1>
        <p class="text-sm text-slate-400 mt-0.5">
          Manage team chat access and features. Advanced features ship in v2.
        </p>
      </div>

      <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 sm:p-6">
        <ChatSettingsPanel />
      </div>
    </div>
  </AdminLayout>
</template>