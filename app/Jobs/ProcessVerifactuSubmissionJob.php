<?php

namespace Crater\Jobs;

use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\VerifactuSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessVerifactuSubmissionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $submissionId;

    public $tries = 3;

    public $backoff = [60, 300, 900];

    public function __construct($submissionId)
    {
        $this->submissionId = $submissionId;
    }

    public function handle(VerifactuSubmissionService $service)
    {
        $submission = VerifactuSubmission::find($this->submissionId);

        if (! $submission) {
            return 0;
        }

        $service->processSubmission($submission);

        return 0;
    }
}
