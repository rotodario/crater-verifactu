<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuPlatformConfig;
use Crater\Models\VerifactuRecord;
use Crater\Services\Verifactu\VerifactuDeclarationService;
use Illuminate\Http\Request;

class CreateDeclarationController extends Controller
{
    public function __invoke(Request $request, VerifactuDeclarationService $service)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $companyId = (int) $request->header('company');

        // Require platform config to be complete before allowing declaration creation
        $platform = VerifactuPlatformConfig::current();
        if (! $platform->software_id || ! $platform->vendor_tax_id) {
            return response()->json([
                'message' => 'Completa primero la Configuración de Plataforma SIF (IdSistemaInformatico y NIF del desarrollador son obligatorios).',
            ], 422);
        }

        $declaration = $service->createDraft($companyId);

        return response()->json([
            'declaration' => $this->format($declaration),
        ], 201);
    }

    private function format($d): array
    {
        return [
            'id'                  => $d->id,
            'software_name'       => $d->software_name,
            'software_version'    => $d->software_version,
            'status'              => $d->status,
            'declaration_payload' => $d->declaration_payload,
            'declared_at'         => optional($d->declared_at)->toDateTimeString(),
            'created_at'          => optional($d->created_at)->toDateTimeString(),
            'updated_at'          => optional($d->updated_at)->toDateTimeString(),
        ];
    }
}
