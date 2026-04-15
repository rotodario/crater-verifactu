<template>
  <BasePage>
    <BasePageHeader title="VERI*FACTU Record">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Records" to="/admin/verifactu/records" />
        <BaseBreadcrumbItem title="Record" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <div v-if="loading" class="mt-6 text-sm text-gray-500">Cargando record...</div>
    <div v-else-if="!record" class="mt-6 text-sm text-gray-500">No se ha encontrado el record.</div>

    <div v-else class="grid grid-cols-1 gap-6 mt-6">
      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900">Resumen</h3>
            <div class="flex items-center gap-3">
              <router-link
                v-if="record.invoice_id"
                :to="`/admin/invoices/${record.invoice_id}/view`"
                class="px-3 py-2 text-sm font-medium text-primary-600 border border-primary-200 rounded-md hover:bg-primary-50"
              >
                Ver factura
              </router-link>
              <BaseBadge :bg-color="getStatusBadgeColor(record.status).bgColor" :color="getStatusBadgeColor(record.status).color">
                {{ record.status }}
              </BaseBadge>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
          <div><span class="font-medium text-gray-500">ID:</span> {{ record.id }}</div>
          <div>
            <span class="font-medium text-gray-500">Factura:</span>
            <router-link v-if="record.invoice_id" :to="`/admin/invoices/${record.invoice_id}/view`" class="text-primary-500">
              {{ record.invoice_number || '-' }}
            </router-link>
            <span v-else>{{ record.invoice_number || '-' }}</span>
          </div>
          <div><span class="font-medium text-gray-500">Cliente:</span> {{ record.customer_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Tipo:</span> {{ getKindLabel(record.invoice_kind) }}</div>
          <div><span class="font-medium text-gray-500">Emitido:</span> {{ record.issued_at || '-' }}</div>
          <div><span class="font-medium text-gray-500">Bloqueado:</span> {{ record.locked_at || '-' }}</div>
          <div class="md:col-span-2 xl:col-span-3 break-all"><span class="font-medium text-gray-500">Hash:</span> {{ record.hash || '-' }}</div>
          <div class="md:col-span-2 xl:col-span-3 break-all"><span class="font-medium text-gray-500">Hash anterior:</span> {{ record.previous_hash || '-' }}</div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Instalacion</h3></template>
        <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2" v-if="record.installation">
          <div><span class="font-medium text-gray-500">ID:</span> {{ record.installation.id }}</div>
          <div><span class="font-medium text-gray-500">Modo:</span> {{ record.installation.mode || '-' }}</div>
          <div><span class="font-medium text-gray-500">Entorno:</span> {{ record.installation.environment || '-' }}</div>
          <div><span class="font-medium text-gray-500">Software:</span> {{ record.installation.software_name || '-' }} {{ record.installation.software_version || '' }}</div>
        </div>
        <div v-else class="text-sm text-gray-500">Sin instalacion asociada.</div>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Submissions</h3></template>
        <div v-if="!submissions.length" class="text-sm text-gray-500">Sin submissions.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="py-3 pr-4 text-left text-gray-500">ID</th>
                <th class="py-3 pr-4 text-left text-gray-500">Estado</th>
                <th class="py-3 pr-4 text-left text-gray-500">Driver</th>
                <th class="py-3 pr-4 text-left text-gray-500">Referencia</th>
                <th class="py-3 pr-4 text-left text-gray-500">Fecha</th>
                <th class="py-3 text-left text-gray-500">Acciones</th>
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
                  <BaseBadge :bg-color="getStatusBadgeColor(submission.status).bgColor" :color="getStatusBadgeColor(submission.status).color">
                    {{ submission.status }}
                  </BaseBadge>
                </td>
                <td class="py-3 pr-4">{{ submission.driver || '-' }}</td>
                <td class="py-3 pr-4 break-all">{{ submission.external_reference || '-' }}</td>
                <td class="py-3 pr-4">{{ submission.completed_at || submission.submitted_at || submission.created_at || '-' }}</td>
                <td class="py-3">
                  <BaseButton
                    v-if="submission.status === 'FAILED'"
                    size="sm"
                    variant="primary-outline"
                    :disabled="retryingSubmissionIds.includes(submission.id)"
                    @click="retrySubmission(submission.id)"
                  >
                    {{ retryingSubmissionIds.includes(submission.id) ? 'Reintentando...' : 'Retry' }}
                  </BaseButton>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Eventos</h3></template>
        <div v-if="!events.length" class="text-sm text-gray-500">Sin eventos.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="py-3 pr-4 text-left text-gray-500">ID</th>
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
                <td class="py-3 pr-4">{{ event.event_type || '-' }}</td>
                <td class="py-3 pr-4">{{ event.event_code || '-' }}</td>
                <td class="py-3 pr-4">{{ shortMessage(event.message) }}</td>
                <td class="py-3">{{ event.created_at || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Snapshot JSON</h3></template>
        <pre class="p-4 overflow-auto text-xs leading-6 text-gray-800 bg-gray-50 rounded">{{ formatJson(record.snapshot) }}</pre>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">QR Payload JSON</h3></template>
        <pre class="p-4 overflow-auto text-xs leading-6 text-gray-800 bg-gray-50 rounded">{{ formatJson(record.qr_payload) }}</pre>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Metadata JSON</h3></template>
        <pre class="p-4 overflow-auto text-xs leading-6 text-gray-800 bg-gray-50 rounded">{{ formatJson(record.metadata) }}</pre>
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
import { useNotificationStore } from '@/scripts/stores/notification'

const route = useRoute()
const loading = ref(false)
const record = ref(null)
const submissions = ref([])
const events = ref([])
const retryingSubmissionIds = ref([])
const notificationStore = useNotificationStore()

async function loadRecord() {
  loading.value = true

  try {
    const response = await axios.get(`/api/v1/verifactu/records/${route.params.id}`)
    record.value = response.data.record || null
    submissions.value = response.data.submissions || []
    events.value = response.data.events || []
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

async function retrySubmission(submissionId) {
  if (retryingSubmissionIds.value.includes(submissionId)) {
    return
  }

  retryingSubmissionIds.value = [...retryingSubmissionIds.value, submissionId]

  try {
    await axios.post(`/api/v1/verifactu/submissions/${submissionId}/retry`)
    notificationStore.showNotification({
      type: 'success',
      message: 'VERI*FACTU submission queued for retry.',
    })
    await loadRecord()
  } catch (error) {
    handleError(error)
  } finally {
    retryingSubmissionIds.value = retryingSubmissionIds.value.filter((id) => id !== submissionId)
  }
}

function formatJson(value) {
  return JSON.stringify(value || {}, null, 2)
}

function getKindLabel(kind) {
  return kind === 'RECTIFICATIVE' ? 'Rectificativa' : 'Ordinaria'
}

function shortMessage(message) {
  if (!message) {
    return '-'
  }

  return message.length > 80 ? `${message.slice(0, 80)}...` : message
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
  loadRecord()
})
</script>
