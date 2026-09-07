# MR-27 (#963) — Comparativa formal Mapas vs MAPA DE RED — verificación

Fecha de medición: 2026-09-06/07, DEV (`192.168.105.11`). Rúbrica usada: **MR-29 (#967)**, congelada,
sin modificar umbrales ni filas. MR-29 en sí **no está operacionalizada** (no tiene `comentarios_claude`
con "con qué se mide" por criterio — el item sigue `aprobado_irving`/sin reclamar), así que este item
determinó la fuente de medición de cada fila directamente contra el sistema real, sin tocar la tabla
congelada de la `description` de MR-29.

## Resultado en una línea

**El módulo nuevo NO gana ningún criterio hoy** (no solo "no todos"): la causa raíz común a los 6 es que
**MR-05 (#941, comando de copia legacy→mapared_\*) nunca se ejecutó de verdad** — sigue `aprobado_revisor`,
sin correr. `mapared_*` no tiene datos reales de producción, solo un puñado de filas de prueba dejadas por
la validación manual de MR-06b-5 (9 `mapared_devices` tipo "charole", 5 `mapared_devices_ports`, **0** en
`mapared_layers`/`mapared_fibers`/`mapared_hilos`/`mapared_empalmes`/`mapared_splitters`/
`mapared_enlaces_servicio`/`mapared_cables`/`mapared_proyects`). Por la regla de desempate de MR-29
("si el módulo nuevo no gana en TODOS los criterios, no se retira el viejo y MR-28 no se ejecuta"), el
resultado hoy es inequívoco: **MR-28 no se ejecuta, se activa MR-30 (contingencia), los dos módulos
siguen conviviendo.**

Esto no es un defecto de diseño de MAPA DE RED — el motor que sí se construyó (`OpticalBudgetService`,
snap, ocupación de puertos, salud NAP, ficha lateral, etc.) está verificado y funciona sobre datos
sintéticos en varios items ya cerrados (MR-13/14/18/20/23...). El bloqueo es un solo paso pendiente
(MR-05 real) que no se ha corrido, no una limitación arquitectónica del módulo nuevo.

## Tabla (rúbrica MR-29, 6 criterios)

| # | Criterio | Umbral | Medido HOY | Resultado | Evidencia |
|---|---|---|---|---|---|
| 1 | Paridad de conteo por tipo de elemento `Mapas` vs `mapared_*` | Tolerancia 0 (coincide exacto) | Ver conteo lado a lado abajo | ❌ **REPRUEBA** (brecha de 3 a 4 órdenes de magnitud) | Query `DB::table($tabla)->count()` en ambos esquemas, pegada abajo |
| 2 | Elementos huérfanos en el módulo nuevo | 0 | **5 de 5** `mapared_devices_ports` apuntan a `device_id=4`, que NO existe en `mapared_devices` (existen 5,6,7,29-34); **9 de 9** `mapared_devices` con `lat`/`lng` NULL | ❌ **REPRUEBA** | Query de huérfanos (join+whereNull) pegada abajo. Legacy equivalente = 0 huérfanos (no es un problema heredado del origen) |
| 3 | Trazo OLT→ONT en zona piloto `T-TULTITLAN-FO96-*` (100% de NAPs) | 100% | **0 NAPs evaluables** — `mapared_enlaces_servicio`, `mapared_hilos`, `mapared_empalmes`, `mapared_splitters`, `mapared_puertos` están en **0 filas**; el caminador (`OpticalBudgetService::trazarRuta()`, línea 59) existe pero no tiene ningún enlace de servicio real sobre el que caminar. MR-16 (#952) como item formal sigue `requiere_irving`, sin cerrar | ❌ **NO MEDIBLE — bloqueado por #941 (MR-05)** | `SELECT COUNT(*)` en las 5 tablas, todas en 0 |
| 4 | Alta de NAP en ≤3 pasos sin escribir el nombre a mano | ≤3 pasos, screenshot | Backend listo (`MR-24d` endpoint de alta rápida + `MR-24c` snap a 15m, ambos `completado`), pero **no existe componente de "modo dibujo"** en `resources/js/components/module/mapared/` (MR-24e sigue `requiere_irving`) — hoy la alta pasa por los formularios legacy portados mecánicamente (`BoxJunctionComponent.vue` etc.), no por un wizard de 3 pasos | ❌ **NO MEDIBLE — bloqueado por MR-24e (#9990439)**, requiere firma visual de Irving de todas formas | `find`/`grep` sobre `resources/js/components/module/mapared/`, sin match de modo-dibujo |
| 5 | Carga del mapa con todos los elementos de Tultitlán ≤3s | ≤3s, 3 corridas, screenshot con contador | **No comparable**: `mapared_layers` (la capa que dibuja el mapa) está en 0 filas — cargar un mapa vacío en <3s no prueba nada frente al legacy con 6,689 `map_layers` reales de toda la red | ❌ **NO MEDIBLE — bloqueado por #941 (MR-05)**, requiere firma visual de Irving de todas formas | Conteo `mapared_layers=0` vs `map_layers=6689` |
| 6 | Presupuesto óptico calculado vs RX real MultiOLT, ≤3dB en ≥20 ONUs | ≤3dB, ≥20 ONUs | `OpticalBudgetService::calcular()` y `rxRealDeMultiOlt()` existen y están verificados con trazos sintéticos (ver #954/#9990440), pero necesitan un `MapaRedEnlaceServicio` real — **0 filas** en `mapared_enlaces_servicio` | ❌ **NO MEDIBLE — bloqueado por #941 (MR-05)** (y de forma secundaria por MR-08/#944, catálogo de equipos, para TX/sensibilidad reales en vez de defaults GPON Clase B+ genéricos — ya señalado en #9990440) | `mapared_enlaces_servicio` count = 0 |

### Conteo lado a lado (criterio 1, medido 2026-09-06)

| Tabla legacy | Filas | Tabla `mapared_*` | Filas |
|---|---|---|---|
| `map_devices` | **8,785** | `mapared_devices` | **9** |
| `map_devices_ports` | **19,229** | `mapared_devices_ports` | **5** |
| `map_devices_ports_connections` | **8,740** | `mapared_devices_ports_connections` | **0** |
| `map_fibers` | **17,242** | `mapared_fibers` | **0** |
| `map_fibers_cut` | **3,596** | `mapared_fibers_cut` | **0** |
| `map_layers` | **6,689** | `mapared_layers` | **0** |
| `map_layers_routes` | **2,130** | `mapared_layers_routes` | **0** |
| `map_proyects` | **1,715** | `mapared_proyects` | **0** |

(Y las entidades nuevas del modelo fino — `mapared_hilos`, `mapared_empalmes`, `mapared_splitters`,
`mapared_cables`, `mapared_enlaces_servicio`, `mapared_correlativos` — todas en **0**.)

## Elementos que no se pudieron migrar

No aplica todavía: no es que haya elementos que **fallaron** al migrar — es que la migración masiva
(**MR-05, #941**) nunca se ejecutó. #941 sigue en `aprobado_revisor`. El único dato en `mapared_*` es
el que se insertó a mano para la validación visual puntual de MR-06b-5 (9 dispositivos tipo "charole"),
que además quedó con huérfanos (ver criterio 2) — no representa datos reales de Tultitlán.

## Tiempos de carga medidos

No se midieron por HTTP — no tiene sentido medir la velocidad de cargar un mapa vacío (`mapared_layers`
= 0 filas) contra uno con 6,689 elementos reales; el resultado no sería comparable ni honesto. Cuando
#941 corra, este criterio (y el 4) igual requieren firma visual de Irving por diseño de la rúbrica
("Quién firma: Irving (visual)") — no son medibles por terminal aunque haya datos.

## Prueba de trazo OLT→ONT en Tultitlán

No se pudo correr: `mapared_enlaces_servicio`/`mapared_hilos`/`mapared_empalmes`/`mapared_splitters`/
`mapared_puertos` están en 0 filas. El motor de trazo (`OpticalBudgetService::trazarRuta()`) existe y
ya fue verificado con datos sintéticos en items anteriores (ver #954), pero no hay ningún enlace de
servicio real de Tultitlán (ni de ninguna otra zona) sobre el que ejecutarlo hoy.

## Recomendación explícita

**No retirar el módulo Mapas. No ejecutar MR-28. Activar MR-30 (contingencia): ambos módulos siguen
conviviendo.** Esto no es una decisión de diseño — es la consecuencia mecánica de la regla de
desempate de MR-29 aplicada a evidencia real: 6 de 6 criterios reprueban o no son medibles hoy, y la
causa raíz de 5 de los 6 es la misma (MR-05/#941 sin correr).

**Para que MR-27 se pueda re-evaluar de verdad** (no repetir esta misma conclusión en el próximo ciclo):
1. Ejecutar #941 (MR-05, comando de copia idempotente) de forma completa, no solo el subset de prueba
   de MR-06b-5.
2. Confirmar que el backfill al modelo fino (MR-15/#951, ya `completado` como capacidad, pero sin datos
   reales que backfillear) corre después y puebla `hilos`/`empalmes`/`splitters`/`enlaces_servicio`.
3. Solo entonces recorrer los 6 criterios — los criterios 3 y 6 dependen de que existan enlaces de
   servicio reales; el 4 y 5 dependen además de MR-24e y de que Irving los firme visualmente.

Esta recomendación queda registrada en el item; la ratificación de "sí, activar MR-30 y no tocar MR-28
por ahora" es la decisión de Irving que pide el DoD de MR-27 — ver item de respuesta `[RESPUESTA] MR-27`.
