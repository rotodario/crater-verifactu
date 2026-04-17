<?php

namespace Crater\Http\Controllers\V1\Admin\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Services\CompanyMailService;
use Illuminate\Http\Request;

class UpdateCompanyMailSettingsController extends Controller
{
    public function __invoke(Request $request, CompanyMailService $mailService)
    {
        $request->validate([
            'driver'       => ['nullable', 'string', 'in:smtp,'],
            'host'         => ['nullable', 'string', 'max:255'],
            'port'         => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption'   => ['nullable', 'string', 'in:tls,ssl,none,'],
            'username'     => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name'    => ['nullable', 'string', 'max:255'],
        ]);

        $companyId = (int) $request->header('company');

        if (empty($request->input('driver'))) {
            $mailService->clearSettings($companyId);
        } else {
            $mailService->saveSettings($request->all(), $companyId);
        }

        return response()->json([
            'success'     => true,
            'mail_config' => $mailService->getSettings($companyId),
        ]);
    }
}
