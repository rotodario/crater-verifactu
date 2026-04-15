<?php

namespace Crater\Services\Verifactu\Drivers;

use Carbon\Carbon;
use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\Drivers\Contracts\VerifactuDriverInterface;
use Illuminate\Support\Facades\Log;

class ShadowDriver implements VerifactuDriverInterface
{
    public function getName(): string
    {
        return 'shadow';
    }

    public function submit(VerifactuSubmission $submission): void
    {
        // Shadow mode: log the submission payload but do not send anything.
        Log::channel('verifactu')->info('VERIFACTU SHADOW submission', [
            'submission_id' => $submission->id,
            'record_id' => $submission->verifactu_record_id,
            'request_payload' => $submission->request_payload,
        ]);

        $submission->status = 'ACCEPTED';
        $submission->external_reference = 'shadow-' . $submission->id;
        $submission->response_payload = [
            'driver' => 'shadow',
            'note' => 'Shadow mode — not sent to AEAT.',
            'logged_at' => Carbon::now()->toIso8601String(),
        ];
        $submission->completed_at = Carbon::now();
        $submission->save();
    }
}
