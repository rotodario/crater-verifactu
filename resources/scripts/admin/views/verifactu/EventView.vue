<template>
  <BasePage>
    <BasePageHeader title="VERI*FACTU Event">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Events" to="/admin/verifactu/events" />
        <BaseBreadcrumbItem title="Event" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <div v-if="loading" class="mt-6 text-sm text-gray-500">Cargando event...</div>
    <div v-else-if="!event" class="mt-6 text-sm text-gray-500">No se ha encontrado el event.</div>

    <div v-else class="grid grid-cols-1 gap-6 mt-6">
      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900">Resumen</h3>
            <BaseBadge bg-color="#E2E8F0" color="#2D3748">
              {{ event.event_type || '-' }}
            </BaseBadge>
          </div>
        </template>

        <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
          <div><span class="font-medium text-gray-500">ID:</span> {{ event.id }}</div>
          <div>
            <span class="font-medium text-gray-500">Record:</span>
            <router-link v-if="event.verifactu_record_id" :to="`/admin/verifactu/records/${event.verifactu_record_id}/view`" class="text-primary-500">
              {{ event.verifactu_record_id }}
            </router-link>
            <span v-else>-</span>
          </div>
          <div>
            <span class="font-medium text-gray-500">Factura:</span>
            <router-link v-if="event.invoice_id" :to="`/admin/invoices/${event.invoice_id}/view`" class="text-primary-500">
              {{ event.invoice_number || '-' }}
            </router-link>
            <span v-else>{{ event.invoice_number || '-' }}</span>
          </div>
          <div><span class="font-medium text-gray-500">Cliente:</span> {{ event.customer_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Codigo:</span> {{ event.event_code || '-' }}</div>
          <div><span class="font-medium text-gray-500">User ID:</span> {{ event.user_id || '-' }}</div>
          <div><span class="font-medium text-gray-500">Created at:</span> {{ event.created_at || '-' }}</div>
          <div><span class="font-medium text-gray-500">Updated at:</span> {{ event.updated_at || '-' }}</div>
          <div v-if="event.record" class="xl:col-span-3">
            <span class="font-medium text-gray-500">Record hash:</span>
            <span class="font-mono text-xs">{{ event.record.hash || '-' }}</span>
          </div>
          <div class="md:col-span-2 xl:col-span-3"><span class="font-medium text-gray-500">Mensaje:</span> {{ event.message || '-' }}</div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Context JSON</h3></template>
        <pre class="p-4 overflow-auto text-xs leading-6 text-gray-800 bg-gray-50 rounded">{{ formatJson(event.context) }}</pre>
      </BaseCard>
    </div>
  </BasePage>
</template>

<script setup>
import axios from 'axios'
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import SectionNav from '@/scripts/admin/views/verifactu/components/SectionNav.vue'
import { handleError } from '@/scripts/helpers/error-handling'

const route = useRoute()
const loading = ref(false)
const event = ref(null)

async function loadEvent() {
  loading.value = true

  try {
    const response = await axios.get(`/api/v1/verifactu/events/${route.params.id}`)
    event.value = response.data.event || null
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

function formatJson(value) {
  return JSON.stringify(value || {}, null, 2)
}

onMounted(() => {
  loadEvent()
})
</script>
