# Fork README: Crater + VERI*FACTU

## Estado actual

Este repositorio no es un Crater limpio. Es una copia restaurada desde hosting a local sobre la que se ha montado una capa fiscal VERI*FACTU desacoplada y una primera base de integracion AEAT preparada para evolucionar.

Fecha de actualizacion de este documento: 2026-04-15.

## Entorno actual

- Ruta local: `C:\xampp3\htdocs\crater`
- Stack detectado: Laravel 8.83.16, PHP 8.1.12, Vue 3, Vite
- Entorno de trabajo: local restaurado desde hosting
- Alcance de pruebas realizadas: base de datos local de esta copia
- Conexion AEAT real: no activa en produccion real
- Drivers de submission disponibles: `shadow`, `stub`, `aeat_sandbox`, `aeat_production`
- Modo configurado por defecto en este estado: revisar `config/verifactu.php` y `/admin/verifactu`

## Que se ha implementado ya

- Estado fiscal independiente del estado comercial en facturas.
- Expedicion fiscal separada y registro interno VERI*FACTU.
- Bloqueo de edicion y borrado tras expedicion fiscal.
- Tablas `verifactu_*` para installations, records, submissions, events y declarations.
- QR/payload y bloque PDF VERI*FACTU.
- Pipeline de submissions con job, comando y scheduler.
- Driver manager por modo (`shadow`, `stub`, `aeat_sandbox`, `aeat_production`).
- Base de cliente AEAT con certificado mTLS, parser de respuesta SOAP/XML y builder XML.
- Huella/hash encadenada para registros y persistencia de `tipo_factura`.
- Rectificativas no destructivas base.
- Numeracion separada para rectificativas.
- Dashboard admin de VERI*FACTU con vistas de `Records`, `Submissions`, `Events` y `Setup`.
- Reintento manual de submissions `FAILED` desde UI.
- Captura de NIF/CIF (`tax_number`) en empresa y cliente para soporte fiscal.
- Abilities/policy propias de VERI*FACTU (`view-verifactu`, `manage-verifactu`).

## Que no hace todavia

- No esta validado como integracion final homologada frente a AEAT.
- No envia nada a Hacienda real mientras no se configure y active conscientemente un modo AEAT operativo.
- No tiene todavia flujo completo de firma/certificado validado extremo a extremo contra AEAT.
- No implementa aun rectificacion por diferencias con importes negativos o parciales.
- No tiene todavia una pantalla admin de edicion segura de configuracion VERI*FACTU; la UI actual es sobre todo de observabilidad.
- No hay todavia declaracion responsable operativa cerrada ni checklist final de cumplimiento por release.

## Datos de prueba ya generados en esta copia local

- Se han creado registros `verifactu_*` reales en la base local.
- Se emitio fiscalmente al menos una factura local para validacion.
- Se genero al menos una rectificativa de prueba.
- Se procesaron submissions `stub` aceptadas de prueba.
- Se probo al menos un retry de submission `FAILED` de forma local.

Esto significa que la copia local ya no es una restauracion virgen. Si se quiere maxima limpieza, conviene clonar la base antes de seguir con validaciones fuertes.

## Puntos de entrada importantes

- Arquitectura tecnica: [docs/verifactu-architecture.md](docs/verifactu-architecture.md)
- Roadmap operativo: [docs/verifactu-roadmap.md](docs/verifactu-roadmap.md)
- Dashboard visible: `/admin/verifactu`
- Config principal: `config/verifactu.php`
- Commit base del fork: `ad81b53 chore: baseline restored crater fork with verifactu foundation`

## Cambios recientes que no estaban bien reflejados

- Se anadio una capa de drivers VERI*FACTU con resolucion por modo en `VerifactuDriverManager`.
- Se incorporo infraestructura AEAT temprana:
  - `AeatHttpClient`
  - `AeatResponseParser`
  - `VerifactuXmlBuilder`
  - `VerifactuHuellaComputer`
  - `VerifactuPreSubmissionValidator`
- `VerifactuService` ya valida antes de expedir y no crea el registro si faltan datos fiscales minimos.
- `VerifactuRecordBuilder` ahora snapshottea `tax_number` de empresa/cliente y calcula `tipo_factura` + huella.
- `verifactu_submissions` pasa a contemplar XML y CSV AEAT.
- La UI ya tiene observabilidad bastante completa: records, submissions, events, setup y detalle de declaraciones.

## Recomendacion para retomar trabajo

1. Confirmar el modo visible en `/admin/verifactu` y en `config/verifactu.php`.
2. Revisar los cambios pendientes sin commit posteriores al baseline `ad81b53`.
3. Agrupar esos cambios en commits pequenos y legibles antes de seguir ampliando funcionalidad.
