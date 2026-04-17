<?php

namespace Crater\Services\Verifactu;

use RuntimeException;
use SimpleXMLElement;

/**
 * Parses the SOAP response from the AEAT VERI*FACTU web service.
 *
 * Expected response root: RespuestaRegFactuSistemaFacturacion
 * Namespace: https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd
 */
class AeatResponseParser
{
    const NS_RESP = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd';

    /**
     * Parse raw SOAP XML and return a structured result array:
     *
     * [
     *   'accepted'      => bool,
     *   'csv'           => string|null,   // Código Seguro de Verificación
     *   'estado_envio'  => string,        // "Correcto" | "IncorrectoError" | "ParcialmenteCorrecto"
     *   'lines'         => [
     *     [
     *       'invoice_number' => string,
     *       'estado'         => string,   // "Correcto" | "Incorrecto"
     *       'error_code'     => string|null,
     *       'error_desc'     => string|null,
     *     ],
     *     ...
     *   ],
     *   'raw_error'     => string|null,   // set when XML itself is a SOAP Fault
     * ]
     *
     * @throws RuntimeException if the XML cannot be parsed at all
     */
    public function parse(string $responseXml): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($responseXml);

        if ($xml === false) {
            $errors = implode('; ', array_map(fn($e) => trim($e->message), libxml_get_errors()));
            libxml_clear_errors();
            throw new RuntimeException("Cannot parse AEAT response XML: {$errors}");
        }

        // Check for SOAP Fault
        $fault = $xml->xpath('//faultstring');
        if (! empty($fault)) {
            return [
                'accepted'     => false,
                'csv'          => null,
                'estado_envio' => 'IncorrectoError',
                'lines'        => [],
                'raw_error'    => (string) $fault[0],
            ];
        }

        // Navigate to RespuestaSuministroLR
        $nsMap = ['r' => self::NS_RESP];
        $xml->registerXPathNamespace('r', self::NS_RESP);

        $respNodes = $xml->xpath('//r:RespuestaRegFactuSistemaFacturacion');
        if (empty($respNodes)) {
            $respNodes = $xml->xpath('//*[local-name()="RespuestaRegFactuSistemaFacturacion"]');
        }

        if (empty($respNodes)) {
            throw new RuntimeException('RespuestaRegFactuSistemaFacturacion element not found in AEAT response.');
        }

        $resp  = $respNodes[0];
        $resp->registerXPathNamespace('r', self::NS_RESP);

        $csv         = $this->nodeText($resp, 'CSV');
        $estadoEnvio = $this->nodeText($resp, 'EstadoEnvio') ?: 'IncorrectoError';
        // Correcto            → all records accepted, no errors
        // ParcialmenteCorrecto→ at least one record accepted (may have warnings)
        // Incorrecto          → all records rejected
        $accepted    = in_array($estadoEnvio, ['Correcto', 'ParcialmenteCorrecto'], true);

        // RespuestaLinea entries
        $lines = [];
        $lineNodes = $resp->xpath('r:RespuestaLinea') ?: $resp->xpath('*[local-name()="RespuestaLinea"]') ?: [];

        foreach ($lineNodes as $line) {
            $idFactura   = $line->xpath('*[local-name()="IDFactura"]')[0] ?? null;
            $numSerie    = $idFactura ? (string) ($idFactura->xpath('*[local-name()="NumSerieFactura"]')[0] ?? '') : '';
            $estadoReg   = (string) ($line->xpath('*[local-name()="EstadoRegistro"]')[0] ?? 'Incorrecto');
            $errorCode   = (string) ($line->xpath('*[local-name()="CodigoErrorRegistro"]')[0] ?? '');
            $errorDesc   = (string) ($line->xpath('*[local-name()="DescripcionErrorRegistro"]')[0] ?? '');

            // Error 3000 = duplicate: the record was already registered by a previous
            // submission. Check the nested RegistroDuplicado for the original status.
            // Treat as accepted if the duplicate was AceptadaConErrores or Correcto.
            if ($errorCode === '3000') {
                $dupNode   = $line->xpath('*[local-name()="RegistroDuplicado"]')[0] ?? null;
                $dupEstado = $dupNode
                    ? (string) ($dupNode->xpath('*[local-name()="EstadoRegistroDuplicado"]')[0] ?? '')
                    : '';
                if (in_array($dupEstado, ['Correcto', 'AceptadaConErrores', 'AceptadoConErrores'], true)) {
                    $estadoReg = 'AceptadoConErrores'; // normalise so driver marks as accepted
                    $errorDesc = 'Duplicado (ya registrado con estado: ' . $dupEstado . ')';
                }
            }

            $lines[] = [
                'invoice_number' => $numSerie,
                'estado'         => $estadoReg,
                'error_code'     => $errorCode ?: null,
                'error_desc'     => $errorDesc ?: null,
            ];
        }

        // Re-evaluate accepted: if ALL lines are Correcto or AceptadoConErrores, consider accepted
        if (! $accepted && ! empty($lines)) {
            $allAccepted = collect($lines)->every(
                fn($l) => in_array($l['estado'], ['Correcto', 'AceptadoConErrores'], true)
            );
            if ($allAccepted) {
                $accepted = true;
            }
        }

        return [
            'accepted'     => $accepted,
            'csv'          => $csv ?: null,
            'estado_envio' => $estadoEnvio,
            'lines'        => $lines,
            'raw_error'    => null,
        ];
    }

    /**
     * Summarise errors from a parsed response into a single readable string.
     */
    public function summariseErrors(array $parsed): string
    {
        if ($parsed['raw_error']) {
            return 'SOAP Fault: ' . $parsed['raw_error'];
        }

        $parts = ["EstadoEnvio={$parsed['estado_envio']}"];
        foreach ($parsed['lines'] as $line) {
            if ($line['estado'] !== 'Correcto') {
                $parts[] = "Factura {$line['invoice_number']}: [{$line['error_code']}] {$line['error_desc']}";
            }
        }

        return implode(' | ', $parts);
    }

    private function nodeText(SimpleXMLElement $el, string $localName): string
    {
        $nodes = $el->xpath("r:{$localName}") ?: $el->xpath("*[local-name()='{$localName}']") ?: [];
        return $nodes ? (string) $nodes[0] : '';
    }
}
