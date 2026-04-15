<?php

namespace Crater\Services\Verifactu;

use Carbon\Carbon;
use Crater\Jobs\ProcessVerifactuSubmissionJob;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;

class VerifactuSubmissionService
{
    public function __construct(
        protected VerifactuDriverManager $driverManager,
        protected VerifactuEventLogger $eventLogger
    ) {}

    public function queueSubmission(VerifactuRecord $record): ?VerifactuSubmission
    {
        if (! $this->driverManager->shouldSubmit()) {
            return null;
        }

        $driver = $this->driverManager->forCurrentMode();

        $submission = VerifactuSubmission::create([
            'verifactu_record_id' => $record->id,
            'company_id' => $record->company_id,
            'driver' => $driver->getName(),
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

    public function retrySubmission(VerifactuSubmission $submission): VerifactuSubmission
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

    public function processSubmission(VerifactuSubmission $submission): VerifactuSubmission
    {
        if (! in_array($submission->status, ['PENDING', 'FAILED'])) {
            return $submission;
        }

        $submission->status = 'PROCESSING';
        $submission->submitted_at = Carbon::now();
        $submission->save();
        $submission->refresh();

        if (! $submission->record) {
            $submission->status = 'FAILED';
            $submission->error_message = 'VERI*FACTU record not found.';
            $submission->completed_at = Carbon::now();
            $submission->save();

            return $submission;
        }

        try {
            $this->driverManager->forDriver($submission->driver)->submit($submission);
        } catch (\Throwable $e) {
            $submission->refresh();
            $submission->status = 'FAILED';
            $submission->error_message = $e->getMessage();
            $submission->completed_at = Carbon::now();
            $submission->save();
        }

        return $submission->fresh();
    }
}
