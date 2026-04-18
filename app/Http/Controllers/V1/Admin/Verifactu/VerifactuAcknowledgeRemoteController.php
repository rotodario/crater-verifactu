<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

/**
 * Acknowledges (or un-acknowledges) a REMOTE_ONLY entry so it stops appearing
 * as an unresolved issue in the reconciliation panel.
 *
 * The acknowledgment is stored in the installation metadata under
 * 'acknowledged_remote_only' as a keyed array indexed by
 * "{ejercicio}_{periodo}_{invoice_number}".
 *
 * POST /api/v1/verifactu/reconciliacion/acknowledge
 */
class VerifactuAcknowledgeRemoteController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $validated = $request->validate([
            'invoice_number' => 'required|string|max:60',
            'invoice_date'   => 'nullable|string|max:20',
            'ejercicio'      => 'required|digits:4',
            'periodo'        => ['required', 'string', 'regex:/^(0[1-9]|1[0-2]|0A)$/'],
            'note'           => 'nullable|string|max:500',
            'unacknowledge'  => 'boolean',
        ]);

        $companyId    = (int) $request->header('company');
        $installation = VerifactuInstallation::where('company_id', $companyId)->first();

        if (! $installation) {
            return response()->json(['success' => false, 'error' => 'No hay instalación VERI*FACTU configurada.'], 422);
        }

        $key      = "{$validated['ejercicio']}_{$validated['periodo']}_{$validated['invoice_number']}";
        $metadata = $installation->metadata ?? [];
        $ack      = $metadata['acknowledged_remote_only'] ?? [];

        if ($validated['unacknowledge'] ?? false) {
            unset($ack[$key]);
        } else {
            $ack[$key] = [
                'invoice_number' => $validated['invoice_number'],
                'invoice_date'   => $validated['invoice_date'] ?? null,
                'ejercicio'      => $validated['ejercicio'],
                'periodo'        => $validated['periodo'],
                'note'           => $validated['note'] ?? null,
                'acknowledged_at'=> now()->toIso8601String(),
                'acknowledged_by'=> $request->user()?->id,
            ];
        }

        $metadata['acknowledged_remote_only'] = $ack;
        $installation->metadata = $metadata;
        $installation->save();

        return response()->json([
            'success'        => true,
            'key'            => $key,
            'unacknowledged' => $validated['unacknowledge'] ?? false,
        ]);
    }
}
