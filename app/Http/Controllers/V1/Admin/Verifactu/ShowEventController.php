<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Crater\Models\VerifactuEvent;
use Illuminate\Http\Request;

class ShowEventController extends Controller
{
    public function __invoke(Request $request, VerifactuEvent $event)
    {
        $this->authorize('viewAny', Invoice::class);

        $companyId = $request->header('company');

        abort_unless((int) $event->company_id === (int) $companyId, 404);

        $event->load(['invoice.customer', 'record']);

        return response()->json([
            'event' => [
                'id' => $event->id,
                'company_id' => $event->company_id,
                'invoice_id' => $event->invoice_id,
                'invoice_number' => optional($event->invoice)->invoice_number,
                'customer_name' => optional(optional($event->invoice)->customer)->name,
                'verifactu_record_id' => $event->verifactu_record_id,
                'user_id' => $event->user_id,
                'event_type' => $event->event_type,
                'event_code' => $event->event_code,
                'message' => $event->message,
                'context' => $event->context,
                'created_at' => optional($event->created_at)->toDateTimeString(),
                'updated_at' => optional($event->updated_at)->toDateTimeString(),
                'record' => $event->record ? [
                    'id' => $event->record->id,
                    'status' => $event->record->status,
                    'hash' => $event->record->hash,
                ] : null,
            ],
        ]);
    }
}
