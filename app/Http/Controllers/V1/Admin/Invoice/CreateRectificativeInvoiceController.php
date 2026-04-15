<?php

namespace Crater\Http\Controllers\V1\Admin\Invoice;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\CreateRectificativeInvoiceRequest;
use Crater\Http\Resources\InvoiceResource;
use Crater\Models\Invoice;
use Crater\Services\Verifactu\VerifactuRectificationService;

class CreateRectificativeInvoiceController extends Controller
{
    public function __invoke(CreateRectificativeInvoiceRequest $request, Invoice $invoice, VerifactuRectificationService $service)
    {
        $this->authorize('create', Invoice::class);

        $rectificative = $service->createRectificativeInvoice($invoice, $request->validated());

        if (is_string($rectificative)) {
            return respondJson($rectificative, $this->messageFor($rectificative));
        }

        return new InvoiceResource($rectificative);
    }

    protected function messageFor(string $code): string
    {
        $messages = [
            'invoice_must_be_fiscally_issued' => 'Only fiscally issued invoices can be rectified.',
            'rectificative_invoice_cannot_be_rectified' => 'Rectificative invoices cannot be rectified again with the current flow.',
            'rectification_type_not_supported' => 'Difference rectifications are not supported yet in this installation.',
            'draft_rectificative_already_exists' => 'A draft rectificative invoice already exists for this invoice.',
        ];

        return $messages[$code] ?? 'Rectificative invoice could not be created.';
    }
}
