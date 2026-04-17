<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class DeleteCertificateController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $companyId    = $request->header('company');
        $installation = VerifactuInstallation::where('company_id', $companyId)->first();

        if ($installation) {
            $installation->cert_data     = null;
            $installation->cert_password = null;
            $installation->cert_filename = null;
            $installation->cert_type     = null;
            $installation->save();
        }

        return response()->json(['success' => true]);
    }
}
