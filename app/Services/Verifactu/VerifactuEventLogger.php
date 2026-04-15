<?php

namespace Crater\Services\Verifactu;

use Crater\Models\Invoice;
use Crater\Models\VerifactuEvent;
use Crater\Models\VerifactuRecord;
use Illuminate\Support\Facades\Auth;

class VerifactuEventLogger
{
    public function log($eventType, Invoice $invoice, VerifactuRecord $record = null, array $context = [], $message = null, $eventCode = null)
    {
        return VerifactuEvent::create([
            'company_id' => $invoice->company_id,
            'invoice_id' => $invoice->id,
            'verifactu_record_id' => optional($record)->id,
            'user_id' => Auth::id(),
            'event_type' => $eventType,
            'event_code' => $eventCode,
            'message' => $message,
            'context' => $context,
        ]);
    }
}
