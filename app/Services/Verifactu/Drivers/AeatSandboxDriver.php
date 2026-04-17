<?php

namespace Crater\Services\Verifactu\Drivers;

use Carbon\Carbon;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\AeatHttpClient;
use Crater\Services\Verifactu\AeatResponseParser;
use Crater\Services\Verifactu\Drivers\Contracts\VerifactuDriverInterface;
use Crater\Services\Verifactu\VerifactuXmlBuilder;
use RuntimeException;

/**
 * Sends VERI*FACTU records to the AEAT pre-production (homologación) endpoint.
 *
 * Sandbox endpoint:
 *   https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP
 *
 * Requirements:
 *   - VERIFACTU_AEAT_SANDBOX_URL  — endpoint URL (env)
 *   - VERIFACTU_CERT_PATH         — path to .p12 or .pem certificate
 *   - VERIFACTU_CERT_PASSWORD     — certificate password
 */
class AeatSandboxDriver implements VerifactuDriverInterface
{
    public function getName(): string
    {
        return 'aeat_sandbox';
    }

    public function submit(VerifactuSubmission $submission): void
    {
        $record = $submission->record;
        if (! $record) {
            throw new RuntimeException('VerifactuRecord not found for submission #' . $submission->id);
        }

        $record->loadMissing(['installation']);
        $this->ensureConfig($record->installation);

        // 1. Build SOAP XML
        $xmlBuilder = new VerifactuXmlBuilder();
        $requestXml = $xmlBuilder->build($record);

        // 2. Persist the request XML for trazabilidad
        $submission->request_xml = $requestXml;
        $submission->save();

        // 3. Send to AEAT sandbox
        $installation = $record->installation;
        $verifySsl  = (bool) config('verifactu.aeat.sandbox_verify_ssl', false);

        $httpClient   = $installation && $installation->hasCertificate()
            ? new AeatHttpClient(
                endpointUrl:  config('verifactu.aeat.sandbox_url'),
                certPassword: $installation->getCertPassword(),
                certData:     $installation->getCertBytes(),
                certType:     $installation->cert_type ?? 'p12',
                verifySsl:    $verifySsl,
            )
            : new AeatHttpClient(
                endpointUrl:  config('verifactu.aeat.sandbox_url'),
                certPath:     config('verifactu.aeat.certificate_path'),
                certPassword: config('verifactu.aeat.certificate_password', ''),
                verifySsl:    $verifySsl,
            );

        $responseXml = $httpClient->send($requestXml);

        // 4. Persist raw response
        $submission->response_xml = $responseXml;
        $submission->save();

        // 5. Parse response
        $parser  = new AeatResponseParser();
        $parsed  = $parser->parse($responseXml);

        $submission->response_payload = $parsed;

        if ($parsed['accepted']) {
            $submission->status           = 'ACCEPTED';
            $submission->csv              = $parsed['csv'];
            $submission->external_reference = $parsed['csv'];
            $submission->completed_at     = Carbon::now();
            $submission->save();

            $record->status     = 'ACCEPTED';
            $record->save();
        } else {
            $errorSummary = $parser->summariseErrors($parsed);
            throw new RuntimeException('AEAT sandbox rejected submission: ' . $errorSummary);
        }
    }

    private function ensureConfig(?\Crater\Models\VerifactuInstallation $installation = null): void
    {
        if (! config('verifactu.aeat.sandbox_url')) {
            throw new RuntimeException('VERIFACTU_AEAT_SANDBOX_URL is not configured.');
        }

        $hasCert = ($installation && $installation->hasCertificate())
            || config('verifactu.aeat.certificate_path');

        if (! $hasCert) {
            throw new RuntimeException('No certificate configured. Upload one in VERI*FACTU Setup.');
        }

        // Pre-flight: detect personal (firma) certificates before AEAT rejects with a cryptic 401.
        // AEAT VERI*FACTU only accepts Certificados de Sello (tipo 0). Personal DNI/firma
        // certificates (tipo 1) are rejected at the SOAP layer with "Solo se admiten certificados de SELLO".
        if ($installation && $installation->hasCertificate()) {
            $this->warnIfPersonalCertificate($installation->getCertBytes(), $installation->getCertPassword());
        } elseif ($path = config('verifactu.aeat.certificate_path')) {
            if (file_exists($path)) {
                $this->warnIfPersonalCertificate(file_get_contents($path), config('verifactu.aeat.certificate_password', ''));
            }
        }
    }

    /**
     * Parse the PKCS12 certificate and throw a descriptive error if it is a
     * personal-signature certificate (persona física / firma electrónica).
     *
     * AEAT VERI*FACTU requires a Certificado de Sello de Entidad (OID 1.3.6.1.4.1.5734.3.10.5
     * for FNMT, or equivalent from other accredited CAs). Personal certificates
     * (OID 1.3.6.1.4.1.5734.3.10.1 for FNMT Ciudadano, serialNumber starting with IDCES-)
     * are rejected by the AEAT endpoint with HTTP 401.
     */
    private function warnIfPersonalCertificate(string $certBytes, string $password): void
    {
        $certs = [];
        if (! @openssl_pkcs12_read($certBytes, $certs, $password)) {
            return; // Can't parse — let the actual send fail with its own error.
        }

        if (empty($certs['cert'])) {
            return;
        }

        $parsed = openssl_x509_parse($certs['cert']);

        // Indicator 1: serialNumber contains IDCES- (FNMT DNI personal cert)
        $serial = $parsed['subject']['serialNumber'] ?? '';
        if (str_starts_with($serial, 'IDCES-')) {
            throw new RuntimeException(
                'Certificado de persona física detectado (serialNumber: ' . $serial . '). ' .
                'AEAT VERI*FACTU solo acepta Certificados de Sello de Entidad (persona jurídica). ' .
                'Obtén un Certificado de Representante de Persona Jurídica en sede.fnmt.gob.es.'
            );
        }

        // Indicator 2: FNMT OID for Certificado de Ciudadano (1.3.6.1.4.1.5734.3.10.1)
        $policies = $parsed['extensions']['certificatePolicies'] ?? '';
        if (str_contains($policies, '1.3.6.1.4.1.5734.3.10.1')) {
            throw new RuntimeException(
                'Certificado FNMT de Ciudadano detectado (OID 1.3.6.1.4.1.5734.3.10.1). ' .
                'AEAT VERI*FACTU solo acepta Certificados de Sello de Entidad (OID 1.3.6.1.4.1.5734.3.10.5). ' .
                'Obtén un Certificado de Representante de Persona Jurídica en sede.fnmt.gob.es.'
            );
        }
    }
}
