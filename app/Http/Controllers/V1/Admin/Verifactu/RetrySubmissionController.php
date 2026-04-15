<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\VerifactuSubmissionService;
use Illuminate\Http\Request;

class RetrySubmissionController extends Controller
{
    public function __invoke(Request $request, VerifactuSubmission $submission, VerifactuSubmissionService $service)
    {
        $this->authorize('viewAny', Invoice::class);

        $companyId = $request->header('company');

        abort_unless((int) $submission->company_id === (int) $companyId, 404);

        if ($submission->status !== 'FAILED') {
            return response()->json([
                'error' => 'verifactu_submission_retry_not_allowed',
            ], 422);
        }

        $submission = $service->retrySubmission($submission);

        return response()->json([
            'success' => true,
            'message' => 'VERI*FACTU submission queued for retry.',
            'submission' => $submission,
        ]);
    }
}
