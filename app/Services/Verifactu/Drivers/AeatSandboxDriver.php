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
        // AEAT has two sandbox endpoints depending on certificate type (from the official WSDL):
        //   prewww1  → persona física / representante
        //   prewww10 → certificado de sello
        // Auto-select based on the loaded certificate; fall back to config default.
        $installation = $record->installation;
        $verifySsl    = (bool) config('verifactu.aeat.sandbox_verify_ssl', false);

        $certBytes    = $installation && $installation->hasCertificate()
            ? $installation->getCertBytes()
            : null;
        $certPassword = $installation && $installation->hasCertificate()
            ? $installation->getCertPassword()
            : config('verifactu.aeat.certificate_password', '');

        $endpointUrl  = $this->resolveEndpoint($certBytes, $certPassword);

        $httpClient   = $installation && $installation->hasCertificate()
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

    /**
     * Select the correct AEAT sandbox endpoint based on the certificate type.
     *
     * AEAT publishes two endpoints (from the official WSDL):
     *   prewww1  → persona física / representante
     *   prewww10 → certificado de sello (tipos 4 y 8 en @firma)
     *
     * We detect the type by inspecting the certificate. If detection fails we
     * fall back to the configured VERIFACTU_AEAT_SANDBOX_URL value.
     */
    private function resolveEndpoint(?string $certBytes, string $password): string
    {
        $default = config('verifactu.aeat.sandbox_url');
        $sello   = config('verifactu.aeat.sandbox_url_sello');

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

        $parsed   = openssl_x509_parse($certs['cert']);
        $serial   = $parsed['subject']['serialNumber'] ?? '';
        $policies = $parsed['extensions']['certificatePolicies'] ?? '';

        // Sello indicators: no IDCES- prefix and FNMT sello OID present
        $isPersonaFisica = str_starts_with($serial, 'IDCES-')
            || str_contains($policies, '1.3.6.1.4.1.5734.3.10.1');

        return $isPersonaFisica ? $default : $sello;
    }

    private function ensureConfig(?\Crater\Models\VerifactuInstallation $installation = null): void
    {
        $hasCert = ($installation && $installation->hasCertificate())
            || config('verifactu.aeat.certificate_path');

        if (! $hasCert) {
            throw new RuntimeException('No certificate configured. Upload one in VERI*FACTU Setup.');
        }
    }
}
