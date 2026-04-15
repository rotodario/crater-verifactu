<?php

namespace Crater\Http\Controllers\V1\Admin\Invoice;

use Crater\Http\Controllers\Controller;
use Crater\Http\Resources\InvoiceResource;
use Crater\Models\Invoice;
use Crater\Services\Verifactu\VerifactuService;
use Illuminate\Http\Request;

class IssueInvoiceController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, VerifactuService $verifactuService)
    {
        $this->authorize('send invoice', $invoice);

        $verifactuService->ensureIssued($invoice, [
            'trigger' => 'manual_issue_endpoint',
        ]);

        $invoice->load(['verifactuRecord']);

        return new InvoiceResource($invoice);
    }
}
