<?php

namespace Crater\Services\Verifactu;

use Carbon\Carbon;
use Crater\Jobs\ProcessVerifactuSubmissionJob;
use Crater\Models\Invoice;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;

class VerifactuSubmissionService
{
    protected $eventLogger;

    public function __construct(VerifactuEventLogger $eventLogger)
    {
        $this->eventLogger = $eventLogger;
    }

    public function queueStubSubmission(VerifactuRecord $record)
    {
        if (! config('verifactu.submission_enabled', false)) {
            return null;
        }

        $submission = VerifactuSubmission::create([
            'verifactu_record_id' => $record->id,
            'company_id' => $record->company_id,
            'driver' => config('verifactu.submission_driver', 'stub'),
            'status' => 'PENDING',
            'attempt' => 1,
            'request_payload' => [
                'record_id' => $record->id,
                'invoice_uid' => $record->invoice_uid,
                'hash' => $record->hash,
            ],
        ]);

        ProcessVerifactuSubmissionJob::dispatch($submission->id);

        return $submission;
    }

    public function retrySubmission(VerifactuSubmission $submission)
    {
        if ($submission->status !== 'FAILED') {
            return $submission;
        }

        $submission->attempt = (int) $submission->attempt + 1;
        $submission->status = 'PENDING';
        $submission->external_reference = null;
        $submission->error_message = null;
        $submission->response_payload = null;
        $submission->submitted_at = null;
        $submission->completed_at = null;
        $submission->save();

        ProcessVerifactuSubmissionJob::dispatch($submission->id);

        return $submission->fresh();
    }

    public function processSubmission(VerifactuSubmission $submission)
    {
        if (! in_array($submission->status, ['PENDING', 'FAILED'])) {
            return $submission;
        }

        $submission->status = 'PROCESSING';
        $submission->submitted_at = Carbon::now();
        $submission->save();

        $submission->refresh();
        $record = $submission->record;

        if (! $record) {
            $submission->status = 'FAILED';
            $submission->error_message = 'VERI*FACTU record not found.';
            $submission->completed_at = Carbon::now();
            $submission->save();

            return $submission;
        }

        if ($submission->driver === 'stub') {
            return $this->processStubSubmission($submission, $record);
        }

        $submission->status = 'FAILED';
        $submission->error_message = 'Unsupported VERI*FACTU submission driver.';
        $submission->completed_at = Carbon::now();
        $submission->save();

        return $submission;
    }

    protected function processStubSubmission(VerifactuSubmission $submission, VerifactuRecord $record)
    {
        $externalReference = 'stub-' . $submission->id . '-' . Carbon::now()->format('YmdHis');

        $submission->status = 'ACCEPTED';
        $submission->external_reference = $externalReference;
        $submission->response_payload = [
            'driver' => 'stub',
            'status' => 'accepted',
            'external_reference' => $externalReference,
            'accepted_at' => Carbon::now()->toIso8601String(),
        ];
        $submission->completed_at = Carbon::now();
        $submission->save();

        $record->status = 'ACCEPTED';
        $record->metadata = array_merge($record->metadata ?? [], [
            'last_submission_id' => $submission->id,
            'last_external_reference' => $externalReference,
        ]);
        $record->save();

        $invoice = Invoice::find($record->invoice_id);

        if ($invoice) {
            $invoice->fiscal_status = Invoice::FISCAL_STATUS_ACCEPTED;
            $invoice->save();

            $this->eventLogger->log(
                'submission_accepted',
                $invoice,
                $record,
                ['submission_id' => $submission->id, 'driver' => 'stub'],
                'VERI*FACTU submission accepted by stub driver.'
            );
        }

        return $submission;
    }
}
