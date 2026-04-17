<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuPlatformConfig;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class UpdatePlatformConfigController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('managePlatform', VerifactuRecord::class);

        $validated = $request->validate([
            'software_name'   => ['required', 'string', 'max:120'],
            'software_version'=> ['required', 'string', 'max:20'],
            'vendor_name'     => ['required', 'string', 'max:120'],
            'vendor_tax_id'   => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]{7,20}$/'],
            'software_id'     => ['required', 'string', 'max:60'],
        ], [
            'vendor_tax_id.regex' => 'El NIF debe contener solo letras y números (7-20 caracteres).',
        ]);

        $config = VerifactuPlatformConfig::persist($validated);

        return response()->json([
            'platform' => [
                'software_name'   => $config->software_name,
                'software_version'=> $config->software_version,
                'vendor_name'     => $config->vendor_name,
                'vendor_tax_id'   => $config->vendor_tax_id,
                'software_id'     => $config->software_id,
                'is_persisted'    => true,
            ],
        ]);
    }
}
