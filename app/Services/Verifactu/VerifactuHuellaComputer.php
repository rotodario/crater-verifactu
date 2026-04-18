<?php

namespace Crater\Services\Verifactu;

use Carbon\Carbon;

/**
 * Computes the VERI*FACTU Huella (SHA-256 chain hash) following the
 * exact formula defined in the AEAT technical specification.
 *
 * Input string (ampersand-separated key=value pairs, no spaces):
 *   IDEmisorFactura={nif}&NumSerieFactura={num}&FechaExpedicionFactura={dd-mm-yyyy}
 *   &TipoFactura={tipo}&CuotaTotal={xx.xx}&ImporteTotal={xx.xx}
 *   &Huella={previous_huella_or_empty}&FechaHoraHusoGenRegistro={iso_datetime}
 *
 * Result: SHA-256 hex digest, uppercase.
 */
class VerifactuHuellaComputer
{
    public function compute(
        string  $issuerNif,
        string  $invoiceNumber,
        string  $invoiceDate,       // dd-mm-yyyy
        string  $tipoFactura,       // F1, F2, R1-R5, …
        string  $cuotaTotal,        // "210.00"
        string  $importeTotal,      // "1210.00"
        ?string $previousHuella,
        string  $fechaHoraHuso      // "2026-04-15T10:00:00+02:00"
    ): string {
        $input = implode('&', [
            "IDEmisorFactura={$issuerNif}",
            "NumSerieFactura={$invoiceNumber}",
            "FechaExpedicionFactura={$invoiceDate}",
            "TipoFactura={$tipoFactura}",
            "CuotaTotal={$cuotaTotal}",
            "ImporteTotal={$importeTotal}",
            'Huella=' . ($previousHuella ?? ''),
            "FechaHoraHusoGenRegistro={$fechaHoraHuso}",
        ]);

        return strtoupper(hash('sha256', $input));
    }

    // -------------------------------------------------------------------------
    // Static formatting helpers — used consistently in builder AND xml builder
    // so the hash computed at record creation always matches the XML at
    // submission time.
    // -------------------------------------------------------------------------

    /**
     * Format a Carbon datetime as AEAT requires: ISO 8601 with Spain timezone offset.
     * e.g. "2026-04-15T10:00:00+02:00"
     */
    public static function formatTimestamp(Carbon $dt): string
    {
        return $dt->copy()->timezone('Europe/Madrid')->format('Y-m-d\TH:i:sP');
    }

    /**
     * Format a Carbon date as AEAT requires for invoice date fields.
     * e.g. "15-04-2026"
     */
    public static function formatInvoiceDate(Carbon $dt): string
    {
        return $dt->format('d-m-Y');
    }

    /**
     * Convert integer cents to decimal euros string with exactly 2 decimal places.
     * e.g. 121000 → "1210.00"
     */
    public static function formatAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * Compute the Huella for a RegistroBaja (cancellation record).
     *
     * The baja formula omits TipoFactura, CuotaTotal and ImporteTotal
     * (financial fields are not part of a cancellation's hash chain):
     *
     *   IDEmisorFactura={nif}&NumSerieFactura={num}&FechaExpedicionFactura={date}
     *   &Huella={previous_or_empty}&FechaHoraHusoGenRegistro={timestamp}
     */
    public function computeBaja(
        string  $issuerNif,
        string  $invoiceNumber,
        string  $invoiceDate,       // dd-mm-yyyy
        ?string $previousHuella,
        string  $fechaHoraHuso
    ): string {
        // VERI*FACTU spec: RegistroBaja hash formula uses the "Anulada" field name
        // variants — different from RegistroAlta which uses plain field names.
        // AEAT verifies: IDEmisorFacturaAnulada, NumSerieFacturaAnulada,
        //                FechaExpedicionFacturaAnulada (NOT the plain versions).
        $input = implode('&', [
            "IDEmisorFacturaAnulada={$issuerNif}",
            "NumSerieFacturaAnulada={$invoiceNumber}",
            "FechaExpedicionFacturaAnulada={$invoiceDate}",
            'Huella=' . ($previousHuella ?? ''),
            "FechaHoraHusoGenRegistro={$fechaHoraHuso}",
        ]);

        return strtoupper(hash('sha256', $input));
    }

    /**
     * Determine the VERI*FACTU TipoFactura code from invoice data.
     *
     * F1 — Full invoice (factura completa)
     * F2 — Simplified invoice (factura simplificada, sin destinatario identificado)
     * R4 — Rectificative invoice (generic; covers most correction cases)
     */
    public static function tipoFactura(array $invoiceSnapshot): string
    {
        $kind = $invoiceSnapshot['invoice_kind'] ?? null;

        if ($kind === 'rectificative') {
            // TODO: map specific rectification_type → R1-R5 when needed
            return 'R4';
        }

        return 'F1';
    }
}
