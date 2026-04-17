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

    <div v-else class="mt-6">

      <!-- Tab bar -->
      <div class="flex gap-1 border-b border-gray-200 mb-6">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          class="px-5 py-2.5 text-sm font-medium rounded-t-md border-b-2 transition-colors -mb-px"
          :class="activeTab === tab.id
            ? 'border-primary-500 text-primary-600 bg-white'
            : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
          @click="activeTab = tab.id"
        >
          {{ tab.label }}
        </button>
      </div>

      <!-- ── RESUMEN ── -->
      <div v-if="activeTab === 'resumen'">
        <div v-if="!installation" class="text-sm text-gray-500 bg-white rounded-lg border border-gray-200 p-6">
          No hay instalación VERI*FACTU asociada a esta compañía.
          <span v-if="canManage" class="ml-1 text-primary-600 cursor-pointer underline" @click="activeTab = 'configuracion'">Configurar ahora</span>
        </div>

        <div v-else class="grid grid-cols-1 gap-5 lg:grid-cols-2">
          <!-- Status card -->
          <BaseCard>
            <template #header><h3 class="font-semibold text-gray-900">Estado del sistema</h3></template>
            <dl class="space-y-3 text-sm">
              <div class="flex justify-between items-center">
                <dt class="text-gray-500">Habilitado</dt>
                <dd>
                  <span :class="installation.enabled ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ installation.enabled ? 'Activo' : 'Desactivado' }}
                  </span>
                </dd>
              </div>
              <div class="flex justify-between items-center">
                <dt class="text-gray-500">Modo</dt>
                <dd>
                  <span :class="modeBadgeClass(installation.mode)"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium font-mono">
                    {{ installation.mode }}
                  </span>
                </dd>
              </div>
              <div class="flex justify-between items-center">
                <dt class="text-gray-500">Entorno</dt>
                <dd>
                  <span :class="envBadgeClass(installation.environment)"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ installation.environment }}
                  </span>
                </dd>
              </div>
              <div class="flex justify-between items-center">
                <dt class="text-gray-500">Envío a AEAT</dt>
                <dd>
                  <span :class="installation.submission_enabled ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ installation.submission_enabled ? 'Activo' : 'Desactivado' }}
                  </span>
                </dd>
              </div>
            </dl>
          </BaseCard>

          <!-- Issuer card -->
          <BaseCard>
            <template #header><h3 class="font-semibold text-gray-900">Emisor</h3></template>
            <dl class="space-y-3 text-sm">
              <div class="flex justify-between">
                <dt class="text-gray-500">Nombre</dt>
                <dd class="text-gray-900 font-medium text-right max-w-[60%] truncate">{{ installation.issuer_name || '—' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-500">NIF/CIF</dt>
                <dd class="text-gray-900 font-mono">{{ installation.issuer_tax_id || '—' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-500">Software</dt>
                <dd class="text-gray-600 font-mono text-xs">{{ installation.software_name || '—' }} {{ installation.software_version || '' }}</dd>
              </div>
              <div class="flex justify-between">
                <dt class="text-gray-500">Actualizado</dt>
                <dd class="text-gray-500 text-xs">{{ installation.updated_at || '—' }}</dd>
              </div>
            </dl>
          </BaseCard>

          <!-- Certificate status -->
          <BaseCard>
            <template #header><h3 class="font-semibold text-gray-900">Certificado digital</h3></template>
            <div v-if="installation.has_certificate" class="flex items-center gap-3">
              <BaseIcon name="ShieldCheckIcon" class="h-8 w-8 text-green-500 shrink-0" />
              <div>
                <p class="text-sm font-medium text-gray-900">{{ installation.cert_filename }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  Tipo: <span class="font-mono uppercase">{{ installation.cert_type }}</span>
                </p>
              </div>
            </div>
            <div v-else class="flex items-center gap-3 text-yellow-700">
              <BaseIcon name="ExclamationIcon" class="h-8 w-8 text-yellow-500 shrink-0" />
              <div>
                <p class="text-sm font-medium">Sin certificado</p>
                <p class="text-xs text-gray-500 mt-0.5">
                  Requerido para los modos <span class="font-mono">aeat_sandbox</span> y <span class="font-mono">aeat_production</span>.
                </p>
              </div>
            </div>
            <div class="mt-4">
              <BaseButton variant="primary-outline" size="sm" @click="activeTab = 'certificados'">
                Gestionar certificado
              </BaseButton>
            </div>
          </BaseCard>

          <!-- Declarations summary -->
          <BaseCard>
            <template #header><h3 class="font-semibold text-gray-900">Declaraciones</h3></template>
            <div v-if="!declarations.length" class="text-sm text-gray-500">
              No hay declaraciones registradas.
            </div>
            <div v-else class="space-y-2">
              <div v-for="dec in declarations.slice(0, 3)" :key="dec.id"
                class="flex justify-between items-center text-sm p-2 rounded-md bg-gray-50 border border-gray-100">
                <span class="text-gray-700 font-mono text-xs">#{{ dec.id }} — {{ dec.software_name }} {{ dec.software_version }}</span>
                <span :class="declarationBadgeClass(dec.status)"
                  class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ dec.status }}
                </span>
              </div>
              <div v-if="declarations.length > 3">
                <button class="text-xs text-primary-600 underline mt-1" @click="activeTab = 'declaraciones'">
                  Ver todas ({{ declarations.length }})
                </button>
              </div>
            </div>
          </BaseCard>
        </div>
      </div>

      <!-- ── PLATAFORMA SIF ── -->
      <div v-if="activeTab === 'plataforma'">
        <BaseCard>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="font-semibold text-gray-900">Identificación SIF de la plataforma</h3>
              <span v-if="!canManage" class="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                <BaseIcon name="LockClosedIcon" class="h-3 w-3" />
                Solo lectura
              </span>
            </div>
          </template>

          <!-- Info banner -->
          <div class="flex items-start gap-3 p-4 mb-6 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-800">
            <BaseIcon name="InformationCircleIcon" class="h-5 w-5 shrink-0 mt-0.5 text-blue-500" />
            <div>
              <p class="font-semibold">Configuración global — compartida por todas las empresas</p>
              <p class="mt-1">
                El <strong>IdSistemaInformatico</strong> identifica el software ante la AEAT en cada envío de factura.
                Es único para toda la plataforma y lo obtienes al presentar la Declaración Responsable.
                Cada empresa firma con su propio certificado pero comparte este identificador de software.
              </p>
            </div>
          </div>

          <div class="space-y-5">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
              <BaseInputGroup
                label="Nombre del software (NombreSistemaInformatico)"
                :error="platformErrors.software_name"
                required
              >
                <BaseInput
                  v-model="platformForm.software_name"
                  :disabled="!canManage"
                  type="text"
                  placeholder="Crater VERI*FACTU"
                  :invalid="!!platformErrors.software_name"
                />
              </BaseInputGroup>

              <BaseInputGroup
                label="Versión"
                :error="platformErrors.software_version"
                required
              >
                <BaseInput
                  v-model="platformForm.software_version"
                  :disabled="!canManage"
                  type="text"
                  placeholder="1.0.0"
                  class="font-mono"
                  :invalid="!!platformErrors.software_version"
                />
              </BaseInputGroup>
            </div>

            <fieldset class="border border-gray-200 rounded-lg p-4">
              <legend class="px-2 text-sm font-medium text-gray-700">Desarrollador del SIF</legend>
              <p class="mt-1 mb-4 text-xs text-gray-500">
                Los datos del desarrollador que aparecen en el bloque <span class="font-mono">SistemaInformatico</span> de cada XML enviado a la AEAT.
              </p>
              <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <BaseInputGroup
                  label="Nombre o razón social (NombreRazon)"
                  :error="platformErrors.vendor_name"
                  required
                >
                  <BaseInput
                    v-model="platformForm.vendor_name"
                    :disabled="!canManage"
                    type="text"
                    placeholder="Tu empresa desarrolladora S.L."
                    :invalid="!!platformErrors.vendor_name"
                  />
                </BaseInputGroup>

                <BaseInputGroup
                  label="NIF del desarrollador"
                  :error="platformErrors.vendor_tax_id"
                  required
                >
                  <BaseInput
                    v-model="platformForm.vendor_tax_id"
                    :disabled="!canManage"
                    type="text"
                    placeholder="B12345678"
                    class="font-mono uppercase"
                    :invalid="!!platformErrors.vendor_tax_id"
                  />
                </BaseInputGroup>
              </div>
            </fieldset>

            <fieldset class="border border-gray-200 rounded-lg p-4">
              <legend class="px-2 text-sm font-medium text-gray-700">Registro AEAT</legend>
              <p class="mt-1 mb-4 text-xs text-gray-500">
                El <strong>IdSistemaInformatico</strong> se obtiene al presentar la Declaración Responsable ante la AEAT.
                Puedes usar cualquier identificador durante desarrollo (modo <span class="font-mono">shadow</span>).
                Es obligatorio para los modos <span class="font-mono">aeat_sandbox</span> y <span class="font-mono">aeat_production</span>.
              </p>
              <div class="max-w-sm">
                <BaseInputGroup
                  label="IdSistemaInformatico"
                  :error="platformErrors.software_id"
                  required
                >
                  <BaseInput
                    v-model="platformForm.software_id"
                    :disabled="!canManage"
                    type="text"
                    placeholder="CRATER-VF-01"
                    class="font-mono"
                    :invalid="!!platformErrors.software_id"
                  />
                </BaseInputGroup>
              </div>
            </fieldset>

            <div v-if="canManage" class="flex items-center gap-3 pt-2 border-t border-gray-100">
              <BaseButton
                variant="primary"
                :loading="savingPlatform"
                @click="onSavePlatform"
              >
                <template #left="slotProps">
                  <BaseIcon name="SaveIcon" :class="slotProps.class" />
                </template>
                {{ $t('general.save') }}
              </BaseButton>
              <span v-if="savePlatformSuccess" class="text-sm text-green-600">Guardado correctamente.</span>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- ── CONFIGURACIÓN EMPRESA ── -->
      <div v-if="activeTab === 'configuracion'">
        <BaseCard>
          <template #header>
            <div class="flex items-center justify-between">
              <h3 class="font-semibold text-gray-900">Configuración de la instalación</h3>
              <span v-if="!canManage" class="inline-flex items-center gap-1 text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">
                <BaseIcon name="LockClosedIcon" class="h-3 w-3" />
                Solo lectura
              </span>
            </div>
          </template>

          <div v-if="!installation && !canManage" class="text-sm text-gray-500">
            Sin instalación. Necesitas permiso de gestión para crearla.
          </div>

          <div class="space-y-6">
            <!-- Toggles -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 bg-gray-50">
                <div>
                  <p class="text-sm font-medium text-gray-900">Habilitado</p>
                  <p class="mt-0.5 text-xs text-gray-500">Activa o desactiva el módulo VERI*FACTU para esta empresa.</p>
                </div>
                <input
                  v-model="configForm.enabled"
                  type="checkbox"
                  :disabled="!canManage"
                  class="h-5 w-5 rounded text-primary-600 border-gray-300 cursor-pointer disabled:opacity-50"
                />
              </div>

              <div class="flex items-center justify-between p-4 rounded-lg border border-gray-200 bg-gray-50">
                <div>
                  <p class="text-sm font-medium text-gray-900">Envío a AEAT</p>
                  <p class="mt-0.5 text-xs text-gray-500">Permite el envío real de registros al servicio de la AEAT.</p>
                </div>
                <input
                  v-model="configForm.submission_enabled"
                  type="checkbox"
                  :disabled="!canManage"
                  class="h-5 w-5 rounded text-primary-600 border-gray-300 cursor-pointer disabled:opacity-50"
                />
              </div>
            </div>

            <!-- Mode + Environment -->
            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
              <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">Modo de operación</label>
                <select
                  v-model="configForm.mode"
                  :disabled="!canManage"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-500"
                >
                  <option value="shadow">shadow — registro local, sin envío</option>
                  <option value="aeat_sandbox">aeat_sandbox — entorno de pruebas AEAT</option>
                  <option value="aeat_production">aeat_production — producción AEAT</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">
                  <strong>shadow:</strong> solo registra localmente.
                  <strong>sandbox/production:</strong> requiere certificado digital.
                </p>
              </div>

              <div>
                <label class="block mb-1 text-sm font-medium text-gray-700">Entorno</label>
                <select
                  v-model="configForm.environment"
                  :disabled="!canManage"
                  class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500 disabled:bg-gray-100 disabled:text-gray-500"
                >
                  <option value="local">local</option>
                  <option value="sandbox">sandbox</option>
                  <option value="production">production</option>
                </select>
              </div>
            </div>

            <!-- Issuer -->
            <fieldset class="border border-gray-200 rounded-lg p-4">
              <legend class="px-2 text-sm font-medium text-gray-700">Datos del emisor (AEAT)</legend>
              <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <BaseInputGroup
                    label="Nombre o razón social"
                    :error="configErrors.issuer_name"
                    required
                  >
                    <BaseInput
                      v-model="configForm.issuer_name"
                      :disabled="!canManage"
                      type="text"
                      placeholder="Mi Empresa S.L."
                      :invalid="!!configErrors.issuer_name"
                    />
                  </BaseInputGroup>
                </div>
                <div>
                  <BaseInputGroup
                    label="NIF / CIF"
                    :error="configErrors.issuer_tax_id"
                    required
                  >
                    <BaseInput
                      v-model="configForm.issuer_tax_id"
                      :disabled="!canManage"
                      type="text"
                      placeholder="B12345678"
                      class="font-mono"
                      :invalid="!!configErrors.issuer_tax_id"
                    />
                  </BaseInputGroup>
                </div>
                <BaseInputGroup label="NumeroInstalacion">
                  <BaseInput
                    v-model="configForm.installation_number"
                    :disabled="!canManage"
                    type="text"
                    placeholder="1"
                    class="font-mono"
                  />
                </BaseInputGroup>
              </div>
            </fieldset>

            <!-- Software info (readonly) -->
            <fieldset class="border border-gray-100 rounded-lg p-4 bg-gray-50">
              <legend class="px-2 text-sm font-medium text-gray-400">Software (gestionado por el sistema)</legend>
              <div class="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                  <label class="block mb-1 text-xs text-gray-500">Nombre del software</label>
                  <p class="text-sm font-mono text-gray-700">{{ installation?.software_name || '—' }}</p>
                </div>
                <div>
                  <label class="block mb-1 text-xs text-gray-500">Versión</label>
                  <p class="text-sm font-mono text-gray-700">{{ installation?.software_version || '—' }}</p>
                </div>
              </div>
            </fieldset>

            <!-- Warning: production mode -->
            <div v-if="configForm.mode === 'aeat_production'"
              class="flex items-start gap-3 p-4 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
              <BaseIcon name="ExclamationIcon" class="h-5 w-5 shrink-0 mt-0.5 text-red-500" />
              <div>
                <p class="font-semibold">Modo producción AEAT activo</p>
                <p class="mt-0.5">Las facturas emitidas se enviarán con firma QR a la AEAT y tendrán validez legal. Asegúrate de que el certificado sea válido y de que el NIF/nombre coincidan exactamente con los datos de la AEAT.</p>
              </div>
            </div>

            <div v-if="canManage" class="flex items-center gap-3 pt-2 border-t border-gray-100">
              <BaseButton
                variant="primary"
                :loading="saving"
                @click="onSaveConfig"
              >
                <template #left="slotProps">
                  <BaseIcon name="SaveIcon" :class="slotProps.class" />
                </template>
                {{ $t('general.save') }}
              </BaseButton>
              <span v-if="saveSuccess" class="text-sm text-green-600">Guardado correctamente.</span>
            </div>
          </div>
        </BaseCard>
      </div>

      <!-- ── CERTIFICADOS ── -->
      <div v-if="activeTab === 'certificados'">
        <BaseCard>
          <template #header><h3 class="font-semibold text-gray-900">Certificado digital AEAT</h3></template>

          <!-- Current status -->
          <div v-if="installation?.has_certificate"
            class="flex items-start gap-4 p-4 mb-5 rounded-lg bg-green-50 border border-green-200">
            <BaseIcon name="ShieldCheckIcon" class="h-6 w-6 text-green-600 shrink-0 mt-0.5" />
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-green-800">Certificado configurado</p>
              <p class="mt-0.5 text-sm text-green-700 truncate">
                {{ installation.cert_filename }}
                <span class="ml-2 px-1.5 py-0.5 text-xs font-mono bg-green-200 text-green-900 rounded">{{ installation.cert_type?.toUpperCase() }}</span>
              </p>
            </div>
            <BaseButton
              v-if="canManage"
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

          <!-- Type hints -->
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

          <!-- Upload form (only for managers) -->
          <div v-if="canManage" class="space-y-4">
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
              <template v-if="uploading">Subiendo...</template>
              <template v-else>
                {{ installation?.has_certificate ? 'Reemplazar certificado' : 'Subir certificado' }}
              </template>
            </BaseButton>
          </div>

          <div v-else class="text-sm text-gray-500 mt-2">
            Necesitas permisos de gestión para subir o eliminar certificados.
          </div>
        </BaseCard>
      </div>

      <!-- ── DECLARACIONES ── -->
      <div v-if="activeTab === 'declaraciones'">

        <!-- Explanation -->
        <div class="flex items-start gap-3 p-4 mb-5 rounded-lg bg-blue-50 border border-blue-200 text-sm text-blue-800">
          <BaseIcon name="InformationCircleIcon" class="h-5 w-5 shrink-0 mt-0.5 text-blue-500" />
          <div>
            <p class="font-semibold">¿Qué es la Declaración Responsable?</p>
            <p class="mt-1">
              Antes de usar VERI*FACTU en producción debes declarar el software ante la AEAT.
              Genera el borrador aquí, revisa los datos y preséntalo en la
              <strong>Sede Electrónica de la AEAT</strong> con tu certificado digital de desarrollador.
              Una vez aceptado, marca la declaración como presentada y luego como aceptada.
            </p>
          </div>
        </div>

        <!-- Create button -->
        <div v-if="canManage" class="flex justify-end mb-4">
          <BaseButton
            variant="primary-outline"
            :loading="creatingDeclaration"
            @click="onCreateDeclaration"
          >
            <template #left="slotProps">
              <BaseIcon name="PlusIcon" :class="slotProps.class" />
            </template>
            Nueva declaración
          </BaseButton>
        </div>

        <!-- Empty state -->
        <BaseCard v-if="!declarations.length">
          <div class="py-10 text-center">
            <BaseIcon name="DocumentTextIcon" class="mx-auto h-10 w-10 text-gray-300 mb-3" />
            <p class="text-sm font-medium text-gray-600">No hay declaraciones registradas</p>
            <p class="mt-1 text-xs text-gray-400">
              Completa la Plataforma SIF y crea tu primera declaración responsable.
            </p>
          </div>
        </BaseCard>

        <!-- Declaration cards -->
        <div v-else class="space-y-4">
          <BaseCard
            v-for="dec in declarations"
            :key="dec.id"
          >
            <!-- Card header -->
            <template #header>
              <div class="flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-3">
                  <span class="text-sm font-mono text-gray-400">#{{ dec.id }}</span>
                  <span class="font-semibold text-gray-900">
                    {{ dec.software_name }} <span class="font-mono text-gray-500">v{{ dec.software_version }}</span>
                  </span>
                  <span :class="declarationStatusClass(dec.status)"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold">
                    <span :class="declarationDotClass(dec.status)" class="h-1.5 w-1.5 rounded-full inline-block"></span>
                    {{ declarationStatusLabel(dec.status) }}
                  </span>
                </div>
                <span class="text-xs text-gray-400">Creada {{ dec.created_at }}</span>
              </div>
            </template>

            <!-- Payload details -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3 text-sm mb-5">
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Nombre del software</p>
                <p class="font-medium text-gray-800">{{ dec.declaration_payload?.software_name || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Versión</p>
                <p class="font-mono text-gray-800">{{ dec.declaration_payload?.software_version || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">IdSistemaInformatico</p>
                <p class="font-mono text-gray-800">{{ dec.declaration_payload?.software_id || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">Desarrollador (NombreRazon)</p>
                <p class="text-gray-800">{{ dec.declaration_payload?.vendor_name || '—' }}</p>
              </div>
              <div>
                <p class="text-xs text-gray-400 mb-0.5">NIF del desarrollador</p>
                <p class="font-mono text-gray-800">{{ dec.declaration_payload?.vendor_tax_id || '—' }}</p>
              </div>
              <div v-if="dec.declared_at">
                <p class="text-xs text-gray-400 mb-0.5">Presentada el</p>
                <p class="text-gray-800">{{ dec.declared_at }}</p>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="dec.declaration_payload?.notes" class="mb-4 p-3 bg-gray-50 rounded-md border border-gray-100 text-sm text-gray-600">
              <span class="font-medium">Notas:</span> {{ dec.declaration_payload.notes }}
            </div>

            <!-- Action buttons per status -->
            <div v-if="canManage" class="flex flex-wrap gap-2 pt-3 border-t border-gray-100">

              <!-- DRAFT → SUBMITTED -->
              <template v-if="dec.status === 'DRAFT'">
                <div class="flex items-center gap-2 flex-wrap w-full">
                  <input
                    v-model="declarationNotes[dec.id]"
                    type="text"
                    class="flex-1 min-w-48 px-3 py-1.5 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary-500"
                    placeholder="Referencia AEAT o notas (opcional)"
                  />
                  <BaseButton
                    variant="primary"
                    size="sm"
                    :loading="updatingDeclaration[dec.id]"
                    @click="onUpdateDeclaration(dec, 'SUBMITTED')"
                  >
                    Marcar como presentada
                  </BaseButton>
                </div>
                <p class="text-xs text-gray-400 w-full">
                  Presenta primero la declaración en la Sede Electrónica de la AEAT y luego marca como presentada.
                </p>
              </template>

              <!-- SUBMITTED → ACCEPTED or REJECTED -->
              <template v-if="dec.status === 'SUBMITTED'">
                <BaseButton
                  variant="primary"
                  size="sm"
                  :loading="updatingDeclaration[dec.id] === 'ACCEPTED'"
                  @click="onUpdateDeclaration(dec, 'ACCEPTED')"
                >
                  Marcar como aceptada
                </BaseButton>
                <BaseButton
                  variant="danger-outline"
                  size="sm"
                  :loading="updatingDeclaration[dec.id] === 'REJECTED'"
                  @click="onUpdateDeclaration(dec, 'REJECTED')"
                >
                  Marcar como rechazada
                </BaseButton>
              </template>

              <!-- REJECTED → DRAFT (re-draft) -->
              <template v-if="dec.status === 'REJECTED'">
                <BaseButton
                  variant="primary-outline"
                  size="sm"
                  :loading="updatingDeclaration[dec.id]"
                  @click="onUpdateDeclaration(dec, 'DRAFT')"
                >
                  Reabrir borrador
                </BaseButton>
              </template>

              <!-- ACCEPTED — no actions, just a note -->
              <template v-if="dec.status === 'ACCEPTED'">
                <p class="text-xs text-green-600">
                  Declaración aceptada. El IdSistemaInformatico de esta declaración es válido para envíos a la AEAT.
                </p>
              </template>
            </div>
          </BaseCard>
        </div>
      </div>

    </div>
  </BasePage>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, reactive, ref } from 'vue'
import SectionNav from '@/scripts/admin/views/verifactu/components/SectionNav.vue'
import { handleError } from '@/scripts/helpers/error-handling'
import { useNotificationStore } from '@/scripts/stores/notification'
import { useDialogStore } from '@/scripts/stores/dialog'
import { useUserStore } from '@/scripts/admin/stores/user'
import { useI18n } from 'vue-i18n'
import abilities from '@/scripts/admin/stub/abilities'

const { t } = useI18n()
const notificationStore = useNotificationStore()
const dialogStore = useDialogStore()
const userStore = useUserStore()

const loading = ref(false)
const saving = ref(false)
const savingPlatform = ref(false)
const savePlatformSuccess = ref(false)
const saveSuccess = ref(false)
const creatingDeclaration = ref(false)
const updatingDeclaration = reactive({}) // { [id]: status string | true }
const declarationNotes = reactive({})    // { [id]: string }
const uploading = ref(false)
const deletingCert = ref(false)

const installation = ref(null)
const declarations = ref([])
const platform = ref(null)
const activeTab = ref('resumen')

const fileInput = ref(null)
const selectedFile = ref(null)
const selectedFileName = ref('')
const certPassword = ref('')
const showPassword = ref(false)
const uploadError = ref('')

// Global platform SIF form (shared across all companies)
const platformForm = reactive({
  software_name: '',
  software_version: '',
  vendor_name: '',
  vendor_tax_id: '',
  software_id: '',
})

const platformErrors = reactive({
  software_name: '',
  software_version: '',
  vendor_name: '',
  vendor_tax_id: '',
  software_id: '',
})

// Per-company installation form
const configForm = reactive({
  enabled: true,
  submission_enabled: false,
  mode: 'shadow',
  environment: 'local',
  issuer_name: '',
  issuer_tax_id: '',
  installation_number: '1',
})

const configErrors = reactive({
  issuer_name: '',
  issuer_tax_id: '',
})

const tabs = [
  { id: 'resumen', label: 'Resumen' },
  { id: 'plataforma', label: 'Plataforma SIF' },
  { id: 'configuracion', label: 'Configuración empresa' },
  { id: 'certificados', label: 'Certificados' },
  { id: 'declaraciones', label: 'Declaraciones' },
]

const canManage = computed(() =>
  userStore.hasAbilities(abilities.MANAGE_VERIFACTU)
)

// ── Helpers ──────────────────────────────────────────────────────────────────

function modeBadgeClass(mode) {
  if (mode === 'aeat_production') return 'bg-red-100 text-red-800'
  if (mode === 'aeat_sandbox') return 'bg-yellow-100 text-yellow-800'
  return 'bg-gray-100 text-gray-600'
}

function envBadgeClass(env) {
  if (env === 'production') return 'bg-red-100 text-red-700'
  if (env === 'sandbox') return 'bg-blue-100 text-blue-700'
  return 'bg-gray-100 text-gray-600'
}

function declarationBadgeClass(status) {
  return declarationStatusClass(status)
}

function declarationStatusClass(status) {
  if (status === 'ACCEPTED') return 'bg-green-100 text-green-800'
  if (status === 'REJECTED') return 'bg-red-100 text-red-700'
  if (status === 'SUBMITTED') return 'bg-blue-100 text-blue-800'
  return 'bg-gray-100 text-gray-600'
}

function declarationDotClass(status) {
  if (status === 'ACCEPTED') return 'bg-green-500'
  if (status === 'REJECTED') return 'bg-red-500'
  if (status === 'SUBMITTED') return 'bg-blue-500'
  return 'bg-gray-400'
}

function declarationStatusLabel(status) {
  const map = { DRAFT: 'Borrador', SUBMITTED: 'Presentada', ACCEPTED: 'Aceptada', REJECTED: 'Rechazada' }
  return map[status] || status
}

function syncPlatformForm() {
  if (!platform.value) return
  platformForm.software_name   = platform.value.software_name   || ''
  platformForm.software_version= platform.value.software_version || ''
  platformForm.vendor_name     = platform.value.vendor_name     || ''
  platformForm.vendor_tax_id   = platform.value.vendor_tax_id   || ''
  platformForm.software_id     = platform.value.software_id     || ''
}

function syncFormFromInstallation() {
  if (!installation.value) return
  configForm.enabled             = installation.value.enabled
  configForm.submission_enabled  = installation.value.submission_enabled
  configForm.mode                = installation.value.mode || 'shadow'
  configForm.environment         = installation.value.environment || 'local'
  configForm.issuer_name         = installation.value.issuer_name || ''
  configForm.issuer_tax_id       = installation.value.issuer_tax_id || ''
  configForm.installation_number = installation.value.installation_number || '1'
}

function validateConfig() {
  configErrors.issuer_name = ''
  configErrors.issuer_tax_id = ''
  let valid = true
  if (!configForm.issuer_name.trim()) {
    configErrors.issuer_name = 'El nombre es obligatorio.'
    valid = false
  }
  if (!configForm.issuer_tax_id.trim()) {
    configErrors.issuer_tax_id = 'El NIF/CIF es obligatorio.'
    valid = false
  } else if (!/^[A-Za-z0-9]{7,15}$/.test(configForm.issuer_tax_id)) {
    configErrors.issuer_tax_id = 'El NIF/CIF debe tener entre 7 y 15 caracteres alfanuméricos.'
    valid = false
  }
  return valid
}

async function onSavePlatform() {
  Object.keys(platformErrors).forEach(k => (platformErrors[k] = ''))
  savePlatformSuccess.value = false
  savingPlatform.value = true
  try {
    const { data } = await axios.put('/api/v1/verifactu/platform', {
      software_name:    platformForm.software_name.trim(),
      software_version: platformForm.software_version.trim(),
      vendor_name:      platformForm.vendor_name.trim(),
      vendor_tax_id:    platformForm.vendor_tax_id.trim().toUpperCase(),
      software_id:      platformForm.software_id.trim(),
    })
    platform.value = data.platform
    syncPlatformForm()
    savePlatformSuccess.value = true
    notificationStore.showNotification({ type: 'success', message: 'Configuración de plataforma SIF guardada.' })
  } catch (error) {
    if (error.response?.data?.errors) {
      const errs = error.response.data.errors
      Object.keys(platformErrors).forEach(k => { platformErrors[k] = errs[k]?.[0] || '' })
    } else {
      handleError(error)
    }
  } finally {
    savingPlatform.value = false
  }
}

// ── API calls ─────────────────────────────────────────────────────────────────

async function loadSetup() {
  loading.value = true
  try {
    const [setupRes, platformRes] = await Promise.all([
      axios.get('/api/v1/verifactu/setup'),
      axios.get('/api/v1/verifactu/platform'),
    ])
    installation.value = setupRes.data.installation || null
    declarations.value = setupRes.data.declarations || []
    platform.value = platformRes.data.platform || null
    syncFormFromInstallation()
    syncPlatformForm()
  } catch (error) {
    handleError(error)
  } finally {
    loading.value = false
  }
}

async function onSaveConfig() {
  saveSuccess.value = false
  if (!validateConfig()) return

  saving.value = true
  try {
    const { data } = await axios.put('/api/v1/verifactu/setup', {
      mode:                configForm.mode,
      enabled:             configForm.enabled,
      submission_enabled:  configForm.submission_enabled,
      environment:         configForm.environment,
      issuer_name:         configForm.issuer_name.trim(),
      issuer_tax_id:       configForm.issuer_tax_id.trim().toUpperCase(),
      installation_number: configForm.installation_number.trim() || '1',
    })

    installation.value = data.installation
    syncFormFromInstallation()
    saveSuccess.value = true

    notificationStore.showNotification({
      type: 'success',
      message: 'Configuración VERI*FACTU guardada.',
    })
  } catch (error) {
    if (error.response?.data?.errors) {
      const errs = error.response.data.errors
      configErrors.issuer_name   = errs.issuer_name?.[0]   || ''
      configErrors.issuer_tax_id = errs.issuer_tax_id?.[0] || ''
    } else {
      handleError(error)
    }
  } finally {
    saving.value = false
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
    const { data } = await axios.post('/api/v1/verifactu/certificate', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    if (installation.value) {
      installation.value.has_certificate = data.has_certificate
      installation.value.cert_filename = data.cert_filename
      installation.value.cert_type = data.cert_type
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
    uploadError.value = error.response?.data?.message || 'Error al subir el certificado.'
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

async function onCreateDeclaration() {
  creatingDeclaration.value = true
  try {
    const { data } = await axios.post('/api/v1/verifactu/declarations')
    declarations.value.unshift(data.declaration)
    notificationStore.showNotification({ type: 'success', message: 'Borrador de declaración creado.' })
  } catch (error) {
    const msg = error.response?.data?.message
    if (msg) {
      notificationStore.showNotification({ type: 'error', message: msg })
    } else {
      handleError(error)
    }
  } finally {
    creatingDeclaration.value = false
  }
}

async function onUpdateDeclaration(declaration, newStatus) {
  updatingDeclaration[declaration.id] = newStatus
  try {
    const { data } = await axios.put(`/api/v1/verifactu/declarations/${declaration.id}`, {
      status: newStatus,
      notes: declarationNotes[declaration.id] || undefined,
    })
    const idx = declarations.value.findIndex(d => d.id === declaration.id)
    if (idx !== -1) declarations.value[idx] = data.declaration
    declarationNotes[declaration.id] = ''
    notificationStore.showNotification({
      type: 'success',
      message: `Declaración marcada como ${declarationStatusLabel(newStatus).toLowerCase()}.`,
    })
  } catch (error) {
    const msg = error.response?.data?.message
    if (msg) {
      notificationStore.showNotification({ type: 'error', message: msg })
    } else {
      handleError(error)
    }
  } finally {
    delete updatingDeclaration[declaration.id]
  }
}

onMounted(() => {
  loadSetup()
})
</script>
