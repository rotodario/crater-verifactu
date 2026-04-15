# VERI*FACTU Roadmap

Fecha de corte: 2026-04-15.

## Fase 0. Base ya realizada

- Auditor?a de la instalaci?n real restaurada.
- Identificaci?n del flujo de facturaci?n, PDF, estados y riesgos.
- Capa fiscal separada del `status` comercial.
- Migraciones y modelos `verifactu_*`.
- Emisi?n fiscal interna y bloqueo de edici?n/borrado.
- Pipeline `stub` con submissions, eventos, jobs y scheduler.
- Rectificativas base y numeraci?n espec?fica.
- Dashboard VERI*FACTU visible en admin.

## Fase 1. Consolidaci?n inmediata

- A?adir detalle de registro VERI*FACTU desde dashboard.
- A?adir detalle de submission con payload/respuesta resumida.
- A?adir filtros por estado, rango y tipo de factura.
- A?adir reproceso controlado de submissions fallidas.
- Revisar permisos por rol sobre acciones fiscales.
- Congelar mejor campos fiscales sensibles en UI y API.

## Fase 2. Preparaci?n seria para AEAT

- Definir modos operativos expl?citos: `off`, `shadow`, `stub`, `aeat_sandbox`, `aeat_production`.
- Implementar cliente AEAT aislado por driver.
- Dise?ar almacenamiento seguro de certificados y metadatos.
- Registrar request/response con trazabilidad suficiente y sanitizaci?n.
- Preparar validaciones previas al env?o.
- Definir estrategia de retry persistente y alertas.

## Fase 3. Rectificativas avanzadas

- Modelo por diferencias.
- Gesti?n de importes negativos donde sea compatible.
- Reglas completas de negocio por motivo de rectificaci?n.
- Serie/contador fiscal robusto por tipo de rectificativa.
- Encadenamiento fiscal espec?fico si aplica.

## Fase 4. Operaci?n y cumplimiento

- Declaraci?n responsable operativa por versi?n.
- Checklist de despliegue.
- Manual interno de soporte.
- Plan de reversi?n local y de restauraci?n.
- Pol?tica de backups y trazabilidad.

## Riesgos abiertos

- Esta copia local ya contiene datos de prueba VERI*FACTU.
- No existe todav?a integraci?n AEAT real ni sandbox oficial.
- La numeraci?n global de Crater sigue siendo un punto sensible y debe vigilarse.
- Hay rutas de debug hist?ricas en el proyecto que conviene retirar antes de endurecer el entorno.

## Antes de crear git y empezar commits/push

1. Confirmar qu? archivos locales de debug se quieren conservar o excluir.
2. Verificar `.env` y credenciales para no versionar secretos.
3. Revisar la base local y decidir si se parte de snapshot actual o de una copia m?s limpia.
4. Hacer primer commit de baseline del fork con documentaci?n incluida.
