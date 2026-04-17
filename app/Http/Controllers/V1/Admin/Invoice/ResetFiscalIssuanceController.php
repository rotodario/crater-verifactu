<?php

namespace Crater\Http\Controllers\V1\Admin\Invoice;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\InvoiceResource;
use Crater\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resets the fiscal issuance of an invoice that was issued via stub or shadow
 * mode (i.e. no real AEAT CSV exists). This allows re-issuing the invoice
 * against the real AEAT sandbox or production endpoint after a mode change.
 *
 * The reset is refused if any submission for the record has a real AEAT CSV,
 * which would mean the record was already accepted by the real AEAT service
 * and cannot be undone.
 */
class ResetFiscalIssuanceController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice)
    {
        $this->authorize('send invoice', $invoice);

        $record = $invoice->verifactuRecord;

        if (! $record) {
            return response()->json(['message' => 'Esta factura no tiene registro fiscal.'], 422);
        }

        $hasRealCsv = $record->submissions()->whereNotNull('csv')->exists();

        if ($hasRealCsv) {
            return response()->json([
                'message' => 'No se puede resetear: la factura tiene un CSV real de la AEAT. Los registros aceptados por la AEAT son inmutables.',
            ], 422);
        }

        DB::transaction(function () use ($invoice, $record) {
            // Delete events, submissions, and the record itself
            $record->events()->delete();
            $record->submissions()->delete();
            $record->delete();

            // Unlock the invoice so it can be re-issued fiscally.
            // Use NOT_ISSUED (not null) so the 'Expedir fiscalmente' button
            // reappears correctly — null leaves the invoice in a button-less limbo.
            $invoice->fiscal_status       = \Crater\Models\Invoice::FISCAL_STATUS_NOT_ISSUED;
            $invoice->fiscal_issued_at    = null;
            $invoice->fiscal_locked_at    = null;
            $invoice->verifactu_record_id = null;
            $invoice->save();
        });

        $invoice->refresh()->load(['verifactuRecord']);

        return new InvoiceResource($invoice);
    }
}
