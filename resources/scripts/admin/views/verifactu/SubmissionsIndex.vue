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
                <div class="flex gap-2 flex-wrap">
                  <BaseButton
                    v-if="submission.status === 'FAILED'"
                    size="sm"
                    variant="primary-outline"
                    :disabled="retryingId === submission.id"
                    @click="retrySubmission(submission.id)"
                  >
                    {{ retryingId === submission.id ? 'Reintentando...' : 'Retry' }}
                  </BaseButton>
                  <BaseButton
                    v-if="submission.record_id && (submission.driver === 'aeat_sandbox' || submission.driver === 'aeat_production')"
                    size="sm"
                    variant="white"
                    :disabled="verifyingId === submission.record_id"
                    @click="verifyRecord(submission.record_id)"
                  >
                    {{ verifyingId === submission.record_id ? 'Consultando...' : 'Verificar AEAT' }}
                  </BaseButton>
                </div>
              </td>
              <td class="py-3">{{ submission.completed_at || submission.submitted_at || submission.created_at || '-' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Verify AEAT result panel -->
      <div v-if="verifyResult" class="mt-6 p-4 border border-gray-200 rounded-lg bg-gray-50">
        <div class="flex items-center justify-between mb-3">
          <h4 class="text-sm font-semibold text-gray-800">Resultado ConsultaFactuSistemaFacturacion — Record #{{ verifyResult.recordId }}</h4>
          <button class="text-gray-400 hover:text-gray-600 text-xs" @click="verifyResult = null">✕ Cerrar</button>
        </div>

        <div v-if="!verifyResult.result.found" class="text-sm text-yellow-700 bg-yellow-50 border border-yellow-200 rounded p-3">
          <strong>SinDatos</strong> — La AEAT no encontró este registro en el sistema de facturación.
          <span v-if="verifyResult.result.raw_error" class="block mt-1 text-xs">{{ verifyResult.result.raw_error }}</span>
        </div>

        <div v-else>
          <div v-for="(rec, idx) in verifyResult.result.records" :key="idx" class="mb-3 last:mb-0 p-3 bg-white border border-gray-200 rounded">
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm">
              <div>
                <span class="text-gray-500">Factura:</span>
                <span class="ml-1 font-medium text-gray-900">{{ rec.invoice_number || '-' }}</span>
              </div>
              <div>
                <span class="text-gray-500">Fecha:</span>
                <span class="ml-1 text-gray-700">{{ rec.invoice_date || '-' }}</span>
              </div>
              <div>
                <span class="text-gray-500">Estado AEAT:</span>
                <span class="ml-1 font-semibold" :class="rec.estado_registro === 'Correcto' ? 'text-green-700' : rec.estado_registro === 'Anulado' ? 'text-red-600' : 'text-yellow-700'">
                  {{ rec.estado_registro || '-' }}
                </span>
              </div>
              <div>
                <span class="text-gray-500">Hash match:</span>
                <span v-if="rec.hash_match === true" class="ml-1 text-green-700 font-semibold">✓ Coincide</span>
                <span v-else-if="rec.hash_match === false" class="ml-1 text-red-600 font-semibold">✗ No coincide</span>
                <span v-else class="ml-1 text-gray-400">-</span>
              </div>
              <div class="col-span-2">
                <span class="text-gray-500">Huella AEAT:</span>
                <span class="ml-1 font-mono text-xs text-gray-700">{{ rec.huella ? rec.huella.substring(0, 32) + '…' : '-' }}</span>
              </div>
              <div>
                <span class="text-gray-500">IdPetición:</span>
                <span class="ml-1 text-xs text-gray-600">{{ rec.id_peticion || '-' }}</span>
              </div>
              <div>
                <span class="text-gray-500">Timestamp presentación:</span>
                <span class="ml-1 text-xs text-gray-600">{{ rec.timestamp_presentacion || '-' }}</span>
              </div>
              <div v-if="rec.error_code" class="col-span-2 mt-1 text-xs text-red-700 bg-red-50 rounded px-2 py-1">
                Error {{ rec.error_code }}: {{ rec.error_desc }}
              </div>
            </div>
          </div>
        </div>

        <details class="mt-3 text-xs text-gray-500">
          <summary class="cursor-pointer select-none">Ver XML de consulta / respuesta</summary>
          <div class="mt-2 space-y-2">
            <div>
              <p class="font-medium text-gray-600 mb-1">Request XML:</p>
              <pre class="overflow-x-auto bg-white border border-gray-200 rounded p-2 text-xs leading-4">{{ verifyResult.request_xml }}</pre>
            </div>
            <div>
              <p class="font-medium text-gray-600 mb-1">Response XML:</p>
              <pre class="overflow-x-auto bg-white border border-gray-200 rounded p-2 text-xs leading-4">{{ verifyResult.response_xml }}</pre>
            </div>
          </div>
        </details>
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
const verifyingId = ref(null)
const verifyResult = ref(null)
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

async function verifyRecord(recordId) {
  verifyingId.value = recordId
  verifyResult.value = null

  try {
    const response = await axios.post(`/api/v1/verifactu/records/${recordId}/verify`)
    verifyResult.value = { recordId, ...response.data }
  } catch (error) {
    handleError(error)
  } finally {
    verifyingId.value = null
  }
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
