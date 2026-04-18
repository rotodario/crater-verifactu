<template>
  <BaseCard container-class="px-4 py-4 mt-6">
    <div class="flex flex-wrap gap-3">
      <router-link
        v-for="item in items"
        :key="item.to"
        :to="item.to"
        class="px-4 py-2 text-sm font-medium rounded-md border transition-colors"
        :class="isActive(item.matcher)
          ? 'border-primary-500 bg-primary-50 text-primary-600'
          : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300 hover:text-gray-900'"
      >
        {{ item.label }}
      </router-link>
    </div>
  </BaseCard>
</template>

<script setup>
import { useRoute } from 'vue-router'

const route = useRoute()

const items = [
  { label: 'Dashboard', to: '/admin/verifactu', matcher: '/admin/verifactu' },
  { label: 'Records', to: '/admin/verifactu/records', matcher: '/admin/verifactu/records' },
  { label: 'Submissions', to: '/admin/verifactu/submissions', matcher: '/admin/verifactu/submissions' },
  { label: 'Events', to: '/admin/verifactu/events', matcher: '/admin/verifactu/events' },
  { label: 'Setup', to: '/admin/verifactu/setup', matcher: '/admin/verifactu/setup' },
  { label: 'Historial AEAT', to: '/admin/verifactu/historial-aeat', matcher: '/admin/verifactu/historial-aeat' },
  { label: 'Reconciliación AEAT', to: '/admin/verifactu/reconciliacion-aeat', matcher: '/admin/verifactu/reconciliacion-aeat' },
]

function isActive(matcher) {
  if (matcher === '/admin/verifactu') {
    return route.path === matcher
  }

  return route.path.startsWith(matcher)
}
</script>
