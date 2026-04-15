# Fork README: Crater + VERI*FACTU

## Estado actual

Este repositorio no es un Crater limpio. Es una copia restaurada desde hosting a local sobre la que se ha empezado una capa fiscal VERI*FACTU desacoplada.

Fecha de corte de este documento: 2026-04-15.

## Entorno actual

- Ruta local: `C:\xampp3\htdocs\crater`
- Stack detectado: Laravel 8.83.16, PHP 8.1.12, Vue 3, Vite
- Entorno de trabajo: local restaurado desde hosting
- Alcance de pruebas realizadas: base de datos local de esta copia
- Conexi?n AEAT real: no activa
- Entorno oficial AEAT sandbox: no integrado todav?a
- Driver de submission actual: `stub`
- Modo VERI*FACTU visible en la app: revisar `/admin/verifactu`

## Qu? se ha implementado ya

- Estado fiscal independiente del estado comercial en facturas.
- Expedici?n fiscal separada y registro interno VERI*FACTU.
- Bloqueo de edici?n y borrado tras expedici?n fiscal.
- Tablas `verifactu_*` para records, submissions, events, declarations e installations.
- QR/payload y bloque PDF VERI*FACTU.
- Pipeline de submissions con `stub`, job, comando y scheduler.
- Rectificativas no destructivas base.
- Numeraci?n separada para rectificativas.
- Dashboard admin de VERI*FACTU en `/admin/verifactu`.

## Qu? no hace todav?a

- No env?a nada a Hacienda real.
- No usa todav?a sandbox oficial AEAT.
- No implementa firma/certificado real.
- No implementa todav?a rectificaci?n por diferencias con importes negativos.
- No tiene a?n panel completo de detalle por submission/record ni reprocesado desde UI.

## Datos de prueba ya generados en esta copia local

- Se han creado registros `verifactu_*` reales en la base local.
- Se emiti? fiscalmente al menos una factura local para validaci?n.
- Se gener? al menos una rectificativa de prueba.
- Se procesaron submissions `stub` aceptadas de prueba.

Esto significa que la copia local ya no es una restauraci?n virgen. Si se quiere m?xima limpieza, conviene clonar la base antes de seguir.

## Puntos de entrada importantes

- Arquitectura t?cnica: [docs/verifactu-architecture.md](docs/verifactu-architecture.md)
- Roadmap operativo: [docs/verifactu-roadmap.md](docs/verifactu-roadmap.md)
- Dashboard visible: `/admin/verifactu`
- Config principal: `config/verifactu.php`

## Recomendaci?n para retomar trabajo

1. Confirmar el modo visible en `/admin/verifactu`.
2. Revisar `docs/verifactu-roadmap.md` y elegir la siguiente fase.
3. Hacer copia de base de datos local antes de cambios grandes.
4. Crear repositorio git del fork antes de conectar AEAT real.
