# VERI*FACTU — Arquitectura técnica

Última actualización: 2026-04-18.

## Objetivo

Separación explícita entre:

- Estado comercial de la factura en Crater (`status`).
- Estado fiscal VERI*FACTU (`fiscal_status`).
- Pipeline de submission fiscal desacoplado del flujo comercial.

La capa no sustituye el flujo de Crater. Lo encapsula.

## Tablas

| Tabla | Propósito |
|---|---|
| `verifactu_installations` | Una por empresa. Modo, certificado, metadatos de instalación. |
| `verifactu_records` | Registro fiscal inmutable (snapshot + hash). |
| `verifactu_submissions` | Envío a AEAT: XML request/response, estado, CSV. |
| `verifactu_events` | Log de eventos fiscales (expedición, anulación, reparación…). |
| `verifactu_declarations` | Declaración Responsable del SIF. |
| `verifactu_platform_config` | Identidad del SIF (una fila global para toda la plataforma). |

## Campos nuevos en `invoices`

`fiscal_status`, `fiscal_issued_at`, `fiscal_locked_at`, `verifactu_record_id`,
`invoice_kind`, `original_invoice_id`, `rectification_type`, `rectification_reason`.

## Capa de servicios

### Núcleo de dominio

| Servicio | Responsabilidad |
|---|---|
| `VerifactuService` | Orquestación: validar, expedir, bloquear. |
| `VerifactuRecordBuilder` | Construye el snapshot fiscal y computa hash+huella. |
| `VerifactuStateManager` | Transiciones de `fiscal_status` en Invoice. |
| `VerifactuQrService` | Genera el payload QR para el PDF. |
| `VerifactuEventLogger` | Persiste eventos fiscales auditables. |
| `VerifactuSubmissionService` | Crea y encola submissions. |
| `VerifactuDeclarationService` | Ciclo de vida de la Declaración Responsable. |
| `VerifactuHuellaComputer` | SHA-256 canónico según spec AEAT (Alta y Baja). |
| `VerifactuXmlBuilder` | XML SOAP `RegistroAlta` + `RegistroBaja`. |

### Drivers (resolución por modo)

| Driver | Comportamiento |
|---|---|
| `ShadowDriver` | Solo registra. No envía. |
| `StubDriver` | Simula envío con ACCEPTED local. |
| `AeatSandboxDriver` | Envía a `prewww1`/`prewww10.aeat.es`. |
| `AeatProductionDriver` | Envía a `www1`/`www10.agenciatributaria.gob.es`. |

Los drivers AEAT auto-seleccionan el endpoint correcto (persona física vs certificado de sello) inspeccionando el certificado PKCS12.

### Comunicación AEAT

- `AeatHttpClient`: SOAP mTLS con PKCS12 (.p12/.pfx) o PEM.
- `AeatResponseParser`: parsea respuesta SOAP, extrae CSV, estado y errores por línea.
- `AeatConsultaXmlBuilder` + `AeatConsultaParser`: consulta del libro registro AEAT.
- `AeatHistorialService`: cruza datos locales con el historial AEAT.
- `VerifactuReconciliacionService`: reconciliación local vs AEAT por ejercicio/periodo.

## Flujo de expedición

1. Factura nace como `DRAFT`.
2. Cuando se expide (botón explícito, o `issue_on_send=true` al enviar):
   - `VerifactuPreSubmissionValidator` valida número, fecha, total, software, NIF.
   - `VerifactuRecordBuilder` genera snapshot inmutable + hash encadenado.
   - `VerifactuStateManager` actualiza `fiscal_status = ISSUED` y bloquea edición.
   - `VerifactuEventLogger` registra el evento.
3. Si el modo envía (`aeat_sandbox`, `aeat_production`):
   - Se crea `VerifactuSubmission` y se procesa por job/scheduler.
   - El driver refresca `FechaHoraHusoGenRegistro` justo antes de enviar (ventana 240s AEAT).
   - `issued_at` del record nunca se modifica tras la creación.
4. La respuesta AEAT se persiste completa (XML + parsed payload).

## Hash chain — clave de encadenamiento

```
previousRecord = VerifactuRecord
    WHERE verifactu_installation_id = {installation.id}
    ORDER BY id DESC
    LIMIT 1
```

**El ámbito es la instalación, no la empresa.** Esto garantiza que cadenas de diferentes instalaciones (sandbox vs production, reinstalaciones) no se crucen entre sí.

## Inmutabilidad del registro fiscal

Una vez creado, `verifactu_records` es inmutable salvo:
- `hash` + `metadata.fecha_hora_huso`: se actualizan justo antes de enviar por la restricción de 240s de AEAT. El hash original se preserva en `metadata.original_hash`.
- `status`: cambia a ACCEPTED/FAILED/CANCELLED según el resultado.
- `issued_at`: **nunca cambia** tras la creación. Representa cuándo se tomó la decisión fiscal.

## Arquitectura multi-tenant SIF

```
Plataforma (global — una fila en verifactu_platform_config)
├── IdSistemaInformatico (software_id)
├── software_name / software_version
└── vendor_name / vendor_tax_id

Por empresa (verifactu_installations)
├── Certificado digital (.p12/.pfx/.pem)
├── issuer_name / issuer_tax_id (NIF del obligado)
├── installation_number
└── mode (shadow | stub | aeat_sandbox | aeat_production)
```

En el XML: `SistemaInformatico` usa datos de plataforma; `ObligadoEmision` usa datos de empresa.

## `issue_on_send`

Controla si enviar una factura por email la expide fiscalmente de forma automática.

**Default: `false`** (requiere activación explícita en `.env`).

Con `true`: el primer email bloquea la factura para siempre. Solo recomendado si el flujo garantiza que las facturas son siempre definitivas antes de enviarse al cliente.

## Declaración Responsable del SIF

Estados: `DRAFT → GENERATED → REVIEWED → ACTIVE → ARCHIVED`.

- Una sola `ACTIVE` a la vez. Al activar una nueva, la anterior queda `ARCHIVED`.
- Snapshot de datos de plataforma congelado al pasar a `GENERATED`.
- PDF disponible desde `GENERATED` en adelante.
- Nivel plataforma (`company_id = NULL`).

## Rectificativas (estado actual)

- No destructivas: factura original queda bloqueada.
- `invoice_kind = rectificative` → `tipo_factura = R4` (genérico).
- Mapeo completo R1–R5 pendiente.
- Rectificación por diferencias (importes negativos) pendiente.

## Permisos

| Acción | Requiere |
|---|---|
| Ver cualquier cosa VERI*FACTU | `view-verifactu` |
| Gestionar records / config empresa | `manage-verifactu` |
| Editar Plataforma SIF / Declaraciones | `User::isOwner()` |

## Riesgos abiertos

- No hay homologación formal AEAT (funciona en sandbox, falta certificación oficial).
- Cola de submissions en driver `sync` — sin reintentos automáticos persistentes.
- Rectificativas avanzadas (R1–R5, por diferencias) no implementadas.
