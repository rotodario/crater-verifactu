<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;
use Illuminate\Http\Request;

class ListSubmissionsController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        $companyId = $request->header('company');
        $limit = min(max((int) $request->get('limit', 50), 1), 200);
        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = VerifactuSubmission::query()
            ->where('company_id', $companyId)
            ->with(['record.invoice.customer']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $submissions = $query
            ->latest('id')
            ->take($limit)
            ->get()
            ->map(function (VerifactuSubmission $submission) {
                $invoice = optional($submission->record)->invoice;

                return [
                    'id' => $submission->id,
                    'record_id' => $submission->verifactu_record_id,
                    'invoice_id' => optional($invoice)->id,
                    'invoice_number' => optional($invoice)->invoice_number,
                    'customer_name' => optional(optional($invoice)->customer)->name,
                    'status' => $submission->status,
                    'driver' => $submission->driver,
                    'external_reference' => $submission->external_reference,
                    'error_code' => $submission->error_code,
                    'submitted_at' => optional($submission->submitted_at)->toDateTimeString(),
                    'completed_at' => optional($submission->completed_at)->toDateTimeString(),
                    'created_at' => optional($submission->created_at)->toDateTimeString(),
                ];
            })
            ->values();

        return response()->json([
            'submissions' => $submissions,
            'meta' => [
                'limit' => $limit,
                'count' => $submissions->count(),
                'filters' => [
                    'status' => $status,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ],
        ]);
    }
}
