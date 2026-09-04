# Item #628 — Ramas huérfanas FASE A + Fase 1 (anti-bucle/metadata decisión) — verificación y limpieza

**Fecha:** 2026-08-29 · **Worker:** wt-4

## Contexto

Dos worktrees quedaron abandonados desde 2026-07-14, con commits que nunca llegaron a `main`:

- `/home/meganet/circuito-fase-a` — rama `circuito/fase-a-anti-bucle`, commit `df82f7e4`
  ("circuito FASE A: separar estados de item decidido + anti-bucle").
- `/home/meganet/megaisp-wt-fase1` — rama `circuito/fase1-metadata-decisiones`, commit `4f324a19`
  ("circuito Fase 1: metadata estructurada de decision + guards de pool").

Confirmado con `git merge-base --is-ancestor <sha> main` → **`NO ES ANCESTOR`** para los dos.
Ambos worktrees están limpios (`git status --short` vacío, sin cambios sin commitear).

## Verificación (`git diff main...<rama>` completo, no solo resumen de archivos)

### `circuito-fase-a` (9 archivos, +494/-5)

| Concepto de la rama | Equivalente en `main` hoy |
|---|---|
| Migración: enum `esperando_merge_irving` + 6 columnas (`excluir_pool_automatico`, `decision_resuelta`, `requiere_sesion_supervisada`, `bloqueado_por_bucle`, `escalacion_fingerprint`, `escalacion_count`) | `2026_07_14_193000_add_decision_metadata_to_roadmap_items.php` (misma fecha, más evolucionada: `esperando_merge_irving` es **columna boolean** en vez de valor de enum, idempotente por `hasColumn`, suma `decision_resumen/fuente/fecha`, `alcance_autorizado`, `fuera_de_alcance`, `siguiente_accion`, `motivo_bloqueo`, `escalaciones_fingerprint` JSON) |
| `RoadmapItem::escalacionFingerprint()` + `contarEscalacion()` + `ESCALACION_BUCLE_UMBRAL` | Presentes en `main` (`RoadmapItem.php:834-847`), misma lógica |
| `RoadmapItem::scopeElegibleParaPool()` | Presente en `main`, ahora `sqlElegibleParaPool()` (línea ~893) — "DEFINICIÓN ÚNICA del predicado de elegibilidad", con candado de coherencia (`PoolGuardCoherenceTest`) y comentario que referencia el bug real que esto arregló (#32: 8 aprobaciones mudas, #186: 32) |
| `cierreManualIrving` (propiedad transitoria, no persistida) | Presente en `main`, mismo nombre, mismo propósito (`RoadmapController.php:1421`) |
| `IntegrarItemCommand::ramaTieneContenido()` + ruteo a `esperando_merge_irving` cuando el nivel C trae contenido | Presente en `main`, **mismo nombre de método**, misma lógica (`IntegrarItemCommand.php:64,78,114`) |
| `DestrabeCommand` / `PriorizarSeguridadCommand` / claim atómico — excluir parqueados del pool | Presente en `main` vía `elegibleParaPool()` / `sqlElegibleParaPool()` en los mismos call sites |
| `FaseASelftestCommand` (200 líneas, fixtures en transacción revertida) | Sin equivalente 1:1, pero cubierto por suite real: `PoolGuardCoherenceTest`, `BloqueoBucleVigenteTest`, `RawWritesDontTouchBloqueoFlagsTest`, `DiagnosticoItemServiceTest` |

### `megaisp-wt-fase1` (6 archivos, +317/-10)

| Concepto de la rama | Equivalente en `main` hoy |
|---|---|
| Migración: 13 columnas de metadata de decisión | La misma migración `2026_07_14_193000_...` ya citada — **superset** exacto de las 13 columnas (mismos nombres: `decision_resuelta`, `decision_resumen`, `decision_fuente`, `decision_fecha`, `alcance_autorizado`, `fuera_de_alcance`, `siguiente_accion`, `requiere_sesion_supervisada`, `excluir_pool_automatico`, `bloqueado_por_bucle`, `motivo_bloqueo`, `escalaciones_fingerprint`, `esperando_merge_irving`) |
| `tieneAprobacionVigente()` (rename de `tieneDecisionVigenteDeIrving()`) | `main` conservó el nombre original `tieneDecisionVigenteDeIrving()` (línea 598) — el rename no se adoptó, pero la función y su semántica siguen intactas |
| `debeExcluirseDelPool()` + `scopeSinBloqueoDePool()` | No existen con ese nombre (grep sin resultados), pero el comportamiento equivalente (excluir del pool automático) lo cubre `elegibleParaPool()`/`sqlElegibleParaPool()`, que es el que de hecho está enganchado en `tomablePorCircuito()`, `DestrabeCommand`, `PriorizarSeguridadCommand` y el claim atómico — los mismos 4 call sites que esta rama tocaba |
| `PriorizarSeguridadCommand`: filtrar candidatos con decisión ya resuelta ANTES de generar el brief (no gastar IA) | `main` resuelve el mismo problema por otra vía: `elegibleParaPool()`/anti-rebote de Fase 0 ya excluye esos items del pool antes de llegar a priorización |
| `tests/Unit/Roadmap/DecisionMetadataHelpersTest.php` (puro unit, sin BD) | Cubierto por la suite real ya citada arriba |

## Conclusión

Ambas ramas están **100% superadas**: la migración real de `main` (misma fecha, `2026_07_14_193000`)
es un superset exacto de las columnas que las dos ramas proponían por separado, y toda la lógica de
negocio (anti-bucle, exclusión del pool, cierre manual, ruteo de nivel C sin auto-merge) ya vive en
`main` con nombres iguales o equivalentes, evolucionada por incidentes reales posteriores (#32, #186,
#921) y protegida por tests de coherencia dedicados que no existían en ninguna de las dos ramas
huérfanas. No hay nada que rescatar por cherry-pick.

**Acción:** se eliminaron ambos worktrees (`git worktree remove`) y ambas ramas (`git branch -D`):
- `/home/meganet/circuito-fase-a` — rama `circuito/fase-a-anti-bucle`
- `/home/meganet/megaisp-wt-fase1` — rama `circuito/fase1-metadata-decisiones`
