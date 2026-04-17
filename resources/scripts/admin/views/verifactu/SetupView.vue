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

      <!-- Installation info -->
      <BaseCard>
        <template #header>
          <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900">Instalación VERI*FACTU</h3>
            <BaseButton variant="primary-outline" @click="loadSetup">
              <template #left="slotProps">
                <BaseIcon name="RefreshIcon" :class="slotProps.class" />
              </template>
              {{ $t('general.retry') }}
            </BaseButton>
          </div>
        </template>

        <div v-if="!installation" class="text-sm text-gray-500">
          No hay instalación VERI*FACTU asociada a esta compañía.
        </div>
        <div v-else class="grid grid-cols-1 gap-3 text-sm text-gray-700 md:grid-cols-2 xl:grid-cols-3">
          <div><span class="font-medium text-gray-500">ID:</span> {{ installation.id }}</div>
          <div><span class="font-medium text-gray-500">Modo:</span> {{ installation.mode || '-' }}</div>
          <div><span class="font-medium text-gray-500">Entorno:</span> {{ installation.environment || '-' }}</div>
          <div><span class="font-medium text-gray-500">Enabled:</span> {{ installation.enabled ? $t('general.yes') : $t('general.no') }}</div>
          <div><span class="font-medium text-gray-500">Submission enabled:</span> {{ installation.submission_enabled ? $t('general.yes') : $t('general.no') }}</div>
          <div><span class="font-medium text-gray-500">Issuer NIF:</span> {{ installation.issuer_tax_id || '-' }}</div>
          <div><span class="font-medium text-gray-500">Issuer name:</span> {{ installation.issuer_name || '-' }}</div>
          <div><span class="font-medium text-gray-500">Software:</span> {{ installation.software_name || '-' }} {{ installation.software_version || '' }}</div>
          <div><span class="font-medium text-gray-500">Actualizado:</span> {{ installation.updated_at || '-' }}</div>
        </div>
      </BaseCard>

      <!-- Certificate management -->
      <BaseCard>
        <template #header>
          <h3 class="text-lg font-semibold text-gray-900">Certificado digital AEAT</h3>
        </template>

        <!-- Current certificate status -->
        <div v-if="installation?.has_certificate" class="flex items-start gap-4 p-4 mb-5 rounded-lg bg-green-50 border border-green-200">
          <BaseIcon name="ShieldCheckIcon" class="h-6 w-6 text-green-600 shrink-0 mt-0.5" />
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-green-800">Certificado configurado</p>
            <p class="mt-0.5 text-sm text-green-700 truncate">
              {{ installation.cert_filename }}
              <span class="ml-2 px-1.5 py-0.5 text-xs font-mono bg-green-200 text-green-900 rounded">{{ installation.cert_type?.toUpperCase() }}</span>
            </p>
          </div>
          <BaseButton
            variant="danger-outline"
            size="sm"
            :disabled="deletingCert"
            @click="onDeleteCertificate"
          >
            {{ deletingCert ? 'Eliminando...' : 'Eliminar' }}
          </BaseButton>
        </div>

        <div v-else class="flex items-center gap-3 p-4 mb-5 rounded-lg bg-yellow-50 border border-yellow-200">
          <BaseIcon name="ExclamationIcon" class="h-5 w-5 text-yellow-600 shrink-0" />
          <p class="text-sm text-yellow-800">
            No hay certificado configurado. Los modos <strong>aeat_sandbox</strong> y <strong>aeat_production</strong> requieren un certificado para enviar registros a la AEAT.
          </p>
        </div>

        <!-- Certificate type hint -->
        <div class="mb-5 grid grid-cols-1 gap-3 md:grid-cols-2">
          <div class="p-3 rounded-lg border border-gray-200 bg-gray-50 text-sm">
            <p class="font-semibold text-gray-800">Persona física / Autónomo</p>
            <p class="mt-1 text-gray-600">Certificado FNMT de persona física (<span class="font-mono text-xs">.p12</span>). Válido para producción. El sandbox AEAT solo acepta certificados de sello.</p>
          </div>
          <div class="p-3 rounded-lg border border-gray-200 bg-gray-50 text-sm">
            <p class="font-semibold text-gray-800">Empresa / Representante</p>
            <p class="mt-1 text-gray-600">Certificado de sello o de representante (<span class="font-mono text-xs">.p12</span>). Compatible con sandbox y producción AEAT.</p>
          </div>
        </div>

        <!-- Upload form -->
        <div class="space-y-4">
          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">
              Archivo de certificado
              <span class="ml-1 text-xs text-gray-400 font-normal">.p12 / .pfx / .pem — máx. 2 MB</span>
            </label>
            <input
              ref="fileInput"
              type="file"
              accept=".p12,.pfx,.pem"
              class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 border border-gray-300 rounded-md cursor-pointer"
              @change="onFileChange"
            />
            <p v-if="selectedFileName" class="mt-1 text-xs text-gray-500">
              Seleccionado: <span class="font-medium">{{ selectedFileName }}</span>
            </p>
          </div>

          <div>
            <label class="block mb-1 text-sm font-medium text-gray-700">Contraseña del certificado</label>
            <div class="relative max-w-sm">
              <input
                v-model="certPassword"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Contraseña del .p12 / .pem"
                class="w-full px-3 py-2 pr-10 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
              />
              <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-gray-600"
                @click="showPassword = !showPassword"
              >
                <BaseIcon :name="showPassword ? 'EyeOffIcon' : 'EyeIcon'" class="h-4 w-4" />
              </button>
            </div>
          </div>

          <div v-if="uploadError" class="p-3 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md">
            {{ uploadError }}
          </div>

          <BaseButton
            variant="primary"
            :disabled="!selectedFile || uploading"
            @click="onUploadCertificate"
          >
            <template v-if="uploading">
              Subiendo...
            </template>
            <template v-else>
              {{ installation?.has_certificate ? 'Reemplazar certificado' : 'Subir certificado' }}
            </template>
          </BaseButton>
        </div>
      </BaseCard>

      <!-- Declarations -->
      <BaseCard>
        <template #header><h3 class="text-lg font-semibold text-gray-900">Declaraciones responsables</h3></template>
        <div v-if="!declarations.length" class="text-sm text-gray-500">No hay declaraciones disponibles.</div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200">
                <th class="py-3 pr-4 text-left text-gray-500">ID</th>
                <th class="py-3 pr-4 text-left text-gray-500">Software</th>
                <th class="py-3 pr-4 text-left text-gray-500">Versión</th>
                <th class="py-3 pr-4 text-left text-gray-500">Estado</th>
                <th class="py-3 pr-4 text-left text-gray-500">Declarado</th>
                <th class="py-3 text-left text-gray-500">Creado</th>
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
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()

const loading = ref(false)
const uploading = ref(false)
const deletingCert = ref(false)
const installation = ref(null)
const declarations = ref([])

const fileInput = ref(null)
const selectedFile = ref(null)
const selectedFileName = ref('')
const certPassword = ref('')
const showPassword = ref(false)
const uploadError = ref('')

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

function onFileChange(event) {
  const file = event.target.files[0] || null
  selectedFile.value = file
  selectedFileName.value = file ? file.name : ''
  uploadError.value = ''
}

async function onUploadCertificate() {
  if (!selectedFile.value) return

  uploadError.value = ''
  uploading.value = true

  const formData = new FormData()
  formData.append('certificate', selectedFile.value)
  formData.append('cert_password', certPassword.value)

  try {
    const response = await axios.post('/api/v1/verifactu/certificate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (installation.value) {
      installation.value.has_certificate = response.data.has_certificate
      installation.value.cert_filename = response.data.cert_filename
      installation.value.cert_type = response.data.cert_type
    }

    selectedFile.value = null
    selectedFileName.value = ''
    certPassword.value = ''
    if (fileInput.value) fileInput.value.value = ''

    notificationStore.showNotification({
      type: 'success',
      message: 'Certificado subido correctamente.',
    })
  } catch (error) {
    if (error.response?.data?.message) {
      uploadError.value = error.response.data.message
    } else {
      handleError(error)
    }
  } finally {
    uploading.value = false
  }
}

async function onDeleteCertificate() {
  const confirmed = await dialogStore.openDialog({
    title: t('general.are_you_sure'),
    message: 'Se eliminará el certificado digital. Los envíos a la AEAT dejarán de funcionar hasta que subas uno nuevo.',
    yesLabel: 'Eliminar',
    noLabel: t('general.cancel'),
    variant: 'danger',
    hideNoButton: false,
    size: 'lg',
  })

  if (!confirmed) return

  deletingCert.value = true
  try {
    await axios.delete('/api/v1/verifactu/certificate')

    if (installation.value) {
      installation.value.has_certificate = false
      installation.value.cert_filename = null
      installation.value.cert_type = null
    }

    notificationStore.showNotification({
      type: 'success',
      message: 'Certificado eliminado.',
    })
  } catch (error) {
    handleError(error)
  } finally {
    deletingCert.value = false
  }
}

onMounted(() => {
  loadSetup()
})
</script>
