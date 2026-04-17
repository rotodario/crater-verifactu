<template>
  <BaseSettingCard
    :title="$t('settings.mail.company_mail_config')"
    :description="$t('settings.mail.company_mail_config_description')"
  >
    <BaseCard class="mt-5">
      <!-- Toggle: use company SMTP or global -->
      <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 bg-gray-50">
        <div>
          <p class="text-sm font-medium text-gray-900">Configuración SMTP propia</p>
          <p class="mt-0.5 text-xs text-gray-500">
            Activa para usar un servidor SMTP específico para esta empresa. Si está desactivado se usa la configuración global del servidor.
          </p>
        </div>
        <div class="ml-4 shrink-0">
          <input
            v-model="useCompanySmtp"
            type="checkbox"
            class="h-5 w-5 rounded text-primary-600 border-gray-300 cursor-pointer"
            @change="onToggleSmtp"
          />
        </div>
      </div>

      <!-- SMTP form (visible only when enabled) -->
      <div v-if="useCompanySmtp" class="mt-6 space-y-5">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
          <BaseInputGroup
            :label="$t('settings.mail.host')"
            :error="v$.host.$error ? v$.host.$errors[0].$message : ''"
            required
          >
            <BaseInput
              v-model="form.host"
              :invalid="v$.host.$error"
              type="text"
              placeholder="smtp.gmail.com"
              @blur="v$.host.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup
            :label="$t('settings.mail.port')"
            :error="v$.port.$error ? v$.port.$errors[0].$message : ''"
            required
          >
            <BaseInput
              v-model="form.port"
              :invalid="v$.port.$error"
              type="number"
              placeholder="587"
              @blur="v$.port.$touch()"
            />
          </BaseInputGroup>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
          <BaseInputGroup :label="$t('settings.mail.username')">
            <BaseInput
              v-model="form.username"
              type="text"
              placeholder="usuario@empresa.com"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.mail.password')">
            <BaseInput
              v-model="form.password"
              type="password"
              placeholder="••••••••"
            />
          </BaseInputGroup>
        </div>

        <div>
          <label class="block mb-1 text-sm font-medium text-gray-700">
            {{ $t('settings.mail.encryption') }}
          </label>
          <div class="flex gap-4">
            <label v-for="opt in encryptionOptions" :key="opt.value" class="flex items-center gap-2 cursor-pointer">
              <input
                v-model="form.encryption"
                type="radio"
                :value="opt.value"
                class="text-primary-600"
              />
              <span class="text-sm text-gray-700">{{ opt.label }}</span>
            </label>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
          <BaseInputGroup
            :label="$t('settings.mail.from_mail')"
            :error="v$.from_address.$error ? v$.from_address.$errors[0].$message : ''"
            required
          >
            <BaseInput
              v-model="form.from_address"
              :invalid="v$.from_address.$error"
              type="email"
              placeholder="facturacion@empresa.com"
              @blur="v$.from_address.$touch()"
            />
          </BaseInputGroup>

          <BaseInputGroup :label="$t('settings.mail.from_name')">
            <BaseInput
              v-model="form.from_name"
              type="text"
              placeholder="Mi Empresa S.L."
            />
          </BaseInputGroup>
        </div>

        <!-- Test email -->
        <div class="pt-2 border-t border-gray-100">
          <p class="mb-3 text-sm font-medium text-gray-700">Enviar email de prueba</p>
          <div class="flex gap-3 items-start">
            <div class="flex-1 max-w-sm">
              <BaseInput
                v-model="testEmail"
                type="email"
                placeholder="destino@ejemplo.com"
              />
            </div>
            <BaseButton
              variant="primary-outline"
              :disabled="testSending || !testEmail"
              @click="onSendTest"
            >
              {{ testSending ? 'Enviando...' : 'Enviar prueba' }}
            </BaseButton>
          </div>
          <p v-if="testResult" :class="testResult.success ? 'text-green-600' : 'text-red-600'" class="mt-2 text-sm">
            {{ testResult.message }}
          </p>
        </div>
      </div>

      <!-- From address (always visible, even when SMTP is disabled, to override global defaults) -->
      <div v-if="!useCompanySmtp" class="mt-5 space-y-5">
        <p class="text-sm text-gray-500">
          Se usará el servidor global. Puedes personalizar el nombre y dirección de envío para esta empresa.
        </p>
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
          <BaseInputGroup :label="$t('settings.mail.from_mail')">
            <BaseInput
              v-model="form.from_address"
              type="email"
              placeholder="facturacion@empresa.com"
            />
          </BaseInputGroup>
          <BaseInputGroup :label="$t('settings.mail.from_name')">
            <BaseInput
              v-model="form.from_name"
              type="text"
              placeholder="Mi Empresa S.L."
            />
          </BaseInputGroup>
        </div>
      </div>

      <div class="flex mt-6">
        <BaseButton
          variant="primary"
          :loading="saving"
          @click="onSave"
        >
          <template #left="slotProps">
            <BaseIcon name="SaveIcon" :class="slotProps.class" />
          </template>
          {{ $t('general.save') }}
        </BaseButton>
      </div>
    </BaseCard>
  </BaseSettingCard>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useVuelidate } from '@vuelidate/core'
import { required, email, helpers } from '@vuelidate/validators'
import axios from 'axios'
import { useNotificationStore } from '@/scripts/stores/notification'
import { handleError } from '@/scripts/helpers/error-handling'
import { useI18n } from 'vue-i18n'

const { t } = useI18n()
const notificationStore = useNotificationStore()

const saving = ref(false)
const testSending = ref(false)
const testEmail = ref('')
const testResult = ref(null)
const useCompanySmtp = ref(false)

const form = reactive({
  host: '',
  port: '',
  encryption: 'tls',
  username: '',
  password: '',
  from_address: '',
  from_name: '',
})

const encryptionOptions = [
  { label: 'TLS', value: 'tls' },
  { label: 'SSL', value: 'ssl' },
  { label: 'Sin cifrado', value: 'none' },
]

const rules = {
  host: {
    required: helpers.withMessage('El host es obligatorio', required),
  },
  port: {
    required: helpers.withMessage('El puerto es obligatorio', required),
  },
  from_address: {
    email: helpers.withMessage('Debe ser un email válido', email),
  },
}

// Only validate host/port when SMTP is enabled
const activeRules = {
  host: useCompanySmtp.value ? rules.host : {},
  port: useCompanySmtp.value ? rules.port : {},
  from_address: rules.from_address,
}

const v$ = useVuelidate(activeRules, form)

async function loadSettings() {
  try {
    const { data } = await axios.get('/api/v1/company/mail/settings')
    const config = data.mail_config

    useCompanySmtp.value = config.configured || false
    form.host         = config.host || ''
    form.port         = config.port || ''
    form.encryption   = config.encryption || 'tls'
    form.username     = config.username || ''
    form.password     = config.password || ''   // will be '••••••••' if set
    form.from_address = config.from_address || ''
    form.from_name    = config.from_name || ''
  } catch (error) {
    handleError(error)
  }
}

function onToggleSmtp() {
  // When disabling SMTP, clear transport fields but keep from address/name
  if (!useCompanySmtp.value) {
    form.host       = ''
    form.port       = ''
    form.username   = ''
    form.password   = ''
    form.encryption = 'tls'
  }
}

async function onSave() {
  v$.value.$touch()
  if (useCompanySmtp.value && v$.value.$invalid) return

  saving.value = true
  try {
    const payload = {
      driver:        useCompanySmtp.value ? 'smtp' : '',
      host:          form.host,
      port:          form.port,
      encryption:    form.encryption,
      username:      form.username,
      password:      form.password,
      from_address:  form.from_address,
      from_name:     form.from_name,
    }

    const { data } = await axios.post('/api/v1/company/mail/settings', payload)

    // Refresh displayed password state
    form.password = data.mail_config.password || ''

    notificationStore.showNotification({
      type: 'success',
      message: 'Configuración de correo guardada.',
    })
  } catch (error) {
    handleError(error)
  } finally {
    saving.value = false
  }
}

async function onSendTest() {
  testResult.value = null
  testSending.value = true

  try {
    await axios.post('/api/v1/company/mail/test', { to: testEmail.value })
    testResult.value = { success: true, message: 'Email de prueba enviado correctamente.' }
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al enviar el email de prueba.'
    testResult.value = { success: false, message: msg }
  } finally {
    testSending.value = false
  }
}

onMounted(() => {
  loadSettings()
})
</script>
