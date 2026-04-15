@if ($invoice->isFiscalIssued() && $invoice->verifactuRecord)
    <div style="margin: 18px 30px 0 30px; page-break-inside: avoid;">
        <table width="100%" style="border: 1px solid #D1D5DB; background: #F9FAFB;">
            <tr>
                <td style="padding: 10px 12px; vertical-align: top;">
                    <div style="font-size: 9px; text-transform: uppercase; letter-spacing: 1px; font-weight: bold; color: #374151;">
                        VERI*FACTU
                    </div>
                    <div style="font-size: 10px; color: #111827; margin-top: 5px;">
                        Estado fiscal: <strong>{{ $invoice->fiscal_status }}</strong>
                    </div>
                    <div style="font-size: 10px; color: #111827; margin-top: 3px;">
                        Expedida: <strong>{{ optional($invoice->fiscal_issued_at)->format('Y-m-d H:i:s') }}</strong>
                    </div>
                    <div style="font-size: 9px; color: #4B5563; margin-top: 6px; word-break: break-all;">
                        Huella: {{ $invoice->verifactuRecord->hash }}
                    </div>
                </td>
                <td style="padding: 10px 12px; vertical-align: top; width: 42%;">
                    <div style="font-size: 9px; color: #4B5563; margin-bottom: 4px;">
                        Payload QR preparado
                    </div>
                    <div style="font-size: 8px; color: #111827; word-break: break-all; line-height: 11px;">
                        {{ $invoice->verifactu_qr_string }}
                    </div>
                </td>
            </tr>
        </table>
    </div>
@endif
