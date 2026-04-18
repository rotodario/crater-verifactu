<?php

namespace Crater\Services\Verifactu\Drivers;

use Carbon\Carbon;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;
use Crater\Services\Verifactu\AeatHttpClient;
use Crater\Services\Verifactu\AeatResponseParser;
use Crater\Services\Verifactu\Drivers\Contracts\VerifactuDriverInterface;
use Crater\Services\Verifactu\VerifactuHuellaComputer;
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

        // 0. Refresh FechaHoraHusoGenRegistro and recompute hash just before sending.
        //    AEAT requires the timestamp to be within 240 seconds of its own clock.
        //    If the record was created/queued earlier its stored timestamp may be stale.
        $this->refreshTimestampAndHash($record);

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
            // Check line-level status — AceptadoConErrores means AEAT registered the
            // record but flagged a warning (e.g. hash mismatch on old records).
            // We still mark it ACCEPTED since AEAT has created the fiscal register.
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
            throw new RuntimeException('AEAT sandbox rejected submission: ' . $errorSummary);
        }
    }

    /**
     * Refresh FechaHoraHusoGenRegistro and recompute the Huella just before
     * sending so the timestamp is always within AEAT's 240-second window.
     *
     * If the hash changes (because the timestamp changed), any pending record
     * that chains from this one has its previous_hash updated automatically.
     */
    /**
     * Refresh FechaHoraHusoGenRegistro and recompute the Huella just before
     * sending so the timestamp is always within AEAT's 240-second window.
     *
     * IMMUTABILITY NOTE:
     * - issued_at is NOT touched — it records when the fiscal decision was made, not when we sent.
     * - Only hash and metadata.fecha_hora_huso are updated (they are technically tied to the
     *   transmission timestamp, not to the issuance decision).
     * - The previous hash value is preserved in metadata.original_hash for audit trail.
     * - Chain propagation to pending records is logged in metadata.hash_refreshed_at.
     */
    private function refreshTimestampAndHash(VerifactuRecord $record): void
    {
        // Safety guard: never mutate a record that AEAT has already accepted.
        // If somehow a PENDING/FAILED submission exists for an ACCEPTED record
        // (e.g. network timeout where AEAT accepted but we never received the response),
        // refreshing its hash would silently corrupt the chain anchor that AEAT already holds.
        if ($record->status === 'ACCEPTED') {
            return;
        }

        $now              = Carbon::now('UTC');
        $newFechaHoraHuso = VerifactuHuellaComputer::formatTimestamp($now);
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
                cuotaTotal:     $meta['cuota_total']  ?? '0.00',
                importeTotal:   $meta['importe_total'] ?? '0.00',
                previousHuella: $record->previous_hash,
                fechaHoraHuso:  $newFechaHoraHuso,
            );
        }

        $metadata = $record->metadata ?? [];

        // Preserve the original hash for audit trail before overwriting
        if ($oldHash !== $newHash && empty($metadata['original_hash'])) {
            $metadata['original_hash']          = $oldHash;
            $metadata['original_fecha_hora_huso'] = $metadata['fecha_hora_huso'] ?? null;
        }

        $metadata['fecha_hora_huso']  = $newFechaHoraHuso;
        $metadata['hash_refreshed_at'] = $now->toISOString();
        $record->metadata             = $metadata;
        $record->hash                 = $newHash;
        // issued_at is intentionally NOT updated — it represents the fiscal issuance decision timestamp.
        $record->save();

        if ($oldHash !== $newHash) {
            // Propagate the updated hash to any pending record that chains from this one.
            // Without this, chained records would carry a stale previous_hash and AEAT would reject them.
            $affected = VerifactuRecord::where('previous_hash', $oldHash)
                ->whereHas('submissions', fn ($q) => $q->whereIn('status', ['PENDING', 'FAILED']))
                ->get();

            foreach ($affected as $chained) {
                $chainedMeta = $chained->metadata ?? [];
                $chainedMeta['previous_hash_updated_from'] = $oldHash;
                $chainedMeta['previous_hash_updated_at']   = $now->toISOString();
                $chained->metadata      = $chainedMeta;
                $chained->previous_hash = $newHash;
                $chained->save();
            }
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
