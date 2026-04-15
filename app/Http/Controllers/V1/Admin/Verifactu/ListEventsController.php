<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuEvent;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class ListEventsController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        $companyId = $request->header('company');
        $limit = min(max((int) $request->get('limit', 50), 1), 200);
        $type = $request->get('event_type');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $query = VerifactuEvent::query()
            ->where('company_id', $companyId)
            ->with(['invoice.customer', 'record']);

        if ($type) {
            $query->where('event_type', $type);
        }

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $events = $query
            ->latest('id')
            ->take($limit)
            ->get()
            ->map(function (VerifactuEvent $event) {
                return [
                    'id' => $event->id,
                    'invoice_id' => $event->invoice_id,
                    'invoice_number' => optional($event->invoice)->invoice_number,
                    'customer_name' => optional(optional($event->invoice)->customer)->name,
                    'record_id' => $event->verifactu_record_id,
                    'event_type' => $event->event_type,
                    'event_code' => $event->event_code,
                    'message' => $event->message,
                    'created_at' => optional($event->created_at)->toDateTimeString(),
                ];
            })
            ->values();

        return response()->json([
            'events' => $events,
            'meta' => [
                'limit' => $limit,
                'count' => $events->count(),
                'filters' => [
                    'event_type' => $type,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ],
        ]);
    }
}
