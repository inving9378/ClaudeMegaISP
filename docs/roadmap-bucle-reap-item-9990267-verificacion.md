# Item #9990267 — Fase 1b-ii (SubItemCommand: detección de ciclos vía DependenciaGate::tieneCiclo()) — bucle reap sobre paraguas ya descompuesto (RESUELTO — se completa el cierre-intento faltante)

**Fecha:** 2026-09-04 · **Worktree:** wt-5

## Qué pedía el item

#9990267 (sub-item de seguimiento de #9990263) pedía, dentro de `SubItemCommand.php`, construir el
grafo `$edges` de todos los hermanos bajo el mismo `origen_item_id` (usando
`DependenciaGate::dependeDeDe()`, ya existente) + la arista nueva del `--depende-de` recién
validado, llamar a `DependenciaGate::tieneCiclo()` y, si detecta ciclo, bloquear la creación del
sub-item con un mensaje claro usando `caminoCiclo()`. Traía como precondición explícita que la Fase
1b-i (#9990266, `--depende-de` + `position` + validación de existencia de posiciones) estuviera
mergeada en `main` antes de arrancar.

## Lo que ya había pasado antes de esta vuelta

Una vuelta previa (**wt-6**, 2026-09-04 09:14) ya hizo lo correcto:

- Corrió `circuito:cabida` y obtuvo **NO CABE** (histórico ~573s > umbral 480s).
- Descompuso el trabajo en 2 fases ejecutables (`origen_item_id=9990267`):
  - **#9990278** — Fase 1b-ii-a: construir `$edges` de hermanos + bloquear con
    `tieneCiclo`/`caminoCiclo`, antes de llamar a `RoadmapIntakeService::crear()`.
  - **#9990279** — Fase 1b-ii-b: verificación manual (ciclo directo A↔B, ciclo indirecto A→B→C→A, y
    caso sin ciclo que sí crea), depende de que 1b-ii-a esté commiteada.

Pero esa vuelta **nunca intentó cerrar** al padre tras crear los 2 sub-items — el item quedó
`en_progreso` colgado con el `worker_sid` de esa sesión. El reaper (`reaper-rapido`) vio el slot
`wt-6` libre (ninguna vuelta corriendo ahí) y lo re-encoló como huérfano (intento 1/3, log
`huerfano_reencolado`), devolviéndolo a `aprobado_revisor`, y el pool lo repartió de nuevo (esta
vez a wt-5) sin que hubiera trabajo propio que hacer.

## Verificado en esta vuelta

Los 2 hijos siguen intactos, sin reclamar:

| id | estado_aprobacion | worker_sid | claimed_at |
|----|--------------------|------------|------------|
| #9990278 | aprobado_revisor | — | — |
| #9990279 | aprobado_revisor | — | — |

La descomposición original (1b-ii-a construcción del grafo + bloqueo / 1b-ii-b verificación
manual, con la dependencia de orden ya declarada entre ambas) seguía siendo correcta — nadie más
la tocó.

## Corrección aplicada

Esta vuelta ejecuta el intento de cierre que faltaba: al intentar `estado_aprobacion='completado'`
sobre #9990267, el guard **(2b) PARAGUAS** (`app/Modules/Addons/Roadmap/Models/RoadmapItem.php`,
~328-359) lo reenrutó a `aprobado_irving` + `excluir_pool_automatico=true` (worker_sid/claimed_at
liberados, evento `paraguas_abierto` en el log), sacándolo del pool/reaper hasta que el hook de
cierre en cascada (`RoadmapItem.php:497-537`) lo complete solo cuando #9990278 y #9990279 cierren
ambos.

**Sin cambio de código de negocio** — el trabajo técnico real (grafo de ciclos en
`SubItemCommand.php` y su verificación manual) sigue en esos dos sub-items, pendientes de que una
terminal los reclame en el orden ya declarado (1b-ii-a antes que 1b-ii-b).
