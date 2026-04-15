<template>
  <BasePage>
    <BasePageHeader :title="$t('verifactu.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Setup" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <div v-if="loading" class="mt-6 text-sm text-gray-500">Cargando setup...</div>

    <div v-else class="grid grid-cols-1 gap-6 mt-6">
      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Installation</h3>
            <BaseButton variant="primary-outline" @click="loadSetup">
              <template #left="slotProps">
                <BaseIcon name="RefreshIcon" :class="slotProps.class" />
              </template>
              {{ $t('general.retry') }}
            </BaseButton>
          </div>
        </template>

        <div v-if="!installation" class="text-sm text-gray-500">No hay instalacion VERI*FACTU asociada a esta compania.</div>
        <div v-else class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
          <div><span class="font-medium text-gray-500">ID:</span> {{ installation.id }}</div>
          <div><span class="font-medium text-gray-500">Modo:</span> {{ installation.mode || '-' }}</div>
          <div><span class="font-medium text-gray-500">Entorno:</span> {{ installation.environment || '-' }}</div>
          <div><span class="font-medium text-gray-500">Enabled:</span> {{ installation.enabled ? $t('general.yes') : $t('general.no') }}</div>
          <div><span class="font-medium text-gray-500">Submission enabled:</span> {{ installation.submission_enabled ? $t('general.yes') : $t('general.no') }}</div>
          <div><span class="font-medium text-gray-500">Issuer tax ID:</span> {{ installation.issuer_tax_id || '-' }}</div>
          <div><span class="font-medium text-gray-500">Issuer name:</span> {{ installation.issuer_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Software:</span> {{ installation.software_name || '-' }} {{ installation.software_version || '' }}</div>
          <div><span class="font-medium text-gray-500">Updated at:</span> {{ installation.updated_at || '-' }}</div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Installation settings JSON</h3></template>
        <pre class="p-4 overflow-auto text-xs leading-6 text-gray-800 bg-gray-50 rounded">{{ formatJson(installation?.settings) }}</pre>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Declarations</h3></template>
        <div v-if="!declarations.length" class="text-sm text-gray-500">No hay declaraciones disponibles.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="py-3 pr-4 text-left text-gray-500">ID</th>
                <th class="py-3 pr-4 text-left text-gray-500">Software</th>
                <th class="py-3 pr-4 text-left text-gray-500">Version</th>
                <th class="py-3 pr-4 text-left text-gray-500">Status</th>
                <th class="py-3 pr-4 text-left text-gray-500">Declared at</th>
                <th class="py-3 text-left text-gray-500">Created at</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="declaration in declarations" :key="declaration.id" class="border-b border-gray-100 last:border-b-0">
                <td class="py-3 pr-4">
                  <router-link :to="`/admin/verifactu/declarations/${declaration.id}/view`" class="text-primary-500">
                    {{ declaration.id }}
                  </router-link>
                </td>
                <td class="py-3 pr-4">{{ declaration.software_name || '-' }}</td>
                <td class="py-3 pr-4">{{ declaration.software_version || '-' }}</td>
                <td class="py-3 pr-4">{{ declaration.status || '-' }}</td>
                <td class="py-3 pr-4">{{ declaration.declared_at || '-' }}</td>
                <td class="py-3">{{ declaration.created_at || '-' }}</td>
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
import { onMounted, ref } from 'vue'
import SectionNav from '@/scripts/admin/views/verifactu/components/SectionNav.vue'
import { handleError } from '@/scripts/helpers/error-handling'

const loading = ref(false)
const installation = ref(null)
const declarations = ref([])

async function loadSetup() {
  loading.value = true

  try {
    const response = await axios.get('/api/v1/verifactu/setup')
    installation.value = response.data.installation || null
    declarations.value = response.data.declarations || []
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
  loadSetup()
})
</script>
