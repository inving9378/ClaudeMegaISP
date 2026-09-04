# Item #917 — Circuito CC #911 Fase 6 (verificación con números del aflojo) — bucle reap sobre paraguas ya descompuesto

**Fecha:** 2026-09-04
**Item:** #917 (sub-item de seguimiento de #911)

## Hallazgo

Mismo patrón que #738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012 (ver
CLAUDE.md, sección "HOJA DE RUTA"): un item descompuesto en sub-items nunca llegó a *intentar*
su propio cierre, así que el guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS",
~301-332) nunca se disparó. El item se quedó `en_progreso` colgado con el `worker_sid` de la
sesión que lo descompuso; al morir esa sesión, `soltar-claim` lo devolvió a `aprobado_revisor`
(log: `claim_liberado_al_morir_la_vuelta`), y el pool lo repartió de nuevo (a mí, `wt-1`) sin que
hubiera trabajo propio pendiente — todo el trabajo real ya estaba correctamente descompuesto.

Una vuelta previa (`wt-2`, 2026-09-03 22:40) ya había hecho el análisis correcto: #917 depende de
que la Fase 5 (#916) esté integrada y corriendo en vivo varias horas para poder medir los 4
números (ocupación, colisiones, vueltas perdidas, incidentes de merge no detectados). Confirmó que
la ventana real ya estaba disponible (perilla del aflojo en 2 desde el commit `c1ee65f1`,
2026-09-03 15:53 -0600, ~7h de operación acumulada al momento de descomponer) y corrió
`circuito:cabida` (NO CABE, histórico ~501s del módulo Roadmap/Circuito CC). Descompuso el trabajo
en:

- **#9990195** — Fase 6a: métricas 1+2 del aflojo (ocupación media, timing de colisiones)
- **#9990196** — Fase 6b: métricas 3+4 del aflojo (vueltas perdidas vs. ganancia, merge no detectado)
- **#9990197** — Fase 6c: redactar `docs/circuito-verificacion-aflojo-item-911.md` (depende de 6a y 6b)

## Verificación en esta vuelta

Confirmado contra la BD de dev que los 3 sub-items siguen intactos y sin reclamar:

| Item | estado_aprobacion | worker_sid |
|------|--------------------|------------|
| #9990195 | `pendiente_revision` | `null` |
| #9990196 | `pendiente_revision` | `null` |
| #9990197 | `pendiente_revision` | `null` |

La descomposición original seguía siendo correcta — nadie más la tocó desde entonces.

## Corrección aplicada

Se ejecutó el intento de cierre faltante (`estado_aprobacion = 'completado'` vía tinker). El guard
de paraguas lo detectó (`tieneSubItemsAbiertos()` = true, 3 abiertos) y lo reenrutó automáticamente
a:

- `estado_aprobacion = 'aprobado_irving'`
- `excluir_pool_automatico = true`
- `worker_sid = null` / `claimed_at = null`
- Log: evento `paraguas_abierto`

Con esto #917 sale del pool/reaper de forma permanente. El hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo completará solo — sin intervención manual — cuando los tres hijos
(#9990195, #9990196, #9990197) cierren.

## Sin cambio de código de negocio

El trabajo técnico real (medir ocupación/colisiones/vueltas perdidas/incidentes de merge y redactar
la conclusión sobre la perilla del aflojo) sigue en #9990195/#9990196/#9990197, pendientes de que
el revisor los tríe (`pendiente_revision`).
