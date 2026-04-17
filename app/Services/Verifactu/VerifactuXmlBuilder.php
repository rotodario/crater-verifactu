<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuRecord;
use DOMDocument;
use DOMElement;

/**
 * Builds the SOAP/XML payload for a VERI*FACTU SuministroLRFacturasEmitidas
 * request following the AEAT schema v1.0.
 *
 * Namespaces:
 *   sum  → SuministroInformacion (Cabecera + common types)
 *   sum1 → SuministroLR         (root element + RegistroFactura)
 *   soapenv → SOAP envelope
 */
class VerifactuXmlBuilder
{
    const NS_SOAP  = 'http://schemas.xmlsoap.org/soap/envelope/';
    const NS_SUM   = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
    const NS_SUM1  = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';

    public function build(VerifactuRecord $record): string
    {
        if ($record->record_type === 'invoice_cancellation') {
            return $this->buildBaja($record);
        }

        return $this->buildAlta($record);
    }

    public function buildAlta(VerifactuRecord $record): string
    {
        $record->loadMissing(['installation']);

        $snap        = $record->snapshot;
        $invoice     = $snap['invoice'];
        $company     = $snap['company'];
        $customer    = $snap['customer'];
        $taxes       = $snap['taxes'] ?? [];
        $software    = $snap['software'];
        $installation = $record->installation;

        $issuedAt    = $record->issued_at;
        $tipoFactura = $record->tipo_factura ?: 'F1';

        // ------------------------------------------------------------------ //
        // DOM setup
        // ------------------------------------------------------------------ //
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // SOAP Envelope
        $envelope = $dom->createElementNS(self::NS_SOAP, 'soapenv:Envelope');
        $envelope->setAttribute('xmlns:sum',  self::NS_SUM);
        $envelope->setAttribute('xmlns:sum1', self::NS_SUM1);
        $dom->appendChild($envelope);

        $envelope->appendChild($dom->createElementNS(self::NS_SOAP, 'soapenv:Header'));

        $body = $dom->createElementNS(self::NS_SOAP, 'soapenv:Body');
        $envelope->appendChild($body);

        // SuministroLRFacturasEmitidas
        $root = $dom->createElementNS(self::NS_SUM1, 'sum1:SuministroLRFacturasEmitidas');
        $body->appendChild($root);

        // ------------------------------------------------------------------ //
        // Cabecera
        // ------------------------------------------------------------------ //
        $cabecera = $dom->createElementNS(self::NS_SUM, 'sum:Cabecera');
        $root->appendChild($cabecera);

        $obligado = $dom->createElementNS(self::NS_SUM, 'sum:ObligadoEmision');
        $cabecera->appendChild($obligado);
        $this->addText($dom, $obligado, 'sum:NombreRazon', self::NS_SUM, $company['name']);
        $this->addText($dom, $obligado, 'sum:NIF',         self::NS_SUM, $company['tax_number'] ?? '');

        // ------------------------------------------------------------------ //
        // RegistroFactura > RegistroAlta
        // ------------------------------------------------------------------ //
        $registroFactura = $dom->createElementNS(self::NS_SUM1, 'sum1:RegistroFactura');
        $root->appendChild($registroFactura);

        $alta = $dom->createElementNS(self::NS_SUM1, 'sum1:RegistroAlta');
        $registroFactura->appendChild($alta);

        $this->addText($dom, $alta, 'sum1:IDVersion', self::NS_SUM1, '1.0');

        // IDFactura
        $idFactura = $dom->createElementNS(self::NS_SUM1, 'sum1:IDFactura');
        $alta->appendChild($idFactura);
        $this->addText($dom, $idFactura, 'sum1:IDEmisorFacturaEmisor',      self::NS_SUM1, $company['tax_number'] ?? '');
        $this->addText($dom, $idFactura, 'sum1:NumSerieFactura',            self::NS_SUM1, $invoice['number']);
        $this->addText($dom, $idFactura, 'sum1:FechaExpedicionFacturaEmisor', self::NS_SUM1,
            VerifactuHuellaComputer::formatInvoiceDate(
                \Carbon\Carbon::parse($invoice['date'])
            )
        );

        $this->addText($dom, $alta, 'sum1:NombreRazonEmisorFactura', self::NS_SUM1, $company['name']);
        $this->addText($dom, $alta, 'sum1:TipoFactura',              self::NS_SUM1, $tipoFactura);

        // DescripcionOperacion — required, max 500 chars
        $description = ! empty($invoice['notes'])
            ? mb_substr(strip_tags($invoice['notes']), 0, 500)
            : 'Servicios/Bienes según factura ' . $invoice['number'];
        $this->addText($dom, $alta, 'sum1:DescripcionOperacion', self::NS_SUM1, $description);

        // ------------------------------------------------------------------ //
        // Destinatarios (skip for F2 simplified invoices without recipient)
        // ------------------------------------------------------------------ //
        if ($tipoFactura !== 'F2' && ! empty($customer['name'])) {
            $destinatarios = $dom->createElementNS(self::NS_SUM1, 'sum1:Destinatarios');
            $alta->appendChild($destinatarios);

            $idDestinatario = $dom->createElementNS(self::NS_SUM1, 'sum1:IDDestinatario');
            $destinatarios->appendChild($idDestinatario);

            $this->addText($dom, $idDestinatario, 'sum1:NombreRazon', self::NS_SUM1, $customer['name']);

            $taxNumber = $customer['tax_number'] ?? '';
            if ($this->isSpanishNif($taxNumber)) {
                $this->addText($dom, $idDestinatario, 'sum1:NIF', self::NS_SUM1, $taxNumber);
            } else {
                // Foreign or unknown identifier
                $idOtro = $dom->createElementNS(self::NS_SUM1, 'sum1:IDOtro');
                $idDestinatario->appendChild($idOtro);
                $this->addText($dom, $idOtro, 'sum1:CodigoPais', self::NS_SUM1, 'ES');
                $this->addText($dom, $idOtro, 'sum1:IDType',     self::NS_SUM1, '07'); // 07 = not censado
                $this->addText($dom, $idOtro, 'sum1:ID',         self::NS_SUM1, $taxNumber ?: 'NO_IDENTIFICADO');
            }
        }

        // ------------------------------------------------------------------ //
        // Desglose (tax breakdown)
        // ------------------------------------------------------------------ //
        $desglose = $dom->createElementNS(self::NS_SUM1, 'sum1:Desglose');
        $alta->appendChild($desglose);

        $desgloseLines = $this->buildDesgloseLines($invoice, $taxes);
        foreach ($desgloseLines as $line) {
            $detalle = $dom->createElementNS(self::NS_SUM1, 'sum1:DetalleDesglose');
            $desglose->appendChild($detalle);

            $this->addText($dom, $detalle, 'sum1:Impuesto',       self::NS_SUM1, $line['impuesto']);
            $this->addText($dom, $detalle, 'sum1:ClaveRegimen',   self::NS_SUM1, $line['clave_regimen']);
            $this->addText($dom, $detalle, 'sum1:CalificacionOperacion', self::NS_SUM1, $line['calificacion']);

            if ($line['tipo_impositivo'] !== null) {
                $this->addText($dom, $detalle, 'sum1:TipoImpositivo', self::NS_SUM1, $line['tipo_impositivo']);
            }
            $this->addText($dom, $detalle, 'sum1:BaseImponibleOImporteNoSujeto', self::NS_SUM1, $line['base']);
            if ($line['cuota'] !== null) {
                $this->addText($dom, $detalle, 'sum1:CuotaRepercutida', self::NS_SUM1, $line['cuota']);
            }
        }

        // ------------------------------------------------------------------ //
        // CuotaTotal / ImporteTotal
        // ------------------------------------------------------------------ //
        $this->addText($dom, $alta, 'sum1:CuotaTotal',    self::NS_SUM1,
            VerifactuHuellaComputer::formatAmount((int) $invoice['tax_total'])
        );
        $this->addText($dom, $alta, 'sum1:ImporteTotal',  self::NS_SUM1,
            VerifactuHuellaComputer::formatAmount((int) $invoice['total'])
        );

        // ------------------------------------------------------------------ //
        // SistemaInformatico
        // ------------------------------------------------------------------ //
        $si = $dom->createElementNS(self::NS_SUM1, 'sum1:SistemaInformatico');
        $alta->appendChild($si);

        $this->addText($dom, $si, 'sum1:NombreRazon',              self::NS_SUM1, $software['vendor_name'] ?? config('verifactu.software.vendor_name'));
        $this->addText($dom, $si, 'sum1:NIF',                      self::NS_SUM1, $software['vendor_tax_id'] ?? config('verifactu.software.vendor_tax_id'));
        $this->addText($dom, $si, 'sum1:NombreSistemaInformatico', self::NS_SUM1, $software['name']);
        $this->addText($dom, $si, 'sum1:IdSistemaInformatico',     self::NS_SUM1, $software['software_id'] ?? config('verifactu.software.id', 'CRATER-VF-01'));
        $this->addText($dom, $si, 'sum1:Version',                  self::NS_SUM1, $software['version']);
        $this->addText($dom, $si, 'sum1:NumeroInstalacion',        self::NS_SUM1, $software['installation_number'] ?? config('verifactu.software.installation_number', '1'));
        // 01 = producción, 02 = pre-producción (sandbox/stub/dev)
        $mode   = optional($installation)->mode ?: config('verifactu.mode', 'stub');
        $usoCod = ($mode === 'aeat_production') ? '01' : '02';
        $this->addText($dom, $si, 'sum1:TipoUsoCodSistemaInformatico', self::NS_SUM1, $usoCod);

        // ------------------------------------------------------------------ //
        // FechaHoraHusoGenRegistro + TipoHuella + Huella
        // ------------------------------------------------------------------ //
        $this->addText($dom, $alta, 'sum1:FechaHoraHusoGenRegistro', self::NS_SUM1,
            VerifactuHuellaComputer::formatTimestamp($issuedAt)
        );
        $this->addText($dom, $alta, 'sum1:TipoHuella', self::NS_SUM1, '01'); // 01 = SHA-256
        $this->addText($dom, $alta, 'sum1:Huella',     self::NS_SUM1, $record->hash);

        // ------------------------------------------------------------------ //
        // Encadenamiento (hash chain)
        // ------------------------------------------------------------------ //
        $encadenamiento = $dom->createElementNS(self::NS_SUM1, 'sum1:Encadenamiento');
        $alta->appendChild($encadenamiento);

        if (! $record->previous_hash) {
            $this->addText($dom, $encadenamiento, 'sum1:PrimerRegistro', self::NS_SUM1, 'S');
        } else {
            $prev = \Crater\Models\VerifactuRecord::where('hash', $record->previous_hash)->first();
            $regAnterior = $dom->createElementNS(self::NS_SUM1, 'sum1:RegistroAnterior');
            $encadenamiento->appendChild($regAnterior);

            $this->addText($dom, $regAnterior, 'sum1:IDEmisorFactura',          self::NS_SUM1, $company['tax_number'] ?? '');
            $this->addText($dom, $regAnterior, 'sum1:NumSerieFactura',          self::NS_SUM1, optional($prev)->invoice_number ?? '');
            $this->addText($dom, $regAnterior, 'sum1:FechaExpedicionFactura',   self::NS_SUM1,
                $prev && $prev->invoice_date
                    ? VerifactuHuellaComputer::formatInvoiceDate(\Carbon\Carbon::parse($prev->invoice_date))
                    : ''
            );
            $this->addText($dom, $regAnterior, 'sum1:Huella', self::NS_SUM1, $record->previous_hash);
        }

        return $dom->saveXML();
    }

    // -------------------------------------------------------------------------

    /**
     * Build the Desglose lines from the invoice-level taxes.
     *
     * Each unique tax rate produces one DetalleDesglose.
     * Rates at 0% are treated as non-subject (N2).
     * Remaining base after all rated lines = assigned to a 0% / N2 line.
     */
    private function buildDesgloseLines(array $invoice, array $taxes): array
    {
        $lines = [];
        $subTotal = (int) ($invoice['sub_total'] ?? 0);
        $allocatedBase = 0;

        // Group taxes by percent
        $grouped = [];
        foreach ($taxes as $tax) {
            $pct = (float) $tax['percent'];
            $amt = (int)   $tax['amount'];
            if (! isset($grouped[$pct])) {
                $grouped[$pct] = 0;
            }
            $grouped[$pct] += $amt;
        }

        foreach ($grouped as $pct => $taxAmountCents) {
            if ($pct > 0) {
                $baseCents = (int) round($taxAmountCents * 100 / $pct);
                $allocatedBase += $baseCents;
                $lines[] = [
                    'impuesto'      => '01',            // IVA
                    'clave_regimen' => '01',            // Régimen general
                    'calificacion'  => 'S1',            // Sujeta no exenta standard
                    'tipo_impositivo' => number_format($pct, 2, '.', ''),
                    'base'          => VerifactuHuellaComputer::formatAmount($baseCents),
                    'cuota'         => VerifactuHuellaComputer::formatAmount($taxAmountCents),
                ];
            }
        }

        // If there's remaining base (0% / no-tax lines)
        $remainingBase = $subTotal - $allocatedBase;
        if ($remainingBase > 0 || empty($lines)) {
            $lines[] = [
                'impuesto'        => '01',
                'clave_regimen'   => '01',
                'calificacion'    => 'N2',   // No sujeta / exenta
                'tipo_impositivo' => null,
                'base'            => VerifactuHuellaComputer::formatAmount(max(0, $remainingBase ?: $subTotal)),
                'cuota'           => null,
            ];
        }

        return $lines;
    }

    private function addText(DOMDocument $dom, DOMElement $parent, string $tagNs, string $ns, ?string $value): void
    {
        $el = $dom->createElementNS($ns, $tagNs);
        $el->appendChild($dom->createTextNode((string) ($value ?? '')));
        $parent->appendChild($el);
    }

    /**
     * Build a RegistroBaja (cancellation) SOAP envelope.
     *
     * Much simpler than RegistroAlta: no tax breakdown, no recipient,
     * just the identifying fields + software block + hash chain.
     */
    public function buildBaja(VerifactuRecord $record): string
    {
        $record->loadMissing(['installation']);

        $snap        = $record->snapshot;
        $invoice     = $snap['invoice'];
        $company     = $snap['company'];
        $software    = $snap['software'];
        $installation = $record->installation;

        $issuedAt = $record->issued_at;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $envelope = $dom->createElementNS(self::NS_SOAP, 'soapenv:Envelope');
        $envelope->setAttribute('xmlns:sum',  self::NS_SUM);
        $envelope->setAttribute('xmlns:sum1', self::NS_SUM1);
        $dom->appendChild($envelope);

        $envelope->appendChild($dom->createElementNS(self::NS_SOAP, 'soapenv:Header'));

        $body = $dom->createElementNS(self::NS_SOAP, 'soapenv:Body');
        $envelope->appendChild($body);

        $root = $dom->createElementNS(self::NS_SUM1, 'sum1:SuministroLRFacturasEmitidas');
        $body->appendChild($root);

        // Cabecera
        $cabecera = $dom->createElementNS(self::NS_SUM, 'sum:Cabecera');
        $root->appendChild($cabecera);
        $obligado = $dom->createElementNS(self::NS_SUM, 'sum:ObligadoEmision');
        $cabecera->appendChild($obligado);
        $this->addText($dom, $obligado, 'sum:NombreRazon', self::NS_SUM, $company['name']);
        $this->addText($dom, $obligado, 'sum:NIF',         self::NS_SUM, $company['tax_number'] ?? '');

        // RegistroFactura > RegistroBaja
        $registroFactura = $dom->createElementNS(self::NS_SUM1, 'sum1:RegistroFactura');
        $root->appendChild($registroFactura);

        $baja = $dom->createElementNS(self::NS_SUM1, 'sum1:RegistroBaja');
        $registroFactura->appendChild($baja);

        $this->addText($dom, $baja, 'sum1:IDVersion', self::NS_SUM1, '1.0');

        $idFactura = $dom->createElementNS(self::NS_SUM1, 'sum1:IDFactura');
        $baja->appendChild($idFactura);
        $this->addText($dom, $idFactura, 'sum1:IDEmisorFacturaEmisor',        self::NS_SUM1, $company['tax_number'] ?? '');
        $this->addText($dom, $idFactura, 'sum1:NumSerieFactura',              self::NS_SUM1, $invoice['number']);
        $this->addText($dom, $idFactura, 'sum1:FechaExpedicionFacturaEmisor', self::NS_SUM1,
            VerifactuHuellaComputer::formatInvoiceDate(\Carbon\Carbon::parse($invoice['date']))
        );

        $this->addText($dom, $baja, 'sum1:NombreRazonEmisorFactura', self::NS_SUM1, $company['name']);

        // SistemaInformatico
        $si = $dom->createElementNS(self::NS_SUM1, 'sum1:SistemaInformatico');
        $baja->appendChild($si);
        $this->addText($dom, $si, 'sum1:NombreRazon',              self::NS_SUM1, $software['vendor_name'] ?? config('verifactu.software.vendor_name'));
        $this->addText($dom, $si, 'sum1:NIF',                      self::NS_SUM1, $software['vendor_tax_id'] ?? config('verifactu.software.vendor_tax_id'));
        $this->addText($dom, $si, 'sum1:NombreSistemaInformatico', self::NS_SUM1, $software['name']);
        $this->addText($dom, $si, 'sum1:IdSistemaInformatico',     self::NS_SUM1, $software['software_id'] ?? config('verifactu.software.id', 'CRATER-VF-01'));
        $this->addText($dom, $si, 'sum1:Version',                  self::NS_SUM1, $software['version']);
        $this->addText($dom, $si, 'sum1:NumeroInstalacion',        self::NS_SUM1, $software['installation_number'] ?? config('verifactu.software.installation_number', '1'));
        $mode   = optional($installation)->mode ?: config('verifactu.mode', 'stub');
        $usoCod = ($mode === 'aeat_production') ? '01' : '02';
        $this->addText($dom, $si, 'sum1:TipoUsoCodSistemaInformatico', self::NS_SUM1, $usoCod);

        // Timestamp + Huella
        $this->addText($dom, $baja, 'sum1:FechaHoraHusoGenRegistro', self::NS_SUM1,
            VerifactuHuellaComputer::formatTimestamp($issuedAt)
        );
        $this->addText($dom, $baja, 'sum1:TipoHuella', self::NS_SUM1, '01');
        $this->addText($dom, $baja, 'sum1:Huella',     self::NS_SUM1, $record->hash);

        // Encadenamiento
        $encadenamiento = $dom->createElementNS(self::NS_SUM1, 'sum1:Encadenamiento');
        $baja->appendChild($encadenamiento);

        if (! $record->previous_hash) {
            $this->addText($dom, $encadenamiento, 'sum1:PrimerRegistro', self::NS_SUM1, 'S');
        } else {
            $prev = \Crater\Models\VerifactuRecord::where('hash', $record->previous_hash)->first();
            $regAnterior = $dom->createElementNS(self::NS_SUM1, 'sum1:RegistroAnterior');
            $encadenamiento->appendChild($regAnterior);
            $this->addText($dom, $regAnterior, 'sum1:IDEmisorFactura',        self::NS_SUM1, $company['tax_number'] ?? '');
            $this->addText($dom, $regAnterior, 'sum1:NumSerieFactura',        self::NS_SUM1, optional($prev)->invoice_number ?? '');
            $this->addText($dom, $regAnterior, 'sum1:FechaExpedicionFactura', self::NS_SUM1,
                $prev && $prev->invoice_date
                    ? VerifactuHuellaComputer::formatInvoiceDate(\Carbon\Carbon::parse($prev->invoice_date))
                    : ''
            );
            $this->addText($dom, $regAnterior, 'sum1:Huella', self::NS_SUM1, $record->previous_hash);
        }

        return $dom->saveXML();
    }

    /**
     * Heuristic to decide whether a tax number is a Spanish NIF/CIF/NIE.
     * Spanish fiscal IDs are exactly 9 alphanumeric characters.
     */
    private function isSpanishNif(?string $nif): bool
    {
        if (! $nif) {
            return false;
        }
        return (bool) preg_match('/^[A-Z0-9]{9}$/i', trim($nif));
    }
}
