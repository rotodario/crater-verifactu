<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use PDF;
use Crater\Models\VerifactuDeclaration;
use Crater\Models\VerifactuRecord;
use Illuminate\Http\Request;

class DeclarationPdfController extends Controller
{
    public function __invoke(Request $request, VerifactuDeclaration $declaration)
    {
        $this->authorize('viewAny', VerifactuRecord::class);

        // PDF is only available once the snapshot has been frozen (GENERATED or later)
        if (! in_array($declaration->status, ['GENERATED', 'REVIEWED', 'ACTIVE', 'ARCHIVED'])) {
            abort(422, 'El PDF solo está disponible a partir del estado GENERATED.');
        }

        $payload  = $declaration->declaration_payload ?? [];
        $filename = 'declaracion-responsable-sif-v' . ($declaration->software_version ?? 'x') . '-' . $declaration->id . '.pdf';

        $pdf = PDF::loadView('app.pdf.verifactu.declaration', [
            'declaration' => $declaration,
            'payload'     => $payload,
        ])->setPaper('a4', 'portrait');

        return $request->has('download')
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }
}
