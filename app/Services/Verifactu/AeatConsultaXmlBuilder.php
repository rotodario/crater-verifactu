<?php

namespace Crater\Services\Verifactu;

use Carbon\Carbon;
use Crater\Models\VerifactuRecord;
use DOMDocument;
use DOMElement;

/**
 * Builds the SOAP/XML payload for a VERI*FACTU ConsultaFactuSistemaFacturacion
 * request following the AEAT ConsultaLR.xsd / SuministroInformacion.xsd v1.0.
 *
 * Namespaces:
 *   cons (NS_CONS) → ConsultaLR          — request envelope elements
 *   sum  (NS_SUM)  → SuministroInformacion — Cabecera children, PeriodoImputacion children
 */
class AeatConsultaXmlBuilder
{
    const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    const NS_CONS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/ConsultaLR.xsd';
    const NS_SUM  = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    /**
     * Build a Consulta request scoped to a specific invoice (for single-record verify).
     */
    public function build(VerifactuRecord $record): string
    {
        $snap    = $record->snapshot;
        $company = $snap['company'];
        $invoice = $snap['invoice'];

        $invoiceDate = Carbon::parse($invoice['date']);

        return $this->buildHistorial(
            nif:       $company['tax_number'] ?? '',
            name:      $company['name'] ?? '',
            ejercicio: $invoiceDate->format('Y'),
            periodo:   $invoiceDate->format('m'),
            numSerie:  $invoice['number'],
        );
    }

    /**
     * Build a Consulta request for the historial view.
     * When $numSerie is null the query returns all records for the period.
     * Pass $clavePaginacion from a previous response to fetch the next page.
     */
    public function buildHistorial(
        string  $nif,
        string  $name,
        string  $ejercicio,
        string  $periodo,
        ?string $numSerie        = null,
        ?string $clavePaginacion = null
    ): string {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $envelope = $dom->createElementNS(self::NS_SOAP, 'soapenv:Envelope');
        $envelope->setAttribute('xmlns:cons', self::NS_CONS);
        $envelope->setAttribute('xmlns:sum',  self::NS_SUM);
        $dom->appendChild($envelope);
        $envelope->appendChild($dom->createElementNS(self::NS_SOAP, 'soapenv:Header'));

        $body = $dom->createElementNS(self::NS_SOAP, 'soapenv:Body');
        $envelope->appendChild($body);

        $root = $dom->createElementNS(self::NS_CONS, 'cons:ConsultaFactuSistemaFacturacion');
        $body->appendChild($root);

        $cabecera = $dom->createElementNS(self::NS_CONS, 'cons:Cabecera');
        $root->appendChild($cabecera);
        $this->text($dom, $cabecera, 'sum:IDVersion', self::NS_SUM, '1.0');
        $obligado = $dom->createElementNS(self::NS_SUM, 'sum:ObligadoEmision');
        $cabecera->appendChild($obligado);
        $this->text($dom, $obligado, 'sum:NombreRazon', self::NS_SUM, $name);
        $this->text($dom, $obligado, 'sum:NIF',         self::NS_SUM, $nif);

        $filtro = $dom->createElementNS(self::NS_CONS, 'cons:FiltroConsulta');
        $root->appendChild($filtro);

        $periodoEl = $dom->createElementNS(self::NS_CONS, 'cons:PeriodoImputacion');
        $filtro->appendChild($periodoEl);
        $this->text($dom, $periodoEl, 'sum:Ejercicio', self::NS_SUM, $ejercicio);
        $this->text($dom, $periodoEl, 'sum:Periodo',   self::NS_SUM, $periodo);

        if ($numSerie !== null && $numSerie !== '') {
            $this->text($dom, $filtro, 'cons:NumSerieFactura', self::NS_CONS, $numSerie);
        }

        if ($clavePaginacion !== null && $clavePaginacion !== '') {
            $this->text($dom, $filtro, 'cons:ClavePaginacion', self::NS_CONS, $clavePaginacion);
        }

        return $dom->saveXML();
    }

    private function text(DOMDocument $dom, DOMElement $parent, string $tag, string $ns, string $value): void
    {
        $el = $dom->createElementNS($ns, $tag);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }
}
