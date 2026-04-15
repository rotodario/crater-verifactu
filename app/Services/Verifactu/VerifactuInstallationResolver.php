<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuInstallation;

class VerifactuInstallationResolver
{
    public function resolveForCompany($companyId)
    {
        return VerifactuInstallation::firstOrCreate(
            ['company_id' => $companyId],
            [
                'mode' => config('verifactu.mode', 'shadow'),
                'enabled' => config('verifactu.enabled', true),
                'submission_enabled' => config('verifactu.submission_enabled', false),
                'environment' => app()->environment(),
                'software_name' => config('verifactu.software.name'),
                'software_version' => config('verifactu.software.version'),
            ]
        );
    }
}
