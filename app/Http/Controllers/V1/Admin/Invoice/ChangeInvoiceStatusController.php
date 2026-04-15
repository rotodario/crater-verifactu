<?php

namespace Crater\Http\Controllers\V1\Admin\Invoice;

use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Crater\Services\Verifactu\VerifactuService;
use Illuminate\Http\Request;

class ChangeInvoiceStatusController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice, VerifactuService $verifactuService)
    {
        $this->authorize('send invoice', $invoice);

        if ($request->status == Invoice::STATUS_SENT) {
            $verifactuService->ensureIssued($invoice, [
                'trigger' => 'manual_mark_as_sent',
            ]);
            $invoice->status = Invoice::STATUS_SENT;
            $invoice->sent = true;
            $invoice->save();
        } elseif ($request->status == Invoice::STATUS_COMPLETED) {
            $invoice->status = Invoice::STATUS_COMPLETED;
            $invoice->paid_status = Invoice::STATUS_PAID;
            $invoice->due_amount = 0;
            $invoice->save();
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
