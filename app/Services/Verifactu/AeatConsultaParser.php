<?php

namespace Crater\Services\Verifactu;

use RuntimeException;

/**
 * Parses the SOAP response from the AEAT ConsultaFactuSistemaFacturacion service.
 *
 * Response root: RespuestaConsultaFactuSistemaFacturacion
 * Namespace: RespuestaConsultaLR.xsd
 */
class AeatConsultaParser
{
    const NS_RESP_CONS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaConsultaLR.xsd';
    const NS_SUM       = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    /**
     * Returns:
     * [
     *   'found'            => bool,
     *   'resultado'        => 'ConDatos'|'SinDatos',
     *   'hay_mas_paginas'  => bool,
     *   'clave_paginacion' => string|null,
     *   'records'          => [
     *     [
     *       'invoice_number'         => string,
     *       'invoice_date'           => string,
     *       'estado_registro'        => string,   // Correcto | AceptadoConErrores | Anulado | Incorrecto
     *       'error_code'             => int|null,
     *       'error_desc'             => string|null,
     *       'huella'                 => string|null,
     *       'timestamp_modificacion' => string|null,
     *       'id_peticion'            => string|null,
     *       'timestamp_presentacion' => string|null,
     *       // Datos registro (when available)
     *       'tipo_factura'           => string|null,
     *       'importe_total'          => string|null,
     *       'cuota_total'            => string|null,
     *       'descripcion'            => string|null,
     *       'fecha_hora_huso'        => string|null,
     *       'destinatario_nombre'    => string|null,
     *       'destinatario_nif'       => string|null,
     *     ],
     *     ...
     *   ],
     *   'raw_error' => string|null,
     * ]
     *
     * @throws RuntimeException
     */
    public function parse(string $responseXml): array
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($responseXml);

        if ($xml === false) {
            $errors = implode('; ', array_map(fn($e) => trim($e->message), libxml_get_errors()));
            libxml_clear_errors();
            throw new RuntimeException("Cannot parse AEAT consulta response: {$errors}");
        }

        // SOAP Fault
        $fault = $xml->xpath('//faultstring');
        if (! empty($fault)) {
            return [
                'found'            => false,
                'resultado'        => 'SinDatos',
                'hay_mas_paginas'  => false,
                'clave_paginacion' => null,
                'records'          => [],
                'raw_error'        => (string) $fault[0],
            ];
        }

        // Navigate to root response element
        $xml->registerXPathNamespace('r', self::NS_RESP_CONS);
        $rootArr = $xml->xpath('//r:RespuestaConsultaFactuSistemaFacturacion');
        if (empty($rootArr)) {
            $rootArr = $xml->xpath('//*[local-name()="RespuestaConsultaFactuSistemaFacturacion"]');
        }
        if (empty($rootArr)) {
            throw new RuntimeException('RespuestaConsultaFactuSistemaFacturacion not found in AEAT response.');
        }

        $root = $rootArr[0];
        $root->registerXPathNamespace('r', self::NS_RESP_CONS);

        // ResultadoConsulta
        $resultadoArr = $root->xpath('r:ResultadoConsulta') ?: $root->xpath('*[local-name()="ResultadoConsulta"]') ?: [];
        $resultado    = $resultadoArr ? (string) $resultadoArr[0] : 'SinDatos';
        $found        = $resultado === 'ConDatos';

        // Pagination
        $indicadorArr    = $root->xpath('*[local-name()="IndicadorPaginacion"]') ?: [];
        $hayMas          = $indicadorArr && strtoupper((string) $indicadorArr[0]) === 'S';
        $claveArr        = $root->xpath('*[local-name()="ClavePaginacion"]') ?: [];
        $clavePaginacion = $claveArr ? (trim((string) $claveArr[0]) ?: null) : null;

        if (! $found) {
            return [
                'found'            => false,
                'resultado'        => $resultado,
                'hay_mas_paginas'  => false,
                'clave_paginacion' => null,
                'records'          => [],
                'raw_error'        => null,
            ];
        }

        // Parse each RegistroRespuestaConsultaFactuSistemaFacturacion
        $recordNodes = $root->xpath('r:RegistroRespuestaConsultaFactuSistemaFacturacion')
            ?: $root->xpath('*[local-name()="RegistroRespuestaConsultaFactuSistemaFacturacion"]')
            ?: [];

        $records = [];
        foreach ($recordNodes as $node) {
            // IDFactura
            $idFacturaArr  = $node->xpath('*[local-name()="IDFactura"]');
            $idFactura     = ($idFacturaArr !== false && !empty($idFacturaArr)) ? $idFacturaArr[0] : null;
            $numSerie      = ($idFactura !== null) ? (string)($idFactura->xpath('*[local-name()="NumSerieFactura"]')[0] ?? '') : '';
            $fechaExp      = ($idFactura !== null) ? (string)($idFactura->xpath('*[local-name()="FechaExpedicionFactura"]')[0] ?? '') : '';

            // Hash — AEAT uses HuellaRegistro directly under the record node
            // (not inside DatosRegistroFacturacion/Huella as originally assumed).
            // We try all known locations so the parser works regardless of schema version.
            $huella = null;
            foreach (['HuellaRegistro', 'Huella'] as $huellaTag) {
                $huellaArr = $node->xpath("*[local-name()=\"{$huellaTag}\"]");
                if ($huellaArr !== false && !empty($huellaArr)) {
                    $huella = trim((string) $huellaArr[0]) ?: null;
                    break;
                }
            }
            // Fallback: DatosRegistroFacturacion/Huella (original assumption)
            if ($huella === null) {
                $datosArr = $node->xpath('*[local-name()="DatosRegistroFacturacion"]');
                $datos    = ($datosArr !== false && !empty($datosArr)) ? $datosArr[0] : null;
                if ($datos !== null) {
                    $huellaArr = $datos->xpath('*[local-name()="Huella"]');
                    if ($huellaArr !== false && !empty($huellaArr)) {
                        $huella = trim((string) $huellaArr[0]) ?: null;
                    }
                }
            }

            // DatosPresentacion → IdPeticion, TimestampPresentacion
            $presArr       = $node->xpath('*[local-name()="DatosPresentacion"]');
            $pres          = ($presArr !== false && !empty($presArr)) ? $presArr[0] : null;
            $idPeticion    = ($pres !== null) ? (string)($pres->xpath('*[local-name()="IdPeticion"]')[0] ?? '') : '';
            $tsPresentacion= ($pres !== null) ? (string)($pres->xpath('*[local-name()="TimestampPresentacion"]')[0] ?? '') : '';

            // EstadoRegistro block
            $estadoBlockArr= $node->xpath('*[local-name()="EstadoRegistro"]');
            $estadoBlock   = ($estadoBlockArr !== false && !empty($estadoBlockArr)) ? $estadoBlockArr[0] : null;
            $estadoReg     = ($estadoBlock !== null) ? (string)($estadoBlock->xpath('*[local-name()="EstadoRegistro"]')[0] ?? 'Desconocido') : 'Desconocido';
            $errorCode     = ($estadoBlock !== null) ? (string)($estadoBlock->xpath('*[local-name()="CodigoErrorRegistro"]')[0] ?? '') : '';
            $errorDesc     = ($estadoBlock !== null) ? (string)($estadoBlock->xpath('*[local-name()="DescripcionErrorRegistro"]')[0] ?? '') : '';
            $tsMod         = ($estadoBlock !== null) ? (string)($estadoBlock->xpath('*[local-name()="TimestampUltimaModificacion"]')[0] ?? '') : '';

            // DatosRegistroFacturacion — extra fields for historial detail
            $datosArr = $node->xpath('*[local-name()="DatosRegistroFacturacion"]');
            $datos    = ($datosArr !== false && !empty($datosArr)) ? $datosArr[0] : null;
            $tipoFactura    = null;
            $importeTotal   = null;
            $cuotaTotal     = null;
            $descripcion    = null;
            $fechaHoraHuso  = null;
            $destNombre     = null;
            $destNif        = null;
            if ($datos !== null) {
                $tipoFactura   = (string)($datos->xpath('*[local-name()="TipoFactura"]')[0]   ?? '') ?: null;
                $importeTotal  = (string)($datos->xpath('*[local-name()="ImporteTotal"]')[0]  ?? '') ?: null;
                $cuotaTotal    = (string)($datos->xpath('*[local-name()="CuotaTotal"]')[0]    ?? '') ?: null;
                $descripcion   = (string)($datos->xpath('*[local-name()="DescripcionOperacion"]')[0] ?? '') ?: null;
                $fechaHoraHuso = (string)($datos->xpath('*[local-name()="FechaHoraHusoGenRegistro"]')[0] ?? '') ?: null;
                $destArr       = $datos->xpath('*[local-name()="Destinatarios"]/*[local-name()="IDDestinatario"]');
                if ($destArr !== false && !empty($destArr)) {
                    $destNombre = (string)($destArr[0]->xpath('*[local-name()="NombreRazon"]')[0] ?? '') ?: null;
                    $destNif    = (string)($destArr[0]->xpath('*[local-name()="NIF"]')[0]         ?? '') ?: null;
                }
            }

            $records[] = [
                'invoice_number'         => $numSerie,
                'invoice_date'           => $fechaExp,
                'estado_registro'        => $estadoReg,
                'error_code'             => $errorCode ? (int) $errorCode : null,
                'error_desc'             => $errorDesc ?: null,
                'huella'                 => $huella,
                'timestamp_modificacion' => $tsMod ?: null,
                'id_peticion'            => $idPeticion ?: null,
                'timestamp_presentacion' => $tsPresentacion ?: null,
                'tipo_factura'           => $tipoFactura,
                'importe_total'          => $importeTotal,
                'cuota_total'            => $cuotaTotal,
                'descripcion'            => $descripcion,
                'fecha_hora_huso'        => $fechaHoraHuso,
                'destinatario_nombre'    => $destNombre,
                'destinatario_nif'       => $destNif,
            ];
        }

        return [
            'found'            => true,
            'resultado'        => $resultado,
            'hay_mas_paginas'  => $hayMas,
            'clave_paginacion' => $clavePaginacion,
            'records'          => $records,
            'raw_error'        => null,
        ];
    }
}
