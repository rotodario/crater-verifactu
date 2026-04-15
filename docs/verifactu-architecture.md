# VERI*FACTU Integration Notes

## Objetivo

Esta capa introduce una separacion explicita entre:

- Estado comercial de la factura en Crater.
- Estado fiscal VERI*FACTU.
- Pipeline de submission fiscal desacoplado del flujo comercial.

No sustituye el flujo actual de Crater. Lo encapsula.

## Piezas nuevas

- `config/verifactu.php`
- Tablas:
  - `verifactu_installations`
  - `verifactu_records`
  - `verifactu_submissions`
  - `verifactu_events`
  - `verifactu_declarations`
- Campos nuevos en `invoices`:
  - `fiscal_status`
  - `fiscal_issued_at`
  - `fiscal_locked_at`
  - `verifactu_record_id`
  - `invoice_kind`
  - `original_invoice_id`
  - `rectification_type`
  - `rectification_reason`
- Campos fiscales nuevos en negocio:
  - `companies.tax_number`
  - `customers.tax_number`
- Persistencia adicional relevante:
  - `verifactu_records.tipo_factura`
  - `verifactu_submissions.request_xml`
  - `verifactu_submissions.response_xml`
  - `verifactu_submissions.csv`
- Job:
  - `ProcessVerifactuSubmissionJob`
- Command:
  - `php artisan verifactu:process-pending`

## Capa de servicios actual

### Nucleo de dominio

- `VerifactuService`
- `VerifactuRecordBuilder`
- `VerifactuStateManager`
- `VerifactuQrService`
- `VerifactuEventLogger`
- `VerifactuDeclarationService`
- `VerifactuSubmissionService`

### Resolucion de modo y drivers

- `VerifactuDriverManager`
- Drivers disponibles:
  - `ShadowDriver`
  - `StubDriver`
  - `AeatSandboxDriver`
  - `AeatProductionDriver`

### Preparacion AEAT

- `VerifactuPreSubmissionValidator`
- `VerifactuHuellaComputer`
- `VerifactuXmlBuilder`
- `AeatHttpClient`
- `AeatResponseParser`

## Flujo minimo implantado

1. La factura sigue naciendo como `DRAFT`.
2. Cuando se envia o se marca como enviada, `VerifactuService` puede asegurar la expedicion fiscal segun modo y configuracion.
3. Antes de expedir, `VerifactuPreSubmissionValidator` valida numero, fecha, total, lineas, software y datos fiscales minimos.
4. Se genera un snapshot inmutable en `verifactu_records`.
5. Se calcula `tipo_factura` y huella/hash encadenada.
6. Se bloquea la edicion fiscal de la factura.
7. Se registra un evento interno.
8. Si el modo actual somete registros, se crea un `verifactu_submission` y se procesa por job o comando.
9. La submission puede usar driver `shadow`, `stub` o drivers AEAT.
10. Una factura fiscalmente expedida puede generar una rectificativa nueva mediante `POST /api/v1/invoices/{invoice}/rectify`.

## Modos de funcionamiento

Definidos en `config/verifactu.php`:

- `off`: capa desactivada, sin records ni submissions.
- `shadow`: genera records y observabilidad, pensado para analisis sin envio operativo real.
- `stub`: simulacion local completa del envio.
- `aeat_sandbox`: preparado para endpoint de pruebas AEAT.
- `aeat_production`: preparado para endpoint real AEAT.

## Snapshot fiscal

El snapshot ya incluye, como minimo:

- datos de factura
- datos de empresa
- `company.tax_number`
- datos de cliente
- `customer.tax_number`
- lineas e impuestos
- identificacion del software

Esto evita depender de mutaciones posteriores del modelo comercial para reconstruir el registro fiscal.

## Submissions

`verifactu_submissions` ya contempla dos niveles de persistencia:

- payloads estructurados (`request_payload`, `response_payload`)
- intercambio XML bruto (`request_xml`, `response_xml`)
- referencia y CSV AEAT (`csv`, `external_reference`)

Estados actualmente usados:

- `PENDING`
- `PROCESSING`
- `ACCEPTED`
- `FAILED`
- `REJECTED`

Operacion actual:

- Procesado manual: `php artisan verifactu:process-pending --limit=25`
- Procesado automatico: scheduler cada 5 minutos
- Reintento manual desde UI para submissions `FAILED`

## AEAT: base tecnica ya preparada

La capa actual ya deja preparada la estructura para salto a AEAT:

- `AeatHttpClient` para mTLS con certificado PEM/P12
- `AeatResponseParser` para respuestas SOAP/XML
- `VerifactuXmlBuilder` para construir XML `SuministroLRFacturasEmitidas`
- `VerifactuHuellaComputer` para hash encadenado y formatos AEAT

Esto no significa homologacion final ni validacion completa. Significa que el sistema ya no esta solo en modo `stub` superficial.

## Rectificativas

- La rectificativa no modifica ni borra la factura original.
- La factura nueva nace con:
  - `invoice_kind=RECTIFICATIVE`
  - `original_invoice_id=<factura original>`
  - `rectification_type=REPLACEMENT`
  - `fiscal_status=NOT_ISSUED`
  - `status=DRAFT`
- Se clonan lineas, impuestos y custom fields de la factura original como base de trabajo.
- La referencia comercial se conserva en `reference_number` con el numero de la factura original.

### Limite actual

Esta primera implementacion crea una rectificativa de sustitucion base, pero no resuelve todavia:

- rectificacion por diferencias con importes negativos o parciales
- mapeo definitivo `rectification_type -> tipo_factura R1-R5`
- reglas finales AEAT por motivo de rectificacion

## UI visible actual

La capa visible ya no se limita a botones sueltos en facturas. Hoy existe:

- `/admin/verifactu` dashboard
- `/admin/verifactu/records`
- `/admin/verifactu/submissions`
- `/admin/verifactu/events`
- `/admin/verifactu/setup`
- detalle de record, submission, event y declaration

## Seguridad y permisos

- Policy dedicada: `VerifactuPolicy`
- Abilities dedicadas:
  - `view-verifactu`
  - `manage-verifactu`
- La idea es desacoplar la observabilidad VERI*FACTU de los permisos comerciales de factura.

## Riesgos abiertos

- La numeracion global de Crater sigue siendo delicada.
- El flujo AEAT real aun necesita validacion externa.
- La copia local ya contiene pruebas y no es una restauracion virgen.
- Hay cambios funcionales nuevos sin commit posteriores al baseline `ad81b53`.
