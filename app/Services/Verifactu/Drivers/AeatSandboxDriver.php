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
        $httpClient   = $installation && $installation->hasCertificate()
            ? new AeatHttpClient(
                endpointUrl:  config('verifactu.aeat.sandbox_url'),
                certPassword: $installation->getCertPassword(),
                certData:     $installation->getCertBytes(),
                certType:     $installation->cert_type ?? 'p12',
            )
            : new AeatHttpClient(
                endpointUrl:  config('verifactu.aeat.sandbox_url'),
                certPath:     config('verifactu.aeat.certificate_path'),
                certPassword: config('verifactu.aeat.certificate_password', ''),
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
    }
}
