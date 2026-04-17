<?php

namespace Crater\Http\Controllers\V1\Admin\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Services\CompanyMailService;
use Illuminate\Http\Request;

class GetCompanyMailSettingsController extends Controller
{
    public function __invoke(Request $request, CompanyMailService $mailService)
    {
        $companyId = (int) $request->header('company');

        return response()->json([
            'mail_config' => $mailService->getSettings($companyId),
        ]);
    }
}
