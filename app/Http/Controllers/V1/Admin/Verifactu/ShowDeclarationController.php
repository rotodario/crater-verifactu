<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\Invoice;
use Crater\Models\VerifactuDeclaration;
use Illuminate\Http\Request;

class ShowDeclarationController extends Controller
{
    public function __invoke(Request $request, VerifactuDeclaration $declaration)
    {
        $this->authorize('viewAny', Invoice::class);

        $companyId = $request->header('company');

        abort_unless((int) $declaration->company_id === (int) $companyId, 404);

        return response()->json([
            'declaration' => [
                'id' => $declaration->id,
                'company_id' => $declaration->company_id,
                'software_name' => $declaration->software_name,
                'software_version' => $declaration->software_version,
                'status' => $declaration->status,
                'declared_at' => optional($declaration->declared_at)->toDateTimeString(),
                'created_at' => optional($declaration->created_at)->toDateTimeString(),
                'updated_at' => optional($declaration->updated_at)->toDateTimeString(),
                'declaration_payload' => $declaration->declaration_payload,
            ],
        ]);
    }
}
