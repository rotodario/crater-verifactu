<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuInstallation;
use Illuminate\Http\Request;

class ShowSetupController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        $companyId = $request->header('company');

        $installation = VerifactuInstallation::query()
            ->where('company_id', $companyId)
            ->first();

        $declarations = VerifactuDeclaration::query()
            ->where('company_id', $companyId)
            ->latest('id')
            ->get()
            ->map(function (VerifactuDeclaration $declaration) {
                return [
                    'id' => $declaration->id,
                    'software_name' => $declaration->software_name,
                    'software_version' => $declaration->software_version,
                    'status' => $declaration->status,
                    'declared_at' => optional($declaration->declared_at)->toDateTimeString(),
                    'created_at' => optional($declaration->created_at)->toDateTimeString(),
                    'updated_at' => optional($declaration->updated_at)->toDateTimeString(),
                    'declaration_payload' => $declaration->declaration_payload,
                ];
            })
            ->values();

        return response()->json([
            'installation' => $installation ? [
                'id'                 => $installation->id,
                'company_id'         => $installation->company_id,
                'mode'               => $installation->mode,
                'enabled'            => (bool) $installation->enabled,
                'submission_enabled' => (bool) $installation->submission_enabled,
                'environment'        => $installation->environment,
                'issuer_name'        => $installation->issuer_name,
                'issuer_tax_id'      => $installation->issuer_tax_id,
                'software_name'       => $installation->software_name,
                'software_version'    => $installation->software_version,
                'vendor_name'         => $installation->vendor_name,
                'vendor_tax_id'       => $installation->vendor_tax_id,
                'software_id'         => $installation->software_id,
                'installation_number' => $installation->installation_number,
                'settings'            => $installation->settings,
                'has_certificate'    => $installation->hasCertificate(),
                'cert_filename'      => $installation->cert_filename,
                'cert_type'          => $installation->cert_type,
                'created_at'         => optional($installation->created_at)->toDateTimeString(),
                'updated_at'         => optional($installation->updated_at)->toDateTimeString(),
            ] : null,
            'declarations' => $declarations,
        ]);
    }
}
