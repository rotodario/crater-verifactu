<?php

namespace Crater\Http\Controllers\V1\Admin\Settings;

use Crater\Http\Controllers\Controller;
use Crater\Mail\TestMail;
use Crater\Services\CompanyMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TestCompanyMailController extends Controller
{
    public function __invoke(Request $request, CompanyMailService $mailService)
    {
        $request->validate([
            'to' => ['required', 'email'],
        ]);

        $companyId  = (int) $request->header('company');
        $mailerName = $mailService->resolveMailerName($companyId);

        try {
            Mail::mailer($mailerName)
                ->to($request->input('to'))
                ->send(new TestMail());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json(['success' => true]);
    }
}
