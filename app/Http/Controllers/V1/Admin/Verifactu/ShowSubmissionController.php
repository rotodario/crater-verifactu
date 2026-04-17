<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;
use Illuminate\Http\Request;

class ShowSubmissionController extends Controller
{
    public function __invoke(Request $request, VerifactuSubmission $submission)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        $companyId = $request->header('company');

        abort_unless((int) $submission->company_id === (int) $companyId, 404);

        $submission->load(['record.invoice.customer']);

        return response()->json([
            'submission' => [
                'id' => $submission->id,
                'company_id' => $submission->company_id,
                'verifactu_record_id' => $submission->verifactu_record_id,
                'invoice_id' => optional(optional($submission->record)->invoice)->id,
                'invoice_number' => optional(optional($submission->record)->invoice)->invoice_number,
                'customer_name' => optional(optional(optional($submission->record)->invoice)->customer)->name,
                'status' => $submission->status,
                'driver' => $submission->driver,
                'external_reference' => $submission->external_reference,
                'error_code' => $submission->error_code,
                'error_message' => $submission->error_message,
                'submitted_at' => optional($submission->submitted_at)->toDateTimeString(),
                'completed_at' => optional($submission->completed_at)->toDateTimeString(),
                'created_at' => optional($submission->created_at)->toDateTimeString(),
                'updated_at' => optional($submission->updated_at)->toDateTimeString(),
                'csv'              => $submission->csv,
                'request_payload'  => $submission->request_payload,
                'response_payload' => $submission->response_payload,
                'request_xml'      => $submission->request_xml,
                'response_xml'     => $submission->response_xml,
                'record' => $submission->record ? [
                    'id' => $submission->record->id,
                    'status' => $submission->record->status,
                    'hash' => $submission->record->hash,
                ] : null,
            ],
        ]);
    }
}
