<template>
  <BasePage>
    <BasePageHeader title="VERI*FACTU Submission">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Submissions" to="/admin/verifactu/submissions" />
        <BaseBreadcrumbItem title="Submission" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <div v-if="loading" class="mt-6 text-sm text-gray-500">Cargando submission...</div>
    <div v-else-if="!submission" class="mt-6 text-sm text-gray-500">No se ha encontrado la submission.</div>

    <div v-else class="grid grid-cols-1 gap-6 mt-6">

      <!-- ── Resumen ──────────────────────────────────────────────── -->
      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between gap-4">
            <h3 class="text-lg font-semibold text-gray-900">Resumen</h3>
            <div class="flex items-center gap-3">
              <BaseButton
                v-if="submission.status === 'FAILED'"
                size="sm"
                variant="primary-outline"
                :disabled="retrying"
                @click="retrySubmission()"
              >
                {{ retrying ? 'Reintentando...' : 'Retry' }}
              </BaseButton>
              <BaseBadge
                :bg-color="statusColor(submission.status).bgColor"
                :color="statusColor(submission.status).color"
              >
                {{ submission.status }}
              </BaseBadge>
            </div>
          </div>
        </template>

        <!-- CSV destacado cuando existe -->
        <div
          v-if="submission.csv"
          class="flex items-center gap-3 px-4 py-3 mb-4 rounded-lg bg-green-50 border border-green-200"
        >
          <span class="text-xs font-semibold uppercase tracking-wide text-green-700">CSV AEAT</span>
          <span class="font-mono text-sm font-bold text-green-900 select-all">{{ submission.csv }}</span>
          <span class="text-xs text-green-600 ml-auto">Código Seguro de Verificación</span>
        </div>

        <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
          <div><span class="font-medium text-gray-500">ID:</span> {{ submission.id }}</div>
          <div>
            <span class="font-medium text-gray-500">Record:</span>
            <router-link
              v-if="submission.verifactu_record_id"
              :to="`/admin/verifactu/records/${submission.verifactu_record_id}/view`"
              class="text-primary-500"
            >
              {{ submission.verifactu_record_id }}
            </router-link>
            <span v-else>-</span>
          </div>
          <div>
            <span class="font-medium text-gray-500">Factura:</span>
            <router-link
              v-if="submission.invoice_id"
              :to="`/admin/invoices/${submission.invoice_id}/view`"
              class="text-primary-500"
            >
              {{ submission.invoice_number || '-' }}
            </router-link>
            <span v-else>{{ submission.invoice_number || '-' }}</span>
          </div>
          <div><span class="font-medium text-gray-500">Cliente:</span> {{ submission.customer_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Driver:</span> {{ submission.driver || '-' }}</div>
          <div><span class="font-medium text-gray-500">Referencia:</span> {{ submission.external_reference || '-' }}</div>
          <div><span class="font-medium text-gray-500">Intento nº:</span> {{ submission.attempt || '-' }}</div>
          <div v-if="submission.error_message" class="md:col-span-2 xl:col-span-3">
            <span class="font-medium text-red-500">Error:</span>
            <span class="text-red-700">{{ submission.error_message }}</span>
          </div>
          <div><span class="font-medium text-gray-500">Enviado:</span> {{ submission.submitted_at || '-' }}</div>
          <div><span class="font-medium text-gray-500">Completado:</span> {{ submission.completed_at || '-' }}</div>
          <div><span class="font-medium text-gray-500">Actualizado:</span> {{ submission.updated_at || '-' }}</div>
        </div>
      </BaseCard>

      <!-- ── XML enviado a la AEAT ───────────────────────────────── -->
      <BaseCard v-if="submission.request_xml">
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">XML enviado a AEAT</h3>
            <BaseButton size="sm" variant="primary-outline" @click="showRequestXml = !showRequestXml">
              {{ showRequestXml ? 'Ocultar' : 'Mostrar' }}
            </BaseButton>
          </div>
        </template>
        <div v-if="showRequestXml">
          <div class="flex justify-end mb-2">
            <BaseButton size="sm" variant="primary-outline" @click="copyToClipboard(submission.request_xml)">
              Copiar
            </BaseButton>
          </div>
          <pre class="p-4 overflow-auto text-xs leading-5 text-gray-800 bg-gray-50 rounded max-h-96 border border-gray-200">{{ submission.request_xml }}</pre>
        </div>
        <div v-else class="text-sm text-gray-400 italic">Pulsa "Mostrar" para ver el XML SOAP enviado.</div>
      </BaseCard>

      <!-- ── XML de respuesta AEAT ───────────────────────────────── -->
      <BaseCard v-if="submission.response_xml">
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Respuesta AEAT</h3>
            <BaseButton size="sm" variant="primary-outline" @click="showResponseXml = !showResponseXml">
              {{ showResponseXml ? 'Ocultar' : 'Mostrar' }}
            </BaseButton>
          </div>
        </template>
        <div v-if="showResponseXml">
          <div class="flex justify-end mb-2">
            <BaseButton size="sm" variant="primary-outline" @click="copyToClipboard(submission.response_xml)">
              Copiar
            </BaseButton>
          </div>
          <pre class="p-4 overflow-auto text-xs leading-5 text-gray-800 bg-gray-50 rounded max-h-96 border border-gray-200">{{ submission.response_xml }}</pre>
        </div>
        <div v-else class="text-sm text-gray-400 italic">Pulsa "Mostrar" para ver la respuesta recibida.</div>
      </BaseCard>

      <!-- ── Payloads JSON (debug) ──────────────────────────────── -->
      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Payload interno (debug)</h3>
            <BaseButton size="sm" variant="primary-outline" @click="showJson = !showJson">
              {{ showJson ? 'Ocultar' : 'Mostrar' }}
            </BaseButton>
          </div>
        </template>
        <div v-if="showJson" class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <div>
            <p class="mb-1 text-xs font-semibold text-gray-500 uppercase">Request</p>
            <pre class="p-3 overflow-auto text-xs leading-5 text-gray-800 bg-gray-50 rounded border border-gray-200 max-h-64">{{ formatJson(submission.request_payload) }}</pre>
          </div>
          <div>
            <p class="mb-1 text-xs font-semibold text-gray-500 uppercase">Response</p>
            <pre class="p-3 overflow-auto text-xs leading-5 text-gray-800 bg-gray-50 rounded border border-gray-200 max-h-64">{{ formatJson(submission.response_payload) }}</pre>
          </div>
        </div>
        <div v-else class="text-sm text-gray-400 italic">Pulsa "Mostrar" para ver los payloads JSON internos.</div>
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
const retrying = ref(false)
const submission = ref(null)
const notificationStore = useNotificationStore()

const showRequestXml  = ref(false)
const showResponseXml = ref(false)
const showJson        = ref(false)

async function loadSubmission() {
  loading.value = true
  try {
    const response = await axios.get(`/api/v1/verifactu/submissions/${route.params.id}`)
    submission.value = response.data.submission || null
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

async function retrySubmission() {
  if (!submission.value) return
  retrying.value = true
  try {
    await axios.post(`/api/v1/verifactu/submissions/${submission.value.id}/retry`)
    notificationStore.showNotification({ type: 'success', message: 'Submission encolada para reintento.' })
    await loadSubmission()
  } catch (error) {
    handleError(error)
  } finally {
    retrying.value = false
  }
}

async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text)
    notificationStore.showNotification({ type: 'success', message: 'Copiado al portapapeles.' })
  } catch {
    notificationStore.showNotification({ type: 'error', message: 'No se pudo copiar.' })
  }
}

function formatJson(value) {
  return JSON.stringify(value || {}, null, 2)
}

function statusColor(status) {
  switch (status) {
    case 'ACCEPTED':  return { bgColor: '#D5EED0', color: '#276749' }
    case 'PROCESSING':
    case 'SUBMITTED': return { bgColor: '#C9E3EC', color: '#2C5282' }
    case 'FAILED':
    case 'REJECTED':  return { bgColor: '#FED7D7', color: '#C53030' }
    default:          return { bgColor: '#F8EDCB', color: '#744210' }
  }
}

onMounted(() => loadSubmission())
</script>
