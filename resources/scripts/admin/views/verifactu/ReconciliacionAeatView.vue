<template>
  <BasePage>
    <BasePageHeader :title="$t('verifactu.title')">
      <BaseBreadcrumb>
        <BaseBreadcrumbItem :title="$t('general.home')" to="/admin/dashboard" />
        <BaseBreadcrumbItem :title="$t('verifactu.title')" to="/admin/verifactu" />
        <BaseBreadcrumbItem title="Reconciliación AEAT" to="#" active />
      </BaseBreadcrumb>
    </BasePageHeader>

    <SectionNav />

    <!-- Filtros -->
    <BaseCard container-class="px-5 py-5 mt-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-1">Reconciliación AEAT</h3>
      <p class="text-sm text-gray-500 mb-5">
        Compara los registros reales de AEAT con los datos locales para detectar divergencias, errores de cadena y registros sin correspondencia.
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
        <div class="flex items-end gap-2">
          <BaseButton
            variant="primary"
            :loading="loading"
            :disabled="!canQuery"
            class="flex-1"
            @click="reconciliar(null)"
          >
            <template #left="slotProps">
              <BaseIcon name="SearchIcon" :class="slotProps.class" />
            </template>
            Reconciliar
          </BaseButton>
          <BaseButton
            v-if="queried"
            variant="gray-outline"
            :disabled="loading"
            @click="resetear"
          >
            Limpiar
          </BaseButton>
        </div>
      </div>

      <!-- Filtro por estado de reconciliación -->
      <div v-if="queried && entries.length" class="mt-4 flex flex-wrap gap-2 items-center">
        <span class="text-xs text-gray-500 font-medium">Filtrar estado:</span>
        <button
          class="px-2 py-1 text-xs rounded-full border transition-colors"
          :class="activeStateFilter === null ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'"
          @click="activeStateFilter = null"
        >
          Todos ({{ entries.length }})
        </button>
        <button
          v-for="(count, state) in result.stats"
          :key="state"
          class="px-2 py-1 text-xs rounded-full border transition-colors"
          :class="activeStateFilter === state
            ? 'border-primary-500 bg-primary-50 text-primary-700'
            : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300'"
          @click="activeStateFilter = activeStateFilter === state ? null : state"
        >
          <span :class="reconStateDot(state)" class="inline-block w-2 h-2 rounded-full mr-1"></span>
          {{ state }} ({{ count }})
        </button>
      </div>

      <div v-if="errorMsg" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-md text-sm text-red-700">
        {{ errorMsg }}
      </div>
    </BaseCard>

    <!-- Stats resumen -->
    <div v-if="queried && result" class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
      <div
        v-for="(count, state) in result.stats"
        :key="state"
        class="bg-white border rounded-lg px-4 py-3 flex flex-col items-center cursor-pointer hover:border-primary-300 transition-colors"
        :class="activeStateFilter === state ? 'border-primary-400 bg-primary-50' : 'border-gray-200'"
        @click="activeStateFilter = activeStateFilter === state ? null : state"
      >
        <span :class="reconStateBadgeClass(state)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mb-1">{{ state }}</span>
        <span class="text-2xl font-bold text-gray-900">{{ count }}</span>
      </div>
    </div>

    <!-- Resultados -->
    <BaseCard v-if="queried" container-class="px-5 py-5 mt-4">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">
            Entradas reconciliadas
            <span v-if="filteredEntries.length !== entries.length" class="ml-2 text-sm font-normal text-gray-500">
              {{ filteredEntries.length }} de {{ entries.length }}
            </span>
            <span v-else class="ml-2 text-sm font-normal text-gray-500">
              {{ entries.length }} entrada{{ entries.length !== 1 ? 's' : '' }}
              — {{ filters.ejercicio }}/{{ filters.periodo }}
            </span>
          </h3>
          <div class="flex items-center gap-2 mt-1">
            <span :class="modeBadgeClass" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
              {{ result?.mode ?? '—' }}
            </span>
            <span v-if="result?.hay_mas_paginas" class="text-xs text-amber-600 font-medium">
              Hay más registros en AEAT — usa "Cargar siguiente" para incluirlos en la reconciliación
            </span>
          </div>
        </div>
        <div class="flex gap-2">
          <BaseButton
            v-if="result?.hay_mas_paginas"
            variant="primary-outline"
            size="sm"
            :loading="loading"
            @click="reconciliar(result.clave_paginacion)"
          >
            Cargar siguiente
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

      <!-- Tabla principal -->
      <div v-if="filteredEntries.length" class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Factura</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
              <th class="px-3 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Importe</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado AEAT</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reconciliación</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acción recomendada</th>
              <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fuente</th>
              <th class="px-3 py-3"></th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-100">
            <template v-for="(entry, idx) in filteredEntries" :key="idx">
              <tr
                class="hover:bg-gray-50 cursor-pointer"
                :class="{ 'bg-primary-50': selectedIdx === idx }"
                @click="toggleDetail(idx)"
              >
                <td class="px-3 py-3 font-mono font-medium text-gray-900">
                  {{ entry.invoice_number }}
                </td>
                <td class="px-3 py-3 text-gray-600 whitespace-nowrap">{{ entry.invoice_date ?? '—' }}</td>
                <td class="px-3 py-3 text-gray-500">{{ entry.tipo_factura ?? '—' }}</td>
                <td class="px-3 py-3 text-right text-gray-700 font-mono whitespace-nowrap">
                  {{ entry.importe_total ? entry.importe_total + ' €' : '—' }}
                </td>
                <td class="px-3 py-3">
                  <span v-if="entry.estado_registro" :class="estadoBadgeClass(entry.estado_registro)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                    {{ entry.estado_registro }}
                  </span>
                  <span v-else class="text-xs text-gray-400">—</span>
                  <span v-if="entry.error_code" class="ml-1 text-xs text-red-500">{{ entry.error_code }}</span>
                </td>
                <td class="px-3 py-3">
                  <span :class="reconStateBadgeClass(entry.recon_state)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                    {{ entry.recon_state }}
                  </span>
                </td>
                <td class="px-3 py-3">
                  <span
                    :class="actionBadgeClass(entry.recommended_action?.severity)"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                  >
                    {{ entry.recommended_action?.label ?? '—' }}
                  </span>
                </td>
                <td class="px-3 py-3">
                  <span :class="sourceBadgeClass(entry.source)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium">
                    {{ sourceLabel(entry.source) }}
                  </span>
                </td>
                <td class="px-3 py-3 text-right">
                  <BaseIcon
                    :name="selectedIdx === idx ? 'ChevronUpIcon' : 'ChevronDownIcon'"
                    class="w-4 h-4 text-gray-400"
                  />
                </td>
              </tr>

              <!-- Fila de detalle -->
              <tr v-if="selectedIdx === idx" :key="'detail-' + idx">
                <td colspan="9" class="px-0 py-0 bg-gray-50 border-b border-gray-200">
                  <div class="px-4 py-5">

                    <!-- Acción principal -->
                    <div class="mb-5 flex flex-wrap gap-3 items-center p-4 rounded-lg border"
                      :class="actionPanelClass(entry.recommended_action?.severity)">
                      <div class="flex-1">
                        <p class="text-sm font-semibold">
                          Estado de reconciliación:
                          <span :class="reconStateBadgeClass(entry.recon_state)" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ml-1">
                            {{ entry.recon_state }}
                          </span>
                        </p>
                        <p class="text-sm mt-1 text-gray-600">{{ reconStateExplanation(entry.recon_state) }}</p>
                      </div>
                      <div class="flex flex-wrap gap-2">
                        <!-- Mark for review -->
                        <BaseButton
                          v-if="entry.recon_state !== 'OK' && entry.recon_state !== 'ANNULLED'"
                          variant="gray-outline"
                          size="sm"
                          :loading="actionLoading[entry.invoice_number + '_review']"
                          @click.stop="markForReview(entry)"
                        >
                          {{ entry.local?.needs_review ? 'Quitar revisión' : 'Marcar revisión' }}
                        </BaseButton>

                        <!-- Retry: LOCAL_ONLY or REJECTED -->
                        <BaseButton
                          v-if="entry.recommended_action?.key === 'retry'"
                          variant="warning"
                          size="sm"
                          :loading="actionLoading[entry.invoice_number + '_retry']"
                          :disabled="!entry.local?.id"
                          @click.stop="confirmRetry(entry)"
                        >
                          Reenviar a AEAT
                        </BaseButton>

                        <!-- Reconcile: REMOTE_ONLY -->
                        <BaseButton
                          v-if="entry.recon_state === 'REMOTE_ONLY'"
                          variant="primary-outline"
                          size="sm"
                          @click.stop="openReconcileDialog(entry)"
                        >
                          Reconciliar / Reconocer
                        </BaseButton>

                        <!-- Unacknowledge: ACKNOWLEDGED -->
                        <BaseButton
                          v-if="entry.recon_state === 'ACKNOWLEDGED'"
                          variant="gray-outline"
                          size="sm"
                          @click.stop="unacknowledge(entry)"
                        >
                          Quitar reconocimiento
                        </BaseButton>

                        <!-- Repair chain: CHAIN_ERROR (error 2000) -->
                        <BaseButton
                          v-if="entry.recon_state === 'CHAIN_ERROR' && entry.local?.id"
                          variant="danger"
                          size="sm"
                          @click.stop="openRepairDialog(entry)"
                        >
                          Anular y reenviar
                        </BaseButton>

                        <!-- MISMATCH: solo revisión manual (no hay botón de reenvío automático) -->
                        <BaseButton
                          v-if="entry.recon_state === 'MISMATCH'"
                          variant="danger-outline"
                          size="sm"
                          disabled
                          title="El hash local difiere del remoto aunque AEAT lo acepta. Revisar manualmente la cadena de huellas."
                        >
                          Revisar cadena manualmente
                        </BaseButton>

                        <!-- View local record -->
                        <router-link
                          v-if="entry.local?.id"
                          :to="`/admin/verifactu/records/${entry.local.id}/view`"
                          class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition-colors"
                          @click.stop
                        >
                          Ver registro local
                        </router-link>
                      </div>
                    </div>

                    <!-- Datos detallados en columnas -->
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">

                      <!-- Datos AEAT remotos -->
                      <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Datos AEAT (remoto)</p>
                        <dl class="space-y-1 text-sm">
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Estado:</dt>
                            <dd>
                              <span v-if="entry.estado_registro" :class="estadoBadgeClass(entry.estado_registro)" class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium">
                                {{ entry.estado_registro }}
                              </span>
                              <span v-else class="text-gray-400">Sin datos remotos</span>
                            </dd>
                          </div>
                          <div v-if="entry.error_code" class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Error {{ entry.error_code }}:</dt>
                            <dd class="text-red-600 text-xs">{{ entry.error_desc }}</dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Tipo factura:</dt>
                            <dd class="text-gray-900">{{ entry.tipo_factura ?? '—' }}</dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Importe total:</dt>
                            <dd class="text-gray-900 font-mono">{{ entry.importe_total ?? '—' }} €</dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Cuota total:</dt>
                            <dd class="text-gray-900 font-mono">{{ entry.cuota_total ?? '—' }} €</dd>
                          </div>
                          <div v-if="entry.destinatario_nombre" class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Destinatario:</dt>
                            <dd class="text-gray-900 text-xs">{{ entry.destinatario_nombre }} <span v-if="entry.destinatario_nif" class="text-gray-500">({{ entry.destinatario_nif }})</span></dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">IdPetición:</dt>
                            <dd class="font-mono text-xs text-gray-600">{{ entry.id_peticion ?? '—' }}</dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Presentación:</dt>
                            <dd class="text-xs text-gray-600">{{ formatTs(entry.timestamp_presentacion) }}</dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">Modificación:</dt>
                            <dd class="text-xs text-gray-600">{{ formatTs(entry.timestamp_modificacion) }}</dd>
                          </div>
                          <div class="flex gap-2">
                            <dt class="text-gray-500 w-36 shrink-0">FechaHoraHuso:</dt>
                            <dd class="font-mono text-xs text-gray-600">{{ entry.fecha_hora_huso ?? '—' }}</dd>
                          </div>
                        </dl>
                      </div>

                      <!-- Datos locales -->
                      <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Registro local</p>
                        <template v-if="entry.local">
                          <dl class="space-y-1 text-sm">
                            <div class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">Record ID:</dt>
                              <dd>
                                <router-link :to="`/admin/verifactu/records/${entry.local.id}/view`" class="text-primary-600 hover:underline text-xs font-mono" @click.stop>
                                  #{{ entry.local.id }}
                                </router-link>
                              </dd>
                            </div>
                            <div class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">Estado local:</dt>
                              <dd><span class="text-xs bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-mono">{{ entry.local.status }}</span></dd>
                            </div>
                            <div v-if="entry.local.needs_review" class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">Revisión:</dt>
                              <dd><span class="text-xs bg-yellow-100 text-yellow-700 px-1.5 py-0.5 rounded font-medium">Marcado para revisión</span></dd>
                            </div>
                            <div class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">Tipo factura:</dt>
                              <dd class="text-gray-900">{{ entry.local.tipo_factura ?? '—' }}</dd>
                            </div>
                            <div class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">Importe total:</dt>
                              <dd class="text-gray-900 font-mono">{{ entry.local.importe_total ?? '—' }} €</dd>
                            </div>
                            <div class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">FechaHoraHuso:</dt>
                              <dd class="font-mono text-xs text-gray-600">{{ entry.local.fecha_hora_huso ?? '—' }}</dd>
                            </div>
                            <div v-if="entry.local.submission_id" class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">Submission:</dt>
                              <dd>
                                <router-link :to="`/admin/verifactu/submissions/${entry.local.submission_id}/view`" class="text-primary-600 hover:underline text-xs" @click.stop>
                                  #{{ entry.local.submission_id }} ({{ entry.local.submission_status }})
                                </router-link>
                              </dd>
                            </div>
                            <div v-if="entry.local.submission_error" class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">Error envío:</dt>
                              <dd class="text-red-600 text-xs">{{ entry.local.submission_error }}</dd>
                            </div>
                            <div class="flex gap-2">
                              <dt class="text-gray-500 w-36 shrink-0">Emitido:</dt>
                              <dd class="text-xs text-gray-600">{{ entry.local.issued_at ?? '—' }}</dd>
                            </div>
                          </dl>
                        </template>
                        <div v-else class="text-sm text-gray-400 italic">Sin registro local correspondiente.</div>
                      </div>

                      <!-- Comparación de huellas -->
                      <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Comparación de huellas</p>
                        <dl class="space-y-2 text-sm">
                          <div>
                            <dt class="text-gray-500 mb-0.5">Huella AEAT (remota):</dt>
                            <dd class="font-mono text-xs break-all bg-gray-100 rounded p-1.5 text-gray-700">{{ entry.huella ?? '—' }}</dd>
                          </div>
                          <template v-if="entry.local">
                            <div>
                              <dt class="text-gray-500 mb-0.5">Hash local:</dt>
                              <dd
                                class="font-mono text-xs break-all bg-gray-100 rounded p-1.5"
                                :class="entry.local.hash_match === false ? 'text-red-700 bg-red-50' : 'text-gray-700'"
                              >
                                {{ entry.local.hash ?? '—' }}
                              </dd>
                            </div>
                            <div class="flex gap-2 items-center mt-1">
                              <dt class="text-gray-500">Coincidencia:</dt>
                              <dd>
                                <span v-if="entry.local.hash_match === true" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">
                                  ✓ Coincide
                                </span>
                                <span v-else-if="entry.local.hash_match === false" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                                  ✗ No coincide
                                </span>
                                <span v-else class="text-gray-400 text-xs">Sin huella remota para comparar</span>
                              </dd>
                            </div>
                          </template>
                          <div v-else class="text-xs text-gray-400 italic">Sin datos locales para comparar.</div>
                        </dl>
                      </div>

                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Empty states -->
      <div v-else-if="!filteredEntries.length && activeStateFilter" class="text-center py-10 text-gray-500">
        <p class="text-sm">No hay entradas con estado <strong>{{ activeStateFilter }}</strong>.</p>
        <button class="mt-2 text-xs text-primary-600 hover:underline" @click="activeStateFilter = null">Ver todas</button>
      </div>
      <div v-else-if="result?.resultado === 'SinDatos'" class="text-center py-10 text-gray-400">
        <p class="text-sm">Sin datos en AEAT para el período indicado, y no hay registros locales para este período.</p>
      </div>

      <!-- Cargar más -->
      <div v-if="result?.hay_mas_paginas" class="mt-4 flex justify-between items-center">
        <p class="text-xs text-amber-600">
          AEAT tiene más registros que no están incluidos aún. La reconciliación está incompleta.
        </p>
        <BaseButton variant="primary-outline" :loading="loading" @click="reconciliar(result.clave_paginacion)">
          Cargar siguiente página
        </BaseButton>
      </div>
    </BaseCard>

    <!-- Diálogo reconciliar REMOTE_ONLY -->
    <div v-if="reconcileDialog.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Reconciliar registro remoto</h3>
        <p class="text-sm text-gray-500 mb-4">
          Factura <strong class="font-mono">{{ reconcileDialog.entry?.invoice_number }}</strong>
          ({{ reconcileDialog.entry?.invoice_date }}) — existe en AEAT sin correspondencia local.
        </p>

        <!-- Búsqueda de factura local -->
        <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="flex gap-2 items-center mb-2">
            <p class="text-xs font-semibold text-gray-600 uppercase flex-1">Buscar factura local</p>
            <BaseButton variant="gray-outline" size="sm" :loading="reconcileDialog.searching" @click="searchLocalInvoice">
              Buscar
            </BaseButton>
          </div>
          <!-- Resultado búsqueda -->
          <div v-if="reconcileDialog.searchDone && reconcileDialog.localMatch" class="text-sm space-y-2">
            <div class="flex items-center gap-2 p-2 bg-green-50 border border-green-200 rounded">
              <span class="text-green-600 font-medium text-xs">Factura encontrada:</span>
              <span class="font-mono text-xs text-gray-700">{{ reconcileDialog.localMatch.invoice_number }}</span>
              <span class="text-xs text-gray-500">— {{ reconcileDialog.localMatch.status }}</span>
              <router-link
                :to="`/admin/invoices/${reconcileDialog.localMatch.id}/view`"
                class="ml-auto text-xs text-primary-600 hover:underline"
                @click="reconcileDialog.open = false"
              >
                Ver factura
              </router-link>
            </div>
            <div v-if="reconcileDialog.localMatch.verifactu_record_id" class="text-xs text-gray-500 px-1">
              Tiene VerifactuRecord #{{ reconcileDialog.localMatch.verifactu_record_id }} —
              <router-link
                :to="`/admin/verifactu/records/${reconcileDialog.localMatch.verifactu_record_id}/view`"
                class="text-primary-600 hover:underline"
                @click="reconcileDialog.open = false"
              >
                ver registro
              </router-link>
            </div>
            <!-- Factura existe pero sin VerifactuRecord → ofrecer reparación directa -->
            <div v-else class="p-3 bg-amber-50 border border-amber-200 rounded-lg">
              <p class="text-xs font-medium text-amber-800 mb-1">
                La factura existe localmente pero no tiene VerifactuRecord (p.ej. tras un reset de sandbox).
              </p>
              <p class="text-xs text-amber-700 mb-3">
                Se puede enviar una <strong>Anulación</strong> del registro que AEAT tiene con error 2000,
                seguida de una <strong>nueva Alta</strong> correcta. Se usará la huella remota
                <span class="font-mono">{{ reconcileDialog.entry?.huella?.substring(0, 16) }}…</span>
                como ancla de cadena.
              </p>
              <div v-if="reconcileDialog.repairResult" class="mb-2 p-2 rounded border text-xs"
                :class="reconcileDialog.repairResult.success ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'"
              >
                {{ reconcileDialog.repairResult.message ?? reconcileDialog.repairResult.error }}
                <div v-if="reconcileDialog.repairResult.success" class="mt-1.5 space-y-1">
                  <p v-if="reconcileDialog.repairResult.new_number" class="font-medium">
                    Factura renombrada: <span class="font-mono">{{ reconcileDialog.repairResult.original_number }}</span>
                    → <span class="font-mono">{{ reconcileDialog.repairResult.new_number }}</span>
                  </p>
                  <div class="flex gap-3">
                    <router-link :to="`/admin/verifactu/records/${reconcileDialog.repairResult.annulment_record_id}/view`" class="text-primary-600 hover:underline" @click="reconcileDialog.open = false">
                      Anulación #{{ reconcileDialog.repairResult.annulment_record_id }}
                    </router-link>
                    <router-link :to="`/admin/verifactu/records/${reconcileDialog.repairResult.new_record_id}/view`" class="text-primary-600 hover:underline" @click="reconcileDialog.open = false">
                      Nueva Alta #{{ reconcileDialog.repairResult.new_record_id }} ({{ reconcileDialog.repairResult.new_number }})
                    </router-link>
                  </div>
                </div>
              </div>
              <BaseButton
                v-if="!reconcileDialog.repairResult?.success"
                variant="warning"
                size="sm"
                :loading="reconcileDialog.repairing"
                @click="doRepairNoLocal"
              >
                Anular en AEAT y reenviar
              </BaseButton>
            </div>
          </div>
          <div v-else-if="reconcileDialog.searchDone && !reconcileDialog.localMatch" class="text-xs text-gray-400 italic">
            No se encontró ninguna factura local con número <span class="font-mono">{{ reconcileDialog.entry?.invoice_number }}</span>.
          </div>
          <div v-else class="text-xs text-gray-400">
            Pulsa "Buscar" para comprobar si existe una factura local con ese número.
          </div>
        </div>

        <!-- Reconocer -->
        <div class="mb-4">
          <label class="block mb-1 text-sm font-medium text-gray-700">Reconocer como registro sin correspondencia local</label>
          <p class="text-xs text-gray-500 mb-2">
            El registro pasará a estado <strong>ACKNOWLEDGED</strong> y dejará de aparecer como incidencia pendiente.
            Reversible con "Quitar reconocimiento".
          </p>
          <textarea
            v-model="reconcileDialog.note"
            rows="2"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"
            placeholder="Ej: registro de sandbox previo al reset, enviado desde SIF externo..."
          ></textarea>
        </div>

        <div class="flex justify-end gap-3">
          <BaseButton variant="gray-outline" size="sm" @click="reconcileDialog.open = false">Cancelar</BaseButton>
          <BaseButton
            variant="primary"
            size="sm"
            :loading="reconcileDialog.loading"
            @click="doAcknowledge"
          >
            Reconocer y cerrar incidencia
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Diálogo reparar cadena (CHAIN_ERROR / error 2000) -->
    <div v-if="repairDialog.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Reparar cadena — Anular y reenviar</h3>
        <p class="text-sm text-gray-500 mb-4">
          Factura <strong class="font-mono">{{ repairDialog.entry?.invoice_number }}</strong>
          — estado AEAT: <strong>AceptadoConErrores</strong>, error 2000 (huella incorrecta).
        </p>

        <div class="space-y-2 mb-5 text-sm">
          <div class="flex gap-2 items-start p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <span class="text-blue-500 mt-0.5 shrink-0">ℹ</span>
            <div>
              <p class="font-medium text-blue-900">Lo que va a ocurrir</p>
              <ol class="mt-1 space-y-1 text-blue-800 list-decimal list-inside">
                <li>Se crea y envía una <strong>Anulación</strong> del registro actual (<span class="font-mono">{{ repairDialog.entry?.invoice_number }}</span>).</li>
                <li>La factura se <strong>renombra automáticamente</strong> añadiendo un sufijo de letra (ej. <span class="font-mono">{{ repairDialog.entry?.invoice_number }}B</span>).</li>
                <li>Se crea y envía una <strong>nueva Alta</strong> con el número renombrado, encadenada desde la anulación.</li>
              </ol>
            </div>
          </div>
          <div class="flex gap-2 items-start p-3 bg-green-50 border border-green-200 rounded-lg">
            <span class="text-green-500 mt-0.5 shrink-0">✓</span>
            <div class="text-green-800 text-xs">
              El renombrado automático evita el error 3000 de AEAT (duplicado). AEAT no permite reregistrar el mismo NIF+NumSerie+Fecha aunque la factura original haya sido anulada.
            </div>
          </div>
        </div>

        <div v-if="repairDialog.result" class="mb-4 p-3 rounded-lg border"
          :class="repairDialog.result.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'"
        >
          <p class="text-sm font-medium" :class="repairDialog.result.success ? 'text-green-800' : 'text-red-800'">
            {{ repairDialog.result.message ?? repairDialog.result.error }}
          </p>
          <div v-if="repairDialog.result.success" class="mt-2 space-y-1.5 text-xs">
            <p v-if="repairDialog.result.new_number" class="text-green-700 font-medium">
              Factura renombrada: <span class="font-mono">{{ repairDialog.result.original_number }}</span>
              → <span class="font-mono">{{ repairDialog.result.new_number }}</span>
            </p>
            <div class="flex gap-3">
              <router-link
                :to="`/admin/verifactu/records/${repairDialog.result.annulment_record_id}/view`"
                class="text-primary-600 hover:underline"
                @click="repairDialog.open = false"
              >
                Ver anulación #{{ repairDialog.result.annulment_record_id }}
              </router-link>
              <router-link
                :to="`/admin/verifactu/records/${repairDialog.result.new_record_id}/view`"
                class="text-primary-600 hover:underline"
                @click="repairDialog.open = false"
              >
                Ver nueva alta #{{ repairDialog.result.new_record_id }} ({{ repairDialog.result.new_number }})
              </router-link>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-3">
          <BaseButton variant="gray-outline" size="sm" @click="closeRepairDialog">
            {{ repairDialog.result?.success ? 'Cerrar' : 'Cancelar' }}
          </BaseButton>
          <BaseButton
            v-if="!repairDialog.result?.success"
            variant="danger"
            size="sm"
            :loading="repairDialog.loading"
            @click="doRepairChain"
          >
            Confirmar: Anular y reenviar
          </BaseButton>
          <BaseButton
            v-if="repairDialog.result?.success"
            variant="primary"
            size="sm"
            @click="closeRepairDialogAndRefresh"
          >
            Ver resultado en reconciliación
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Diálogo confirmación retry -->
    <div v-if="retryDialog.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-2">Confirmar reenvío a AEAT</h3>
        <p class="text-sm text-gray-600 mb-1">
          Vas a reenviar la factura <strong class="font-mono">{{ retryDialog.entry?.invoice_number }}</strong> a AEAT.
        </p>
        <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-2 mb-4">
          Esta acción creará una nueva submission. Asegúrate de que el registro local es correcto antes de continuar.
        </p>
        <div class="flex justify-end gap-3">
          <BaseButton variant="gray-outline" size="sm" @click="retryDialog.open = false">Cancelar</BaseButton>
          <BaseButton variant="warning" size="sm" :loading="retryDialog.loading" @click="doRetry">
            Confirmar reenvío
          </BaseButton>
        </div>
      </div>
    </div>

    <!-- Diálogo confirmación mark review -->
    <div v-if="reviewDialog.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
      <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4 p-6">
        <h3 class="text-base font-semibold text-gray-900 mb-2">
          {{ reviewDialog.entry?.local?.needs_review ? 'Quitar marca de revisión' : 'Marcar para revisión manual' }}
        </h3>
        <p class="text-sm text-gray-600 mb-3">
          Factura <strong class="font-mono">{{ reviewDialog.entry?.invoice_number }}</strong>.
        </p>
        <div v-if="!reviewDialog.entry?.local?.needs_review" class="mb-4">
          <label class="block mb-1 text-sm font-medium text-gray-600">Motivo (opcional)</label>
          <textarea
            v-model="reviewDialog.reason"
            rows="2"
            class="w-full px-3 py-2 text-sm border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary-500"
            placeholder="Describe el motivo de la revisión..."
          ></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <BaseButton variant="gray-outline" size="sm" @click="reviewDialog.open = false">Cancelar</BaseButton>
          <BaseButton variant="primary" size="sm" :loading="reviewDialog.loading" @click="doMarkReview">
            Confirmar
          </BaseButton>
        </div>
      </div>
    </div>

  </BasePage>
</template>

<script setup>
import { ref, computed, reactive } from 'vue'
import axios from 'axios'
import { handleError } from '@/scripts/helpers/error-handling'
import SectionNav from './components/SectionNav.vue'

// ─── State ───────────────────────────────────────────────────────────────────

const loading          = ref(false)
const errorMsg         = ref(null)
const queried          = ref(false)
const result           = ref(null)
const entries          = ref([])
const selectedIdx      = ref(null)
const showXml          = ref(false)
const activeStateFilter = ref(null)
const actionLoading    = reactive({})

const retryDialog      = reactive({ open: false, entry: null, loading: false })
const reviewDialog     = reactive({ open: false, entry: null, reason: '', loading: false })
const repairDialog     = reactive({ open: false, entry: null, loading: false, result: null })
const reconcileDialog  = reactive({
  open: false, entry: null, note: '', loading: false,
  searching: false, searchDone: false, localMatch: null,
  repairing: false, repairResult: null,
})

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

// ─── Computed ─────────────────────────────────────────────────────────────────

const canQuery = computed(() =>
  filters.value.ejercicio.length === 4 && filters.value.periodo !== ''
)

const filteredEntries = computed(() => {
  if (!activeStateFilter.value) return entries.value
  return entries.value.filter(e => e.recon_state === activeStateFilter.value)
})

const modeBadgeClass = computed(() => {
  const m = result.value?.mode
  if (m === 'aeat_production') return 'bg-red-100 text-red-700'
  if (m === 'aeat_sandbox')    return 'bg-yellow-100 text-yellow-700'
  return 'bg-gray-100 text-gray-500'
})

// ─── Helpers de estilo ────────────────────────────────────────────────────────

function estadoBadgeClass(estado) {
  if (estado === 'Correcto')           return 'bg-green-100 text-green-700'
  if (estado === 'AceptadoConErrores') return 'bg-yellow-100 text-yellow-700'
  if (estado === 'Incorrecto')         return 'bg-red-100 text-red-700'
  if (estado === 'Anulado')            return 'bg-gray-200 text-gray-600'
  return 'bg-gray-100 text-gray-500'
}

function reconStateBadgeClass(state) {
  const map = {
    OK:                   'bg-green-100 text-green-700',
    MISMATCH:             'bg-orange-100 text-orange-700',
    CHAIN_ERROR:          'bg-red-100 text-red-700',
    ACCEPTED_WITH_ERRORS: 'bg-yellow-100 text-yellow-700',
    REJECTED:             'bg-red-200 text-red-800',
    ANNULLED:             'bg-gray-200 text-gray-600',
    LOCAL_ONLY:           'bg-blue-100 text-blue-700',
    REMOTE_ONLY:          'bg-purple-100 text-purple-700',
    PENDING_REVIEW:       'bg-indigo-100 text-indigo-700',
    ACKNOWLEDGED:         'bg-gray-100 text-gray-500',
  }
  return map[state] ?? 'bg-gray-100 text-gray-500'
}

function reconStateDot(state) {
  const map = {
    OK:                   'bg-green-500',
    MISMATCH:             'bg-orange-500',
    CHAIN_ERROR:          'bg-red-600',
    ACCEPTED_WITH_ERRORS: 'bg-yellow-500',
    REJECTED:             'bg-red-500',
    ANNULLED:             'bg-gray-400',
    LOCAL_ONLY:           'bg-blue-500',
    REMOTE_ONLY:          'bg-purple-500',
    PENDING_REVIEW:       'bg-indigo-500',
    ACKNOWLEDGED:         'bg-gray-300',
  }
  return map[state] ?? 'bg-gray-400'
}

function actionBadgeClass(severity) {
  if (severity === 'ok')      return 'bg-green-50 text-green-600'
  if (severity === 'warning') return 'bg-amber-100 text-amber-700'
  if (severity === 'danger')  return 'bg-red-100 text-red-700'
  if (severity === 'info')    return 'bg-blue-100 text-blue-700'
  return 'bg-gray-100 text-gray-500'
}

function actionPanelClass(severity) {
  if (severity === 'ok')      return 'border-green-200 bg-green-50'
  if (severity === 'warning') return 'border-amber-200 bg-amber-50'
  if (severity === 'danger')  return 'border-red-200 bg-red-50'
  if (severity === 'info')    return 'border-blue-200 bg-blue-50'
  return 'border-gray-200 bg-gray-50'
}

function sourceBadgeClass(source) {
  if (source === 'both')        return 'bg-gray-100 text-gray-600'
  if (source === 'remote_only') return 'bg-purple-100 text-purple-700'
  if (source === 'local_only')  return 'bg-blue-100 text-blue-700'
  return 'bg-gray-100 text-gray-500'
}

function sourceLabel(source) {
  if (source === 'both')        return 'Ambos'
  if (source === 'remote_only') return 'Solo AEAT'
  if (source === 'local_only')  return 'Solo local'
  return source
}

function reconStateExplanation(state) {
  const map = {
    OK:                   'El registro consta como correcto en AEAT y el hash local coincide.',
    MISMATCH:             'AEAT lo acepta como correcto, pero el hash local difiere. Revisar la cadena de huellas.',
    CHAIN_ERROR:          'AEAT devolvió error 2000 (fórmula de huella incorrecta). La cadena está rota.',
    ACCEPTED_WITH_ERRORS: 'AEAT lo aceptó pero con errores. Puede requerir subsanación.',
    REJECTED:             'AEAT rechazó el registro (Incorrecto). Debe corregirse y reenviarse.',
    ANNULLED:             'El registro está anulado en AEAT. No se requiere acción.',
    LOCAL_ONLY:           'Existe localmente pero no aparece en AEAT para este período. Posiblemente nunca enviado.',
    REMOTE_ONLY:          'Existe en AEAT pero no hay registro local correspondiente. Usa "Reconciliar / Reconocer" para gestionarlo.',
    ACKNOWLEDGED:         'Reconocido manualmente: existe en AEAT sin correspondencia local y ha sido aceptado como tal.',
    PENDING_REVIEW:       'Marcado manualmente para revisión.',
  }
  return map[state] ?? 'Estado desconocido.'
}

function formatTs(ts) {
  if (!ts) return '—'
  const m = ts.match(/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/)
  if (!m) return ts
  return `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}`
}

// ─── Interacciones ────────────────────────────────────────────────────────────

function toggleDetail(idx) {
  selectedIdx.value = selectedIdx.value === idx ? null : idx
}

function toggleXml() {
  showXml.value = !showXml.value
}

function resetear() {
  queried.value        = false
  result.value         = null
  entries.value        = []
  selectedIdx.value    = null
  errorMsg.value       = null
  activeStateFilter.value = null
}

// ─── API calls ────────────────────────────────────────────────────────────────

async function reconciliar(clavePaginacion = null) {
  if (!canQuery.value) return

  loading.value     = true
  errorMsg.value    = null
  selectedIdx.value = null

  const payload = {
    ejercicio:        filters.value.ejercicio,
    periodo:          filters.value.periodo,
    num_serie:        filters.value.num_serie || null,
    clave_paginacion: clavePaginacion,
  }

  try {
    const response = await axios.post('/api/v1/verifactu/reconciliacion', payload)
    const data     = response.data

    if (!data.success) {
      errorMsg.value = data.error || 'Error desconocido'
      queried.value  = true
      result.value   = data
      return
    }

    if (clavePaginacion && result.value?.entries) {
      // Append new entries for next page
      result.value = {
        ...data,
        entries: [...result.value.entries, ...data.entries],
        stats:   mergeStats(result.value.stats ?? {}, data.stats ?? {}),
      }
      entries.value = result.value.entries
    } else {
      result.value  = data
      entries.value = data.entries ?? []
    }

    queried.value = true
    showXml.value = false
  } catch (err) {
    handleError(err)
    errorMsg.value = err?.response?.data?.error || 'Error al conectar con AEAT'
    queried.value  = true
  } finally {
    loading.value = false
  }
}

function mergeStats(a, b) {
  const out = { ...a }
  for (const [k, v] of Object.entries(b)) {
    out[k] = (out[k] ?? 0) + v
  }
  return out
}

function confirmRetry(entry) {
  retryDialog.entry   = entry
  retryDialog.open    = true
  retryDialog.loading = false
}

async function doRetry() {
  const entry = retryDialog.entry
  if (!entry?.local?.submission_id) return

  retryDialog.loading = true
  try {
    await axios.post(`/api/v1/verifactu/submissions/${entry.local.submission_id}/retry`)
    retryDialog.open = false
    // Refresh reconciliation
    await reconciliar(null)
  } catch (err) {
    handleError(err)
  } finally {
    retryDialog.loading = false
  }
}

function markForReview(entry) {
  reviewDialog.entry   = entry
  reviewDialog.reason  = ''
  reviewDialog.open    = true
  reviewDialog.loading = false
}

async function doMarkReview() {
  const entry     = reviewDialog.entry
  const recordId  = entry?.local?.id
  if (!recordId) return

  reviewDialog.loading = true
  const newValue = !entry.local.needs_review
  try {
    await axios.post(`/api/v1/verifactu/records/${recordId}/mark-review`, {
      needs_review: newValue,
      reason:       reviewDialog.reason || null,
    })
    reviewDialog.open = false
    if (entry.local) {
      entry.local.needs_review = newValue
    }
    await reconciliar(null)
  } catch (err) {
    handleError(err)
  } finally {
    reviewDialog.loading = false
  }
}

function openRepairDialog(entry) {
  repairDialog.entry   = entry
  repairDialog.loading = false
  repairDialog.result  = null
  repairDialog.open    = true
}

async function doRepairChain() {
  const recordId = repairDialog.entry?.local?.id
  if (!recordId) return
  repairDialog.loading = true
  try {
    const res = await axios.post(`/api/v1/verifactu/records/${recordId}/repair-chain`)
    repairDialog.result = res.data
  } catch (err) {
    repairDialog.result = {
      success: false,
      message: null,
      error: err?.response?.data?.error || 'Error al ejecutar la reparación.',
    }
  } finally {
    repairDialog.loading = false
  }
}

function closeRepairDialog() {
  repairDialog.open = false
}

async function closeRepairDialogAndRefresh() {
  repairDialog.open = false
  await reconciliar(null)
}

function openReconcileDialog(entry) {
  reconcileDialog.entry       = entry
  reconcileDialog.note        = ''
  reconcileDialog.open        = true
  reconcileDialog.loading     = false
  reconcileDialog.searching   = false
  reconcileDialog.searchDone  = false
  reconcileDialog.localMatch  = null
  reconcileDialog.repairing   = false
  reconcileDialog.repairResult = null
}

async function doRepairNoLocal() {
  const invoiceId   = reconcileDialog.localMatch?.id
  const remoteHuella = reconcileDialog.entry?.huella
  if (!invoiceId || !remoteHuella) return
  reconcileDialog.repairing    = true
  reconcileDialog.repairResult = null
  try {
    const res = await axios.post('/api/v1/verifactu/reconciliacion/repair-no-local', {
      invoice_id:    invoiceId,
      remote_huella: remoteHuella,
    })
    reconcileDialog.repairResult = res.data
  } catch (err) {
    reconcileDialog.repairResult = {
      success: false,
      error: err?.response?.data?.error || 'Error al ejecutar la reparación.',
    }
  } finally {
    reconcileDialog.repairing = false
  }
}

async function searchLocalInvoice() {
  const num = reconcileDialog.entry?.invoice_number
  if (!num) return
  reconcileDialog.searching  = true
  reconcileDialog.searchDone = false
  reconcileDialog.localMatch = null
  try {
    const res = await axios.get('/api/v1/invoices', {
      params: { invoice_number: num, limit: 1 },
    })
    const invoices = res.data?.data ?? res.data?.invoices ?? []
    const match = invoices.find(
      (inv) => inv.invoice_number === num || inv.sequence_number === num
    )
    reconcileDialog.localMatch = match ?? null
    reconcileDialog.searchDone = true
  } catch (err) {
    reconcileDialog.searchDone = true
    reconcileDialog.localMatch = null
  } finally {
    reconcileDialog.searching = false
  }
}

async function doAcknowledge() {
  const entry = reconcileDialog.entry
  reconcileDialog.loading = true
  try {
    await axios.post('/api/v1/verifactu/reconciliacion/acknowledge', {
      invoice_number: entry.invoice_number,
      invoice_date:   entry.invoice_date,
      ejercicio:      filters.value.ejercicio,
      periodo:        filters.value.periodo,
      note:           reconcileDialog.note || null,
      unacknowledge:  false,
    })
    reconcileDialog.open = false
    await reconciliar(null)
  } catch (err) {
    handleError(err)
  } finally {
    reconcileDialog.loading = false
  }
}

async function unacknowledge(entry) {
  try {
    await axios.post('/api/v1/verifactu/reconciliacion/acknowledge', {
      invoice_number: entry.invoice_number,
      invoice_date:   entry.invoice_date,
      ejercicio:      filters.value.ejercicio,
      periodo:        filters.value.periodo,
      unacknowledge:  true,
    })
    await reconciliar(null)
  } catch (err) {
    handleError(err)
  }
}
</script>
