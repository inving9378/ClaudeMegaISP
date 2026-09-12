# Item #9990869 — CIRC-03 "Bandeja de decisiones real" — verificación (duplicado ya resuelto)

**Fecha:** 2026-09-11 · **Terminal:** wt-5 · **Resultado:** RESUELTO sin cambio de código (duplicado exacto de trabajo ya completado)

## Qué pedía el item

#9990869 ("CIRC-03 — Bandeja de decisiones real: separar 'espera decisión' de 'espera insumo
material'") es un sub-item de seguimiento de #9990854 ("corrección del Circuito CC — para
desatorar el flujo", posición 5 de su descomposición). Pedía:

1. Migración aditiva `motivo_espera` (enum nullable: `decision|credencial|hardware|
   sesion_presencial|autorizacion|frontera_produccion`).
2. Separar la bandeja de la Torre en dos listas: "Esperan tu decisión" / "Esperan un insumo
   tuyo" (agrupada por tipo de insumo).
3. Clasificar los 8 items conocidos (#683, #692, #283/#671, #9990075, #691, #718/#661,
   #9990571, #724) con su `motivo_espera` + `excluir_pool_automatico=true`.
4. Barrido de la cola `aprobado_irving` por texto equivalente ("bloqueado por", "requiere
   credencial", etc.), con tope de 15 antes de tocar en masa.
5. Que un item con `motivo_espera` distinto de `decision` no cuente como pendiente de Irving
   en las métricas del circuito.

## Hallazgo: es un duplicado textual de #9990886, ya completado

Existe un **segundo item, #9990886**, con título casi idéntico ("CIRC-03: Bandeja de decisiones
real — separar espera decisión de espera insumo material"), spec y tabla de 8 items **idénticos**
palabra por palabra, generado independientemente como sub-item de otro paraguas de corrección del
circuito (cita "Depende de: CIRC-01 (#9990881)" en vez de "#9990854" — dos pasadas de auditoría
del circuito llegaron a la misma conclusión y crearon el mismo item por caminos separados).

**#9990886 ya está `completado` y mergeado** (`merge_commit=793a657d`, completado 2026-09-11
19:33), descompuesto en 3 sub-items que también cerraron:

| Sub-item | Fase | Estado | merge_commit |
|---|---|---|---|
| #9990904 | Fase A — migración aditiva `motivo_espera` | completado | `a94fd956` |
| #9990905 | Fase B — clasificar los 8 items + barrido | completado | `ea6705a8` |
| #9990906 | Fase C — Torre: separar bandeja + métricas | completado | `589e490d` |

## Verificación punto por punto (contra `main` real, no contra el reporte)

1. **Migración/columna** — `Schema::hasColumn('roadmap_items','motivo_espera')` = `true`.
   Commits `7c811799` (columna) + `ac262d94` (índice, #9990926).
2. **Split de la Torre** — `RoadmapItem::scopeBandejaDecision()` /
   `scopeBandejaInsumo()` (`app/Modules/Addons/Roadmap/Models/RoadmapItem.php:1938-1960`),
   consumidos por `RoadmapController` (`espera_decision`/`espera_insumo` en el resumen,
   `cola_espera_insumo` agrupado). Frontend: `TorreControl.vue` tiene la tarjeta
   "📦 Esperan un insumo tuyo" (línea ~350) con acordeón por `motivo_espera`, tinte ámbar,
   separada de la bandeja de decisión existente.
3. **Clasificación de los 8 items** — verificado en BD real, los 10 ids de la tabla (283, 661,
   671, 683, 691, 692, 718, 724, 9990075, 9990571) tienen `motivo_espera` poblado
   (`decision`×3, `credencial`×2, `hardware`×1, `sesion_presencial`×1, `autorizacion`×1,
   `frontera_produccion`×2) y `excluir_pool_automatico=1` en los 10.
4. **Barrido** — hecho en #9990905 (commit `2771a232` ajustó el barrido para quitar
   "frontera dura" por ser demasiado ruidoso — decisión ya tomada y verificada en esa vuelta).
5. **Métricas** — `RoadmapController::index()` calcula `espera_decision` vía `bandejaDecision()`
   y `espera_insumo` vía `bandejaInsumo()` por separado (líneas 964-965); el KPI "Requiere
   Irving" del panorama ya solo cuenta `bandejaDecision()`, confirmado en el propio
   `reporte_coloquial` de #9990906.

Todo el criterio de aceptación de #9990869 ("Irving abre la Torre y ve, separadas, las
decisiones que puede resolver escribiendo ahora mismo y la lista de cosas materiales que tiene
que conseguir") ya está satisfecho por el trabajo de #9990886 y sus 3 hijos.

## Conclusión

No hay nada que implementar: #9990869 pide exactamente lo que #9990886 ya construyó y verificó
(build con `npm-build.sh`, scopes probados en tinker). Cerrado como duplicado ya resuelto,
mismo patrón documentado repetidamente en `CLAUDE.md` (carrera de dos pasadas de auditoría que
generaron el mismo item de forma independiente). **Sin cambio de código.**
