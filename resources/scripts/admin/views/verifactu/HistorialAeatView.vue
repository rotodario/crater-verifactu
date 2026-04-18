<template>
  <BasePage>
    <BasePageHeader :title="$t('verifactu.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Historial AEAT" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <!-- Filtros -->
    <BaseCard container-class="px-5 py-5 mt-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Consultar historial remoto AEAT</h3>
      <p class="text-sm text-gray-500 mb-5">
        Consulta qué registros constan realmente en AEAT para un período dado.
        Solo disponible en modos <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">aeat_sandbox</code> y <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">aeat_production</code>.
      </p>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">Ejercicio <span class="text-red-500">*</span></label>
          <input
            v-model="filters.ejercicio"
            type="text"
            maxlength="4"
            placeholder="2026"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"
          />
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">Período <span class="text-red-500">*</span></label>
          <select v-model="filters.periodo" class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">-- Seleccionar --</option>
            <option v-for="p in periodos" :key="p.value" :value="p.value">{{ p.label }}</option>
          </select>
        </div>
        <div>
          <label class="block mb-1 text-sm font-medium text-gray-600">Número de factura (opcional)</label>
          <input
            v-model="filters.num_serie"
            type="text"
            placeholder="010226"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"
          />
        </div>
        <div class="flex items-end">
          <BaseButton
            variant="primary"
            :loading="loading"
            :disabled="!canQuery"
            class="w-full"
            @click="consultar(null)"
          >
            <template #left="slotProps">
              <BaseIcon name="SearchIcon" :class="slotProps.class" />
            </template>
            Consultar AEAT
          </BaseButton>
        </div>
      </div>

      <div v-if="errorMsg" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
        {{ errorMsg }}
      </div>
    </BaseCard>

    <!-- Resultados -->
    <BaseCard v-if="queried" container-class="px-5 py-5 mt-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">
            Registros AEAT
            <span v-if="result" class="ml-2 text-sm font-normal text-gray-500">
              {{ result.records.length }} registro{{ result.records.length !== 1 ? 's' : '' }}
              — Ejercicio {{ filters.ejercicio }}/{{ filters.periodo }}
            </span>
          </h3>
          <div class="flex items-center gap-3 mt-1">
            <span
              :class="modeBadgeClass"
              class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
            >
              {{ result?.mode ?? '—' }}
            </span>
            <span v-if="result?.resultado === 'SinDatos'" class="text-sm text-gray-400">Sin datos en AEAT para este período.</span>
            <span v-else-if="result?.hay_mas_paginas" class="text-sm text-amber-600">Hay más páginas disponibles</span>
          </div>
        </div>
        <div class="flex gap-2">
          <BaseButton
            v-if="result?.hay_mas_paginas"
            variant="primary-outline"
            size="sm"
            :loading="loading"
            @click="consultar(result.clave_paginacion)"
          >
            Página siguiente
          </BaseButton>
          <BaseButton variant="gray-outline" size="sm" @click="toggleXml">
            {{ showXml ? 'Ocultar XML' : 'Ver XML' }}
          </BaseButton>
        </div>
      </div>

      <!-- XML debug -->
      <div v-if="showXml && result" class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Request XML</p>
          <pre class="text-xs bg-gray-50 border border-gray-200 rounded p-3 overflow-auto max-h-48">{{ result.request_xml }}</pre>
        </div>
        <div>
          <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Response XML</p>
          <pre class="text-xs bg-gray-50 border border-gray-200 rounded p-3 overflow-auto max-h-48">{{ result.response_xml }}</pre>
        </div>
      </div>

      <!-- Tabla -->
      <div v-if="result?.records?.length" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Factura</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
              <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado AEAT</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Local</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Huella</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Presentación</th>
              <th class="px-3 py-3"></th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            <template v-for="(rec, idx) in result.records" :key="idx">
              <tr
                class="hover:bg-gray-50 cursor-pointer"
                :class="{ 'bg-primary-50': expandedIdx === idx }"
                @click="toggleExpand(idx)"
              >
                <td class="px-3 py-3 font-mono font-medium text-gray-900">{{ rec.invoice_number }}</td>
                <td class="px-3 py-3 text-gray-600">{{ rec.invoice_date }}</td>
                <td class="px-3 py-3 text-gray-500">{{ rec.tipo_factura ?? '—' }}</td>
                <td class="px-3 py-3 text-right text-gray-700 font-mono">
                  {{ rec.importe_total ? rec.importe_total + ' €' : '—' }}
                </td>
                <td class="px-3 py-3">
                  <span :class="estadoBadgeClass(rec.estado_registro)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                    {{ rec.estado_registro }}
                  </span>
                  <span v-if="rec.error_code" class="ml-1 text-xs text-red-500">{{ rec.error_code }}</span>
                </td>
                <td class="px-3 py-3">
                  <template v-if="rec.local">
                    <span v-if="rec.local.hash_match === true" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                      ✓ Coincide
                    </span>
                    <span v-else-if="rec.local.hash_match === false" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                      ✗ Difiere
                    </span>
                    <span v-else class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">
                      Local #{{ rec.local.id }}
                    </span>
                  </template>
                  <span v-else class="text-xs text-gray-400">Sin registro local</span>
                </td>
                <td class="px-3 py-3 font-mono text-xs text-gray-400 truncate max-w-xs" :title="rec.huella">
                  {{ rec.huella ? rec.huella.substring(0, 16) + '…' : '—' }}
                </td>
                <td class="px-3 py-3 text-xs text-gray-500">{{ formatTs(rec.timestamp_presentacion) }}</td>
                <td class="px-3 py-3 text-right">
                  <BaseIcon
                    :name="expandedIdx === idx ? 'ChevronUpIcon' : 'ChevronDownIcon'"
                    class="w-4 h-4 text-gray-400"
                  />
                </td>
              </tr>
              <!-- Fila de detalle expandida -->
              <tr v-if="expandedIdx === idx" :key="'detail-' + idx">
                <td colspan="9" class="px-4 py-4 bg-gray-50 border-b border-gray-200">
                  <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">

                    <!-- Datos AEAT -->
                    <div>
                      <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Datos AEAT</p>
                      <dl class="space-y-1 text-sm">
                        <div class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Estado:</dt><dd class="text-gray-900">{{ rec.estado_registro }}</dd></div>
                        <div v-if="rec.error_code" class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Error {{ rec.error_code }}:</dt><dd class="text-red-600">{{ rec.error_desc }}</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Tipo factura:</dt><dd class="text-gray-900">{{ rec.tipo_factura ?? '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Importe total:</dt><dd class="text-gray-900 font-mono">{{ rec.importe_total ?? '—' }} €</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Cuota total:</dt><dd class="text-gray-900 font-mono">{{ rec.cuota_total ?? '—' }} €</dd></div>
                        <div v-if="rec.destinatario_nombre" class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Destinatario:</dt><dd class="text-gray-900">{{ rec.destinatario_nombre }} <span v-if="rec.destinatario_nif" class="text-gray-500">({{ rec.destinatario_nif }})</span></dd></div>
                        <div v-if="rec.descripcion" class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Descripción:</dt><dd class="text-gray-700 text-xs">{{ rec.descripcion }}</dd></div>
                      </dl>
                    </div>

                    <!-- Timestamps / IDs -->
                    <div>
                      <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Presentación</p>
                      <dl class="space-y-1 text-sm">
                        <div class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">IdPetición:</dt><dd class="font-mono text-xs text-gray-700">{{ rec.id_peticion ?? '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Timestamp:</dt><dd class="text-gray-700">{{ rec.timestamp_presentacion ?? '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">Última modif.:</dt><dd class="text-gray-700">{{ rec.timestamp_modificacion ?? '—' }}</dd></div>
                        <div class="flex gap-2"><dt class="text-gray-500 w-36 shrink-0">FechaHoraHuso:</dt><dd class="font-mono text-xs text-gray-700">{{ rec.fecha_hora_huso ?? '—' }}</dd></div>
                      </dl>
                    </div>

                    <!-- Huella y comparación local -->
                    <div>
                      <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Huella y registro local</p>
                      <dl class="space-y-1 text-sm">
                        <div class="flex gap-2 flex-wrap">
                          <dt class="text-gray-500 w-36 shrink-0">Huella AEAT:</dt>
                          <dd class="font-mono text-xs break-all text-gray-700">{{ rec.huella ?? '—' }}</dd>
                        </div>
                        <template v-if="rec.local">
                          <div class="flex gap-2 flex-wrap">
                            <dt class="text-gray-500 w-36 shrink-0">Hash local:</dt>
                            <dd class="font-mono text-xs break-all" :class="rec.local.hash_match === false ? 'text-red-600' : 'text-gray-700'">
                              {{ rec.local.hash }}
                            </dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Coincidencia:</dt>
                            <dd>
                              <span v-if="rec.local.hash_match === true" class="text-green-600 font-medium">✓ Coincide</span>
                              <span v-else-if="rec.local.hash_match === false" class="text-red-600 font-medium">✗ No coincide</span>
                              <span v-else class="text-gray-400">Sin huella remota</span>
                            </dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Record local ID:</dt>
                            <dd>
                              <router-link
                                :to="`/admin/verifactu/records/${rec.local.id}/view`"
                                class="text-primary-600 hover:underline text-xs"
                              >
                                #{{ rec.local.id }} ({{ rec.local.status }})
                              </router-link>
                            </dd>
                          </div>
                          <div v-if="rec.local.submission_id" class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Submission local:</dt>
                            <dd>
                              <router-link
                                :to="`/admin/verifactu/submissions/${rec.local.submission_id}/view`"
                                class="text-primary-600 hover:underline text-xs"
                              >
                                #{{ rec.local.submission_id }} ({{ rec.local.submission_status }})
                              </router-link>
                            </dd>
                          </div>
                        </template>
                        <div v-else class="text-sm text-gray-400">Sin registro local correspondiente.</div>
                      </dl>
                    </div>

                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div v-else-if="result?.resultado === 'ConDatos'" class="text-center py-8 text-gray-500 text-sm">
        No se encontraron registros para los filtros indicados.
      </div>

      <!-- Paginación -->
      <div v-if="result?.hay_mas_paginas" class="mt-4 flex justify-end">
        <BaseButton
          variant="primary-outline"
          :loading="loading"
          @click="consultar(result.clave_paginacion)"
        >
          Cargar siguiente página
        </BaseButton>
      </div>

      <!-- Nota último registro -->
      <div v-if="ultimoRegistro" class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-md text-sm text-blue-800">
        <strong>Último registro del período:</strong>
        Factura <strong>{{ ultimoRegistro.invoice_number }}</strong>
        ({{ ultimoRegistro.invoice_date }}) —
        Huella: <code class="text-xs">{{ ultimoRegistro.huella ? ultimoRegistro.huella.substring(0, 24) + '…' : 'N/A' }}</code>
        — Estado: {{ ultimoRegistro.estado_registro }}
      </div>
    </BaseCard>
  </BasePage>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { handleError } from '@/scripts/helpers/error-handling'
import SectionNav from './components/SectionNav.vue'

const loading     = ref(false)
const errorMsg    = ref(null)
const queried     = ref(false)
const result      = ref(null)
const expandedIdx = ref(null)
const showXml     = ref(false)

const filters = ref({
  ejercicio: new Date().getFullYear().toString(),
  periodo:   String(new Date().getMonth() + 1).padStart(2, '0'),
  num_serie: '',
})

const periodos = [
  { value: '01', label: 'Enero (01)' },
  { value: '02', label: 'Febrero (02)' },
  { value: '03', label: 'Marzo (03)' },
  { value: '04', label: 'Abril (04)' },
  { value: '05', label: 'Mayo (05)' },
  { value: '06', label: 'Junio (06)' },
  { value: '07', label: 'Julio (07)' },
  { value: '08', label: 'Agosto (08)' },
  { value: '09', label: 'Septiembre (09)' },
  { value: '10', label: 'Octubre (10)' },
  { value: '11', label: 'Noviembre (11)' },
  { value: '12', label: 'Diciembre (12)' },
  { value: '0A', label: 'Anual (0A)' },
]

const canQuery = computed(() =>
  filters.value.ejercicio.length === 4 && filters.value.periodo !== ''
)

const modeBadgeClass = computed(() => {
  const m = result.value?.mode
  if (m === 'aeat_production') return 'bg-red-100 text-red-700'
  if (m === 'aeat_sandbox') return 'bg-yellow-100 text-yellow-700'
  return 'bg-gray-100 text-gray-500'
})

// El último registro del listado — útil para diagnosticar encadenamiento
const ultimoRegistro = computed(() => {
  const recs = result.value?.records
  if (!recs || recs.length === 0) return null
  return recs[recs.length - 1]
})

function estadoBadgeClass(estado) {
  if (estado === 'Correcto') return 'bg-green-100 text-green-700'
  if (estado === 'AceptadoConErrores') return 'bg-yellow-100 text-yellow-700'
  if (estado === 'Incorrecto') return 'bg-red-100 text-red-700'
  if (estado === 'Anulado') return 'bg-gray-200 text-gray-600'
  return 'bg-gray-100 text-gray-500'
}

function formatTs(ts) {
  if (!ts) return '—'
  // "2026-04-17T19:31:59+02:00" → "17/04/2026 19:31"
  const m = ts.match(/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/)
  if (!m) return ts
  return `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}`
}

function toggleExpand(idx) {
  expandedIdx.value = expandedIdx.value === idx ? null : idx
}

function toggleXml() {
  showXml.value = !showXml.value
}

async function consultar(clavePaginacion = null) {
  if (!canQuery.value) return

  loading.value    = true
  errorMsg.value   = null
  expandedIdx.value = null

  const payload = {
    ejercicio:        filters.value.ejercicio,
    periodo:          filters.value.periodo,
    num_serie:        filters.value.num_serie || null,
    clave_paginacion: clavePaginacion,
  }

  try {
    const response = await axios.post('/api/v1/verifactu/historial', payload)
    const data = response.data

    if (!data.success) {
      errorMsg.value = data.error || 'Error desconocido'
      queried.value  = true
      result.value   = data
      return
    }

    // If paginating, append records instead of replacing
    if (clavePaginacion && result.value?.records) {
      result.value = {
        ...data,
        records: [...result.value.records, ...data.records],
      }
    } else {
      result.value = data
    }

    queried.value = true
    showXml.value = false
  } catch (error) {
    handleError(error)
    errorMsg.value = error?.response?.data?.error || 'Error al conectar con AEAT'
    queried.value  = true
  } finally {
    loading.value = false
  }
}
</script>
