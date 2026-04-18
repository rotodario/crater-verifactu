<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

/**
 * Marks (or unmarks) a VerifactuRecord as needing manual review.
 *
 * POST /api/v1/verifactu/records/{record}/mark-review
 */
class VerifactuMarkReviewController extends Controller
{
    public function __invoke(Request $request, VerifactuRecord $record)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $validated = $request->validate([
            'needs_review' => 'required|boolean',
            'reason'       => 'nullable|string|max:500',
        ]);

        $metadata = $record->metadata ?? [];
        $metadata['needs_review']        = $validated['needs_review'];
        $metadata['review_reason']       = $validated['reason'] ?? null;
        $metadata['review_flagged_at']   = now()->toIso8601String();
        $metadata['review_flagged_by']   = $request->user()?->id;

        $record->metadata = $metadata;
        $record->save();

        return response()->json([
            'success'      => true,
            'needs_review' => $validated['needs_review'],
            'record_id'    => $record->id,
        ]);
    }
}
