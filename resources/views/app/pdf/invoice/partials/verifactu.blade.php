@if ($invoice->isFiscalIssued() && $invoice->verifactuRecord)
    {{-- clear: both evita solapamiento con floats del contenido anterior --}}
    <div style="clear: both;"></div>
    <div style="margin: 32px 30px 0 30px; page-break-before: auto; page-break-inside: avoid;">
        <table width="100%" style="border: 1px solid #D1D5DB; background: #F9FAFB; border-radius: 4px;">
            <tr>
                {{-- Left column: fiscal metadata --}}
                <td style="padding: 10px 14px; vertical-align: top;">
                    <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; color: #374151; margin-bottom: 6px;">
                        VERI*FACTU — Factura verificable
                    </div>

                    <table style="font-size: 9.5px; color: #111827; border-collapse: collapse;">
                        <tr>
                            <td style="color: #6B7280; padding-right: 6px; white-space: nowrap;">NIF emisor:</td>
                            <td><strong>{{ optional($invoice->company)->tax_number }}</strong></td>
                        </tr>
                        <tr>
                            <td style="color: #6B7280; padding-right: 6px; white-space: nowrap;">Nº factura:</td>
                            <td><strong>{{ $invoice->invoice_number }}</strong></td>
                        </tr>
                        <tr>
                            <td style="color: #6B7280; padding-right: 6px; white-space: nowrap;">Fecha:</td>
                            <td><strong>{{ optional($invoice->invoice_date)->format('d-m-Y') }}</strong></td>
                        </tr>
                        <tr>
                            <td style="color: #6B7280; padding-right: 6px; white-space: nowrap;">Importe total:</td>
                            <td><strong>{{ number_format($invoice->total / 100, 2, ',', '.') }} €</strong></td>
                        </tr>
                        <tr>
                            <td style="color: #6B7280; padding-right: 6px; padding-top: 4px; white-space: nowrap; vertical-align: top;">Huella:</td>
                            <td style="padding-top: 4px; font-size: 7.5px; word-break: break-all; line-height: 11px; color: #4B5563;">
                                {{ $invoice->verifactuRecord->hash }}
                            </td>
                        </tr>
                    </table>
                </td>

                {{-- Right column: QR code image --}}
                <td style="padding: 10px 14px; vertical-align: middle; text-align: center; width: 140px;">
                    @if (!empty($verifactu_qr_image))
                        <img src="{{ $verifactu_qr_image }}"
                             width="120" height="120"
                             alt="QR VERI*FACTU"
                             style="display: block; margin: 0 auto;" />
                        <div style="font-size: 7px; color: #9CA3AF; margin-top: 4px; text-align: center;">
                            Escanea para verificar
                        </div>
                    @else
                        <div style="font-size: 8px; color: #9CA3AF; text-align: center;">
                            QR no disponible
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>
@endif
