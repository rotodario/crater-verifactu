<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuInstallation;
use Crater\Models\VerifactuRecord;
use Crater\Models\VerifactuSubmission;

/**
 * Queries AEAT ConsultaFactuSistemaFacturacion for a list of registered
 * invoices (historial remoto). Supports period filtering and pagination.
 */
class AeatHistorialService
{
    /**
     * Query AEAT for the remote historial of a given period.
     *
     * Returns:
     * [
     *   'success'          => bool,
     *   'mode'             => string,
     *   'resultado'        => 'ConDatos'|'SinDatos',
     *   'hay_mas_paginas'  => bool,
     *   'clave_paginacion' => string|null,
     *   'records'          => [...],   // parsed + local-matched
     *   'request_xml'      => string,
     *   'response_xml'     => string,
     *   'error'            => string|null,
     * ]
     */
    public function query(
        VerifactuInstallation $installation,
        string  $nif,
        string  $name,
        string  $ejercicio,
        string  $periodo,
        ?string $numSerie        = null,
        ?string $clavePaginacion = null
    ): array {
        $mode = $installation->mode ?? config('verifactu.mode', 'shadow');

        try {
            $httpClient = $this->buildHttpClient($installation, $mode);
        } catch (\Throwable $e) {
            return $this->errorResult($mode, '', '', 'Error al configurar el cliente AEAT: ' . $e->getMessage());
        }

        $builder    = new AeatConsultaXmlBuilder();
        $requestXml = $builder->buildHistorial($nif, $name, $ejercicio, $periodo, $numSerie, $clavePaginacion);

        try {
            $responseXml = $httpClient->send($requestXml);
        } catch (\Throwable $e) {
            return $this->errorResult($mode, $requestXml, '', 'Error de comunicación con AEAT: ' . $e->getMessage());
        }

        try {
            $parser = new AeatConsultaParser();
            $parsed = $parser->parse($responseXml);
        } catch (\Throwable $e) {
            return $this->errorResult($mode, $requestXml, $responseXml, 'Error al procesar la respuesta AEAT: ' . $e->getMessage());
        }

        if ($parsed['raw_error']) {
            return $this->errorResult($mode, $requestXml, $responseXml, $parsed['raw_error']);
        }

        // Enrich each record with local data
        $records = [];
        foreach ($parsed['records'] as $r) {
            $r['local'] = $this->matchLocalRecord($nif, $r['invoice_number'], $r['invoice_date'], $r['huella']);
            $records[]  = $r;
        }

        return [
            'success'          => true,
            'mode'             => $mode,
            'resultado'        => $parsed['resultado'],
            'hay_mas_paginas'  => $parsed['hay_mas_paginas'],
            'clave_paginacion' => $parsed['clave_paginacion'],
            'records'          => $records,
            'request_xml'      => $requestXml,
            'response_xml'     => $responseXml,
            'error'            => null,
        ];
    }

    // -------------------------------------------------------------------------

    private function buildHttpClient(VerifactuInstallation $installation, string $mode): AeatHttpClient
    {
        $isProduction = $mode === 'aeat_production';
        $verifySsl    = $isProduction || (bool) config('verifactu.aeat.sandbox_verify_ssl', false);

        $certBytes    = $installation->hasCertificate() ? $installation->getCertBytes()    : null;
        $certPassword = $installation->hasCertificate() ? $installation->getCertPassword() : config('verifactu.aeat.certificate_password', '');

        $defaultUrl = $isProduction
            ? config('verifactu.aeat.production_url')
            : config('verifactu.aeat.sandbox_url');
        $selloUrl = $isProduction
            ? config('verifactu.aeat.production_url_sello')
            : config('verifactu.aeat.sandbox_url_sello');

        $endpointUrl = $this->resolveEndpoint($certBytes, $certPassword, $defaultUrl, $selloUrl);

        if ($certBytes) {
            return new AeatHttpClient(
                endpointUrl:  $endpointUrl,
                certPassword: $certPassword,
                certData:     $certBytes,
                certType:     $installation->cert_type ?? 'p12',
                verifySsl:    $verifySsl,
            );
        }

        return new AeatHttpClient(
            endpointUrl:  $endpointUrl,
            certPath:     config('verifactu.aeat.certificate_path'),
            certPassword: config('verifactu.aeat.certificate_password', ''),
            verifySsl:    $verifySsl,
        );
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

    /**
     * Try to find a local VerifactuRecord matching this remote entry.
     * Returns null if no reliable match exists.
     */
    private function matchLocalRecord(string $nif, string $invoiceNumber, string $invoiceDate, ?string $remoteHuella): ?array
    {
        if ($invoiceNumber === '') {
            return null;
        }

        $record = VerifactuRecord::where('invoice_number', $invoiceNumber)
            ->whereHas('installation', fn($q) => $q->whereHas('company', fn($q2) => $q2->where('tax_number', $nif)))
            ->latest('id')
            ->first();

        if (! $record) {
            return null;
        }

        $hashMatch = ($remoteHuella !== null)
            ? (strtoupper($remoteHuella) === strtoupper($record->hash))
            : null;

        $submission = VerifactuSubmission::where('verifactu_record_id', $record->id)
            ->latest('id')
            ->first();

        return [
            'id'               => $record->id,
            'hash'             => $record->hash,
            'hash_match'       => $hashMatch,
            'status'           => $record->status,
            'submission_status' => $submission?->status,
            'submission_id'    => $submission?->id,
        ];
    }

    private function errorResult(string $mode, string $requestXml, string $responseXml, string $error): array
    {
        return [
            'success'          => false,
            'mode'             => $mode,
            'resultado'        => 'SinDatos',
            'hay_mas_paginas'  => false,
            'clave_paginacion' => null,
            'records'          => [],
            'request_xml'      => $requestXml,
            'response_xml'     => $responseXml,
            'error'            => $error,
        ];
    }
}
