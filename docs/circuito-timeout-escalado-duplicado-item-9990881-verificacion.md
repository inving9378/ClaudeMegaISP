# Item #9990881 (CIRC-01) — duplicado exacto de #9990855, ya resuelto (RESUELTO — sin cambio de código)

## Contexto

`#9990881` ("CIRC-01: Fix del escalado por timeout que manda a `requiere_irving` items ya
parqueados por el guard de paraguas (+ saneamiento de los 4 atorados)") nació como sub-item de
`#9990874` ("CIRC-00/doc origen", 2026-09-11 18:35:14) con el mismo título, la misma descripción
del bug, los mismos 4 items de ejemplo (#9990801/#9990714/#9990776/#9990810) y el mismo prompt de
4 fases (diagnóstico → fix de 3 guardas → saneamiento → candado de regresión) que el item
`#9990855` ("Fix del escalado por timeout: no escalar a `requiere_irving` items ya parqueados por
el guard de paraguas (+ saneamiento de los 4 atorados)", creado 2026-09-11 18:15:11 — **20
minutos antes**).

`circuito:cabida 9990881` devolvió NO CABE (`historico_excede_umbral`). Antes de descomponer el
prompt en sub-items nuevos, se verificó si el trabajo ya existía — y sí: es el mismo bug, el mismo
fix, y ya está mergeado a `main`.

## Verificado

**El fix (Fases 1-2 del prompt) ya está en `main`.** `app/Modules/Addons/Roadmap/Console/
ParquearTimeoutCommand.php` (líneas 93-171) tiene, en ese orden:

1. **Guarda de estado** (líneas 93-133): si `estado_aprobacion !== 'en_progreso'` al llegar el
   manejador de timeout, no escala — registra `timeout_no_escalado` con el actor y evento que ya
   resolvió el item durante la misma vuelta. Comentario explícito referenciando `#9990801`/
   `#9990714`/`#9990776`/`#9990810` como el incidente que motivó la guarda.
2. **Guarda de paraguas** (líneas 135-171): si `$item->tieneSubItemsAbiertos()`, no escala —
   parquea como `aprobado_irving` + `excluir_pool_automatico=true` y libera `worker_sid`/
   `claimed_at`.
3. **Medición de `commits_rama`** (líneas 218-231): ya confirmado que se mide sobre
   `items.branch` (la rama de trabajo real del item vía `RoadmapCircuitoService::commitsDeRama()`),
   no sobre una rama efímera de cierre — esa rama efímera nunca existió en el código; el
   `commits_rama:0` de `#9990810` salía de leer el dato DESPUÉS de que la decisión ya estaba
   tomada, lo que las dos guardas de arriba cierran de raíz.

Commit del fix: `a4b200ac`, integrado a `main` vía merge `ff72b2a3391e6e60c11bb53bf03f3929b5bd0034`
(ver `git log --oneline | grep ff72b2a3`, visible también en el log de commits recientes de este
worktree).

**El saneamiento (Fase 3) ya se ejecutó.** Reverificado contra la BD real en esta vuelta
(2026-09-11 18:40), estado actual de los 4 items:

| Item | `estado_aprobacion` | `excluir_pool_automatico` | `merge_commit` |
|---|---|---|---|
| #9990801 | `completado` | `1` | `e7969164` |
| #9990714 | `completado` | `1` | `385d5030` |
| #9990776 | `aprobado_irving` | `1` | `45cc8a47` |
| #9990810 | `aprobado_irving` | `1` | `cee7aea6` |

Ninguno está en `requiere_irving`. Los dos que siguen en `aprobado_irving` es el estado CORRECTO
para un paraguas parqueado (cierran por cascada cuando su propio sub-item abierto cierre) — no es
el bug, es el comportamiento esperado del guard.

El barrido histórico (Fase 3, punto 8 del prompt — buscar más casos vivos del mismo patrón) ya se
ejecutó en la vuelta de `#9990855`: 0 casos adicionales encontrados fuera de esos 4.

**Lo único pendiente (Fase 4 — candado de regresión) ya está descompuesto y en curso**, como
sub-item **`#9990880`** ("Fase 4 de #9990855: candado de regresión para ParquearTimeoutCommand (3
pruebas)"), `en_progreso`, reclamado por `wt-2`. No se toca (un item = un dueño, #341).

## Por qué pasó (carrera de timing)

Mismo patrón ya documentado varias veces en este repo (`docs/inventario-seguimiento-218-item-733-
verificacion.md`, `docs/circuito-candado-esquema-storage-path-item-9990003-verificacion.md`,
`docs/backups-purge-test-guard-path-item-9990353-verificacion.md`): el item origen (`#9990874`,
"CIRC-00/doc origen") generó dos sub-items independientes con el mismo contenido — probablemente
porque el documento fuente listaba este bug como un punto separado y el generador no comprobó si
ya existía un item vivo cubriéndolo. `#9990855` llegó primero, se trabajó y casi se cerró (Fases
1-3 completas) mientras `#9990881` seguía sin reclamar.

## Veredicto

**RESUELTO — sin cambio de código.** El trabajo que pedía `#9990881` ya está hecho y mergeado a
`main` bajo `#9990855`; lo único que falta (el candado de regresión) ya tiene su propio dueño en
`#9990880`. No se crean sub-items nuevos ni se duplica el trabajo.
