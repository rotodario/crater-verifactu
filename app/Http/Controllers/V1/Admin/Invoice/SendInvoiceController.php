<?php

namespace Crater\Http\Controllers\V1\Admin\Invoice;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\SendInvoiceRequest;
use Crater\Models\Invoice;
use Crater\Services\Verifactu\VerifactuService;

class SendInvoiceController extends Controller
{
    public function __invoke(SendInvoiceRequest $request, Invoice $invoice, VerifactuService $verifactuService)
    {
        $this->authorize('send invoice', $invoice);

        $verifactuService->ensureIssued($invoice, [
            'trigger' => 'send_invoice_endpoint',
        ]);
        $invoice->send($request->all());

        return response()->json([
            'success' => true,
        ]);
    }
}
