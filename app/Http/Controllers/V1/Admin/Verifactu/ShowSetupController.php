<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuPlatformConfig;
use Crater\Models\VerifactuRecord;
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

        // Platform declarations belong to the SIF, not to individual companies.
        // company_id IS NULL for all platform-level declarations.
        $declarations = VerifactuDeclaration::query()
            ->whereNull('company_id')
            ->latest('id')
            ->get()
            ->map(fn (VerifactuDeclaration $d) => [
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
            ])
            ->values();

        $platform = VerifactuPlatformConfig::current();

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
                'installation_number' => $installation->installation_number,
                'settings'            => $installation->settings,
                'has_certificate'    => $installation->hasCertificate(),
                'cert_filename'      => $installation->cert_filename,
                'cert_type'          => $installation->cert_type,
                'created_at'         => optional($installation->created_at)->toDateTimeString(),
                'updated_at'         => optional($installation->updated_at)->toDateTimeString(),
            ] : null,
            'platform' => [
                'software_name'    => $platform->software_name,
                'software_version' => $platform->software_version,
                'vendor_name'      => $platform->vendor_name,
                'vendor_tax_id'    => $platform->vendor_tax_id,
                'software_id'      => $platform->software_id,
                'exists'           => $platform->exists,
            ],
            'declarations' => $declarations,
        ]);
    }
}
