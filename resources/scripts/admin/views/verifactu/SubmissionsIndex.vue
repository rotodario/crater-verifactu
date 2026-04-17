<template>
  <BasePage>
    <BasePageHeader :title="$t('verifactu.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Submissions" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <BaseCard container-class="px-5 py-5 mt-6">
      <div class="flex items-center justify-between">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">Submissions</h3>
          <p class="mt-1 text-sm text-gray-500">Listado técnico de intentos de envío y simulación VERI*FACTU.</p>
        </div>
        <BaseButton variant="primary-outline" @click="loadSubmissions">
          <template #left="slotProps">
            <BaseIcon name="RefreshIcon" :class="slotProps.class" />
          </template>
          {{ $t('general.retry') }}
        </BaseButton>
      </div>

      <div class="grid grid-cols-1 gap-4 mt-5 md:grid-cols-2 xl:grid-cols-4">
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">Estado</label>
          <select v-model="filters.status" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
            <option value="">Todos</option>
            <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
          </select>
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
          <label class="block mb-1 text-sm font-medium text-gray-600">Límite</label>
          <select v-model="filters.limit" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md">
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="200">200</option>
          </select>
        </div>
      </div>

      <div class="flex flex-wrap gap-3 mt-4">
        <BaseButton variant="primary" @click="loadSubmissions">Aplicar filtros</BaseButton>
        <BaseButton variant="primary-outline" @click="resetFilters">Limpiar</BaseButton>
      </div>

      <div v-if="loading" class="mt-4 text-sm text-gray-500">Cargando submissions...</div>
      <div v-else-if="!submissions.length" class="mt-4 text-sm text-gray-500">No hay submissions disponibles.</div>

      <div v-else class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead>
            <tr class="border-b border-gray-200">
              <th class="py-3 pr-4 text-left text-gray-500">ID</th>
              <th class="py-3 pr-4 text-left text-gray-500">Record</th>
              <th class="py-3 pr-4 text-left text-gray-500">Factura</th>
              <th class="py-3 pr-4 text-left text-gray-500">Cliente</th>
              <th class="py-3 pr-4 text-left text-gray-500">Estado</th>
              <th class="py-3 pr-4 text-left text-gray-500">Driver</th>
              <th class="py-3 pr-4 text-left text-gray-500">CSV</th>
              <th class="py-3 pr-4 text-left text-gray-500">Acciones</th>
              <th class="py-3 text-left text-gray-500">Fecha</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="submission in submissions" :key="submission.id" class="border-b border-gray-100 last:border-b-0">
              <td class="py-3 pr-4">
                <router-link :to="`/admin/verifactu/submissions/${submission.id}/view`" class="text-primary-500">
                  {{ submission.id }}
                </router-link>
              </td>
              <td class="py-3 pr-4">
                <router-link v-if="submission.record_id" :to="`/admin/verifactu/records/${submission.record_id}/view`" class="text-primary-500">
                  {{ submission.record_id }}
                </router-link>
                <span v-else>-</span>
              </td>
              <td class="py-3 pr-4">
                <router-link v-if="submission.invoice_id" :to="`/admin/invoices/${submission.invoice_id}/view`" class="text-primary-500">
                  {{ submission.invoice_number || '-' }}
                </router-link>
                <span v-else>{{ submission.invoice_number || '-' }}</span>
              </td>
              <td class="py-3 pr-4">{{ submission.customer_name || '-' }}</td>
              <td class="py-3 pr-4">
                <BaseBadge :bg-color="getStatusBadgeColor(submission.status).bgColor" :color="getStatusBadgeColor(submission.status).color">
                  {{ submission.status }}
                </BaseBadge>
              </td>
              <td class="py-3 pr-4">{{ submission.driver || '-' }}</td>
              <td class="py-3 pr-4 font-mono text-xs text-green-700 font-semibold">{{ submission.csv || '-' }}</td>
              <td class="py-3 pr-4">
                <BaseButton
                  v-if="submission.status === 'FAILED'"
                  size="sm"
                  variant="primary-outline"
                  :disabled="retryingId === submission.id"
                  @click="retrySubmission(submission.id)"
                >
                  {{ retryingId === submission.id ? 'Reintentando...' : 'Retry' }}
                </BaseButton>
                <span v-else class="text-xs text-gray-400">-</span>
              </td>
              <td class="py-3">{{ submission.completed_at || submission.submitted_at || submission.created_at || '-' }}</td>
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
import { useNotificationStore } from '@/scripts/stores/notification'
import SectionNav from '@/scripts/admin/views/verifactu/components/SectionNav.vue'

const loading = ref(false)
const retryingId = ref(null)
const submissions = ref([])
const notificationStore = useNotificationStore()
const statusOptions = ['PENDING', 'PROCESSING', 'ACCEPTED', 'FAILED', 'REJECTED']
const filters = ref({
  status: '',
  date_from: '',
  date_to: '',
  limit: 50,
})

async function loadSubmissions() {
  loading.value = true

  try {
    const response = await axios.get('/api/v1/verifactu/submissions', {
      params: filters.value,
    })
    submissions.value = response.data.submissions || []
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

async function retrySubmission(id) {
  retryingId.value = id

  try {
    await axios.post(`/api/v1/verifactu/submissions/${id}/retry`)
    notificationStore.showNotification({
      type: 'success',
      message: 'VERI*FACTU submission queued for retry.',
    })
    await loadSubmissions()
  } catch (error) {
    handleError(error)
  } finally {
    retryingId.value = null
  }
}

function resetFilters() {
  filters.value = {
    status: '',
    date_from: '',
    date_to: '',
    limit: 50,
  }

  loadSubmissions()
}

function getStatusBadgeColor(status) {
  switch (status) {
    case 'ACCEPTED':
      return { bgColor: '#D5EED0', color: '#276749' }
    case 'ISSUED':
    case 'SUBMITTED':
    case 'PROCESSING':
      return { bgColor: '#C9E3EC', color: '#2C5282' }
    case 'FAILED':
    case 'REJECTED':
      return { bgColor: '#FED7D7', color: '#C53030' }
    default:
      return { bgColor: '#F8EDCB', color: '#744210' }
  }
}

onMounted(() => {
  loadSubmissions()
})
</script>
