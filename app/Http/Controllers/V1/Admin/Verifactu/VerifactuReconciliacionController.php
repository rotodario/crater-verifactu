<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Crater\Services\Verifactu\VerifactuReconciliacionService;
use Crater\Services\Verifactu\AeatHistorialService;
use Illuminate\Http\Request;

/**
 * Runs the full reconciliation between AEAT remote history and local records
 * for a given fiscal period.
 *
 * POST /api/v1/verifactu/reconciliacion
 */
class VerifactuReconciliacionController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $validated = $request->validate([
            'ejercicio'        => 'required|digits:4',
            'periodo'          => ['required', 'string', 'regex:/^(0[1-9]|1[0-2]|0A)$/'],
            'num_serie'        => 'nullable|string|max:60',
            'clave_paginacion' => 'nullable|string|max:200',
        ]);

        $companyId    = (int) $request->header('company');
        $installation = VerifactuInstallation::where('company_id', $companyId)->first();

        if (! $installation) {
            return response()->json([
                'success' => false,
                'error'   => 'No hay instalación VERI*FACTU configurada para esta empresa.',
            ], 422);
        }

        $company = $installation->company;
        if (! $company || ! $company->tax_number) {
            return response()->json([
                'success' => false,
                'error'   => 'La empresa no tiene NIF configurado.',
            ], 422);
        }

        $mode = $installation->mode ?? config('verifactu.mode', 'shadow');
        if (! in_array($mode, ['aeat_sandbox', 'aeat_production'])) {
            return response()->json([
                'success' => false,
                'error'   => "La reconciliación solo está disponible en modos aeat_sandbox o aeat_production (modo actual: {$mode}).",
            ], 422);
        }

        $service = new VerifactuReconciliacionService(new AeatHistorialService());
        $result  = $service->reconcile(
            installation:    $installation,
            nif:             $company->tax_number,
            name:            $company->name ?? '',
            ejercicio:       $validated['ejercicio'],
            periodo:         $validated['periodo'],
            numSerie:        $validated['num_serie'] ?? null,
            clavePaginacion: $validated['clave_paginacion'] ?? null,
        );

        return response()->json($result);
    }
}
