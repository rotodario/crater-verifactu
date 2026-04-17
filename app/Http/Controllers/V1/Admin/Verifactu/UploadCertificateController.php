<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class UploadCertificateController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $request->validate([
            'certificate' => ['required', 'file', 'max:2048'],
            'cert_password' => ['nullable', 'string', 'max:255'],
        ]);

        $file      = $request->file('certificate');
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['p12', 'pfx', 'pem'])) {
            return response()->json([
                'message' => 'Invalid certificate format. Accepted: .p12, .pfx, .pem',
            ], 422);
        }

        $certType = in_array($extension, ['p12', 'pfx']) ? 'p12' : 'pem';
        $password = $request->input('cert_password', '');

        // Validate PKCS12 is readable with the provided password
        if ($certType === 'p12') {
            $certs = [];
            if (! openssl_pkcs12_read($file->get(), $certs, $password)) {
                return response()->json([
                    'message' => 'Could not read the certificate. Check the file and password.',
                ], 422);
            }
        }

        $companyId    = $request->header('company');
        $installation = VerifactuInstallation::firstOrCreate(
            ['company_id' => $companyId],
            [
                'mode'               => config('verifactu.mode', 'shadow'),
                'enabled'            => true,
                'submission_enabled' => false,
                'environment'        => app()->environment(),
                'software_name'      => config('verifactu.software.name'),
                'software_version'   => config('verifactu.software.version'),
            ]
        );

        $installation->cert_data     = $file->get();
        $installation->cert_password = $password;
        $installation->cert_filename = $file->getClientOriginalName();
        $installation->cert_type     = $certType;
        $installation->save();

        return response()->json([
            'success'      => true,
            'cert_filename' => $installation->cert_filename,
            'cert_type'    => $installation->cert_type,
            'has_certificate' => true,
        ]);
    }
}
