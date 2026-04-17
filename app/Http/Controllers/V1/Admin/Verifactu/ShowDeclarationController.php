<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class ShowDeclarationController extends Controller
{
    public function __invoke(Request $request, VerifactuDeclaration $declaration)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        return response()->json([
            'declaration' => [
                'id'                  => $declaration->id,
                'company_id'          => $declaration->company_id,
                'software_name'       => $declaration->software_name,
                'software_version'    => $declaration->software_version,
                'status'              => $declaration->status,
                'declaration_payload' => $declaration->declaration_payload,
                'notes'               => $declaration->notes,
                'generated_at'        => optional($declaration->generated_at)->toDateTimeString(),
                'reviewed_at'         => optional($declaration->reviewed_at)->toDateTimeString(),
                'activated_at'        => optional($declaration->activated_at)->toDateTimeString(),
                'archived_at'         => optional($declaration->archived_at)->toDateTimeString(),
                'created_at'          => optional($declaration->created_at)->toDateTimeString(),
                'updated_at'          => optional($declaration->updated_at)->toDateTimeString(),
            ],
        ]);
    }
}
