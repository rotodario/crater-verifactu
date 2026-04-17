<template>
  <BasePage>
    <BasePageHeader :title="$t('verifactu.declaration_detail', 'Declaración Responsable del SIF')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Setup" to="/admin/verifactu/setup" />
        <BaseBreadcrumbItem :title="$t('verifactu.declaration_detail', 'Declaración')" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <div v-if="loading" class="mt-6 text-sm text-gray-500">Cargando declaración…</div>
    <div v-else-if="!declaration" class="mt-6 text-sm text-gray-500">Declaración no encontrada.</div>

    <div v-else class="grid grid-cols-1 gap-6 mt-6">
      <!-- Resumen -->
      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Resumen</h3>
            <span :class="statusClass(declaration.status)" class="px-3 py-1 text-xs font-semibold rounded-full">
              {{ statusLabel(declaration.status) }}
            </span>
          </div>
        </template>
        <div class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
          <div><span class="font-medium text-gray-500">ID:</span> {{ declaration.id }}</div>
          <div><span class="font-medium text-gray-500">Software:</span> {{ declaration.software_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Versión:</span> {{ declaration.software_version || '-' }}</div>
          <div v-if="declaration.generated_at">
            <span class="font-medium text-gray-500">Generada:</span> {{ declaration.generated_at }}
          </div>
          <div v-if="declaration.reviewed_at">
            <span class="font-medium text-gray-500">Revisada:</span> {{ declaration.reviewed_at }}
          </div>
          <div v-if="declaration.activated_at">
            <span class="font-medium text-gray-500">Vigente desde:</span> {{ declaration.activated_at }}
          </div>
          <div v-if="declaration.archived_at">
            <span class="font-medium text-gray-500">Archivada:</span> {{ declaration.archived_at }}
          </div>
        </div>
      </BaseCard>

      <!-- Datos del SIF (snapshot inmutable) -->
      <BaseCard>
        <template #header>
          <h3 class="text-lg font-semibold text-gray-900">Datos del SIF certificados</h3>
        </template>
        <div v-if="payload" class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2">
          <div><span class="font-medium text-gray-500">Nombre software:</span> {{ payload.software_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Versión software:</span> {{ payload.software_version || '-' }}</div>
          <div><span class="font-medium text-gray-500">Nombre productor:</span> {{ payload.vendor_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">NIF productor:</span> {{ payload.vendor_tax_id || '-' }}</div>
          <div><span class="font-medium text-gray-500">IdSistemaInformatico:</span> {{ payload.software_id || '-' }}</div>
        </div>
        <div v-else class="text-sm text-gray-400">Sin datos de snapshot.</div>
      </BaseCard>

      <!-- Notas -->
      <BaseCard v-if="declaration.notes">
        <template #header>
          <h3 class="text-lg font-semibold text-gray-900">Notas</h3>
        </template>
        <p class="text-sm text-gray-700">{{ declaration.notes }}</p>
      </BaseCard>

      <!-- Acciones de transición (solo superadmin) -->
      <BaseCard v-if="canManagePlatform && allowedTransitions.length > 0">
        <template #header>
          <h3 class="text-lg font-semibold text-gray-900">Cambiar estado</h3>
        </template>
        <div class="space-y-3">
          <BaseTextarea
            v-model="notes"
            label="Notas internas (opcional)"
            rows="2"
            placeholder="Motivo del cambio…"
          />
          <div class="flex flex-wrap gap-2">
            <BaseButton
              v-for="status in allowedTransitions"
              :key="status"
              :color="transitionColor(status)"
              :loading="updating"
              @click="doTransition(status)"
            >
              {{ transitionLabel(status) }}
            </BaseButton>
          </div>
        </div>
      </BaseCard>
    </div>
  </BasePage>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useUserStore } from '@/scripts/admin/stores/user'
import SectionNav from '@/scripts/admin/views/verifactu/components/SectionNav.vue'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'

const route = useRoute()
const userStore = useUserStore()
const notificationStore = useNotificationStore()

const loading = ref(false)
const updating = ref(false)
const declaration = ref(null)
const notes = ref('')

const canManagePlatform = computed(() => !!userStore.currentUser?.is_owner)
const payload = computed(() => declaration.value?.declaration_payload ?? null)

const TRANSITIONS = {
  DRAFT:     ['GENERATED'],
  GENERATED: ['REVIEWED', 'DRAFT'],
  REVIEWED:  ['ACTIVE', 'DRAFT'],
  ACTIVE:    [],
  ARCHIVED:  [],
}

const allowedTransitions = computed(() => {
  if (!declaration.value) return []
  return TRANSITIONS[declaration.value.status] ?? []
})

function statusClass(status) {
  return {
    DRAFT:     'bg-gray-100 text-gray-600',
    GENERATED: 'bg-yellow-100 text-yellow-800',
    REVIEWED:  'bg-blue-100 text-blue-800',
    ACTIVE:    'bg-green-100 text-green-800',
    ARCHIVED:  'bg-gray-100 text-gray-400',
  }[status] ?? 'bg-gray-100 text-gray-600'
}

function statusLabel(status) {
  return {
    DRAFT:     'Borrador',
    GENERATED: 'Generada',
    REVIEWED:  'Revisada',
    ACTIVE:    'Vigente',
    ARCHIVED:  'Archivada',
  }[status] ?? status
}

function transitionLabel(status) {
  return {
    GENERATED: 'Generar declaración',
    REVIEWED:  'Marcar como revisada',
    ACTIVE:    'Activar como vigente',
    DRAFT:     'Volver a borrador',
  }[status] ?? status
}

function transitionColor(status) {
  return { GENERATED: 'yellow', REVIEWED: 'blue', ACTIVE: 'green', DRAFT: 'gray' }[status] ?? 'gray'
}

async function loadDeclaration() {
  loading.value = true
  try {
    const { data } = await axios.get(`/api/v1/verifactu/declarations/${route.params.id}`)
    declaration.value = data.declaration ?? null
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

async function doTransition(newStatus) {
  updating.value = true
  try {
    const { data } = await axios.put(`/api/v1/verifactu/declarations/${declaration.value.id}`, {
      status: newStatus,
      notes: notes.value || undefined,
    })
    declaration.value = data.declaration
    notes.value = ''
    notificationStore.showNotification({ type: 'success', message: `Declaración marcada como ${statusLabel(newStatus).toLowerCase()}.` })
  } catch (error) {
    handleError(error)
  } finally {
    updating.value = false
  }
}

onMounted(() => {
  loadDeclaration()
})
</script>
