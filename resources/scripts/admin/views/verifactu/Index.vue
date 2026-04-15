<template>
  <BasePage>
    <BasePageHeader :title="$t('verifactu.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="#" active />
      </BaseBreadcrumb>

      <template #actions>
        <BaseButton variant="primary-outline" @click="loadDashboard">
          <template #left="slotProps">
            <BaseIcon name="RefreshIcon" :class="slotProps.class" />
          </template>
          {{ $t('general.retry') }}
        </BaseButton>
      </template>
    </BasePageHeader>

    <SectionNav />

    <BaseCard container-class="px-5 py-5 mt-6">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div>
          <div class="text-sm font-medium text-gray-500">
            Modo actual VERI*FACTU
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-3">
            <BaseBadge :bg-color="modeBadge.bgColor" :color="modeBadge.color">
              {{ modeLabel }}
            </BaseBadge>
            <span class="text-sm text-gray-600">
              {{ modeDescription }}
            </span>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 sm:grid-cols-2 xl:min-w-[420px]">
          <div>
            <span class="font-medium text-gray-500">Driver:</span>
            {{ environment.submission_driver || '-' }}
          </div>
          <div>
            <span class="font-medium text-gray-500">Submissions activas:</span>
            {{ environment.submission_enabled ? $t('general.yes') : $t('general.no') }}
          </div>
          <div>
            <span class="font-medium text-gray-500">Expedir al enviar:</span>
            {{ environment.issue_on_send ? $t('general.yes') : $t('general.no') }}
          </div>
          <div>
            <span class="font-medium text-gray-500">Software:</span>
            {{ environment.software_name || '-' }} {{ environment.software_version || '' }}
          </div>
        </div>
      </div>
    </BaseCard>

    <div class="grid grid-cols-1 gap-5 mt-6 md:grid-cols-2 xl:grid-cols-3">
      <BaseCard
        v-for="card in summaryCards"
        :key="card.key"
        container-class="px-5 py-5"
      >
        <div class="text-sm font-medium text-gray-500">
          {{ card.label }}
        </div>
        <div class="mt-2 text-3xl font-semibold text-gray-900">
          {{ card.value }}
        </div>
      </BaseCard>
    </div>

    <div class="grid grid-cols-1 gap-6 mt-6">
      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ $t('verifactu.latest_records') }}
            </h3>
            <span class="text-sm text-gray-500">
              {{ records.length }} {{ $t('verifactu.visible_rows') }}
            </span>
          </div>
        </template>

        <div v-if="loading" class="text-sm text-gray-500">
          {{ $t('verifactu.loading') }}
        </div>

        <div v-else-if="!records.length" class="text-sm text-gray-500">
          {{ $t('verifactu.no_records') }}
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="py-3 pr-4 text-left text-gray-500">ID</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('invoices.number') }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $tc('customers.customer', 1) }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('invoices.status') }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('verifactu.kind') }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('verifactu.hash') }}</th>
                <th class="py-3 text-left text-gray-500">{{ $t('verifactu.date') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="record in records"
                :key="record.id"
                class="border-b border-gray-100 last:border-b-0"
              >
                <td class="py-3 pr-4">
                  <router-link :to="`/admin/verifactu/records/${record.id}/view`" class="text-primary-500">
                    {{ record.id }}
                  </router-link>
                </td>
                <td class="py-3 pr-4">
                  <router-link
                    v-if="record.invoice_id"
                    :to="`/admin/invoices/${record.invoice_id}/view`"
                    class="font-medium text-primary-500"
                  >
                    {{ record.invoice_number || '-' }}
                  </router-link>
                  <span v-else>{{ record.invoice_number || '-' }}</span>
                </td>
                <td class="py-3 pr-4 text-gray-800">{{ record.customer_name || '-' }}</td>
                <td class="py-3 pr-4">
                  <BaseBadge
                    :bg-color="getStatusBadgeColor(record.status).bgColor"
                    :color="getStatusBadgeColor(record.status).color"
                  >
                    {{ record.status }}
                  </BaseBadge>
                </td>
                <td class="py-3 pr-4 text-gray-800">{{ getKindLabel(record.invoice_kind) }}</td>
                <td class="py-3 pr-4 font-mono text-xs text-gray-600">{{ record.hash || '-' }}</td>
                <td class="py-3 text-gray-800">{{ record.issued_at || record.created_at || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ $t('verifactu.latest_submissions') }}
            </h3>
            <span class="text-sm text-gray-500">
              {{ submissions.length }} {{ $t('verifactu.visible_rows') }}
            </span>
          </div>
        </template>

        <div v-if="loading" class="text-sm text-gray-500">
          {{ $t('verifactu.loading') }}
        </div>

        <div v-else-if="!submissions.length" class="text-sm text-gray-500">
          {{ $t('verifactu.no_submissions') }}
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="py-3 pr-4 text-left text-gray-500">ID</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('invoices.number') }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('invoices.status') }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">Driver</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('verifactu.reference') }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('verifactu.error_code') }}</th>
                <th class="py-3 text-left text-gray-500">{{ $t('verifactu.date') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="submission in submissions"
                :key="submission.id"
                class="border-b border-gray-100 last:border-b-0"
              >
                <td class="py-3 pr-4">
                  <router-link :to="`/admin/verifactu/submissions/${submission.id}/view`" class="text-primary-500">
                    {{ submission.id }}
                  </router-link>
                </td>
                <td class="py-3 pr-4">
                  <router-link
                    v-if="submission.invoice_id"
                    :to="`/admin/invoices/${submission.invoice_id}/view`"
                    class="font-medium text-primary-500"
                  >
                    {{ submission.invoice_number || '-' }}
                  </router-link>
                  <span v-else>{{ submission.invoice_number || '-' }}</span>
                </td>
                <td class="py-3 pr-4">
                  <BaseBadge
                    :bg-color="getStatusBadgeColor(submission.status).bgColor"
                    :color="getStatusBadgeColor(submission.status).color"
                  >
                    {{ submission.status }}
                  </BaseBadge>
                </td>
                <td class="py-3 pr-4 text-gray-800">{{ submission.driver || '-' }}</td>
                <td class="py-3 pr-4 font-mono text-xs text-gray-600">
                  {{ submission.external_reference || '-' }}
                </td>
                <td class="py-3 pr-4 text-gray-800">{{ submission.error_code || '-' }}</td>
                <td class="py-3 text-gray-800">
                  {{ submission.completed_at || submission.submitted_at || submission.created_at || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">
              {{ $t('verifactu.latest_events') }}
            </h3>
            <span class="text-sm text-gray-500">
              {{ events.length }} {{ $t('verifactu.visible_rows') }}
            </span>
          </div>
        </template>

        <div v-if="loading" class="text-sm text-gray-500">
          {{ $t('verifactu.loading') }}
        </div>

        <div v-else-if="!events.length" class="text-sm text-gray-500">
          {{ $t('verifactu.no_events') }}
        </div>

        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="py-3 pr-4 text-left text-gray-500">ID</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('invoices.number') }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">{{ $t('verifactu.event') }}</th>
                <th class="py-3 pr-4 text-left text-gray-500">Código</th>
                <th class="py-3 text-left text-gray-500">{{ $t('verifactu.date') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="event in events"
                :key="event.id"
                class="border-b border-gray-100 last:border-b-0"
              >
                <td class="py-3 pr-4"><router-link :to="`/admin/verifactu/events/${event.id}/view`" class="text-primary-500">{{ event.id }}</router-link></td>
                <td class="py-3 pr-4">
                  <router-link
                    v-if="event.invoice_id"
                    :to="`/admin/invoices/${event.invoice_id}/view`"
                    class="font-medium text-primary-500"
                  >
                    {{ event.invoice_number || '-' }}
                  </router-link>
                  <span v-else>{{ event.invoice_number || '-' }}</span>
                </td>
                <td class="py-3 pr-4 text-gray-800">{{ event.event_type || '-' }}</td>
                <td class="py-3 pr-4">
                  <BaseBadge
                    :bg-color="getLevelBadgeColor(event.level).bgColor"
                    :color="getLevelBadgeColor(event.level).color"
                  >
                    {{ event.event_code || '-' }}
                  </BaseBadge>
                </td>
                <td class="py-3 text-gray-800">{{ event.created_at || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </BaseCard>
    </div>
  </BasePage>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import SectionNav from '@/scripts/admin/views/verifactu/components/SectionNav.vue'
import { handleError } from '@/scripts/helpers/error-handling'

const { t } = useI18n()
const loading = ref(false)
const environment = ref({
  enabled: false,
  mode: 'shadow',
  issue_on_send: false,
  submission_enabled: false,
  submission_driver: 'stub',
  software_name: '',
  software_version: '',
})
const summary = ref({
  records_total: 0,
  accepted_records: 0,
  issued_records: 0,
  pending_submissions: 0,
  failed_submissions: 0,
  events_total: 0,
})
const records = ref([])
const submissions = ref([])
const events = ref([])

const summaryCards = computed(() => [
  { key: 'records_total', label: 'Registros', value: summary.value.records_total },
  { key: 'accepted_records', label: 'Aceptados', value: summary.value.accepted_records },
  { key: 'issued_records', label: 'Emitidos / sometidos', value: summary.value.issued_records },
  { key: 'pending_submissions', label: 'Submissions en cola', value: summary.value.pending_submissions },
  { key: 'failed_submissions', label: 'Submissions fallidas', value: summary.value.failed_submissions },
  { key: 'events_total', label: 'Eventos', value: summary.value.events_total },
])

const modeLabel = computed(() => {
  const mode = environment.value.mode || 'shadow'
  const labels = {
    off: 'OFF',
    shadow: 'SHADOW',
    stub: 'STUB',
    aeat_sandbox: 'AEAT SANDBOX',
    aeat_production: 'AEAT PRODUCCION',
  }

  return labels[mode] || mode
})

const modeDescription = computed(() => {
  const mode = environment.value.mode || 'shadow'
  const labels = {
    off: 'La capa fiscal esta desactivada y no deberia emitir ni someter registros.',
    shadow: 'Se generan estructuras internas y trazabilidad, pero no se somete nada a AEAT.',
    stub: 'Las submissions se simulan localmente. No hay comunicacion real con Hacienda.',
    aeat_sandbox: 'Preparado para un futuro entorno oficial de pruebas de AEAT, no productivo.',
    aeat_production: 'Modo de produccion real. Debe tratarse como envio fiscal activo.',
  }

  return labels[mode] || mode
})

const modeBadge = computed(() => {
  const mode = environment.value.mode || 'shadow'

  switch (mode) {
    case 'off':
      return { bgColor: '#E2E8F0', color: '#2D3748' }
    case 'aeat_production':
      return { bgColor: '#FED7D7', color: '#C53030' }
    case 'aeat_sandbox':
      return { bgColor: '#FEEBC8', color: '#C05621' }
    case 'stub':
      return { bgColor: '#D5EED0', color: '#276749' }
    default:
      return { bgColor: '#C9E3EC', color: '#2C5282' }
  }
})

async function loadDashboard() {
  loading.value = true

  try {
    const response = await axios.get('/api/v1/verifactu/dashboard')
    environment.value = response.data.environment || environment.value
    summary.value = response.data.summary
    records.value = response.data.records || []
    submissions.value = response.data.submissions || []
    events.value = response.data.events || []
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
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


function getKindLabel(kind) {
  return kind === 'RECTIFICATIVE' ? 'Rectificativa' : 'Ordinaria'
}

onMounted(() => {
  loadDashboard()
})
</script>
