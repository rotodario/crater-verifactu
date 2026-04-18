<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuRecord;

/**
 * Deletes a declaration that is still in DRAFT status.
 * Active, Reviewed, Generated and Archived declarations cannot be deleted
 * to preserve the audit trail.
 *
 * DELETE /api/v1/verifactu/declarations/{declaration}
 */
class DeleteDeclarationController extends Controller
{
    public function __invoke(VerifactuDeclaration $declaration)
    {
        $this->authorize('managePlatform', VerifactuRecord::class);

        if ($declaration->status !== 'DRAFT') {
            return response()->json([
                'message' => 'Solo se pueden borrar declaraciones en estado DRAFT.',
            ], 422);
        }

        $declaration->delete();

        return response()->json(['success' => true]);
    }
}
