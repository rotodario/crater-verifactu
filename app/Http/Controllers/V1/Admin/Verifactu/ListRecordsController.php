<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class ListRecordsController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        $companyId = $request->header('company');
        $limit = min(max((int) $request->get('limit', 50), 1), 200);
        $status = $request->get('status');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = VerifactuRecord::query()
            ->where('company_id', $companyId)
            ->with(['invoice.customer']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $records = $query
            ->latest('id')
            ->take($limit)
            ->get()
            ->map(function (VerifactuRecord $record) {
                return [
                    'id' => $record->id,
                    'invoice_id' => $record->invoice_id,
                    'invoice_number' => optional($record->invoice)->invoice_number,
                    'customer_name' => optional(optional($record->invoice)->customer)->name,
                    'invoice_kind' => optional($record->invoice)->invoice_kind,
                    'status' => $record->status,
                    'hash' => $record->hash,
                    'issued_at' => optional($record->issued_at)->toDateTimeString(),
                    'created_at' => optional($record->created_at)->toDateTimeString(),
                ];
            })
            ->values();

        return response()->json([
            'records' => $records,
            'meta' => [
                'limit' => $limit,
                'count' => $records->count(),
                'filters' => [
                    'status' => $status,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ],
        ]);
    }
}
