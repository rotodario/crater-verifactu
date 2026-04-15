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
              <BaseBadge :bg-color="getStatusBadgeColor(submission.status).bgColor" :color="getStatusBadgeColor(submission.status).color">
                {{ submission.status }}
              </BaseBadge>
            </div>
          </div>
        </template>

        <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
          <div><span class="font-medium text-gray-500">ID:</span> {{ submission.id }}</div>
          <div>
            <span class="font-medium text-gray-500">Record:</span>
            <router-link v-if="submission.verifactu_record_id" :to="`/admin/verifactu/records/${submission.verifactu_record_id}/view`" class="text-primary-500">
              {{ submission.verifactu_record_id }}
            </router-link>
            <span v-else>-</span>
          </div>
          <div>
            <span class="font-medium text-gray-500">Factura:</span>
            <router-link v-if="submission.invoice_id" :to="`/admin/invoices/${submission.invoice_id}/view`" class="text-primary-500">
              {{ submission.invoice_number || '-' }}
            </router-link>
            <span v-else>{{ submission.invoice_number || '-' }}</span>
          </div>
          <div><span class="font-medium text-gray-500">Cliente:</span> {{ submission.customer_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Driver:</span> {{ submission.driver || '-' }}</div>
          <div><span class="font-medium text-gray-500">Referencia:</span> {{ submission.external_reference || '-' }}</div>
          <div><span class="font-medium text-gray-500">Error code:</span> {{ submission.error_code || '-' }}</div>
          <div class="md:col-span-2 xl:col-span-3"><span class="font-medium text-gray-500">Error message:</span> {{ submission.error_message || '-' }}</div>
          <div><span class="font-medium text-gray-500">Submitted at:</span> {{ submission.submitted_at || '-' }}</div>
          <div><span class="font-medium text-gray-500">Completed at:</span> {{ submission.completed_at || '-' }}</div>
          <div><span class="font-medium text-gray-500">Updated at:</span> {{ submission.updated_at || '-' }}</div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Request Payload JSON</h3></template>
        <pre class="p-4 overflow-auto text-xs leading-6 text-gray-800 bg-gray-50 rounded">{{ formatJson(submission.request_payload) }}</pre>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Response Payload JSON</h3></template>
        <pre class="p-4 overflow-auto text-xs leading-6 text-gray-800 bg-gray-50 rounded">{{ formatJson(submission.response_payload) }}</pre>
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
  if (!submission.value) {
    return
  }

  retrying.value = true

  try {
    await axios.post(`/api/v1/verifactu/submissions/${submission.value.id}/retry`)
    notificationStore.showNotification({
      type: 'success',
      message: 'VERI*FACTU submission queued for retry.',
    })
    await loadSubmission()
  } catch (error) {
    handleError(error)
  } finally {
    retrying.value = false
  }
}

function formatJson(value) {
  return JSON.stringify(value || {}, null, 2)
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
  loadSubmission()
})
</script>
