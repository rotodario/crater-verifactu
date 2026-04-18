# Fork README: Crater + VERI*FACTU

## Estado actual

Este repositorio es un fork de Crater (Laravel 8 / Vue 3 / Vite) con una capa fiscal VERI*FACTU completamente desacoplada del estado comercial de facturas, validada contra el entorno sandbox de AEAT.

Última actualización de este documento: **2026-04-18**.

---

## Entorno

| | |
|---|---|
| Ruta local | `C:\xampp3\htdocs\crater` |
| Stack | Laravel 8.83, PHP 8.1, Vue 3, Vite |
| Base de datos | MySQL (local restaurada desde hosting) |
| Conexión AEAT sandbox | **Activa y validada** (prewww10.aeat.es) |
| Conexión AEAT producción | Disponible; requiere configurar certificado de sello |
| Modo por defecto | Revisar `config/verifactu.php` y `/admin/verifactu/setup` |

---

## Qué está implementado y funcionando

### Núcleo VERI*FACTU

- Estado fiscal independiente del estado comercial (`fiscal_status` vs `status`).
- Expedición fiscal y bloqueo de edición/borrado tras expedición.
- Tablas: `verifactu_installations`, `verifactu_records`, `verifactu_submissions`, `verifactu_events`, `verifactu_declarations`, `verifactu_platform_config`.
- Campos en `invoices`: `fiscal_status`, `fiscal_issued_at`, `fiscal_locked_at`, `verifactu_record_id`, `invoice_kind`, `original_invoice_id`, `rectification_type`, `rectification_reason`.
- Huella/hash encadenada SHA-256 (RegistroAlta y RegistroBaja con sus respectivas fórmulas).
- `tipo_factura` persistido: F1, F2, R4 (base rectificativas).
- QR VERI*FACTU y bloque fiscal en PDF de facturas.
- Pipeline de submissions: job, comando artisan y scheduler.

### Drivers de submission

| Driver | Descripción |
|---|---|
| `shadow` | Solo registra localmente, no envía |
| `stub` | Simula envío y devuelve ACCEPTED local |
| `aeat_sandbox` | Envía a `prewww10.aeat.es` (homologación) |
| `aeat_production` | Envía a `www10.aeat.es` (producción real) |

Ambos drivers AEAT auto-seleccionan el endpoint correcto (persona física `www1`/`prewww1` vs certificado de sello `www10`/`prewww10`) inspeccionando el certificado.

### Comunicación AEAT real

- `AeatHttpClient`: cliente SOAP mTLS con certificado PKCS12 (.p12/.pfx) o PEM.
- `AeatResponseParser`: parsea respuesta SOAP, extrae CSV, estado y errores por línea.
- `VerifactuXmlBuilder`: genera XML `RegistroAlta` y `RegistroBaja` completos.
- `VerifactuHuellaComputer`: SHA-256 con el string canónico correcto para Alta y Baja.
- Certificados por empresa: subida, almacenamiento cifrado y contraseña cifrada en BD.
- **Timestamp refresh antes de envío**: `refreshTimestampAndHash()` en ambos drivers AEAT renueva `FechaHoraHusoGenRegistro` y recomputa la huella justo antes de enviar, garantizando que el registro esté dentro de la ventana de 240 segundos de AEAT (evita error 2004).

### Correcciones críticas AEAT validadas

| Error AEAT | Causa | Fix |
|---|---|---|
| **2000** (huella incorrecta) | `computeBaja()` usaba nombres de campo sin sufijo `Anulada` | Hash y XML de RegistroBaja usan `IDEmisorFacturaAnulada`, `NumSerieFacturaAnulada`, `FechaExpedicionFacturaAnulada` |
| **2004** (timestamp fuera de ventana) | Hash computado al crear el record, enviado más tarde | `refreshTimestampAndHash()` en drivers AEAT antes de enviar |
| **3000** (duplicado) | AEAT bloquea permanentemente NIF+NumSerie+Fecha aunque haya anulación previa | Sufijado automático: `010426 → 010426B → 010426C` |

### Arquitectura multi-tenant SIF

```
Plataforma (global, una fila)
├── IdSistemaInformatico  ← verifactu_platform_config
├── vendor_name / vendor_tax_id / vendor_address
└── software_name / software_version

Por empresa (independiente)
├── Certificado digital (.p12/.pfx/.pem)
├── issuer_name / issuer_tax_id (NIF del obligado)
└── installation_number
```

En el XML: `SistemaInformatico` usa datos de plataforma; `ObligadoEmision` usa datos de empresa.

### Panel de administración VERI*FACTU (`/admin/verifactu`)

Navegación por secciones con `SectionNav.vue`:

**Records** — lista de registros fiscales con estado, tipo, hash, submissions anidadas.

**Submissions** — lista de envíos con XML request/response, estado, CSV AEAT. Reintento manual de submissions `FAILED`.

**Events** — log de eventos fiscales (expedición, anulación, reparación, etc.).

**Historial AEAT** (`/admin/verifactu/historial-aeat`) — consulta el libro registro en AEAT directamente por ejercicio/periodo. Muestra los registros que AEAT tiene, con su estado y huella.

**Reconciliación AEAT** (`/admin/verifactu/reconciliacion-aeat`) — cruza registros locales con los de AEAT por ejercicio/periodo y detecta discrepancias.

Estados de reconciliación:
| Estado | Significado |
|---|---|
| `OK` | Local y AEAT coinciden |
| `MISMATCH` | Existe en ambos pero con diferencias de hash/estado |
| `CHAIN_ERROR` | AEAT aceptó con error 2000 (huella incorrecta) |
| `ACCEPTED_WITH_ERRORS` | AEAT aceptó con avisos pero sin error 2000 |
| `REJECTED` | AEAT rechazó el registro |
| `ANNULLED` | Anulado correctamente en AEAT |
| `LOCAL_ONLY` | Existe localmente pero no en AEAT |
| `REMOTE_ONLY` | Existe en AEAT pero no hay record local |
| `ACKNOWLEDGED` | REMOTE_ONLY reconocido manualmente (sin acción requerida) |
| `PENDING_REVIEW` | Marcado para revisión manual |

Acciones disponibles desde el panel:
- **Anular y reenviar** (CHAIN_ERROR): crea RegistroBaja + nueva Alta con número sufijado automáticamente.
- **Reparar sin local** (REMOTE_ONLY con Invoice local): acepta la huella remota AEAT como ancla, crea anulación + nueva Alta con número sufijado.
- **Reconocer** (REMOTE_ONLY): marca como ACKNOWLEDGED en `installation.metadata`.
- **Reenviar a AEAT**: crea nueva submission para un record existente.
- **Marcar revisión**: anota el record con motivo para revisión manual.

### Sufijado automático de número de factura

Al reparar (anular + reenviar) se renombra la factura automáticamente para evitar el error 3000:
- Se busca la primera letra B–Z no usada en `verifactu_records` ni `invoices` para esa empresa.
- `010426` → `010426B` → `010426C` → …
- La anulación usa el número original (lo que AEAT tiene registrado).
- La nueva Alta usa el número sufijado.
- `Invoice.invoice_number` se actualiza en la BD y el snapshot se reconstruye.

### Declaración Responsable del SIF

Ciclo de vida completo con estados `DRAFT → GENERATED → REVIEWED → ACTIVE → ARCHIVED`:
- Una sola declaración `ACTIVE` a la vez; al activar una nueva, la anterior queda `ARCHIVED`.
- Snapshot de datos de plataforma congelado al pasar a `GENERATED`.
- PDF descargable desde estado `GENERATED` en adelante (`/verifactu/declarations/{id}/pdf`).
- Nivel plataforma (`company_id = NULL`), no por empresa.

### Setup (`/admin/verifactu/setup`)

5 pestañas:
1. **Resumen** — badges de estado, info emisor, estado certificado, últimas declaraciones.
2. **Plataforma SIF** — `VerifactuPlatformConfig`: `software_name`, `software_version`, `vendor_name`, `vendor_tax_id`, `software_id`. Solo editable por superadmin.
3. **Configuración empresa** — datos de la instalación por empresa.
4. **Certificados** — subida/eliminación de certificado digital AEAT por empresa.
5. **Declaraciones** — gestión del ciclo de vida de la DR.

### Dashboard contable

Las facturas con `fiscal_status = 'ANNULLED'` quedan **excluidas** de todos los cálculos del dashboard: gráfico mensual de ventas, total ventas, total facturas, total pendiente de cobro y facturas recientes.

### Rectificativas (base)

- Numeración separada para rectificativas.
- `tipo_factura` R4 para rectificativas genéricas.
- No destructivas: la factura original queda bloqueada.

### SMTP por empresa

`CompanyMailService` registra mailers dinámicos `company_{id}` con contraseña cifrada. Facturas, presupuestos y pagos usan el mailer de su empresa.

### Permisos

| Acción | Requiere |
|---|---|
| Ver cualquier cosa VERI*FACTU | `view-verifactu` |
| Editar config empresa / certificados / records | `manage-verifactu` |
| Editar Plataforma SIF / Declaraciones | `User::isOwner()` (superadmin) |

---

## Qué no hace todavía

- **Rectificativas avanzadas**: falta implementar rectificación por diferencias (importes negativos/parciales) y el mapeo completo R1–R5.
- **Homologación formal AEAT**: el sistema funciona contra sandbox pero no está certificado formalmente como SIF homologado.
- **Cola persistente**: las submissions van con driver `sync`. Para producción real conviene migrar a Redis + Horizon para reintentos automáticos robustos.
- **Producción real activa**: la conexión a `www10.aeat.es` está codificada pero requiere un certificado de sello válido en producción y activación consciente del modo `aeat_production`.
- **Facturación simplificada F2**: no hay UI específica para facturas sin destinatario identificado.
- **Agrupación de facturas**: no implementado el caso de RegistroAlta con múltiples facturas en un solo envío.

---

## Configuración clave (.env)

```env
VERIFACTU_ENABLED=true
VERIFACTU_MODE=aeat_sandbox          # off | shadow | stub | aeat_sandbox | aeat_production
VERIFACTU_SUBMISSION_ENABLED=true
VERIFACTU_AEAT_SANDBOX_URL=https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP
VERIFACTU_AEAT_SANDBOX_URL_SELLO=https://prewww10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP
VERIFACTU_AEAT_PRODUCTION_URL=https://www1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP
VERIFACTU_AEAT_PRODUCTION_URL_SELLO=https://www10.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP
VERIFACTU_CERT_PATH=                 # ruta a .p12/.pem (alternativa a certificado en BD)
VERIFACTU_CERT_PASSWORD=
```

---

## Puntos de entrada importantes

- Dashboard VERI*FACTU: `/admin/verifactu`
- Setup: `/admin/verifactu/setup`
- Reconciliación AEAT: `/admin/verifactu/reconciliacion-aeat`
- Historial AEAT: `/admin/verifactu/historial-aeat`
- Config principal: `config/verifactu.php`
- Commit base del fork: `ad81b53 chore: baseline restored crater fork with verifactu foundation`

---

## Notas técnicas

- `isFiscalLocked()`: `fiscal_locked_at IS NOT NULL` — bloquea edición/borrado.
- `VerifactuPlatformConfig::current()` siempre devuelve objeto con defaults aunque no exista fila en BD.
- QR VERI*FACTU requiere extensión GD habilitada en `php.ini`.
- DomPDF `@page` margins: DomPDF 1.x requiere al menos 2 reglas `@page` (`:first`, `:left`, `:right`) para aplicar márgenes en todas las páginas.
- SimpleXMLElement: los nodos contenedor (sin texto) son falsy — usar `!== null`, nunca ternario directo.
- Registros `CANCELLED`/`FAILED` son historial inerte — no borrar, no interfieren con la cadena activa.
- La huella de RegistroBaja usa `IDEmisorFacturaAnulada`, `NumSerieFacturaAnulada`, `FechaExpedicionFacturaAnulada` (distinto de RegistroAlta).
