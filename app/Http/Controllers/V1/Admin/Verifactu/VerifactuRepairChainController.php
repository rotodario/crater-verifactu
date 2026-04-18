<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Carbon\Carbon;
use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Crater\Models\VerifactuRecord;
use Crater\Services\Verifactu\VerifactuEventLogger;
use Crater\Services\Verifactu\VerifactuHuellaComputer;
use Crater\Services\Verifactu\VerifactuRecordBuilder;
use Crater\Services\Verifactu\VerifactuStateManager;
use Crater\Services\Verifactu\VerifactuSubmissionService;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Repairs a broken hash chain for a CHAIN_ERROR (error 2000) record by:
 *   1. Creating and queueing a RegistroBaja (anulación) for the original record.
 *   2. Creating and queueing a new RegistroAlta for the same invoice with a
 *      correctly computed hash that chains from the anulación.
 *
 * POST /api/v1/verifactu/records/{record}/repair-chain
 */
class VerifactuRepairChainController extends Controller
{
    public function __invoke(
        Request                    $request,
        VerifactuRecord            $record,
        VerifactuSubmissionService $submissionService,
        VerifactuStateManager      $stateManager,
        VerifactuEventLogger       $eventLogger,
        VerifactuRecordBuilder     $recordBuilder
    ) {
        $this->authorize('manage', VerifactuRecord::class);

        $companyId = (int) $request->header('company');
        abort_unless((int) $record->company_id === $companyId, 404);

        if ($record->record_type !== 'invoice_registration') {
            return response()->json([
                'success' => false,
                'error'   => 'Solo se puede reparar la cadena de registros de tipo alta (invoice_registration).',
            ], 422);
        }

        $invoice = $record->invoice;
        if (! $invoice) {
            return response()->json([
                'success' => false,
                'error'   => 'No se encontró la factura asociada al registro.',
            ], 422);
        }

        $installation = $record->installation;
        if (! $installation) {
            return response()->json([
                'success' => false,
                'error'   => 'No se encontró la instalación VERI*FACTU del registro.',
            ], 422);
        }

        $mode = $installation->mode ?? config('verifactu.mode', 'shadow');
        if (! in_array($mode, ['aeat_sandbox', 'aeat_production'])) {
            return response()->json([
                'success' => false,
                'error'   => "La reparación de cadena solo está disponible en modos aeat_sandbox o aeat_production (modo actual: {$mode}).",
            ], 422);
        }

        $invoice->load('company', 'taxes');

        $huellaComputer  = new VerifactuHuellaComputer();
        $issuerNif       = $invoice->company->tax_number ?? '';
        $originalNumber  = $invoice->invoice_number;
        $invoiceDate     = VerifactuHuellaComputer::formatInvoiceDate(
            $invoice->invoice_date ?? Carbon::now('UTC')
        );

        // The last record in the chain right now (anchors the anulación)
        $lastRecord = VerifactuRecord::where('company_id', $companyId)
            ->latest('id')
            ->first();

        // ── Step 1: Anulación (keeps the ORIGINAL invoice number) ────────────
        $bajaIssuedAt      = Carbon::now('UTC');
        $bajaFechaHoraHuso = VerifactuHuellaComputer::formatTimestamp($bajaIssuedAt);

        $bajaHuella = $huellaComputer->computeBaja(
            issuerNif:      $issuerNif,
            invoiceNumber:  $originalNumber,
            invoiceDate:    $invoiceDate,
            previousHuella: optional($lastRecord)->hash,
            fechaHoraHuso:  $bajaFechaHoraHuso,
        );

        $annulmentRecord = VerifactuRecord::create([
            'company_id'               => $companyId,
            'invoice_id'               => $invoice->id,
            'verifactu_installation_id' => $installation->id,
            'record_type'              => 'invoice_cancellation',
            'status'                   => 'ISSUED',
            'invoice_number'           => $originalNumber,
            'invoice_date'             => optional($invoice->invoice_date)->format('Y-m-d'),
            'invoice_uid'              => implode('-', [
                $companyId, $originalNumber,
                $bajaIssuedAt->format('YmdHis'), 'REPAIR-BAJA',
            ]),
            'tipo_factura'             => $record->tipo_factura ?? 'F1',
            'hash'                     => $bajaHuella,
            'previous_hash'            => optional($lastRecord)->hash,
            'issued_at'                => $bajaIssuedAt,
            'locked_at'                => $bajaIssuedAt,
            'snapshot'                 => $record->snapshot ?? [],
            'metadata'                 => [
                'cancellation_of_record_id' => $record->id,
                'fecha_hora_huso'           => $bajaFechaHoraHuso,
                'repair_chain'              => true,
            ],
        ]);

        // ── Step 2: Rename invoice to next correction number ─────────────────
        // AEAT permanently blocks the same NIF+NumSerie+Fecha combination even
        // after a successful anulación (error 3000). We must reissue under a
        // new number: 010426 → 010426B → 010426C, etc.
        $newNumber = $this->nextCorrectionNumber($originalNumber, $companyId);
        $invoice->invoice_number = $newNumber;
        $invoice->save();

        // Rebuild snapshot with the updated invoice number
        $builtAttrs = $recordBuilder->build($invoice, $installation);
        $newSnapshot = $builtAttrs['snapshot'];

        // ── Step 3: Nueva Alta (with suffixed invoice number) ────────────────
        // Compute IVA-only amounts (exclude IRPF / negative-percent taxes)
        $ivaTotal = 0;
        foreach ($invoice->taxes as $tax) {
            if ((float) $tax->percent > 0) {
                $ivaTotal += (int) $tax->amount;
            }
        }
        $subTotal        = (int) $invoice->sub_total;
        $cuotaTotalFmt   = VerifactuHuellaComputer::formatAmount($ivaTotal);
        $importeTotalFmt = VerifactuHuellaComputer::formatAmount($subTotal + $ivaTotal);
        $tipoFactura     = $record->tipo_factura ?? 'F1';

        $altaIssuedAt      = Carbon::now('UTC');
        $altaFechaHoraHuso = VerifactuHuellaComputer::formatTimestamp($altaIssuedAt);

        $altaHuella = $huellaComputer->compute(
            issuerNif:      $issuerNif,
            invoiceNumber:  $newNumber,
            invoiceDate:    $invoiceDate,
            tipoFactura:    $tipoFactura,
            cuotaTotal:     $cuotaTotalFmt,
            importeTotal:   $importeTotalFmt,
            previousHuella: $bajaHuella,   // chain anchors from the anulación
            fechaHoraHuso:  $altaFechaHoraHuso,
        );

        $newRecord = VerifactuRecord::create([
            'company_id'               => $companyId,
            'invoice_id'               => $invoice->id,
            'verifactu_installation_id' => $installation->id,
            'record_type'              => 'invoice_registration',
            'status'                   => 'ISSUED',
            'invoice_number'           => $newNumber,
            'invoice_date'             => optional($invoice->invoice_date)->format('Y-m-d'),
            'invoice_uid'              => implode('-', [
                $companyId, $newNumber,
                $altaIssuedAt->format('YmdHis'), 'REPAIR-ALTA',
            ]),
            'tipo_factura'             => $tipoFactura,
            'hash'                     => $altaHuella,
            'previous_hash'            => $bajaHuella,
            'issued_at'                => $altaIssuedAt,
            'locked_at'                => $altaIssuedAt,
            'snapshot'                 => $newSnapshot,
            'metadata'                 => [
                'fecha_hora_huso'      => $altaFechaHoraHuso,
                'cuota_total'          => $cuotaTotalFmt,
                'importe_total'        => $importeTotalFmt,
                'repair_chain'         => true,
                'replaces_record_id'   => $record->id,
                'original_number'      => $originalNumber,
            ],
        ]);

        // Point the invoice to the new Alta record
        $stateManager->markIssued($invoice, $newRecord->id);

        // Queue: anulación first, then the new Alta
        $submissionService->queueSubmission($annulmentRecord);
        $submissionService->queueSubmission($newRecord);

        $eventLogger->log(
            'chain_repair',
            $invoice,
            $newRecord,
            [
                'original_record_id'   => $record->id,
                'annulment_record_id'  => $annulmentRecord->id,
                'new_record_id'        => $newRecord->id,
                'original_number'      => $originalNumber,
                'new_number'           => $newNumber,
            ],
            "Reparación de cadena: anulación #" . $annulmentRecord->id . " ({$originalNumber})" .
            " y nueva alta #" . $newRecord->id . " ({$newNumber}) encoladas."
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
