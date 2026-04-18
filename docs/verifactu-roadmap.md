# VERI*FACTU — Roadmap

Última actualización: 2026-04-18.

## Estado real actual

Sistema funcionando contra AEAT sandbox con certificado real. Todas las correcciones críticas validadas.

---

## ✅ Completado

### Base (Fase 0)
- Capa fiscal desacoplada del estado comercial.
- Tablas `verifactu_*`, snapshot fiscal inmutable, bloqueo de edición/borrado.
- Pipeline de submissions: job, artisan command, scheduler.
- Drivers: `shadow`, `stub`, `aeat_sandbox`, `aeat_production`.
- Dashboard admin: Records, Submissions, Events, Setup.
- Retry manual de submissions FAILED desde UI.
- QR VERI*FACTU y bloque fiscal en PDF.
- Rectificativas base no destructivas.

### Integración AEAT real (Fase 1–2)
- `AeatHttpClient` mTLS con PKCS12. Subida y almacenamiento cifrado del certificado por empresa.
- `VerifactuXmlBuilder`: RegistroAlta + RegistroBaja completos y validados contra AEAT.
- `VerifactuHuellaComputer`: hash canónico correcto para Alta y Baja (con sufijos `Anulada`).
- Auto-selección de endpoint persona física vs certificado de sello.
- Fix error 2004: `refreshTimestampAndHash()` en drivers AEAT — ventana 240s garantizada.
- Fix error 2000: fórmula correcta para hash de RegistroBaja.
- Fix error 3000: sufijado automático `010426 → 010426B → 010426C` al reparar.
- `issued_at` inmutable tras creación; trazabilidad de `original_hash` en metadata.
- Encadenamiento por `verifactu_installation_id` (no solo `company_id`).

### Observabilidad y reconciliación
- Panel Historial AEAT (`/admin/verifactu/historial-aeat`): consulta libro registro AEAT.
- Panel Reconciliación AEAT (`/admin/verifactu/reconciliacion-aeat`): cruza local vs AEAT.
- 9 estados de reconciliación con acciones por estado.
- Repair chain (CHAIN_ERROR): anula + reenvía con número sufijado.
- Repair no-local (REMOTE_ONLY con Invoice local): ancla en huella remota + nuevo alta.
- Acknowledge REMOTE_ONLY: reconocimiento manual sin acción fiscal.
- Mark review: marcar records para revisión manual.

### Declaración Responsable del SIF
- Ciclo de vida completo: `DRAFT → GENERATED → REVIEWED → ACTIVE → ARCHIVED`.
- PDF descargable desde estado GENERATED.
- Configuración plataforma SIF (`VerifactuPlatformConfig`).

### Higiene y configuración
- `issue_on_send` default cambiado a `false` (menos agresivo).
- `.env.example` incluye todas las variables VERI*FACTU con comentarios.
- Dashboard excluye facturas ANNULLED de todos los cálculos contables.
- SMTP por empresa (`CompanyMailService`).

---

## 🔲 Pendiente

### Rectificativas avanzadas
- Mapeo completo `rectification_type → tipo_factura R1-R5`.
- Rectificación por diferencias (importes negativos/parciales).
- Reglas de negocio por motivo de rectificación según normativa AEAT.

### Operación producción
- Cola persistente (Redis + Horizon) para reintentos automáticos robustos.
- Checklist de activación `aeat_production` (certificado sello, NIF correcto, modo verificado).
- Alertas automáticas si submissions llevan más de X minutos en PENDING.

### Homologación formal
- El sistema funciona en sandbox pero no tiene la certificación oficial AEAT como SIF homologado.
- Facturación simplificada F2 (sin destinatario identificado).

---

## Riesgos abiertos

| Riesgo | Severidad | Estado |
|---|---|---|
| Sin homologación formal AEAT | Alto | Abierto |
| Cola `sync` sin reintentos persistentes | Medio | Abierto |
| Rectificativas R1-R5 no implementadas | Medio | Abierto |
| Producción requiere checklist manual de activación | Alto | Mitigado por `issue_on_send=false` default |
