<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuPlatformConfig;

class VerifactuDeclarationService
{
    /**
     * Create a new DRAFT declaration for the given company using the current
     * platform SIF configuration. Always creates a fresh draft — does not
     * re-use an existing one so the operator can track declaration history.
     */
    public function createDraft(int $companyId): VerifactuDeclaration
    {
        $platform = VerifactuPlatformConfig::current();

        $softwareName    = $platform->software_name    ?: config('verifactu.software.name');
        $softwareVersion = $platform->software_version ?: config('verifactu.software.version');

        return VerifactuDeclaration::create([
            'company_id'          => $companyId,
            'software_name'       => $softwareName,
            'software_version'    => $softwareVersion,
            'status'              => 'DRAFT',
            'declaration_payload' => [
                'software_name'    => $softwareName,
                'software_version' => $softwareVersion,
                'vendor_name'      => $platform->vendor_name    ?: config('verifactu.software.vendor_name'),
                'vendor_tax_id'    => $platform->vendor_tax_id  ?: config('verifactu.software.vendor_tax_id'),
                'software_id'      => $platform->software_id    ?: config('verifactu.software.id'),
            ],
        ]);
    }

    /** @deprecated Use createDraft() */
    public function ensureDraftDeclaration($companyId)
    {
        return $this->createDraft((int) $companyId);
    }
}
