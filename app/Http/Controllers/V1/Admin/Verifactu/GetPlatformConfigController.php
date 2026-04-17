<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuPlatformConfig;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class GetPlatformConfigController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        $config = VerifactuPlatformConfig::current();

        return response()->json([
            'platform' => [
                'software_name'       => $config->software_name    ?: config('verifactu.software.name'),
                'software_version'    => $config->software_version ?: config('verifactu.software.version'),
                'vendor_name'         => $config->vendor_name,
                'vendor_tax_id'       => $config->vendor_tax_id,
                'software_id'         => $config->software_id,
                'vendor_address'      => $config->vendor_address,
                'vendor_description'  => $config->vendor_description,
                'subscription_place'  => $config->subscription_place,
                'is_persisted'        => $config->exists,
            ],
        ]);
    }
}
