<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UpdateDeclarationController extends Controller
{
    // Valid status transitions
    const TRANSITIONS = [
        'DRAFT'     => ['SUBMITTED'],
        'SUBMITTED' => ['ACCEPTED', 'REJECTED'],
        'ACCEPTED'  => [],
        'REJECTED'  => ['DRAFT'],   // allow re-drafting after rejection
    ];

    public function __invoke(Request $request, VerifactuDeclaration $declaration)
    {
        $this->authorize('managePlatform', VerifactuRecord::class);

        $companyId = (int) $request->header('company');
        abort_unless((int) $declaration->company_id === $companyId, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['SUBMITTED', 'ACCEPTED', 'REJECTED', 'DRAFT'])],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        $newStatus = $validated['status'];
        $allowed   = self::TRANSITIONS[$declaration->status] ?? [];

        if (! in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "No se puede pasar de {$declaration->status} a {$newStatus}.",
            ], 422);
        }

        $declaration->status = $newStatus;

        if ($newStatus === 'SUBMITTED') {
            $declaration->declared_at = now();
        }

        // Attach notes to payload if provided
        if (! empty($validated['notes'])) {
            $payload = $declaration->declaration_payload ?? [];
            $payload['notes'] = $validated['notes'];
            $declaration->declaration_payload = $payload;
        }

        $declaration->save();

        return response()->json([
            'declaration' => [
                'id'                  => $declaration->id,
                'software_name'       => $declaration->software_name,
                'software_version'    => $declaration->software_version,
                'status'              => $declaration->status,
                'declaration_payload' => $declaration->declaration_payload,
                'declared_at'         => optional($declaration->declared_at)->toDateTimeString(),
                'created_at'          => optional($declaration->created_at)->toDateTimeString(),
                'updated_at'          => optional($declaration->updated_at)->toDateTimeString(),
            ],
        ]);
    }
}
