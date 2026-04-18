<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Crater\Models\VerifactuEvent;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Wipes all VERI*FACTU data for the current company and resets all invoices
 * to NOT_ISSUED so they can be re-issued from a clean state.
 *
 * Blocked in aeat_production mode to prevent accidental data loss.
 *
 * POST /api/v1/verifactu/sandbox-reset
 */
class VerifactuSandboxResetController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $companyId = (int) $request->header('company');

        // Hard block for production — this action is irreversible
        $installation = \Crater\Models\VerifactuInstallation::where('company_id', $companyId)->first();
        if ($installation && $installation->mode === 'aeat_production') {
            abort(422, 'El reset no está disponible en modo producción AEAT.');
        }

        DB::transaction(function () use ($companyId) {
            // 1. Delete events
            $recordIds = VerifactuRecord::where('company_id', $companyId)->pluck('id');
            VerifactuEvent::whereIn('verifactu_record_id', $recordIds)->delete();

            // 2. Delete submissions
            VerifactuSubmission::whereIn('verifactu_record_id', $recordIds)->delete();

            // 3. Delete records
            VerifactuRecord::where('company_id', $companyId)->delete();

            // 4. Reset all invoices that have any fiscal activity
            Invoice::where('company_id', $companyId)
                ->where(function ($q) {
                    $q->whereNotNull('verifactu_record_id')
                      ->orWhereNotNull('fiscal_status')
                      ->orWhereNotNull('fiscal_issued_at');
                })
                ->where('fiscal_status', '!=', Invoice::FISCAL_STATUS_ANNULLED)
                ->update([
                    'fiscal_status'       => Invoice::FISCAL_STATUS_NOT_ISSUED,
                    'fiscal_issued_at'    => null,
                    'fiscal_locked_at'    => null,
                    'verifactu_record_id' => null,
                ]);

            // 5. Archive all non-active declarations so a fresh one can be created
            \Crater\Models\VerifactuDeclaration::whereNull('company_id')
                ->whereIn('status', ['DRAFT', 'GENERATED', 'REVIEWED'])
                ->update(['status' => 'ARCHIVED', 'archived_at' => now()]);
        });

        // 6. Reset auto-increment counters so IDs start from 1 again (cosmetic, outside transaction)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE verifactu_records AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE verifactu_submissions AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE verifactu_events AUTO_INCREMENT = 1');
            DB::statement('ALTER TABLE verifactu_declarations AUTO_INCREMENT = 1');
        }

        return response()->json([
            'success' => true,
            'message' => 'Sistema VERI*FACTU reseteado correctamente. Todas las facturas están listas para ser re-expedidas.',
        ]);
    }
}
