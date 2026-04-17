<?php

namespace Crater\Services\Verifactu;

use Crater\Models\Invoice;
use Carbon\Carbon;

class VerifactuStateManager
{
    public function markIssued(Invoice $invoice, $recordId)
    {
        $timestamp = Carbon::now();

        $invoice->fiscal_status = Invoice::FISCAL_STATUS_ISSUED;
        $invoice->fiscal_issued_at = $timestamp;
        $invoice->fiscal_locked_at = $timestamp;
        $invoice->verifactu_record_id = $recordId;
        $invoice->save();

        return $invoice;
    }

    public function markAnnulled(Invoice $invoice)
    {
        $invoice->fiscal_status = Invoice::FISCAL_STATUS_ANNULLED;
        $invoice->save();

        return $invoice;
    }
}
