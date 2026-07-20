<template>
  <span v-if="role" class="role-badge" :class="`role-badge--${slug}`">
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  role: { type: String, default: null },
})

const slug = computed(() => (props.role || '').replace(/[^a-z0-9]+/gi, '-').toLowerCase())

const label = computed(() => {
  const map = { super_admin: 'Super admin', admin: 'Admin', team: 'Team' }
  return map[props.role] || props.role
})
</script>

<style scoped>
/* Solid pastel pills (GitHub-label style). One design, readable on both
   light and dark canvases — no OS/theme detection needed. */
.role-badge {
  display: inline-flex;
  align-items: center;
  height: 18px;
  padding: 0 8px;
  border-radius: 999px;
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.02em;
  line-height: 1;
  white-space: nowrap;
}
.role-badge--super-admin { color: #5b21b6; background: #ede9fe; }
.role-badge--admin       { color: #115e59; background: #d7f2ee; }
.role-badge--team        { color: #854d0e; background: #fdf0d2; }
</style>