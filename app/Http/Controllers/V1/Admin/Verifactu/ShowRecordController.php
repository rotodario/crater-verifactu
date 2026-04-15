<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class ShowRecordController extends Controller
{
    public function __invoke(Request $request, VerifactuRecord $record)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        $companyId = $request->header('company');

        abort_unless((int) $record->company_id === (int) $companyId, 404);

        $record->load([
            'invoice.customer',
            'installation',
            'submissions.record.invoice',
            'events',
        ]);

        return response()->json([
            'record' => [
                'id' => $record->id,
                'company_id' => $record->company_id,
                'invoice_id' => $record->invoice_id,
                'invoice_number' => optional($record->invoice)->invoice_number,
                'customer_name' => optional(optional($record->invoice)->customer)->name,
                'invoice_kind' => optional($record->invoice)->invoice_kind,
                'status' => $record->status,
                'hash' => $record->hash,
                'previous_hash' => $record->previous_hash,
                'issued_at' => optional($record->issued_at)->toDateTimeString(),
                'locked_at' => optional($record->locked_at)->toDateTimeString(),
                'created_at' => optional($record->created_at)->toDateTimeString(),
                'updated_at' => optional($record->updated_at)->toDateTimeString(),
                'snapshot' => $record->snapshot,
                'qr_payload' => $record->qr_payload,
                'metadata' => $record->metadata,
                'installation' => $record->installation ? [
                    'id' => $record->installation->id,
                    'mode' => $record->installation->mode,
                    'environment' => $record->installation->environment,
                    'software_name' => $record->installation->software_name,
                    'software_version' => $record->installation->software_version,
                ] : null,
            ],
            'submissions' => $record->submissions->sortByDesc('id')->values()->map(function ($submission) {
                return [
                    'id' => $submission->id,
                    'status' => $submission->status,
                    'driver' => $submission->driver,
                    'external_reference' => $submission->external_reference,
                    'error_code' => $submission->error_code,
                    'submitted_at' => optional($submission->submitted_at)->toDateTimeString(),
                    'completed_at' => optional($submission->completed_at)->toDateTimeString(),
                    'created_at' => optional($submission->created_at)->toDateTimeString(),
                ];
            })->all(),
            'events' => $record->events->sortByDesc('id')->values()->map(function ($event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'event_code' => $event->event_code,
                    'message' => $event->message,
                    'context' => $event->context,
                    'created_at' => optional($event->created_at)->toDateTimeString(),
                ];
            })->all(),
        ]);
    }
}
