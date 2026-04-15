# VERI*FACTU Roadmap

Fecha de actualizacion: 2026-04-15.

## Estado real actual

Ya existe un baseline funcional sobre esta copia restaurada de Crater con:

- capa fiscal desacoplada del estado comercial
- records, submissions, events, declarations e installations
- bloqueo fiscal de facturas expedidas
- rectificativas base no destructivas
- numeracion separada para rectificativas
- dashboard VERI*FACTU visible en admin
- vistas de Records, Submissions, Events y Setup
- retry manual de submissions FAILED desde UI
- base de integracion AEAT por drivers, XML, huella y parser
- repositorio git inicializado y baseline publicado en GitHub

## Fase 0. Base ya realizada

- Auditoria de la instalacion real restaurada.
- Identificacion del flujo de facturacion, PDF, estados y riesgos.
- Capa fiscal separada del `status` comercial.
- Migraciones y modelos `verifactu_*`.
- Emision fiscal interna y bloqueo de edicion/borrado.
- Pipeline `stub` con submissions, eventos, jobs y scheduler.
- Rectificativas base y numeracion especifica.
- Dashboard VERI*FACTU visible en admin.
- Setup visible con installation/declarations.
- Commit baseline del fork y push a `origin/main`.

## Fase 1. Consolidacion inmediata

- Revisar los cambios pendientes sin commit introducidos despues del baseline (`tax_number`, abilities VERI*FACTU, AEAT/XML, validators, issue button en UI).
- Normalizar textos y documentacion para que no quede contexto repartido entre chat y codigo.
- Decidir si la capa AEAT nueva se considera experimental o ya forma parte del baseline funcional.
- Revisar permisos por rol sobre acciones fiscales (`view-verifactu`, `manage-verifactu`).
- Congelar mejor campos fiscales sensibles en UI y API.
- Validar con datos reales locales que `tax_number` se propaga correctamente a snapshot, XML y vistas.

## Fase 2. Preparacion seria para AEAT

- Validar y endurecer modos operativos: `off`, `shadow`, `stub`, `aeat_sandbox`, `aeat_production`.
- Confirmar estrategia definitiva del `DriverManager`.
- Probar `AeatHttpClient` con certificados reales en sandbox controlado.
- Validar `VerifactuXmlBuilder` contra ejemplos y respuestas reales de AEAT.
- Validar `AeatResponseParser` con respuestas correctas y de error.
- Registrar request/response con trazabilidad suficiente y sanitizacion.
- Cerrar almacenamiento seguro de certificados y metadatos.
- Preparar alertas y reintentos persistentes mas duros si el envio sale de `stub`.

## Fase 3. Rectificativas avanzadas

- Modelo por diferencias.
- Gestion de importes negativos donde sea compatible.
- Reglas completas de negocio por motivo de rectificacion.
- Mapeo completo `rectification_type -> tipo_factura R1-R5`.
- Serie y contador fiscal robusto por tipo de rectificativa.
- Encadenamiento fiscal especifico si aplica.

## Fase 4. Operacion y cumplimiento

- Declaracion responsable operativa por version.
- Checklist de despliegue.
- Manual interno de soporte.
- Plan de restauracion y rollback local.
- Politica de backups y trazabilidad.
- Checklist previa a activar `aeat_production`.

## Riesgos abiertos

- Esta copia local ya contiene datos de prueba VERI*FACTU.
- Hay cambios nuevos sin commit posteriores al baseline `ad81b53`.
- La base AEAT/XML existe, pero no esta cerrada como integracion final homologada.
- La numeracion global de Crater sigue siendo un punto sensible.
- Hay ficheros historicos de debug/utilidad en la restauracion que conviene decidir si permanecen en el fork publico.

## Siguiente paso recomendado

1. Hacer un commit separado de documentacion y saneo de contexto.
2. Revisar funcionalmente el bloque nuevo sin commit (AEAT/XML, NIF, abilities, issue manual).
3. Agrupar esos cambios en uno o varios commits limpios antes de seguir implementando sobre `main`.
