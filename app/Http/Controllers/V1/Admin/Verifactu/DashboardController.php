<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuEvent;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        $companyId = $request->header('company');

        $installation = VerifactuInstallation::where('company_id', $companyId)->first();

        $recordQuery = VerifactuRecord::query()->where('company_id', $companyId);
        $submissionQuery = VerifactuSubmission::query()->where('company_id', $companyId);
        $eventQuery = VerifactuEvent::query()->where('company_id', $companyId);

        $records = (clone $recordQuery)
            ->with(['invoice.customer'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function (VerifactuRecord $record) {
                return [
                    'id' => $record->id,
                    'invoice_id' => $record->invoice_id,
                    'invoice_number' => optional($record->invoice)->invoice_number,
                    'customer_name' => optional(optional($record->invoice)->customer)->name,
                    'invoice_kind' => optional($record->invoice)->invoice_kind,
                    'status' => $record->status,
                    'hash' => Str::limit($record->hash, 24, '...'),
                    'issued_at' => optional($record->issued_at)->toDateTimeString(),
                    'created_at' => optional($record->created_at)->toDateTimeString(),
                ];
            })
            ->values();

        $submissions = (clone $submissionQuery)
            ->with(['record.invoice'])
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function (VerifactuSubmission $submission) {
                $invoice = optional($submission->record)->invoice;

                return [
                    'id' => $submission->id,
                    'record_id' => $submission->verifactu_record_id,
                    'invoice_id' => optional($invoice)->id,
                    'invoice_number' => optional($invoice)->invoice_number,
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

        $events = (clone $eventQuery)
            ->with('invoice')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function (VerifactuEvent $event) {
                return [
                    'id' => $event->id,
                    'record_id' => $event->verifactu_record_id,
                    'invoice_id' => $event->invoice_id,
                    'invoice_number' => optional($event->invoice)->invoice_number,
                    'event_type' => $event->event_type,
                    'event_code' => $event->event_code,
                    'message' => $event->message,
                    'created_at' => optional($event->created_at)->toDateTimeString(),
                ];
            })
            ->values();

        $effectiveMode = $installation->mode ?? config('verifactu.mode', 'shadow');

        return response()->json([
            'environment' => [
                'enabled'            => $installation ? (bool) $installation->enabled : (bool) config('verifactu.enabled'),
                'mode'               => $effectiveMode,
                'submission_driver'  => $effectiveMode,
                'submission_enabled' => $installation ? (bool) $installation->submission_enabled : (bool) config('verifactu.submission_enabled'),
                'issue_on_send'      => (bool) config('verifactu.issue_on_send'),
                'software_name'      => config('verifactu.software.name'),
                'software_version'   => config('verifactu.software.version'),
            ],
            'summary' => [
                'records_total' => (clone $recordQuery)->count(),
                'accepted_records' => (clone $recordQuery)->where('status', 'ACCEPTED')->count(),
                'issued_records' => (clone $recordQuery)->whereIn('status', ['ISSUED', 'SUBMITTED'])->count(),
                'pending_submissions' => (clone $submissionQuery)->whereIn('status', ['PENDING', 'PROCESSING'])->count(),
                'failed_submissions' => (clone $submissionQuery)->where('status', 'FAILED')->count(),
                'events_total' => (clone $eventQuery)->count(),
            ],
            'records' => $records,
            'submissions' => $submissions,
            'events' => $events,
        ]);
    }
}
