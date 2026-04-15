<?php

namespace Crater\Services\Verifactu;

use Crater\Models\Invoice;
use Crater\Models\VerifactuRecord;

class VerifactuQrService
{
    public function buildPayload(Invoice $invoice, VerifactuRecord $record)
    {
        $baseUrl = config('verifactu.qr.base_url') ?: url('/invoices/pdf/'.$invoice->unique_hash);

        return [
            'ver' => '1.0',
            'invoice_number' => $invoice->invoice_number,
            'invoice_date' => optional($invoice->invoice_date)->format('Y-m-d'),
            'company_tax_id' => optional($invoice->company)->tax_number ?? null,
            'customer_tax_id' => optional($invoice->customer)->tax_number ?? null,
            'total' => $invoice->total,
            'currency_id' => $invoice->currency_id,
            'record_hash' => $record->hash,
            'record_uid' => $record->invoice_uid,
            'url' => $baseUrl,
        ];
    }

    public function buildDisplayString(array $payload)
    {
        return http_build_query(array_filter($payload, function ($value) {
            return ! is_null($value) && $value !== '';
        }));
    }
}
