<template>
  <BasePage>
    <BasePageHeader title="VERI*FACTU Declaration">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Setup" to="/admin/verifactu/setup" />
        <BaseBreadcrumbItem title="Declaration" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <div v-if="loading" class="mt-6 text-sm text-gray-500">Cargando declaration...</div>
    <div v-else-if="!declaration" class="mt-6 text-sm text-gray-500">No se ha encontrado la declaration.</div>

    <div v-else class="grid grid-cols-1 gap-6 mt-6">
      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Resumen</h3></template>
        <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
          <div><span class="font-medium text-gray-500">ID:</span> {{ declaration.id }}</div>
          <div><span class="font-medium text-gray-500">Software:</span> {{ declaration.software_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Version:</span> {{ declaration.software_version || '-' }}</div>
          <div><span class="font-medium text-gray-500">Status:</span> {{ declaration.status || '-' }}</div>
          <div><span class="font-medium text-gray-500">Declared at:</span> {{ declaration.declared_at || '-' }}</div>
          <div><span class="font-medium text-gray-500">Updated at:</span> {{ declaration.updated_at || '-' }}</div>
        </div>
      </BaseCard>

      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Declaration payload JSON</h3></template>
        <pre class="p-4 overflow-auto text-xs leading-6 text-gray-800 bg-gray-50 rounded">{{ formatJson(declaration.declaration_payload) }}</pre>
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
const declaration = ref(null)

async function loadDeclaration() {
  loading.value = true

  try {
    const response = await axios.get(`/api/v1/verifactu/declarations/${route.params.id}`)
    declaration.value = response.data.declaration || null
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
  loadDeclaration()
})
</script>
