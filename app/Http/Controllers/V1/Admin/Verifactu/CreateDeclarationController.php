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
        $this->authorize('managePlatform', VerifactuRecord::class);

        $platform = VerifactuPlatformConfig::current();

        // Platform config must be persisted and contain the minimum required fields
        if (
            ! $platform->exists
            || ! $platform->software_id
            || ! $platform->vendor_tax_id
            || ! $platform->software_version
        ) {
            return response()->json([
                'message' => 'Completa y guarda primero la Configuración de Plataforma SIF (IdSistemaInformatico, versión y NIF del productor son obligatorios).',
            ], 422);
        }

        try {
            $declaration = $service->createDraft();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

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
            'notes'               => $d->notes,
            'generated_at'        => optional($d->generated_at)->toDateTimeString(),
            'reviewed_at'         => optional($d->reviewed_at)->toDateTimeString(),
            'activated_at'        => optional($d->activated_at)->toDateTimeString(),
            'archived_at'         => optional($d->archived_at)->toDateTimeString(),
            'created_at'          => optional($d->created_at)->toDateTimeString(),
            'updated_at'          => optional($d->updated_at)->toDateTimeString(),
        ];
    }
}
