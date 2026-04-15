<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuDeclaration;

class VerifactuDeclarationService
{
    public function ensureDraftDeclaration($companyId)
    {
        return VerifactuDeclaration::firstOrCreate(
            [
                'company_id' => $companyId,
                'software_version' => config('verifactu.software.version'),
            ],
            [
                'software_name' => config('verifactu.software.name'),
                'status' => 'DRAFT',
                'declaration_payload' => [
                    'software_name' => config('verifactu.software.name'),
                    'software_version' => config('verifactu.software.version'),
                    'vendor_name' => config('verifactu.software.vendor_name'),
                    'vendor_tax_id' => config('verifactu.software.vendor_tax_id'),
                ],
            ]
        );
    }
}
