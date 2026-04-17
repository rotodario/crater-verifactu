<?php

namespace Crater\Services\Verifactu\Drivers;

use Carbon\Carbon;
use Crater\Models\VerifactuInstallation;
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
 */
class AeatProductionDriver implements VerifactuDriverInterface
{
    public function getName(): string
    {
        return 'aeat_production';
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

        // 3. Send to AEAT production
        $installation = $record->installation;
        $httpClient   = $installation && $installation->hasCertificate()
            ? new AeatHttpClient(
                endpointUrl:  config('verifactu.aeat.production_url'),
                certPassword: $installation->getCertPassword(),
                certData:     $installation->getCertBytes(),
                certType:     $installation->cert_type ?? 'p12',
            )
            : new AeatHttpClient(
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

    private function ensureConfig(?VerifactuInstallation $installation = null): void
    {
        if (! config('verifactu.aeat.production_url')) {
            throw new RuntimeException('VERIFACTU_AEAT_PRODUCTION_URL is not configured.');
        }

        $hasCert = ($installation && $installation->hasCertificate())
            || config('verifactu.aeat.certificate_path');

        if (! $hasCert) {
            throw new RuntimeException('No certificate configured. Upload one in VERI*FACTU Setup.');
        }

        if ($installation && $installation->hasCertificate()) {
            $this->warnIfPersonalCertificate($installation->getCertBytes(), $installation->getCertPassword());
        } elseif ($path = config('verifactu.aeat.certificate_path')) {
            if (file_exists($path)) {
                $this->warnIfPersonalCertificate(file_get_contents($path), config('verifactu.aeat.certificate_password', ''));
            }
        }
    }

    private function warnIfPersonalCertificate(string $certBytes, string $password): void
    {
        $certs = [];
        if (! @openssl_pkcs12_read($certBytes, $certs, $password)) {
            return;
        }

        if (empty($certs['cert'])) {
            return;
        }

        $parsed = openssl_x509_parse($certs['cert']);

        $serial = $parsed['subject']['serialNumber'] ?? '';
        if (str_starts_with($serial, 'IDCES-')) {
            throw new RuntimeException(
                'Certificado de persona física detectado (serialNumber: ' . $serial . '). ' .
                'AEAT VERI*FACTU solo acepta Certificados de Sello de Entidad (persona jurídica). ' .
                'Obtén un Certificado de Representante de Persona Jurídica en sede.fnmt.gob.es.'
            );
        }

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
