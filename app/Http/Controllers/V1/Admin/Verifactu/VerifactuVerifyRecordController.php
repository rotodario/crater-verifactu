<?php

namespace Crater\Http\Controllers\V1\Admin\Verifactu;

use Crater\Http\Controllers\Controller;
use Crater\Models\VerifactuRecord;
use Crater\Services\Verifactu\AeatConsultaParser;
use Crater\Services\Verifactu\AeatConsultaXmlBuilder;
use Crater\Services\Verifactu\AeatHttpClient;
use Illuminate\Http\Request;

/**
 * Calls AEAT ConsultaFactuSistemaFacturacion to verify the registration
 * status of a VERI*FACTU record in the AEAT fiscal registry.
 *
 * POST /api/v1/verifactu/records/{record}/verify
 */
class VerifactuVerifyRecordController extends Controller
{
    public function __invoke(Request $request, VerifactuRecord $record)
    {
        $this->authorize('manage', VerifactuRecord::class);

        $companyId = $request->header('company');
        abort_unless((int) $record->company_id === (int) $companyId, 404);

        $record->loadMissing(['installation']);
        $installation = $record->installation;

        // Determine which endpoint to use (same dual-endpoint logic as drivers)
        $certBytes    = $installation && $installation->hasCertificate()
            ? $installation->getCertBytes()
            : null;
        $certPassword = $installation && $installation->hasCertificate()
            ? $installation->getCertPassword()
            : config('verifactu.aeat.certificate_password', '');

        $mode = config('verifactu.mode');

        if ($mode === 'aeat_production') {
            $endpointUrl = $this->resolveEndpoint(
                $certBytes, $certPassword,
                config('verifactu.aeat.production_url'),
                config('verifactu.aeat.production_url_sello')
            );
            $verifySsl = true;
        } else {
            // aeat_sandbox or any other: use sandbox endpoints
            $endpointUrl = $this->resolveEndpoint(
                $certBytes, $certPassword,
                config('verifactu.aeat.sandbox_url'),
                config('verifactu.aeat.sandbox_url_sello')
            );
            $verifySsl = (bool) config('verifactu.aeat.sandbox_verify_ssl', false);
        }

        // Build HTTP client
        $httpClient = ($installation && $installation->hasCertificate())
            ? new AeatHttpClient(
                endpointUrl:  $endpointUrl,
                certPassword: $installation->getCertPassword(),
                certData:     $installation->getCertBytes(),
                certType:     $installation->cert_type ?? 'p12',
                verifySsl:    $verifySsl,
            )
            : new AeatHttpClient(
                endpointUrl:  $endpointUrl,
                certPath:     config('verifactu.aeat.certificate_path'),
                certPassword: config('verifactu.aeat.certificate_password', ''),
                verifySsl:    $verifySsl,
            );

        // Build & send Consulta request
        $builder    = new AeatConsultaXmlBuilder();
        $requestXml = $builder->build($record);

        $responseXml = $httpClient->send($requestXml);

        // Parse response
        $parser = new AeatConsultaParser();
        $parsed = $parser->parse($responseXml);

        // Attach hash comparison
        if ($parsed['found']) {
            foreach ($parsed['records'] as &$r) {
                $r['hash_match'] = ($r['huella'] !== null)
                    ? (strtoupper($r['huella']) === strtoupper($record->hash))
                    : null;
            }
        }

        return response()->json([
            'success'      => true,
            'mode'         => $mode,
            'record_id'    => $record->id,
            'local_hash'   => $record->hash,
            'result'       => $parsed,
            'request_xml'  => $requestXml,
            'response_xml' => $responseXml,
        ]);
    }

    private function resolveEndpoint(?string $certBytes, string $password, string $default, string $sello): string
    {
        if (! $certBytes) {
            return $default;
        }
        $certs = [];
        if (! @openssl_pkcs12_read($certBytes, $certs, $password)) {
            return $default;
        }
        if (empty($certs['cert'])) {
            return $default;
        }
        $parsed          = openssl_x509_parse($certs['cert']);
        $serial          = $parsed['subject']['serialNumber'] ?? '';
        $policies        = $parsed['extensions']['certificatePolicies'] ?? '';
        $isPersonaFisica = str_starts_with($serial, 'IDCES-')
            || str_contains($policies, '1.3.6.1.4.1.5734.3.10.1');

        return $isPersonaFisica ? $default : $sello;
    }
}
