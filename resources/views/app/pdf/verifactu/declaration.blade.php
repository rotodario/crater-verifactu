<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Declaración Responsable del SIF — v{{ $declaration->software_version }}</title>
  <style type="text/css">
    /* DomPDF 1.x only applies @page margins when count($page_styles) > 1.
       Explicit :first/:left/:right rules ensure all pages receive margins. */
    @page {
      margin-top: 28px;
      margin-bottom: 48px;
      margin-left: 36px;
      margin-right: 36px;
    }
    @page :first {
      margin-top: 28px;
      margin-bottom: 48px;
      margin-left: 36px;
      margin-right: 36px;
    }
    @page :left {
      margin-top: 28px;
      margin-bottom: 48px;
      margin-left: 36px;
      margin-right: 36px;
    }
    @page :right {
      margin-top: 28px;
      margin-bottom: 48px;
      margin-left: 36px;
      margin-right: 36px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: "DejaVu Sans", sans-serif;
      font-size: 10px;
      color: #1a1a2e;
      background: #fff;
    }

    /* @page handles all margins — no extra padding needed on .page */
    .page { padding: 0; }

    /* ── Header ── */
    .doc-header {
      border-bottom: 3px solid #1E3A5F;
      padding-bottom: 10px;
      margin-bottom: 18px;
    }
    .doc-title {
      font-size: 14px;
      font-weight: bold;
      color: #1E3A5F;
      letter-spacing: 0.2px;
      text-transform: uppercase;
    }
    .doc-subtitle {
      font-size: 8.5px;
      color: #5a6a7e;
      margin-top: 3px;
    }
    .doc-meta {
      text-align: right;
      font-size: 9px;
      color: #5a6a7e;
    }
    .status-badge {
      display: inline-block;
      padding: 2px 7px;
      font-size: 7.5px;
      font-weight: bold;
      letter-spacing: 0.5px;
      text-transform: uppercase;
    }
    .status-ACTIVE    { background: #d1fae5; color: #065f46; }
    .status-REVIEWED  { background: #dbeafe; color: #1e40af; }
    .status-GENERATED { background: #fef3c7; color: #92400e; }
    .status-ARCHIVED  { background: #f3f4f6; color: #6b7280; }

    /* ── Section ── */
    .section { margin-bottom: 14px; }
    .section-title {
      font-size: 8.5px;
      font-weight: bold;
      color: #1E3A5F;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      border-bottom: 1px solid #d1d9e6;
      padding-bottom: 3px;
      margin-bottom: 9px;
    }

    /* ── Data table ── */
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table td { padding: 3px 4px; vertical-align: top; }
    .data-label { width: 32%; font-size: 8.5px; color: #5a6a7e; font-weight: bold; }
    .data-value { font-size: 9.5px; color: #1a1a2e; }
    .data-mono  { font-family: "Courier New", monospace; font-size: 9px; color: #1a1a2e; }
    .pending    { font-style: italic; color: #b45309; font-size: 9px; }

    /* ── Text blocks ── */
    .text-block { font-size: 9.5px; line-height: 1.65; color: #2d3748; text-align: justify; }
    .text-block p { margin-bottom: 6px; }
    .text-block p:last-child { margin-bottom: 0; }

    .text-list { font-size: 9.5px; line-height: 1.65; color: #2d3748; margin: 0; padding-left: 0; }
    .text-list li { margin-bottom: 4px; list-style: none; padding-left: 14px; text-indent: -14px; }

    /* ── Declaration box ── */
    .declaration-box {
      border: 1px solid #c3cfe2;
      border-left: 4px solid #1E3A5F;
      background: #f8fafc;
      padding: 10px 14px;
    }

    /* ── Technical block ── */
    .tech-block {
      border: 1px solid #e2e8f0;
      background: #f8fafc;
      padding: 8px 12px;
      margin-top: 6px;
    }
    .tech-label {
      font-size: 8.5px;
      font-weight: bold;
      color: #1E3A5F;
      margin-bottom: 3px;
    }
    .tech-value { font-size: 9px; color: #374151; line-height: 1.5; }

    /* ── Signature ── */
    .sig-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .sig-table td { padding: 3px 4px; vertical-align: top; }
    .sig-label { font-size: 8.5px; color: #5a6a7e; font-weight: bold; width: 34%; }
    .sig-value { font-size: 9.5px; color: #1a1a2e; }
    .sig-line  { border-bottom: 1px solid #9ca3af; height: 26px; width: 75%; margin-top: 18px; }

    /* ── Pending warning ── */
    .warning-banner {
      background: #fffbeb;
      border: 1px solid #f59e0b;
      padding: 5px 10px;
      font-size: 8.5px;
      color: #92400e;
      margin-bottom: 12px;
    }

    /* ── Footer ── */
    .doc-footer {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      border-top: 1px solid #d1d9e6;
      padding: 4px 0;
      font-size: 7.5px;
      color: #9ca3af;
      background: #fff;
    }
    .fl { float: left; }
    .fr { float: right; }
    .fc { clear: both; }
  </style>
</head>
<body>

@php
  $p = $payload;

  // Anonymous function — avoids "cannot redeclare" in environments with OPcache
  $pval = function($v, $mono = false) {
    if ($v === null || $v === '') {
      return '<span class="pending">[Pendiente de completar]</span>';
    }
    $cls = $mono ? 'data-mono' : 'data-value';
    return '<span class="' . $cls . '">' . e($v) . '</span>';
  };

  $hasPending = empty($p['vendor_address']) || empty($p['subscription_place']);

  // Fecha de suscripción: cuando se generó el snapshot (GENERATED)
  $dateSigned = $declaration->generated_at
      ? $declaration->generated_at->format('d \d\e F \d\e Y')
      : '[Pendiente]';

  // Datos clave para interpolar en textos legales
  $softwareName    = $p['software_name']  ?? $declaration->software_name  ?? '[sistema]';
  $softwareVersion = $p['software_version'] ?? $declaration->software_version ?? '[versión]';
  $softwareId      = $p['software_id']    ?? '[IdSistemaInformatico]';
  $vendorName      = $p['vendor_name']    ?? '[productor]';
@endphp

<div class="doc-footer">
  <span class="fl">
    Documento generado por el SIF &mdash;
    Declaración Responsable v{{ $declaration->software_version ?? '—' }} &mdash;
    ID interno #{{ $declaration->id }}
  </span>
  <span class="fr">Generado el {{ now()->format('d/m/Y H:i') }}</span>
  <div class="fc"></div>
</div>

<div class="page">

  {{-- CABECERA --}}
  <div class="doc-header">
    <table style="width:100%; border-collapse:collapse;">
      <tr>
        <td style="vertical-align:bottom; padding:0;">
          <div class="doc-title">Declaración Responsable del Sistema Informático de Facturación</div>
          <div class="doc-subtitle">
            Conforme al Real Decreto 1007/2023, de 5 de diciembre &mdash; Reglamento VERI*FACTU
          </div>
        </td>
        <td style="vertical-align:top; text-align:right; padding:0; width:140px;">
          <div class="doc-meta">
            <div>Ref. interna: <strong>#{{ $declaration->id }}</strong></div>
            <div style="margin-top:4px;">
              <span class="status-badge status-{{ $declaration->status }}">{{ $declaration->status }}</span>
            </div>
            @if($declaration->generated_at)
              <div style="margin-top:4px;">{{ $declaration->generated_at->format('d/m/Y') }}</div>
            @endif
          </div>
        </td>
      </tr>
    </table>
  </div>

  {{-- AVISO CAMPOS PENDIENTES --}}
  @if($hasPending)
  <div class="warning-banner">
    &#9888;&nbsp; Este documento contiene campos pendientes. Complete la Configuración de Plataforma SIF antes de publicar la versión definitiva.
  </div>
  @endif

  {{-- ─── SECCIÓN 1: IDENTIFICACIÓN ─── --}}
  <div class="section">
    <div class="section-title">1. Identificación del productor y del sistema informático</div>
    <table class="data-table">
      <tr>
        <td class="data-label">1.a)&nbsp; NIF del productor</td>
        <td>{!! $pval($p['vendor_tax_id'] ?? null, true) !!}</td>
        <td class="data-label">1.b)&nbsp; Nombre / Razón social</td>
        <td>{!! $pval($p['vendor_name'] ?? null) !!}</td>
      </tr>
      <tr>
        <td class="data-label" style="padding-top:5px;">1.c)&nbsp; Domicilio fiscal</td>
        <td colspan="3" style="padding-top:5px;">{!! $pval($p['vendor_address'] ?? null) !!}</td>
      </tr>
      <tr>
        <td class="data-label" style="padding-top:5px;">1.d)&nbsp; Nombre del sistema</td>
        <td style="padding-top:5px;">{!! $pval($p['software_name'] ?? null) !!}</td>
        <td class="data-label" style="padding-top:5px;">1.e)&nbsp; Versión</td>
        <td style="padding-top:5px;">{!! $pval($p['software_version'] ?? null, true) !!}</td>
      </tr>
      <tr>
        <td class="data-label" style="padding-top:5px;">1.f)&nbsp; IdSistemaInformatico</td>
        <td colspan="3" style="padding-top:5px;">{!! $pval($p['software_id'] ?? null, true) !!}</td>
      </tr>
      <tr>
        <td class="data-label" style="padding-top:5px;">1.h)&nbsp; Tipo de sistema</td>
        <td colspan="3" style="padding-top:5px;"><span class="data-value">Desarrollado por el propio productor &mdash; plataforma SaaS multi-empresa</span></td>
      </tr>
    </table>
  </div>

  {{-- ─── 1.g DESCRIPCIÓN Y FUNCIONALIDADES ─── --}}
  <div class="section">
    <div class="section-title">1.g) Descripción del sistema y funcionalidades</div>
    <div class="text-block">
      @if(! empty($p['vendor_description']))
        {!! nl2br(e($p['vendor_description'])) !!}
      @else
        <p>
          <strong>{{ $softwareName }}</strong> es un sistema informático de gestión y facturación electrónica
          conforme al Real Decreto 1007/2023 (VERI*FACTU), diseñado como plataforma SaaS multi-empresa.
          El sistema permite gestionar múltiples obligados tributarios de forma independiente, cada uno
          con su propio certificado digital, compartiendo un único <em>IdSistemaInformatico</em> de plataforma.
        </p>
        <p>El sistema proporciona las siguientes funcionalidades en relación con la normativa VERI*FACTU:</p>
        <ul class="text-list">
          <li>&#8212;&nbsp; Generación de facturas ordinarias y rectificativas con todos los campos fiscales obligatorios.</li>
          <li>&#8212;&nbsp; Generación automática de registros de facturación en formato XML conforme al esquema VERI*FACTU publicado por la AEAT (RegistroAlta y RegistroBaja).</li>
          <li>&#8212;&nbsp; Encadenamiento de registros mediante huella digital SHA-256, garantizando la trazabilidad e inalterabilidad del historial de facturación.</li>
          <li>&#8212;&nbsp; Generación e incrustación de código QR de verificación en los documentos de factura, conforme a la especificación técnica de la AEAT.</li>
          <li>&#8212;&nbsp; Transmisión de registros al servicio web de la AEAT mediante autenticación con certificado digital del obligado tributario (empresa emisora).</li>
          <li>&#8212;&nbsp; Bloqueo fiscal automático de facturas expedidas: una vez emitido el registro fiscal, el sistema impide su modificación o eliminación sin el correspondiente registro de anulación (RegistroBaja).</li>
          <li>&#8212;&nbsp; Separación estricta entre el estado comercial y el estado fiscal de cada factura.</li>
          <li>&#8212;&nbsp; Modos de operación configurables: <em>shadow</em> (registro local sin envío), <em>sandbox</em> (entorno de pruebas AEAT) y <em>production</em> (envío real con validez legal).</li>
        </ul>
      @endif
    </div>
  </div>

  {{-- ─── 1.k DECLARACIÓN RESPONSABLE ─── --}}
  <div class="section">
    <div class="section-title">1.k) Declaración responsable del productor</div>
    <div class="declaration-box">
      <div class="text-block">
        <p>
          La persona productora del sistema informático al que se refiere la presente declaración hace
          constar, bajo su responsabilidad, que el sistema denominado
          <strong>{{ $softwareName }}</strong>, versión <strong>{{ $softwareVersion }}</strong>,
          identificado con IdSistemaInformatico <strong class="data-mono">{{ $softwareId }}</strong>,
          cumple los requisitos exigidos por el Real Decreto 1007/2023, de 5 de diciembre, por el que
          se aprueba el Reglamento que establece los requisitos que deben adoptar los sistemas y programas
          informáticos o electrónicos que soporten los procesos de facturación de empresarios y profesionales,
          y la estandarización de formatos de los registros de facturación (VERI*FACTU).
        </p>
        <p>En particular, declara que el sistema:</p>
        <ul class="text-list" style="margin-top:4px;">
          <li>&#8212;&nbsp; Garantiza la integridad, conservación, accesibilidad, legibilidad, trazabilidad e inalterabilidad de los registros de facturación.</li>
          <li>&#8212;&nbsp; No permite la alteración de los registros de facturación una vez expedidos, quedando constancia de cualquier anulación mediante el correspondiente RegistroBaja.</li>
          <li>&#8212;&nbsp; Genera los registros de facturación en el formato y con las condiciones técnicas establecidas por la Agencia Estatal de Administración Tributaria, incluyendo la huella digital de encadenamiento y el código QR de verificación.</li>
        </ul>
        <p style="margin-top:6px;">
          {{ $vendorName }} asume plena responsabilidad por la veracidad del contenido de esta declaración.
        </p>
      </div>
    </div>
  </div>

  {{-- ─── SECCIÓN 2: DESCRIPCIÓN TÉCNICA ─── --}}
  <div class="section">
    <div class="section-title">2. Descripción técnica del sistema</div>

    <div class="tech-block" style="margin-bottom:6px;">
      <div class="tech-label">2.a)&nbsp; Mecanismo de encadenamiento e integridad</div>
      <div class="tech-value">
        Cada registro de facturación incorpora la huella digital (hash SHA-256) del registro
        inmediatamente anterior, formando una cadena verificable. La huella se calcula sobre la
        concatenación canónica de los campos fiscales obligatorios conforme a la especificación
        técnica de la AEAT. Cualquier alteración retroactiva rompe la cadena y es detectable.
      </div>
    </div>

    <div class="tech-block" style="margin-bottom:6px;">
      <div class="tech-label">2.b)&nbsp; Autenticación y firma digital</div>
      <div class="tech-value">
        La transmisión de registros al servicio web de la AEAT se realiza mediante autenticación
        mutua TLS con el certificado digital del obligado tributario (empresa emisora). Cada empresa
        utiliza su propio certificado X.509 en formato PKCS#12 (.p12/.pfx), almacenado cifrado en
        la base de datos del sistema. El productor de la plataforma no accede al contenido de los
        certificados de los obligados tributarios.
      </div>
    </div>

    <div class="tech-block">
      <div class="tech-label">2.c)&nbsp; Código QR de verificación y bloqueo fiscal</div>
      <div class="tech-value">
        El sistema genera el código QR de verificación conforme a la especificación publicada por
        la AEAT, a partir de los datos fiscales del registro (NIF emisor, número de factura, fecha,
        importe e IdSistemaInformatico). El QR se incrusta directamente en el documento PDF de cada
        factura. Una vez expedido el registro fiscal, el sistema activa el bloqueo fiscal de la factura,
        impidiendo cualquier edición o eliminación posterior sin el correspondiente registro de anulación
        (RegistroBaja), conforme al artículo 12 del Real Decreto 1007/2023.
      </div>
    </div>
  </div>

  {{-- ─── 1.l SUSCRIPCIÓN ─── --}}
  <div class="section">
    <div class="section-title">1.l) Suscripción de la declaración</div>
    <table class="sig-table">
      <tr>
        <td class="sig-label">Fecha:</td>
        <td class="sig-value">{{ $dateSigned }}</td>
        <td class="sig-label">Lugar:</td>
        <td class="sig-value">{!! $pval($p['subscription_place'] ?? null) !!}</td>
      </tr>
      <tr>
        <td class="sig-label" style="padding-top:6px;">En representación de:</td>
        <td class="sig-value" style="padding-top:6px;">{!! $pval($p['vendor_name'] ?? null) !!}</td>
        <td class="sig-label" style="padding-top:6px;">NIF:</td>
        <td class="sig-value" style="padding-top:6px;">{!! $pval($p['vendor_tax_id'] ?? null, true) !!}</td>
      </tr>
    </table>
    <div style="margin-top:18px;">
      <div class="sig-label" style="margin-bottom:3px;">Firma del productor:</div>
      <div class="sig-line"></div>
    </div>
  </div>

  {{-- NOTAS INTERNAS (solo si existen) --}}
  @if($declaration->notes)
  <div class="section">
    <div class="section-title">Notas internas del documento</div>
    <div class="text-block">{{ $declaration->notes }}</div>
  </div>
  @endif

</div>
</body>
</html>
