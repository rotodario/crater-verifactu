<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuPlatformConfig;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Transitions a Declaración Responsable del SIF through its lifecycle.
 *
 * El productor certifica esta versión del SIF y la declaración queda
 * incorporada y accesible dentro del propio sistema.
 *
 * This controller has NO knowledge of AEAT submission or acceptance —
 * those concepts belong exclusively to RegistroAlta / RegistroBaja flows.
 *
 * On DRAFT → GENERATED: the declaration_payload is frozen as a full snapshot
 * of the current platform config. This is the authoritative data for the PDF.
 */
class UpdateDeclarationController extends Controller
{
    // Valid state transitions for the SIF declaration lifecycle
    const TRANSITIONS = [
        'DRAFT'     => ['GENERATED'],
        'GENERATED' => ['REVIEWED', 'DRAFT'],
        'REVIEWED'  => ['ACTIVE', 'DRAFT'],
        'ACTIVE'    => [],      // archived automatically when a new declaration reaches ACTIVE
        'ARCHIVED'  => [],
    ];

    public function __invoke(Request $request, VerifactuDeclaration $declaration)
    {
        $this->authorize('managePlatform', VerifactuRecord::class);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['GENERATED', 'REVIEWED', 'ACTIVE', 'DRAFT'])],
            'notes'  => ['nullable', 'string', 'max:1000'],
        ]);

        $newStatus = $validated['status'];
        $allowed   = self::TRANSITIONS[$declaration->status] ?? [];

        if (! in_array($newStatus, $allowed)) {
            return response()->json([
                'message' => "No se puede pasar de {$declaration->status} a {$newStatus}.",
            ], 422);
        }

        $declaration->status = $newStatus;

        // Record lifecycle timestamps; revert to DRAFT clears forward-progress timestamps
        // so they accurately reflect when the state was *last* reached, not a prior attempt.
        if ($newStatus === 'DRAFT') {
            $declaration->generated_at = null;
            $declaration->reviewed_at  = null;
        }

        if ($newStatus === 'GENERATED') {
            $declaration->generated_at = now();

            // Freeze full platform snapshot — this is the document data locked for the PDF.
            // Includes extended fields (address, description, place) if already configured.
            $platform = VerifactuPlatformConfig::current();
            $declaration->declaration_payload = [
                'software_name'      => $platform->software_name,
                'software_version'   => $platform->software_version,
                'vendor_name'        => $platform->vendor_name,
                'vendor_tax_id'      => $platform->vendor_tax_id,
                'software_id'        => $platform->software_id,
                'vendor_address'     => $platform->vendor_address,
                'vendor_description' => $platform->vendor_description,
                'subscription_place' => $platform->subscription_place,
            ];
        }

        if ($newStatus === 'REVIEWED') {
            $declaration->reviewed_at = now();
        }

        if ($newStatus === 'ACTIVE') {
            $declaration->activated_at = now();

            // Archive whichever declaration was previously ACTIVE — the new one replaces it.
            // This is the only place where an ACTIVE declaration gets archived.
            VerifactuDeclaration::whereNull('company_id')
                ->where('status', 'ACTIVE')
                ->where('id', '!=', $declaration->id)
                ->update(['status' => 'ARCHIVED', 'archived_at' => now()]);
        }

        // Update notes whenever the key is present in the request,
        // including null or empty string (which clears the field).
        if (array_key_exists('notes', $validated)) {
            $declaration->notes = $validated['notes'] !== '' ? $validated['notes'] : null;
        }

        $declaration->save();

        return response()->json([
            'declaration' => $this->format($declaration),
        ]);
    }

    private function format(VerifactuDeclaration $d): array
    {
        return [
            'id'                  => $d->id,
            'software_name'       => $d->software_name,
            'software_version'    => $d->software_version,
            'status'              => $d->status,
            'declaration_payload' => $d->declaration_payload,
            'notes'               => $d->notes,
            'generated_at'        => optional($d->generated_at)->toDateTimeString(),
            'reviewed_at'         => optional($d->reviewed_at)->toDateTimeString(),
            'activated_at'        => optional($d->activated_at)->toDateTimeString(),
            'archived_at'         => optional($d->archived_at)->toDateTimeString(),
            'created_at'          => optional($d->created_at)->toDateTimeString(),
            'updated_at'          => optional($d->updated_at)->toDateTimeString(),
        ];
    }
}
