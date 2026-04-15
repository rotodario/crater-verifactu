<?php

namespace Crater\Services\Verifactu\Drivers;

use Carbon\Carbon;
use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\AeatHttpClient;
use Crater\Services\Verifactu\AeatResponseParser;
use Crater\Services\Verifactu\Drivers\Contracts\VerifactuDriverInterface;
use Crater\Services\Verifactu\VerifactuXmlBuilder;
use RuntimeException;

/**
 * Sends VERI*FACTU records to the AEAT production endpoint.
 *
 * Production endpoint:
 *   https://www10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP
 *
 * ⚠️  Records submitted here have full legal effect before AEAT.
 *     The hash chain is immutable once accepted.
 *
 * Requirements:
 *   - VERIFACTU_AEAT_PRODUCTION_URL — endpoint URL (env)
 *   - VERIFACTU_CERT_PATH           — path to .p12 or .pem certificate
 *   - VERIFACTU_CERT_PASSWORD       — certificate password
 */
class AeatProductionDriver implements VerifactuDriverInterface
{
    public function getName(): string
    {
        return 'aeat_production';
    }

    public function submit(VerifactuSubmission $submission): void
    {
        $this->ensureConfig();

        $record = $submission->record;
        if (! $record) {
            throw new RuntimeException('VerifactuRecord not found for submission #' . $submission->id);
        }

        $record->loadMissing(['installation']);

        // 1. Build SOAP XML
        $xmlBuilder = new VerifactuXmlBuilder();
        $requestXml = $xmlBuilder->build($record);

        // 2. Persist the request XML for trazabilidad
        $submission->request_xml = $requestXml;
        $submission->save();

        // 3. Send to AEAT production
        $httpClient = new AeatHttpClient(
            endpointUrl:  config('verifactu.aeat.production_url'),
            certPath:     config('verifactu.aeat.certificate_path'),
            certPassword: config('verifactu.aeat.certificate_password', ''),
        );

        $responseXml = $httpClient->send($requestXml);

        // 4. Persist raw response
        $submission->response_xml = $responseXml;
        $submission->save();

        // 5. Parse response
        $parser = new AeatResponseParser();
        $parsed = $parser->parse($responseXml);

        $submission->response_payload = $parsed;

        if ($parsed['accepted']) {
            $submission->status             = 'ACCEPTED';
            $submission->csv                = $parsed['csv'];
            $submission->external_reference = $parsed['csv'];
            $submission->completed_at       = Carbon::now();
            $submission->save();

            $record->status = 'ACCEPTED';
            $record->save();
        } else {
            $errorSummary = $parser->summariseErrors($parsed);
            throw new RuntimeException('AEAT production rejected submission: ' . $errorSummary);
        }
    }

    private function ensureConfig(): void
    {
        if (! config('verifactu.aeat.production_url')) {
            throw new RuntimeException('VERIFACTU_AEAT_PRODUCTION_URL is not configured.');
        }
        if (! config('verifactu.aeat.certificate_path')) {
            throw new RuntimeException('VERIFACTU_CERT_PATH is not configured.');
        }
    }
}
