# Item #9990931 (CIRC-01) — cuarto duplicado exacto de #9990855, ya resuelto (RESUELTO — sin cambio de código)

## Contexto

`#9990931` ("CIRC-01 · Fix del escalado por timeout que manda a `requiere_irving` items ya
parqueados por el guard de paraguas (+ saneamiento de los 4 atorados)") tiene título, descripción
del bug, los mismos 4 items de ejemplo (#9990801/#9990714/#9990776/#9990810) y el mismo prompt de
4 fases (diagnóstico → fix de 3 guardas → saneamiento → candado de regresión) que `#9990855`
(creado 2026-09-11 18:15:11, ya `completado`).

Es el **tercer** duplicado detectado de este mismo bug — antes ya se documentaron `#9990865`
(`docs/circuito-fix-timeout-paraguas-item-9990865-verificacion.md`) y `#9990881`
(`docs/circuito-timeout-escalado-duplicado-item-9990881-verificacion.md`), ambos con el mismo
veredicto: el trabajo ya existía bajo `#9990855` y no había nada que duplicar.

`circuito:cabida 9990931 --sid=wt-2` devolvió CABE (`sin_senal_de_riesgo`, histórico ~2700s). Antes
de tocar código se verificó si el trabajo ya existía — y sí, completo.

## Verificado

**El fix (Fases 1-2 del prompt) está en `main`.** `app/Modules/Addons/Roadmap/Console/
ParquearTimeoutCommand.php` tiene, en orden:

1. **Guarda de estado** (líneas ~93-133): si `estado_aprobacion !== 'en_progreso'` al llegar el
   manejador de timeout, no escala — registra `timeout_no_escalado` con el actor/evento que ya
   resolvió el item durante la misma vuelta.
2. **Guarda de paraguas** (líneas ~135-171): si `$item->tieneSubItemsAbiertos()`, no escala —
   parquea como `aprobado_irving` + `excluir_pool_automatico=true` y libera `worker_sid`/
   `claimed_at`.
3. **Medición de `commits_rama`** (líneas ~218-231): confirmado que se mide sobre `items.branch`
   (la rama de trabajo real del item, vía `RoadmapCircuitoService::commitsDeRama()`), no sobre una
   rama efímera de cierre — esa rama efímera nunca existió en el código.

Commit del fix: `a4b200ac`, integrado a `main` vía merge `ff72b2a3` (item `#9990855`).

**El saneamiento (Fase 3) está aplicado.** Reverificado contra la BD real en esta vuelta
(2026-09-12), estado actual de los 4 items:

| Item | `estado_aprobacion` | `excluir_pool_automatico` | `merge_commit` |
|---|---|---|---|
| #9990801 | `completado` | `1` | `e7969164` |
| #9990714 | `completado` | `1` | `385d5030` |
| #9990776 | `aprobado_irving` | `1` | `45cc8a47` |
| #9990810 | `aprobado_irving` | `1` | `cee7aea6` |

Ninguno está en `requiere_irving`. Los dos que siguen en `aprobado_irving` es el estado CORRECTO
de un paraguas parqueado (cierran por cascada cuando su propio sub-item abierto cierre) — no es el
bug, es el comportamiento esperado del guard.

El barrido histórico (Fase 3, punto 8 del prompt) ya se ejecutó en la vuelta de `#9990855`: 0 casos
adicionales encontrados fuera de esos 4.

**El candado de regresión (Fase 4) ya existe y pasa.** `tests/Feature/Roadmap/
ParquearTimeoutGuardsRegresionTest.php` (sub-item `#9990880`, `completado`, merge `cb50405f`) cubre
las 3 pruebas exigidas por el prompt (paraguas con sub-items abiertos no escala; item sin avance
sigue escalando igual que antes; item con commits reales en su rama se reanuda sin escalar).
Re-ejecutado en esta vuelta:

```
DB_DATABASE=megaisp_test APP_ENV=testing php artisan test tests/Feature/Roadmap/ParquearTimeoutGuardsRegresionTest.php
Tests:    3 passed (22 assertions)
```

(Nota de entorno, sin relación con el item: el shell de este worktree trae `DB_DATABASE=megaisp` y
`APP_ENV=local` exportados como variables de entorno reales, que pisan los `<env>` no forzados de
`phpunit.xml` — por eso `php artisan test` sin el override cae en la base `megaisp` y el guard de
`tests/GuardBaseDePruebas.php` aborta correctamente. Se sorteó con el override explícito en la
línea de comando; no se tocó ninguna config del repo.)

## Por qué pasó (carrera de timing)

Mismo patrón ya documentado varias veces en este repo (`#733`/`#741`/`#753`, `#9990003`,
`#9990353`, `#9990865`, `#9990881`): el bug de escalado por timeout se documentó como hallazgo
independiente más de una vez (probablemente por el mismo documento origen o auditoría repetida), y
cada copia nació como item nuevo sin comprobar que ya había uno vivo cubriéndolo. `#9990855` llegó
primero y ya se cerró completo (Fases 1-4) antes de que `#9990931` se reclamara.

## Veredicto

**RESUELTO — sin cambio de código.** Todo lo que pedía `#9990931` (guardas de timeout, saneamiento
de los 4 items, barrido histórico, candado de regresión) ya está hecho, mergeado a `main` y
verificado de nuevo en esta vuelta bajo `#9990855`/`#9990880`. No se crean sub-items nuevos ni se
duplica el trabajo.
