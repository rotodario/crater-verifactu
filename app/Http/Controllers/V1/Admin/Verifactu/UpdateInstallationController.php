<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateInstallationController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $companyId = (int) $request->header('company');

        $validated = $request->validate([
            'mode'               => ['required', Rule::in(['shadow', 'aeat_sandbox', 'aeat_production'])],
            'enabled'            => ['required', 'boolean'],
            'submission_enabled' => ['required', 'boolean'],
            'environment'        => ['required', Rule::in(['local', 'sandbox', 'production'])],
            'issuer_name'        => ['required', 'string', 'max:120'],
            'issuer_tax_id'      => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]{7,15}$/'],
            'installation_number'=> ['nullable', 'string', 'max:40'],
        ], [
            'issuer_tax_id.regex' => 'El NIF/CIF debe contener solo letras y números (7-15 caracteres).',
        ]);

        $installation = VerifactuInstallation::firstOrNew(['company_id' => $companyId]);
        $installation->fill($validated);
        $installation->company_id = $companyId;
        $installation->save();

        return response()->json([
            'installation' => [
                'id'                  => $installation->id,
                'company_id'          => $installation->company_id,
                'mode'                => $installation->mode,
                'enabled'             => (bool) $installation->enabled,
                'submission_enabled'  => (bool) $installation->submission_enabled,
                'environment'         => $installation->environment,
                'issuer_name'         => $installation->issuer_name,
                'issuer_tax_id'       => $installation->issuer_tax_id,
                'software_name'       => $installation->software_name,
                'software_version'    => $installation->software_version,
                'installation_number' => $installation->installation_number,
                'has_certificate'     => $installation->hasCertificate(),
                'cert_filename'       => $installation->cert_filename,
                'cert_type'           => $installation->cert_type,
                'updated_at'          => optional($installation->updated_at)->toDateTimeString(),
            ],
        ]);
    }
}
