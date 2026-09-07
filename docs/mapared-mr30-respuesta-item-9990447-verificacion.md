# MR-30 — Contingencia activada: reporte formal contra la épica (#936) — item #9990447

## Contexto

`#936` es la épica MAPA DE RED. `#967` (MR-29) congeló una rúbrica de 6 criterios para decidir
si el módulo nuevo (`mapared_*`) reemplaza al viejo (Mapas, `map_*`). `#963` (MR-27) llenó esa
rúbrica con evidencia real medida contra la BD de dev y los 6 criterios reprueban o no son
medibles. `#968` (MR-30) es la contingencia que se ejecuta cuando la rúbrica reprueba, y su DoD
exige un item `[RESPUESTA]` abierto contra `#936` con causa raíz por criterio — este item
(`#9990447`) es ese reporte formal.

## Verificación (re-confirmada en esta vuelta, 2026-09-07)

| Claim del reporte | Verificado contra | Resultado |
|---|---|---|
| MR-28 (`#964`, retiro del módulo perdedor) NO se ejecutó | `module_registry` | `addon-mapas` (id=16) y `addon-mapa-red` (id=51) ambos `active=1` |
| Sidebar etiqueta "MAPA DE RED" como beta | `app/Modules/Addons/MapaRed/module.json` + `sidebar.blade.php:237-238,254-255` | `"badge": "beta"` presente en el module.json; el blade renderiza `<span class="badge bg-warning text-dark rounded-pill ms-1">{{ strtoupper($mod['badge']) }}</span>` cuando `$mod['badge']` no está vacío. Ya mergeado a `main` (commit `12424cbf`, integrado vía `9d629b84` — commit **anterior** a este item, bajo `#968`) |
| Criterio 1 (paridad de conteo) reprueba | `map_devices` vs `mapared_devices` | `map_devices=8785` vs `mapared_devices=9` — brecha confirmada |
| `#941` (MR-05, causa raíz de 4/6 criterios) sigue sin ejecutarse | `roadmap_items` | `aprobado_revisor`, sin `merge_commit` |
| `#952` (MR-16, causa raíz criterio 3) sigue sin ejecutarse | `roadmap_items` | `requiere_irving`, sin `merge_commit` |
| `#9990439` (MR-24e, causa raíz criterio 4) sigue sin ejecutarse | `roadmap_items` | `requiere_irving`, sin `merge_commit` |
| `#9990446` ya pide la ratificación de no retirar Mapas / activar MR-30 | `roadmap_items` | Existe, `requiere_irving`, pendiente de Irving |
| `#964` (MR-28, retiro) sigue sin ejecutarse (correcto — es lo que pide la contingencia) | `roadmap_items` | `aprobado_irving`, sin `merge_commit` |

Todos los datos citados en el reporte original coinciden con el estado real de la BD y el código.
No se encontró ninguna inconsistencia.

## Qué hace este item

Es un item de tipo **reporte/respuesta**, no de código: documenta formalmente contra la épica
`#936` que la contingencia MR-30 quedó activada (6/6 criterios de la rúbrica de MR-29 reprueban o
no son medibles), con causa raíz por criterio, y dónde vive cada causa raíz (`#941`/`#952`/
`#9990439`). El trabajo de código que el reporte describe (etiqueta beta en el sidebar) **ya se
hizo y se mergeó bajo el item `#968`** — no se duplica aquí.

## Qué NO se hace en este item (y por qué)

- No se ejecuta `#941`/`#952`/`#9990439` — están fuera de alcance de MR-30 (que es la
  contingencia, no la vía para adelantar el trabajo pendiente de otros items de la épica).
- No se retira ni se oculta ningún módulo — prohibido explícitamente por el prompt de MR-30.
- No se genera una segunda pregunta de ratificación — `#9990446` ya la pidió.

## Decisión pendiente para Irving

Ninguna nueva en este item — la ratificación ya está pedida en `#9990446`. Este item queda como
reporte colgando de la épica para que Irving lo vea, y puede cerrarlo junto con `#9990446` en el
mismo acto si lo desea.

## Cómo verlo en la UI (DoD visual)

`/administracion` → sidebar izquierdo → sección de módulos dinámicos: aparecen tanto **Mapas**
como **MAPA DE RED**, este último con la etiqueta `BETA` (pill amarillo) junto al nombre.
