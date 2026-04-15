<?php

namespace Crater\Http\Controllers\V1\Admin\General;

use Crater\Http\Controllers\Controller;
use Crater\Models\Estimate;
use Crater\Models\Invoice;
use Crater\Models\Payment;
use Crater\Services\SerialNumberFormatter;
use Crater\Services\Verifactu\VerifactuRectificativeNumberFormatter;
use Illuminate\Http\Request;

class NextNumberController extends Controller
{
    public function __invoke(
        Request $request,
        Invoice $invoice,
        Estimate $estimate,
        Payment $payment,
        VerifactuRectificativeNumberFormatter $rectificativeNumberFormatter
    ) {
        $key = $request->key;
        $nextNumber = null;
        $serial = (new SerialNumberFormatter())
            ->setCompany($request->header('company'))
            ->setCustomer($request->userId);

        try {
            switch ($key) {
                case 'invoice':
                    $nextNumber = $serial->setModel($invoice)
                        ->setModelObject($request->model_id)
                        ->getNextNumber();
                    break;

                case 'invoice_rectificative':
                    $sourceInvoice = $request->model_id
                        ? Invoice::with('customer')->findOrFail($request->model_id)
                        : new Invoice([
                            'company_id' => $request->header('company'),
                            'customer_id' => $request->userId,
                        ]);

                    if ($sourceInvoice->customer_id) {
                        $sourceInvoice->loadMissing('customer');
                    }

                    $nextNumber = $rectificativeNumberFormatter
                        ->forInvoice($sourceInvoice)
                        ->getNextNumber();
                    break;

                case 'estimate':
                    $nextNumber = $serial->setModel($estimate)
                        ->setModelObject($request->model_id)
                        ->getNextNumber();
                    break;

                case 'payment':
                    $nextNumber = $serial->setModel($payment)
                        ->setModelObject($request->model_id)
                        ->getNextNumber();
                    break;

                default:
                    return;
            }
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        return response()->json([
            'success' => true,
            'nextNumber' => $nextNumber,
        ]);
    }
}
