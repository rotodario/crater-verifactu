<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;

/**
 * Reconciliation service: merges remote AEAT history with local records,
 * calculates reconciliation state and recommended action for each entry.
 *
 * Reconciliation states:
 *   OK                   — Remote Correcto + local hash matches (or no hash to compare)
 *   MISMATCH             — Remote Correcto but local hash differs
 *   CHAIN_ERROR          — Remote AceptadoConErrores with error 2000 (hash formula wrong)
 *   ACCEPTED_WITH_ERRORS — Remote AceptadoConErrores (other errors)
 *   REJECTED             — Remote Incorrecto
 *   ANNULLED             — Remote Anulado
 *   LOCAL_ONLY           — Exists locally, not found in AEAT for this period
 *   REMOTE_ONLY          — Exists in AEAT, no local correspondence found
 *   PENDING_REVIEW       — Flagged manually for review
 */
class VerifactuReconciliacionService
{
    public function __construct(
        private AeatHistorialService $historialService
    ) {}

    public function reconcile(
        VerifactuInstallation $installation,
        string  $nif,
        string  $name,
        string  $ejercicio,
        string  $periodo,
        ?string $numSerie        = null,
        ?string $clavePaginacion = null
    ): array {
        // 1. Query AEAT
        $remote = $this->historialService->query(
            installation:    $installation,
            nif:             $nif,
            name:            $name,
            ejercicio:       $ejercicio,
            periodo:         $periodo,
            numSerie:        $numSerie,
            clavePaginacion: $clavePaginacion,
        );

        if (! $remote['success']) {
            return array_merge($remote, ['entries' => []]);
        }

        // 2. Query local records for this period
        $localRecords = $this->queryLocalRecords(
            $installation->company_id, $nif, $ejercicio, $periodo, $numSerie
        );

        // Index local records by invoice_number for fast lookup
        $localByNumber = [];
        foreach ($localRecords as $lr) {
            $localByNumber[$lr['invoice_number']] = $lr;
        }

        // Load acknowledged remote-only keys from installation metadata
        $acknowledgedKeys = $installation->metadata['acknowledged_remote_only'] ?? [];

        // 3. Build entries from remote records
        $matchedNumbers = [];
        $entries = [];

        foreach ($remote['records'] as $remoteRec) {
            $num   = $remoteRec['invoice_number'];
            $local = $localByNumber[$num] ?? null;
            if ($local) {
                $matchedNumbers[] = $num;
            }
            $state  = $this->calculateState($remoteRec, $local);

            // Override: if REMOTE_ONLY but acknowledged, mark as such
            $ackKey = "{$ejercicio}_{$periodo}_{$num}";
            $ackData = null;
            if ($state === 'REMOTE_ONLY' && isset($acknowledgedKeys[$ackKey])) {
                $state   = 'ACKNOWLEDGED';
                $ackData = $acknowledgedKeys[$ackKey];
            }

            $action = $this->recommendedAction($state, $local);
            $entries[] = array_merge($remoteRec, [
                'source'             => $local ? 'both' : 'remote_only',
                'recon_state'        => $state,
                'recommended_action' => $action,
                'local'              => $local,
                'acknowledged'       => $ackData,
                'ack_key'            => $ackKey,
            ]);
        }

        // 4. LOCAL_ONLY: local records not found remotely (only meaningful when not filtering by num_serie)
        if ($numSerie === null) {
            foreach ($localRecords as $lr) {
                if (! in_array($lr['invoice_number'], $matchedNumbers)) {
                    $state  = ($lr['needs_review'] ?? false) ? 'PENDING_REVIEW' : 'LOCAL_ONLY';
                    $entries[] = [
                        'source'                 => 'local_only',
                        'recon_state'            => $state,
                        'recommended_action'     => $this->recommendedAction($state, $lr),
                        'remote'                 => null,
                        // Fill remote-like display fields from local data
                        'invoice_number'         => $lr['invoice_number'],
                        'invoice_date'           => $lr['invoice_date'],
                        'tipo_factura'           => $lr['tipo_factura'],
                        'importe_total'          => $lr['importe_total'],
                        'cuota_total'            => null,
                        'estado_registro'        => null,
                        'error_code'             => null,
                        'error_desc'             => null,
                        'huella'                 => null,
                        'timestamp_presentacion' => null,
                        'timestamp_modificacion' => null,
                        'id_peticion'            => null,
                        'fecha_hora_huso'        => null,
                        'destinatario_nombre'    => null,
                        'destinatario_nif'       => null,
                        'local'                  => $lr,
                    ];
                }
            }
        }

        return array_merge($remote, [
            'entries' => $entries,
            'stats'   => $this->buildStats($entries),
        ]);
    }

    // -------------------------------------------------------------------------

    private function calculateState(array $remote, ?array $local): string
    {
        if ($local !== null && ($local['needs_review'] ?? false)) {
            return 'PENDING_REVIEW';
        }

        if ($local === null) {
            return 'REMOTE_ONLY';
        }

        return match ($remote['estado_registro'] ?? '') {
            'Correcto'            => ($local['hash_match'] === false) ? 'MISMATCH' : 'OK',
            'AceptadoConErrores'  => ($remote['error_code'] === 2000) ? 'CHAIN_ERROR' : 'ACCEPTED_WITH_ERRORS',
            'Incorrecto'          => 'REJECTED',
            'Anulado'             => 'ANNULLED',
            default               => 'PENDING_REVIEW',
        };
    }

    private function recommendedAction(string $state, ?array $local): array
    {
        return match ($state) {
            'OK'                   => ['key' => 'none',         'label' => 'Sin acción requerida',       'severity' => 'ok'],
            'MISMATCH'             => ['key' => 'review_chain', 'label' => 'Revisar cadena de huellas',  'severity' => 'warning'],
            'CHAIN_ERROR'          => ['key' => 'review_chain', 'label' => 'Revisar huella (error 2000)','severity' => 'danger'],
            'ACCEPTED_WITH_ERRORS' => ['key' => 'review',       'label' => 'Revisar / Subsanar',         'severity' => 'warning'],
            'REJECTED'             => ['key' => 'retry',        'label' => 'Reintentar envío',           'severity' => 'danger'],
            'ANNULLED'             => ['key' => 'none',         'label' => 'Anulado — sin acción',        'severity' => 'ok'],
            'LOCAL_ONLY'           => ['key' => 'retry',        'label' => 'Reenviar a AEAT',            'severity' => 'warning'],
            'REMOTE_ONLY'          => ['key' => 'reconcile',    'label' => 'Reconciliar con local',      'severity' => 'info'],
            'ACKNOWLEDGED'         => ['key' => 'none',         'label' => 'Reconocido — sin local',     'severity' => 'ok'],
            'PENDING_REVIEW'       => ['key' => 'review',       'label' => 'En revisión manual',         'severity' => 'info'],
            default                => ['key' => 'review',       'label' => 'Revisar',                    'severity' => 'info'],
        };
    }

    private function queryLocalRecords(int $companyId, string $nif, string $ejercicio, string $periodo, ?string $numSerie): array
    {
        $q = VerifactuRecord::where('company_id', $companyId)
            ->where('record_type', 'invoice_registration');

        if ($periodo === '0A') {
            $q->whereYear('invoice_date', $ejercicio);
        } else {
            $q->whereYear('invoice_date', $ejercicio)
              ->whereMonth('invoice_date', (int) $periodo);
        }

        if ($numSerie) {
            $q->where('invoice_number', $numSerie);
        }

        return $q->with(['submissions' => fn($s) => $s->latest('id')])->get()
            ->map(function (VerifactuRecord $r) {
                $submission = $r->submissions->first();
                $snap       = $r->snapshot ?? [];
                $invoice    = $snap['invoice'] ?? [];
                return [
                    'id'                => $r->id,
                    'invoice_number'    => $r->invoice_number,
                    'invoice_date'      => $r->invoice_date ? \Carbon\Carbon::parse($r->invoice_date)->format('d-m-Y') : null,
                    'tipo_factura'      => $r->tipo_factura,
                    'importe_total'     => $r->metadata['importe_total'] ?? null,
                    'hash'              => $r->hash,
                    'hash_match'        => null, // filled after remote comparison
                    'status'            => $r->status,
                    'needs_review'      => (bool) ($r->metadata['needs_review'] ?? false),
                    'issued_at'         => $r->issued_at?->toIso8601String(),
                    'submission_id'     => $submission?->id,
                    'submission_status' => $submission?->status,
                    'submission_error'  => $submission?->error_message,
                    'fecha_hora_huso'   => $r->metadata['fecha_hora_huso'] ?? null,
                ];
            })->all();
    }

    private function buildStats(array $entries): array
    {
        $counts = [];
        foreach ($entries as $e) {
            $s = $e['recon_state'];
            $counts[$s] = ($counts[$s] ?? 0) + 1;
        }
        return $counts;
    }
}
