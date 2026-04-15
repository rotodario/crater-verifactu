<?php

namespace Crater\Console\Commands;

use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\VerifactuSubmissionService;
use Illuminate\Console\Command;

class ProcessPendingVerifactuSubmissions extends Command
{
    protected $signature = 'verifactu:process-pending {--limit=25}';

    protected $description = 'Process pending VERI*FACTU submissions.';

    public function handle(VerifactuSubmissionService $service)
    {
        $limit = (int) $this->option('limit');

        $submissions = VerifactuSubmission::whereIn('status', ['PENDING', 'FAILED'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($submissions as $submission) {
            $service->processSubmission($submission);
            $this->line('Processed submission #' . $submission->id . ' => ' . $submission->fresh()->status);
        }

        $this->info('Processed ' . $submissions->count() . ' submission(s).');

        return 0;
    }
}
