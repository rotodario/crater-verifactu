<?php

namespace Crater\Services\Verifactu\Drivers;

use Carbon\Carbon;
use Crater\Models\Invoice;
use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\Drivers\Contracts\VerifactuDriverInterface;
use Crater\Services\Verifactu\VerifactuEventLogger;

class StubDriver implements VerifactuDriverInterface
{
    public function __construct(protected VerifactuEventLogger $eventLogger) {}

    public function getName(): string
    {
        return 'stub';
    }

    public function submit(VerifactuSubmission $submission): void
    {
        $record = $submission->record;

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
    }
}
