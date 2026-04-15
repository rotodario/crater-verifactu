<template>
  <BasePage>
    <BasePageHeader :title="$t('verifactu.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Events" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <BaseCard container-class="px-5 py-5 mt-6">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Events</h3>
          <p class="mt-1 text-sm text-gray-500">Listado tecnico de eventos de trazabilidad VERI*FACTU.</p>
        </div>
        <BaseButton variant="primary-outline" @click="loadEvents">
          <template #left="slotProps">
            <BaseIcon name="RefreshIcon" :class="slotProps.class" />
          </template>
          {{ $t('general.retry') }}
        </BaseButton>
      </div>

      <div class="grid grid-cols-1 gap-4 mt-5 md:grid-cols-2 xl:grid-cols-4">
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">Tipo</label>
          <input v-model="filters.event_type" type="text" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md" placeholder="ISSUE, SUBMISSION_ACCEPTED..." />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">Desde</label>
          <input v-model="filters.date_from" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">Hasta</label>
          <input v-model="filters.date_to" type="date" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md" />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">Limite</label>
          <select v-model="filters.limit" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="200">200</option>
          </select>
        </div>
      </div>

      <div class="flex flex-wrap gap-3 mt-4">
        <BaseButton variant="primary" @click="loadEvents">Aplicar filtros</BaseButton>
        <BaseButton variant="primary-outline" @click="resetFilters">Limpiar</BaseButton>
      </div>

      <div v-if="loading" class="mt-4 text-sm text-gray-500">Cargando events...</div>
      <div v-else-if="!events.length" class="mt-4 text-sm text-gray-500">No hay events disponibles.</div>

      <div v-else class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200">
              <th class="py-3 pr-4 text-left text-gray-500">ID</th>
              <th class="py-3 pr-4 text-left text-gray-500">Factura</th>
              <th class="py-3 pr-4 text-left text-gray-500">Cliente</th>
              <th class="py-3 pr-4 text-left text-gray-500">Record</th>
              <th class="py-3 pr-4 text-left text-gray-500">Tipo</th>
              <th class="py-3 pr-4 text-left text-gray-500">Codigo</th>
              <th class="py-3 pr-4 text-left text-gray-500">Mensaje</th>
              <th class="py-3 text-left text-gray-500">Fecha</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="event in events" :key="event.id" class="border-b border-gray-100 last:border-b-0">
              <td class="py-3 pr-4">
                <router-link :to="`/admin/verifactu/events/${event.id}/view`" class="text-primary-500">
                  {{ event.id }}
                </router-link>
              </td>
              <td class="py-3 pr-4">
                <router-link v-if="event.invoice_id" :to="`/admin/invoices/${event.invoice_id}/view`" class="text-primary-500">
                  {{ event.invoice_number || '-' }}
                </router-link>
                <span v-else>{{ event.invoice_number || '-' }}</span>
              </td>
              <td class="py-3 pr-4">{{ event.customer_name || '-' }}</td>
              <td class="py-3 pr-4">
                <router-link v-if="event.record_id" :to="`/admin/verifactu/records/${event.record_id}/view`" class="text-primary-500">
                  {{ event.record_id }}
                </router-link>
                <span v-else>-</span>
              </td>
              <td class="py-3 pr-4">{{ event.event_type || '-' }}</td>
              <td class="py-3 pr-4">{{ event.event_code || '-' }}</td>
              <td class="py-3 pr-4">{{ shortMessage(event.message) }}</td>
              <td class="py-3">{{ event.created_at || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </BaseCard>
  </BasePage>
</template>

<script setup>
import axios from 'axios'
import { onMounted, ref } from 'vue'
import { handleError } from '@/scripts/helpers/error-handling'
import SectionNav from '@/scripts/admin/views/verifactu/components/SectionNav.vue'

const loading = ref(false)
const events = ref([])
const filters = ref({
  event_type: '',
  date_from: '',
  date_to: '',
  limit: 50,
})

async function loadEvents() {
  loading.value = true

  try {
    const response = await axios.get('/api/v1/verifactu/events', {
      params: filters.value,
    })
    events.value = response.data.events || []
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = {
    event_type: '',
    date_from: '',
    date_to: '',
    limit: 50,
  }

  loadEvents()
}

function shortMessage(message) {
  if (!message) {
    return '-'
  }

  return message.length > 80 ? `${message.slice(0, 80)}...` : message
}

onMounted(() => {
  loadEvents()
})
</script>
