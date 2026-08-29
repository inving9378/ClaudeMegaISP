# Item #752 — Doc. Corporativa Fase 3.3 (mapa Leaflet + formularios) — RESUELTO vía sub-items

**Fecha:** 2026-08-29 · **Sesión:** wt-2

## Contexto

El item #752 pedía: (1) mapa Leaflet (npm, sin CDN, tiles OSM) para los conceptos de
`dc_activos` con `config.mapa === true` (torres, postería, fibra óptica instalada, redes
troncales, centros de distribución, almacenes y bodegas), y (2) formularios de alta/edición
para `dc_activos`, `dc_activos_digitales` (titular obligatorio + aviso de titularidad a
regularizar) y `dc_inventario_accesos` (campo credencial SIEMPRE de solo lectura con
asteriscos + leyenda del backend, nunca un input editable).

Este item ya había sido descompuesto por una sesión previa (wt-2, 2026-08-29 03:13) en dos
sub-items enfocados: **#783** (Fase 3.3a — mapa) y **#784** (Fase 3.3b — formularios), tras
un `circuito:cabida` = NO CABE (2 timeouts previos).

## Verificación (esta vuelta)

Ambos sub-items ya están `completado` y su trabajo ya está en `main`:

- **#783** → merge `b395e3d7` (rama `circuito/item-783-doc-corporativa-fase-33a-mapa-leafle`).
- **#784** → cerrado sin código nuevo el 2026-08-29 12:14 (sesión wt-1): el propio commit de
  #783 (`04ff6494`, mensaje "cubre los sub-items #783 y #784") ya traía completo también el
  trabajo de #784.

Confirmado contra el código real en `main` (no solo contra el texto de los items):

- `resources/js/components/module/documentacion-corporativa/DcActivosMapa.vue` existe (mapa
  Leaflet vía npm, `L.circleMarker`, tiles OSM, mismo patrón que `Flotas/FleetMap.vue`).
- `DcExpediente.vue`: botón "Ver mapa" (línea ~335) + `esMapaGestionable()` (metricas.mapa) +
  `TABLAS_CON_CRUD` extendido con `dc_activos`, `dc_activos_digitales`,
  `dc_inventario_accesos` (línea ~614-618).
- `DcRegistros.vue`: `TABLA_A_RECURSO` mapea los 3 recursos nuevos (línea ~239-241); configs
  `activos_digitales`/`inventario_accesos` con campo tipo `solo_lectura` para credencial y
  banner de `titularidad_a_regularizar` (línea ~75).
- Backend `RegistroEstructuradoController.php`: recursos `activos`/`activos_digitales`/
  `inventario_accesos` con su apartado y reglas (línea ~63-65, 178-213).
- `CompletitudService::resumirConceptos` sigue exponiendo `confidencialidad` por concepto
  (línea ~124) — intacto, sin tocar, tal como pedía el item.
- `bash deploy/circuito/npm-build.sh` compila OK (verificado esta vuelta).

## Resolución

Sin código nuevo que escribir en #752: el trabajo completo de sus 2 sub-items ya está en
`main`. Se cierra el padre como paraguas resuelto, referenciando el merge de #783
(`b395e3d7`) como evidencia.

**Pendiente heredado (no de esta vuelta):** validación visual de Irving en
`/documentacion-corporativa` (mapa con torres/postería reales, formularios de alta,
asteriscos+leyenda de credencial) — un ejecutor on-box no navega el navegador.
