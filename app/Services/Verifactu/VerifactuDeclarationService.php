<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuPlatformConfig;

class VerifactuDeclarationService
{
    /**
     * Create a new DRAFT declaration for the current SIF platform version.
     *
     * El productor certifica esta versión del SIF y la declaración queda
     * incorporada y accesible dentro del propio sistema.
     *
     * Rules enforced:
     *  - Only one declaration lifecycle may be in progress per software_version.
     *    Attempting to create a new DRAFT for a version that already has a
     *    DRAFT/GENERATED/REVIEWED declaration throws RuntimeException (→ 409).
     *  - In-progress declarations for OTHER (stale) versions are archived automatically.
     *  - The currently ACTIVE declaration is NOT touched; it remains the certified
     *    version of the SIF until the new declaration explicitly reaches ACTIVE state
     *    (handled in UpdateDeclarationController → transition to ACTIVE).
     *
     * @throws \RuntimeException when an in-progress declaration already exists for this version
     * @throws \RuntimeException when platform config has not been persisted yet
     */
    public function createDraft(): VerifactuDeclaration
    {
        $platform = VerifactuPlatformConfig::current();
        $version  = $platform->software_version ?: config('verifactu.software.version');

        // Block: a declaration for this exact version is already in progress
        $inProgress = VerifactuDeclaration::whereNull('company_id')
            ->whereIn('status', ['DRAFT', 'GENERATED', 'REVIEWED'])
            ->where('software_version', $version)
            ->exists();

        if ($inProgress) {
            throw new \RuntimeException(
                "Ya existe una declaración en curso para la versión {$version}. Complétala o archívala antes de crear una nueva."
            );
        }

        // Archive stale in-progress declarations from a previous/different version.
        // The currently ACTIVE declaration is intentionally left untouched: it remains
        // the certified version of the SIF until the new declaration explicitly reaches
        // ACTIVE state (handled in UpdateDeclarationController).
        VerifactuDeclaration::whereNull('company_id')
            ->whereIn('status', ['DRAFT', 'GENERATED', 'REVIEWED'])
            ->where('software_version', '!=', $version)
            ->update(['status' => 'ARCHIVED', 'archived_at' => now()]);

        return VerifactuDeclaration::create([
            'company_id'          => null,
            'software_name'       => $platform->software_name    ?: config('verifactu.software.name'),
            'software_version'    => $version,
            'status'              => 'DRAFT',
            // Preliminary snapshot — will be refreshed with full platform data
            // (including address, description, subscription_place) when transitioning
            // DRAFT → GENERATED in UpdateDeclarationController.
            'declaration_payload' => [
                'software_name'      => $platform->software_name    ?: config('verifactu.software.name'),
                'software_version'   => $version,
                'vendor_name'        => $platform->vendor_name      ?: config('verifactu.software.vendor_name'),
                'vendor_tax_id'      => $platform->vendor_tax_id    ?: config('verifactu.software.vendor_tax_id'),
                'software_id'        => $platform->software_id      ?: config('verifactu.software.id'),
                'vendor_address'     => $platform->vendor_address,
                'vendor_description' => $platform->vendor_description,
                'subscription_place' => $platform->subscription_place,
            ],
        ]);
    }
}
