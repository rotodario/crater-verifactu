<?php

namespace Crater\Services\Verifactu\Drivers;

use Carbon\Carbon;
use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\AeatHttpClient;
use Crater\Services\Verifactu\AeatResponseParser;
use Crater\Services\Verifactu\Drivers\Contracts\VerifactuDriverInterface;
use Crater\Services\Verifactu\VerifactuHuellaComputer;
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

        // 0. Refresh FechaHoraHusoGenRegistro and recompute hash just before sending.
        $this->refreshTimestampAndHash($record);

        // 1. Build SOAP XML
        $xmlBuilder = new VerifactuXmlBuilder();
        $requestXml = $xmlBuilder->build($record);

        // 2. Persist the request XML for trazabilidad
        $submission->request_xml = $requestXml;
        $submission->save();

        // 3. Send to AEAT production
        // AEAT has two production endpoints depending on certificate type (from the official WSDL):
        //   www1  → persona física / representante
        //   www10 → certificado de sello
        $installation = $record->installation;
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
            )
            : new AeatHttpClient(
                endpointUrl:  $endpointUrl,
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
            $hasLineErrors = collect($parsed['lines'])
                ->contains(fn($l) => ! in_array($l['estado'], ['Correcto', 'AceptadoConErrores'], true));

            $submission->status             = 'ACCEPTED';
            $submission->csv                = $parsed['csv'];
            $submission->external_reference = $parsed['csv'];
            $submission->completed_at       = Carbon::now();
            $submission->error_message      = $hasLineErrors ? $parser->summariseErrors($parsed) : null;
            $submission->save();

            $record->status = 'ACCEPTED';
            $record->save();
        } else {
            $errorSummary = $parser->summariseErrors($parsed);
            throw new RuntimeException('AEAT production rejected submission: ' . $errorSummary);
        }
    }

    private function refreshTimestampAndHash(VerifactuRecord $record): void
    {
        $newIssuedAt      = Carbon::now('UTC');
        $newFechaHoraHuso = VerifactuHuellaComputer::formatTimestamp($newIssuedAt);
        $oldHash          = $record->hash;
        $huellaComputer   = new VerifactuHuellaComputer();
        $companyNif       = $record->snapshot['company']['tax_number'] ?? '';
        $invoiceDate      = VerifactuHuellaComputer::formatInvoiceDate(
            \Carbon\Carbon::parse($record->invoice_date)
        );

        if ($record->record_type === 'invoice_cancellation') {
            $newHash = $huellaComputer->computeBaja(
                issuerNif:      $companyNif,
                invoiceNumber:  $record->invoice_number,
                invoiceDate:    $invoiceDate,
                previousHuella: $record->previous_hash,
                fechaHoraHuso:  $newFechaHoraHuso,
            );
        } else {
            $meta    = $record->metadata ?? [];
            $newHash = $huellaComputer->compute(
                issuerNif:      $companyNif,
                invoiceNumber:  $record->invoice_number,
                invoiceDate:    $invoiceDate,
                tipoFactura:    $record->tipo_factura ?? 'F1',
                cuotaTotal:     $meta['cuota_total']   ?? '0.00',
                importeTotal:   $meta['importe_total'] ?? '0.00',
                previousHuella: $record->previous_hash,
                fechaHoraHuso:  $newFechaHoraHuso,
            );
        }

        $metadata                    = $record->metadata ?? [];
        $metadata['fecha_hora_huso'] = $newFechaHoraHuso;
        $record->metadata            = $metadata;
        $record->hash                = $newHash;
        $record->issued_at           = $newIssuedAt;
        $record->save();

        if ($oldHash !== $newHash) {
            VerifactuRecord::where('previous_hash', $oldHash)
                ->whereHas('submissions', fn ($q) => $q->whereIn('status', ['PENDING', 'FAILED']))
                ->update(['previous_hash' => $newHash]);
        }
    }

    private function resolveEndpoint(?string $certBytes, string $password): string
    {
        $default = config('verifactu.aeat.production_url');
        $sello   = config('verifactu.aeat.production_url_sello');

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

        $isPersonaFisica = str_starts_with($serial, 'IDCES-')
            || str_contains($policies, '1.3.6.1.4.1.5734.3.10.1');

        return $isPersonaFisica ? $default : $sello;
    }

    private function ensureConfig(?VerifactuInstallation $installation = null): void
    {
        $hasCert = ($installation && $installation->hasCertificate())
            || config('verifactu.aeat.certificate_path');

        if (! $hasCert) {
            throw new RuntimeException('No certificate configured. Upload one in VERI*FACTU Setup.');
        }
    }
}
