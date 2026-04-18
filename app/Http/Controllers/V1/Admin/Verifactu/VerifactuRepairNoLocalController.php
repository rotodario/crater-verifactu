<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Carbon\Carbon;
use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Crater\Services\Verifactu\VerifactuEventLogger;
use Crater\Services\Verifactu\VerifactuHuellaComputer;
use Crater\Services\Verifactu\VerifactuRecordBuilder;
use Crater\Services\Verifactu\VerifactuStateManager;
use Crater\Services\Verifactu\VerifactuSubmissionService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Repairs a REMOTE_ONLY entry that has a local Invoice but no local VerifactuRecord
 * (e.g. after a sandbox reset that deleted VerifactuRecords but kept invoices).
 *
 * Uses the remote AEAT huella as the hash-chain anchor instead of a local record.
 *
 * Flow:
 *   1. Create RegistroBaja (anulación) anchored to remote_huella.
 *   2. Create new RegistroAlta anchored to the anulación's hash.
 *   3. Queue both submissions.
 *
 * POST /api/v1/verifactu/reconciliacion/repair-no-local
 */
class VerifactuRepairNoLocalController extends Controller
{
    public function __invoke(
        Request                    $request,
        VerifactuSubmissionService $submissionService,
        VerifactuStateManager      $stateManager,
        VerifactuEventLogger       $eventLogger,
        VerifactuRecordBuilder     $recordBuilder
    ) {
        $this->authorize('manage', VerifactuRecord::class);

        $validated = $request->validate([
            'invoice_id'    => 'required|integer',
            'remote_huella' => 'required|string|max:64',   // last AEAT record's hash → chain anchor
        ]);

        $companyId    = (int) $request->header('company');
        $invoice      = Invoice::where('id', $validated['invoice_id'])
                               ->where('company_id', $companyId)
                               ->first();

        if (! $invoice) {
            return response()->json(['success' => false, 'error' => 'Factura no encontrada.'], 404);
        }

        $installation = VerifactuInstallation::where('company_id', $companyId)->first();
        if (! $installation) {
            return response()->json(['success' => false, 'error' => 'No hay instalación VERI*FACTU configurada.'], 422);
        }

        $mode = $installation->mode ?? config('verifactu.mode', 'shadow');
        if (! in_array($mode, ['aeat_sandbox', 'aeat_production'])) {
            return response()->json([
                'success' => false,
                'error'   => "Esta acción solo está disponible en modos aeat_sandbox o aeat_production (modo actual: {$mode}).",
            ], 422);
        }

        $invoice->load('company', 'taxes');

        // Build snapshot with original invoice number (used by the anulación)
        $builtAttrs     = $recordBuilder->build($invoice, $installation);
        $bajaSnapshot   = $builtAttrs['snapshot'];

        $huellaComputer  = new VerifactuHuellaComputer();
        $issuerNif       = $invoice->company->tax_number ?? '';
        $originalNumber  = $invoice->invoice_number;
        $invoiceDate     = VerifactuHuellaComputer::formatInvoiceDate(
            $invoice->invoice_date ?? Carbon::now('UTC')
        );
        $remoteHuella    = strtoupper(trim($validated['remote_huella']));

        // ── Step 1: Anulación anchored to remote AEAT huella (original number) ─
        $bajaIssuedAt      = Carbon::now('UTC');
        $bajaFechaHoraHuso = VerifactuHuellaComputer::formatTimestamp($bajaIssuedAt);

        $bajaHuella = $huellaComputer->computeBaja(
            issuerNif:      $issuerNif,
            invoiceNumber:  $originalNumber,
            invoiceDate:    $invoiceDate,
            previousHuella: $remoteHuella,
            fechaHoraHuso:  $bajaFechaHoraHuso,
        );

        $annulmentRecord = VerifactuRecord::create([
            'company_id'                => $companyId,
            'invoice_id'                => $invoice->id,
            'verifactu_installation_id' => $installation->id,
            'record_type'               => 'invoice_cancellation',
            'status'                    => 'ISSUED',
            'invoice_number'            => $originalNumber,
            'invoice_date'              => optional($invoice->invoice_date)->format('Y-m-d'),
            'invoice_uid'               => implode('-', [
                $companyId, $originalNumber,
                $bajaIssuedAt->format('YmdHis'), 'NOLOCAL-BAJA',
            ]),
            'tipo_factura'              => 'F1',
            'hash'                      => $bajaHuella,
            'previous_hash'             => $remoteHuella,
            'issued_at'                 => $bajaIssuedAt,
            'locked_at'                 => $bajaIssuedAt,
            'snapshot'                  => $bajaSnapshot,
            'metadata'                  => [
                'fecha_hora_huso'   => $bajaFechaHoraHuso,
                'repair_no_local'   => true,
                'remote_anchor'     => $remoteHuella,
            ],
        ]);

        // ── Step 2: Rename invoice to next correction number ─────────────────
        // AEAT permanently blocks the same NIF+NumSerie+Fecha combination even
        // after a successful anulación (error 3000). Reissue under a new number:
        // 010426 → 010426B → 010426C, etc.
        $newNumber = $this->nextCorrectionNumber($originalNumber, $companyId);
        $invoice->invoice_number = $newNumber;
        $invoice->save();

        // Rebuild snapshot with the new invoice number
        $altaBuiltAttrs = $recordBuilder->build($invoice, $installation);
        $altaSnapshot   = $altaBuiltAttrs['snapshot'];

        // ── Step 3: Nueva Alta (with suffixed invoice number) ────────────────
        $ivaTotal = 0;
        foreach ($invoice->taxes as $tax) {
            if ((float) $tax->percent > 0) {
                $ivaTotal += (int) $tax->amount;
            }
        }
        $subTotal        = (int) $invoice->sub_total;
        $cuotaTotalFmt   = VerifactuHuellaComputer::formatAmount($ivaTotal);
        $importeTotalFmt = VerifactuHuellaComputer::formatAmount($subTotal + $ivaTotal);

        $altaIssuedAt      = Carbon::now('UTC');
        $altaFechaHoraHuso = VerifactuHuellaComputer::formatTimestamp($altaIssuedAt);

        $altaHuella = $huellaComputer->compute(
            issuerNif:      $issuerNif,
            invoiceNumber:  $newNumber,
            invoiceDate:    $invoiceDate,
            tipoFactura:    'F1',
            cuotaTotal:     $cuotaTotalFmt,
            importeTotal:   $importeTotalFmt,
            previousHuella: $bajaHuella,
            fechaHoraHuso:  $altaFechaHoraHuso,
        );

        $newRecord = VerifactuRecord::create([
            'company_id'                => $companyId,
            'invoice_id'                => $invoice->id,
            'verifactu_installation_id' => $installation->id,
            'record_type'               => 'invoice_registration',
            'status'                    => 'ISSUED',
            'invoice_number'            => $newNumber,
            'invoice_date'              => optional($invoice->invoice_date)->format('Y-m-d'),
            'invoice_uid'               => implode('-', [
                $companyId, $newNumber,
                $altaIssuedAt->format('YmdHis'), 'NOLOCAL-ALTA',
            ]),
            'tipo_factura'              => 'F1',
            'hash'                      => $altaHuella,
            'previous_hash'             => $bajaHuella,
            'issued_at'                 => $altaIssuedAt,
            'locked_at'                 => $altaIssuedAt,
            'snapshot'                  => $altaSnapshot,
            'metadata'                  => [
                'fecha_hora_huso'  => $altaFechaHoraHuso,
                'cuota_total'      => $cuotaTotalFmt,
                'importe_total'    => $importeTotalFmt,
                'repair_no_local'  => true,
                'remote_anchor'    => $remoteHuella,
                'original_number'  => $originalNumber,
            ],
        ]);

        // Update invoice fiscal state to point to new Alta
        $stateManager->markIssued($invoice, $newRecord->id);

        // Queue: anulación first, then the new Alta
        $submissionService->queueSubmission($annulmentRecord);
        $submissionService->queueSubmission($newRecord);

        $eventLogger->log(
            'chain_repair_no_local',
            $invoice,
            $newRecord,
            [
                'annulment_record_id' => $annulmentRecord->id,
                'new_record_id'       => $newRecord->id,
                'remote_anchor'       => $remoteHuella,
                'original_number'     => $originalNumber,
                'new_number'          => $newNumber,
            ],
            "Reparación sin local: anulación #" . $annulmentRecord->id . " ({$originalNumber})" .
            " y nueva alta #" . $newRecord->id . " ({$newNumber}) encoladas" .
            " (ancla remota: " . substr($remoteHuella, 0, 16) . "…)."
        );

        return response()->json([
            'success'             => true,
            'annulment_record_id' => $annulmentRecord->id,
            'new_record_id'       => $newRecord->id,
            'original_number'     => $originalNumber,
            'new_number'          => $newNumber,
            'message'             => "Anulación ({$originalNumber}) y nuevo alta ({$newNumber}) encolados. Comprueba el resultado en Submissions.",
        ]);
    }

    /**
     * Generate the next correction number for an invoice.
     *
     * Strips any trailing B-Z suffix to find the base number, then iterates
     * B → C → … → Z until a candidate is not already used in VerifactuRecords
     * or Invoices for this company.
     *
     * Examples: 010426 → 010426B   |   010426B → 010426C   |   010426C → 010426D
     */
    private function nextCorrectionNumber(string $number, int $companyId): string
    {
        // Strip trailing single uppercase B-Z letter to get base
        $base = preg_replace('/[B-Z]$/', '', $number);

        foreach (range('B', 'Z') as $letter) {
            $candidate = $base . $letter;

            $usedInRecords = VerifactuRecord::where('company_id', $companyId)
                ->where('invoice_number', $candidate)
                ->exists();

            $usedInInvoices = Invoice::where('company_id', $companyId)
                ->where('invoice_number', $candidate)
                ->exists();

            if (! $usedInRecords && ! $usedInInvoices) {
                return $candidate;
            }
        }

        throw new RuntimeException("No hay letra de corrección disponible para la factura {$number} (B–Z agotadas).");
    }
}
