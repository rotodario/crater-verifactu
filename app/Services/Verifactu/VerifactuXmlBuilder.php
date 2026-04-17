<?php

namespace Crater\Services\Verifactu;

use Crater\Models\VerifactuRecord;
use DOMDocument;
use DOMElement;

/**
 * Builds the SOAP/XML payload for a VERI*FACTU RegFactuSistemaFacturacion
 * request following the AEAT schema v1.0.
 *
 * Namespaces (from the official WSDL / XSD):
 *   sum  (NS_SUM)  → SuministroInformacion — contains RegistroAlta, RegistroAnulacion and all their children
 *   sum1 (NS_SUM1) → SuministroLR         — contains RegFactuSistemaFacturacion, Cabecera wrapper, RegistroFactura
 */
class VerifactuXmlBuilder
{
    const NS_SOAP  = 'http://schemas.xmlsoap.org/soap/envelope/';
    const NS_SUM   = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';
    const NS_SUM1  = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';

    public function build(VerifactuRecord $record): string
    {
        if ($record->record_type === 'invoice_cancellation') {
            return $this->buildAnulacion($record);
        }

        return $this->buildAlta($record);
    }

    public function buildAlta(VerifactuRecord $record): string
    {
        $record->loadMissing(['installation']);

        $snap         = $record->snapshot;
        $invoice      = $snap['invoice'];
        $company      = $snap['company'];
        $customer     = $snap['customer'];
        $taxes        = $snap['taxes'] ?? [];
        $software     = $snap['software'];
        $installation = $record->installation;

        $issuedAt    = $record->issued_at;
        $tipoFactura = $record->tipo_factura ?: 'F1';

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

        // RegFactuSistemaFacturacion — root element, defined in SuministroLR.xsd
        $root = $dom->createElementNS(self::NS_SUM1, 'sum1:RegFactuSistemaFacturacion');
        $body->appendChild($root);

        // Cabecera — locally defined in SuministroLR.xsd (sum1 namespace)
        // Its children come from CabeceraType in SuministroInformacion.xsd (sum namespace)
        $cabecera = $dom->createElementNS(self::NS_SUM1, 'sum1:Cabecera');
        $root->appendChild($cabecera);
        $obligado = $dom->createElementNS(self::NS_SUM, 'sum:ObligadoEmision');
        $cabecera->appendChild($obligado);
        $this->addText($dom, $obligado, 'sum:NombreRazon', self::NS_SUM, $company['name']);
        $this->addText($dom, $obligado, 'sum:NIF',         self::NS_SUM, $company['tax_number'] ?? '');

        // RegistroFactura — locally defined in SuministroLR.xsd (sum1 namespace)
        $registroFactura = $dom->createElementNS(self::NS_SUM1, 'sum1:RegistroFactura');
        $root->appendChild($registroFactura);

        // RegistroAlta — top-level element in SuministroInformacion.xsd (sum namespace)
        $alta = $dom->createElementNS(self::NS_SUM, 'sum:RegistroAlta');
        $registroFactura->appendChild($alta);

        // All children of RegistroAlta are defined in SuministroInformacion.xsd → sum namespace
        // Order must follow RegistroFacturacionAltaType sequence exactly.

        $this->addText($dom, $alta, 'sum:IDVersion', self::NS_SUM, '1.0');

        // IDFactura
        $idFactura = $dom->createElementNS(self::NS_SUM, 'sum:IDFactura');
        $alta->appendChild($idFactura);
        $this->addText($dom, $idFactura, 'sum:IDEmisorFactura',          self::NS_SUM, $company['tax_number'] ?? '');
        $this->addText($dom, $idFactura, 'sum:NumSerieFactura',          self::NS_SUM, $invoice['number']);
        $this->addText($dom, $idFactura, 'sum:FechaExpedicionFactura',   self::NS_SUM,
            VerifactuHuellaComputer::formatInvoiceDate(\Carbon\Carbon::parse($invoice['date']))
        );

        // NombreRazonEmisor (NOT NombreRazonEmisorFactura)
        $this->addText($dom, $alta, 'sum:NombreRazonEmisor', self::NS_SUM, $company['name']);
        $this->addText($dom, $alta, 'sum:TipoFactura',       self::NS_SUM, $tipoFactura);

        // DescripcionOperacion — required, max 500 chars
        $description = ! empty($invoice['notes'])
            ? mb_substr(strip_tags($invoice['notes']), 0, 500)
            : 'Servicios/Bienes según factura ' . $invoice['number'];
        $this->addText($dom, $alta, 'sum:DescripcionOperacion', self::NS_SUM, $description);

        // Destinatarios (optional, skip for F2 simplified)
        if ($tipoFactura !== 'F2' && ! empty($customer['name'])) {
            $destinatarios = $dom->createElementNS(self::NS_SUM, 'sum:Destinatarios');
            $alta->appendChild($destinatarios);

            $idDestinatario = $dom->createElementNS(self::NS_SUM, 'sum:IDDestinatario');
            $destinatarios->appendChild($idDestinatario);
            $this->addText($dom, $idDestinatario, 'sum:NombreRazon', self::NS_SUM, $customer['name']);

            $taxNumber = $customer['tax_number'] ?? '';
            if ($this->isSpanishNif($taxNumber)) {
                $this->addText($dom, $idDestinatario, 'sum:NIF', self::NS_SUM, $taxNumber);
            } else {
                $idOtro = $dom->createElementNS(self::NS_SUM, 'sum:IDOtro');
                $idDestinatario->appendChild($idOtro);
                $this->addText($dom, $idOtro, 'sum:CodigoPais', self::NS_SUM, 'ES');
                $this->addText($dom, $idOtro, 'sum:IDType',     self::NS_SUM, '07');
                $this->addText($dom, $idOtro, 'sum:ID',         self::NS_SUM, $taxNumber ?: 'NO_IDENTIFICADO');
            }
        }

        // Desglose
        $desglose = $dom->createElementNS(self::NS_SUM, 'sum:Desglose');
        $alta->appendChild($desglose);

        foreach ($this->buildDesgloseLines($invoice, $taxes) as $line) {
            $detalle = $dom->createElementNS(self::NS_SUM, 'sum:DetalleDesglose');
            $desglose->appendChild($detalle);
            $this->addText($dom, $detalle, 'sum:Impuesto',       self::NS_SUM, $line['impuesto']);
            $this->addText($dom, $detalle, 'sum:ClaveRegimen',   self::NS_SUM, $line['clave_regimen']);
            $this->addText($dom, $detalle, 'sum:CalificacionOperacion', self::NS_SUM, $line['calificacion']);
            if ($line['tipo_impositivo'] !== null) {
                $this->addText($dom, $detalle, 'sum:TipoImpositivo', self::NS_SUM, $line['tipo_impositivo']);
            }
            $this->addText($dom, $detalle, 'sum:BaseImponibleOImporteNoSujeto', self::NS_SUM, $line['base']);
            if ($line['cuota'] !== null) {
                $this->addText($dom, $detalle, 'sum:CuotaRepercutida', self::NS_SUM, $line['cuota']);
            }
        }

        // CuotaTotal / ImporteTotal
        $this->addText($dom, $alta, 'sum:CuotaTotal',   self::NS_SUM,
            VerifactuHuellaComputer::formatAmount((int) $invoice['tax_total'])
        );
        $this->addText($dom, $alta, 'sum:ImporteTotal', self::NS_SUM,
            VerifactuHuellaComputer::formatAmount((int) $invoice['total'])
        );

        // Encadenamiento — MUST come before SistemaInformatico
        $encadenamiento = $dom->createElementNS(self::NS_SUM, 'sum:Encadenamiento');
        $alta->appendChild($encadenamiento);
        $this->buildEncadenamiento($dom, $encadenamiento, $record, $company);

        // SistemaInformatico
        $this->buildSistemaInformatico($dom, $alta, $software, $installation);

        // FechaHoraHusoGenRegistro / TipoHuella / Huella — after SistemaInformatico
        $this->addText($dom, $alta, 'sum:FechaHoraHusoGenRegistro', self::NS_SUM,
            VerifactuHuellaComputer::formatTimestamp($issuedAt)
        );
        $this->addText($dom, $alta, 'sum:TipoHuella', self::NS_SUM, '01');
        $this->addText($dom, $alta, 'sum:Huella',     self::NS_SUM, $record->hash);

        return $dom->saveXML();
    }

    // -------------------------------------------------------------------------

    /**
     * Build a RegistroAnulacion (cancellation) SOAP envelope.
     */
    public function buildAnulacion(VerifactuRecord $record): string
    {
        $record->loadMissing(['installation']);

        $snap         = $record->snapshot;
        $invoice      = $snap['invoice'];
        $company      = $snap['company'];
        $software     = $snap['software'];
        $installation = $record->installation;
        $issuedAt     = $record->issued_at;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $envelope = $dom->createElementNS(self::NS_SOAP, 'soapenv:Envelope');
        $envelope->setAttribute('xmlns:sum',  self::NS_SUM);
        $envelope->setAttribute('xmlns:sum1', self::NS_SUM1);
        $dom->appendChild($envelope);
        $envelope->appendChild($dom->createElementNS(self::NS_SOAP, 'soapenv:Header'));

        $body = $dom->createElementNS(self::NS_SOAP, 'soapenv:Body');
        $envelope->appendChild($body);

        $root = $dom->createElementNS(self::NS_SUM1, 'sum1:RegFactuSistemaFacturacion');
        $body->appendChild($root);

        $cabecera = $dom->createElementNS(self::NS_SUM1, 'sum1:Cabecera');
        $root->appendChild($cabecera);
        $obligado = $dom->createElementNS(self::NS_SUM, 'sum:ObligadoEmision');
        $cabecera->appendChild($obligado);
        $this->addText($dom, $obligado, 'sum:NombreRazon', self::NS_SUM, $company['name']);
        $this->addText($dom, $obligado, 'sum:NIF',         self::NS_SUM, $company['tax_number'] ?? '');

        $registroFactura = $dom->createElementNS(self::NS_SUM1, 'sum1:RegistroFactura');
        $root->appendChild($registroFactura);

        // RegistroAnulacion — top-level element in SuministroInformacion.xsd
        $anulacion = $dom->createElementNS(self::NS_SUM, 'sum:RegistroAnulacion');
        $registroFactura->appendChild($anulacion);

        $this->addText($dom, $anulacion, 'sum:IDVersion', self::NS_SUM, '1.0');

        $idFactura = $dom->createElementNS(self::NS_SUM, 'sum:IDFactura');
        $anulacion->appendChild($idFactura);
        $this->addText($dom, $idFactura, 'sum:IDEmisorFactura',        self::NS_SUM, $company['tax_number'] ?? '');
        $this->addText($dom, $idFactura, 'sum:NumSerieFactura',        self::NS_SUM, $invoice['number']);
        $this->addText($dom, $idFactura, 'sum:FechaExpedicionFactura', self::NS_SUM,
            VerifactuHuellaComputer::formatInvoiceDate(\Carbon\Carbon::parse($invoice['date']))
        );

        // Encadenamiento — before SistemaInformatico
        $encadenamiento = $dom->createElementNS(self::NS_SUM, 'sum:Encadenamiento');
        $anulacion->appendChild($encadenamiento);
        $this->buildEncadenamiento($dom, $encadenamiento, $record, $company);

        // SistemaInformatico
        $this->buildSistemaInformatico($dom, $anulacion, $software, $installation);

        $this->addText($dom, $anulacion, 'sum:FechaHoraHusoGenRegistro', self::NS_SUM,
            VerifactuHuellaComputer::formatTimestamp($issuedAt)
        );
        $this->addText($dom, $anulacion, 'sum:TipoHuella', self::NS_SUM, '01');
        $this->addText($dom, $anulacion, 'sum:Huella',     self::NS_SUM, $record->hash);

        return $dom->saveXML();
    }

    // -------------------------------------------------------------------------

    private function buildEncadenamiento(DOMDocument $dom, DOMElement $parent, VerifactuRecord $record, array $company): void
    {
        if (! $record->previous_hash) {
            $this->addText($dom, $parent, 'sum:PrimerRegistro', self::NS_SUM, 'S');
        } else {
            $prev        = \Crater\Models\VerifactuRecord::where('hash', $record->previous_hash)->first();
            $regAnterior = $dom->createElementNS(self::NS_SUM, 'sum:RegistroAnterior');
            $parent->appendChild($regAnterior);
            $this->addText($dom, $regAnterior, 'sum:IDEmisorFactura',        self::NS_SUM, $company['tax_number'] ?? '');
            $this->addText($dom, $regAnterior, 'sum:NumSerieFactura',        self::NS_SUM, optional($prev)->invoice_number ?? '');
            $this->addText($dom, $regAnterior, 'sum:FechaExpedicionFactura', self::NS_SUM,
                $prev && $prev->invoice_date
                    ? VerifactuHuellaComputer::formatInvoiceDate(\Carbon\Carbon::parse($prev->invoice_date))
                    : ''
            );
            $this->addText($dom, $regAnterior, 'sum:Huella', self::NS_SUM, $record->previous_hash);
        }
    }

    private function buildSistemaInformatico(DOMDocument $dom, DOMElement $parent, array $software, $installation): void
    {
        $si = $dom->createElementNS(self::NS_SUM, 'sum:SistemaInformatico');
        $parent->appendChild($si);

        $this->addText($dom, $si, 'sum:NombreRazon', self::NS_SUM,
            $software['vendor_name'] ?? config('verifactu.software.vendor_name'));

        $vendorNif = $software['vendor_tax_id'] ?? config('verifactu.software.vendor_tax_id');
        if ($this->isSpanishNif($vendorNif)) {
            $this->addText($dom, $si, 'sum:NIF', self::NS_SUM, $vendorNif);
        } else {
            $idOtro = $dom->createElementNS(self::NS_SUM, 'sum:IDOtro');
            $si->appendChild($idOtro);
            $this->addText($dom, $idOtro, 'sum:CodigoPais', self::NS_SUM, 'ES');
            $this->addText($dom, $idOtro, 'sum:IDType',     self::NS_SUM, '07');
            $this->addText($dom, $idOtro, 'sum:ID',         self::NS_SUM, $vendorNif ?: 'NO_ID');
        }

        $this->addText($dom, $si, 'sum:NombreSistemaInformatico', self::NS_SUM, $software['name']);

        // IdSistemaInformatico: max 2 chars — AEAT-assigned code, use configured value truncated
        $softwareId = mb_substr($software['software_id'] ?? config('verifactu.software.id', '01'), 0, 2);
        $this->addText($dom, $si, 'sum:IdSistemaInformatico', self::NS_SUM, $softwareId);

        $this->addText($dom, $si, 'sum:Version',            self::NS_SUM, $software['version']);
        $this->addText($dom, $si, 'sum:NumeroInstalacion',  self::NS_SUM,
            $software['installation_number'] ?? config('verifactu.software.installation_number', '1'));

        // TipoUsoPosibleSoloVerifactu: S = only VERI*FACTU, N = also non-verifactu invoices
        $this->addText($dom, $si, 'sum:TipoUsoPosibleSoloVerifactu', self::NS_SUM, 'S');
        // TipoUsoPosibleMultiOT: S = can serve multiple taxpayers, N = single taxpayer
        $this->addText($dom, $si, 'sum:TipoUsoPosibleMultiOT',       self::NS_SUM, 'N');
        // IndicadorMultiplesOT: S = currently has multiple taxpayers active
        $this->addText($dom, $si, 'sum:IndicadorMultiplesOT',        self::NS_SUM, 'N');
    }

    // -------------------------------------------------------------------------

    private function buildDesgloseLines(array $invoice, array $taxes): array
    {
        $lines         = [];
        $subTotal      = (int) ($invoice['sub_total'] ?? 0);
        $allocatedBase = 0;

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
                $baseCents      = (int) round($taxAmountCents * 100 / $pct);
                $allocatedBase += $baseCents;
                $lines[] = [
                    'impuesto'        => '01',
                    'clave_regimen'   => '01',
                    'calificacion'    => 'S1',
                    'tipo_impositivo' => number_format($pct, 2, '.', ''),
                    'base'            => VerifactuHuellaComputer::formatAmount($baseCents),
                    'cuota'           => VerifactuHuellaComputer::formatAmount($taxAmountCents),
                ];
            }
        }

        $remainingBase = $subTotal - $allocatedBase;
        if ($remainingBase > 0 || empty($lines)) {
            $lines[] = [
                'impuesto'        => '01',
                'clave_regimen'   => '01',
                'calificacion'    => 'N2',
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

    private function isSpanishNif(?string $nif): bool
    {
        if (! $nif) {
            return false;
        }
        return (bool) preg_match('/^[A-Z0-9]{9}$/i', trim($nif));
    }
}
