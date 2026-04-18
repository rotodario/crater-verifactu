<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuPlatformConfig;

class VerifactuDeclarationService
{
    /**
     * Ensure a declaration exists (DRAFT or beyond) for the current software version.
     * Called automatically before issuing an invoice so fiscal records always have
     * a corresponding declaration context. If a suitable declaration already exists,
     * this is a no-op. If not, a new DRAFT is created silently.
     */
    public function ensureDraftDeclaration(): void
    {
        $version = config('verifactu.software.version');

        $exists = VerifactuDeclaration::whereNull('company_id')
            ->whereIn('status', ['DRAFT', 'GENERATED', 'REVIEWED', 'ACTIVE'])
            ->where('software_version', $version)
            ->exists();

        if (! $exists) {
            try {
                $this->createDraft();
            } catch (\RuntimeException $e) {
                // Another declaration is already in progress (race condition) — safe to ignore.
            }
        }
    }

    /**
     * Create a new DRAFT declaration for the current SIF platform version.
     *
     * @throws \RuntimeException when an in-progress declaration already exists for this version
     * @throws \RuntimeException when platform config has not been persisted yet
     */
    public function createDraft(): VerifactuDeclaration
    {
        $platform = VerifactuPlatformConfig::current();
        $version  = $platform->software_version ?: config('verifactu.software.version');

        // If a declaration for this exact version is already in progress, return it (idempotent).
        $existing = VerifactuDeclaration::whereNull('company_id')
            ->whereIn('status', ['DRAFT', 'GENERATED', 'REVIEWED'])
            ->where('software_version', $version)
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
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
