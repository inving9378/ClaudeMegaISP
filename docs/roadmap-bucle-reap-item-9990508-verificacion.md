# Item #9990508 — bucle reap sobre paraguas ya descompuesto (MR-22 Fase 3, árbol derivado de 3 niveles)

## Contexto

`#9990508` ("MR-22 Fase 3 (retoma) - Implementar árbol derivado 3 niveles en panel lateral, tras
liberar colisión con MR-22 Fase 2") es un sub-item de seguimiento de `#9990459`. Tras varias
vueltas atascado (timeout por max-turns sin commits, límite de cuenta detectado ~90 veces
seguidas, un `soltar-claim` de `wt-5` por muerte de proceso) e Irving aprobándolo con las 4
respuestas de la pregunta estructurada, el propio log del item muestra que una vuelta previa
(`wt-3`, 2026-09-07 10:41) ya hizo lo correcto:

1. Corrió `circuito:cabida` → **NO CABE** (`ya_timeouteo_antes`).
2. Confirmó que la dependencia bloqueante `#9990458` (MR-22 Fase 2 — Panel de capas encendibles)
   ya estaba mergeada a `main` (`da0185b4`).
3. Descompuso el trabajo en 3 fases secuenciales encadenadas:
   - **#9990515** — Fase 3a: función pura `deriveThreeLevelTree` + estado de nodo seleccionado.
   - **#9990516** — Fase 3b: sección colapsable del árbol derivado en el panel lateral (depende
     de 3a).
   - **#9990517** — Fase 3c: botón toggle en la top bar + persistencia en `localStorage`
     (depende de 3b).
4. Dejó la nota de decisión en `comentarios_claude`.

Pero el proceso **murió antes de intentar el cierre** del padre: el log registra a las `10:41:49`
el evento `claim_liberado_al_morir_la_vuelta` (`soltar-claim`, sid `wt-3`) — "La vuelta de wt-3
terminó sin cerrar el item (muerte del proceso: kill, OOM o freno a media vuelta)" — el claim se
liberó y el item volvió a `aprobado_revisor` sin que nadie hubiera intentado cerrarlo. El pool lo
repartió de nuevo (a `wt-3`, esta vuelta) sin que hubiera trabajo propio que hacer: la misma
familia de bug ya documentada en
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#852`/`#905`/`#878`/`#906`/`#907`/`#924`/`#9990012`/
`#917`/`#910`/`#936`/`#9990422`/`#9990412`/`#9990484`/`#9990507` (y otros).

## Verificación

Consultados los 3 hijos (`origen_item_id=9990508`) directo en BD:

| Item | Título | Estado | Worker | merge_commit |
|------|--------|--------|--------|---------------|
| #9990515 | Fase 3a — función `deriveThreeLevelTree` + estado de nodo seleccionado | `pendiente_revision` | (sin reclamar) | — |
| #9990516 | Fase 3b — sección colapsable del árbol derivado en el panel lateral | `pendiente_revision` | (sin reclamar) | — |
| #9990517 | Fase 3c — botón toggle en top bar + persistencia localStorage | `pendiente_revision` | (sin reclamar) | — |

Los 3 siguen intactos, sin reclamar por ninguna terminal — la descomposición original sigue
siendo correcta y completa, nadie más la tocó ni hace falta re-descomponerla.

## Corrección

Esta vuelta ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` vía
tinker) sobre `#9990508`. El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b)
PARAGUAS") lo reenrutó a `aprobado_irving` + `excluir_pool_automatico=true`, liberando
`worker_sid`/`claimed_at` y dejando en el log tanto el evento `paraguas_abierto` ("le quedan 3
sub-item(s) abierto(s)") como el cambio de flags, sacándolo del pool/reaper hasta que el hook de
cierre en cascada (`RoadmapItem.php:459-491` aprox.) lo complete solo cuando `#9990515`,
`#9990516` y `#9990517` cierren los tres.

## Resultado

Sin cambio de código de negocio. El trabajo técnico real de MR-22 Fase 3 (función de derivación
del árbol, sección colapsable en el panel lateral, toggle + localStorage) sigue en `#9990515`,
`#9990516` y `#9990517`, todos `pendiente_revision` y sin reclamar por ninguna terminal.
