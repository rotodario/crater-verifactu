<?php

namespace Crater\Services\Verifactu;

use Crater\Models\Invoice;
use Crater\Models\VerifactuRecord;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;

class VerifactuQrService
{
    /**
     * Official AEAT verification URL format (production):
     * https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR
     *   ?nif={nif}&numserie={num}&fecha={dd-mm-yyyy}&importe={total}
     */
    const AEAT_VERIFY_URL = 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR';

    public function buildPayload(Invoice $invoice, VerifactuRecord $record): array
    {
        return [
            'nif'      => optional($invoice->company)->tax_number ?? '',
            'numserie' => $invoice->invoice_number,
            'fecha'    => optional($invoice->invoice_date)->format('d-m-Y') ?? '',
            'importe'  => number_format($invoice->total / 100, 2, '.', ''),
        ];
    }

    /**
     * Build the QR content string.
     *
     * In production: AEAT verification URL with query params.
     * In development (no base_url set): local URL so the QR still scans to something useful.
     */
    public function buildDisplayString(array $payload): string
    {
        $base = config('verifactu.qr.base_url') ?: self::AEAT_VERIFY_URL;

        return $base . '?' . http_build_query(array_filter($payload, fn($v) => $v !== ''));
    }

    /**
     * Generate a QR code PNG and return it as a base64 data URI.
     * Safe to embed directly in <img src="..."> inside DOMPDF.
     */
    public function generateBase64(string $content, int $size = 120): string
    {
        $qrCode = QrCode::create($content)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->setSize($size)
            ->setMargin(4)
            ->setRoundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return $result->getDataUri();
    }

    /**
     * Build payload, render QR image and return the data URI.
     * Returns null if the record has no data.
     */
    public function buildQrDataUri(Invoice $invoice, VerifactuRecord $record): ?string
    {
        $payload = $this->buildPayload($invoice, $record);
        $content = $this->buildDisplayString($payload);

        if (! $content) {
            return null;
        }

        try {
            return $this->generateBase64($content);
        } catch (\Throwable $e) {
            \Log::warning('Verifactu QR generation failed: ' . $e->getMessage());
            return null;
        }
    }
}
