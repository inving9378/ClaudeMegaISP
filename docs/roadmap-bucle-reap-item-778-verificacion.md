# Item #778 — bucle reap/re-encolado sobre un paraguas ya descompuesto (RESUELTO — se cierra el paraguas correctamente)

## Contexto

#778 ("P0 no-mergeados auditoría — lote A (24 ids: 186-866)", sub-item de #743/#624) llegó a
`circuito:cabida` con veredicto **NO CABE** en una vuelta previa (`wt-2`, 2026-08-29 ~10:33): ya
había timeouteado antes a 600s con 0 commits al intentar los 24 ids juntos. Esa sesión hizo lo
correcto: en vez de reintentar el lote completo, lo descompuso en tres sub-items propios —

- **#802** — parte 1/2 (12 ids: 186, 254, 308, 463, 485, 528, 541, 542, 544, 559, 566, 595).
- **#803** — parte 2/2 (12 ids: 794, 795, 811, 820, 835, 836, 842, 850, 853, 857, 862, 866).
- **#804** — consolidación final (merge part1+part2 → `docs/roadmap-p0-auditoria-item624-batchA.json`).

— sin picar código ni crear rama, según el protocolo NO CABE, y cerró su vuelta con el META
(`ejecuto:false`). Hasta aquí, correcto.

## El bucle

`#778` volvió a aparecer reclamado (`en_progreso`) para esta nueva sesión `wt-2`, con
`reap_count=1` y una entrada `reaper-rapido` en el log (2026-08-29 10:38): "el slot wt-2 está libre
(ninguna vuelta corriendo ahí): reclamo huérfano → re-encolado". `circuito:cabida` esta vez devolvió
`CABE [ya_descompuesto]` — la señal de que no hay que volver a descomponer, solo completar el cierre.

Verificado en la BD: **#802, #803 y #804 siguen existiendo, `aprobado_revisor`, `status=pending`,
`worker_sid` vacío** — la descomposición de la sesión anterior sigue intacta, nadie los tocó.

**Causa raíz del bucle (idéntica a la documentada para #738 y #745):** `RoadmapItem` tiene un guard
(`saving`, bloque "(2b) PARAGUAS", `app/Modules/Addons/Roadmap/Models/RoadmapItem.php:301-326`) que
SOLO aparca correctamente un paraguas (`estado_aprobacion → aprobado_irving` +
`excluir_pool_automatico = true`) cuando algo **intenta activamente poner
`estado_aprobacion = 'completado'`** sobre el padre y detecta hijos abiertos. La sesión anterior
nunca hizo ese intento — `circuito:sub-item` (que solo crea el hijo, no toca al padre) fue lo último
que corrió antes de terminar la vuelta con el META. El padre se quedó en `en_progreso` con el
`worker_sid` de esa sesión, sin nadie liberando el claim.

El **reaper** (`circuito:reap-stuck`, vía rápida por slot libre) solo sabe hacer una cosa con un
`en_progreso` huérfano: re-encolarlo a su estado previo (`aprobado_revisor`) para que el pool lo
vuelva a repartir — no sabe que ya fue descompuesto, porque `tieneSubItemsAbiertos()` nunca llegó a
evaluarse (eso solo pasa dentro del guard de arriba, que nadie disparó). Resultado: cada vuelta que
reclama #778 encuentra el mismo estado (ya descompuesto, sin trabajo propio), no lo cierra
explícitamente, y dispara el mismo ciclo la próxima vez que el reaper lo libera.

## Corrección aplicada en esta vuelta

Sin tocar #802/#803/#804 (no son de esta sesión — un item, un dueño) y sin re-descomponer (ya está
hecho), esta vuelta simplemente **completa el paso que faltó**: intentar cerrar #778 a
`completado`. El guard (2b) del modelo detecta los 3 hijos abiertos y lo reenruta solo —
`estado_aprobacion = aprobado_irving`, `excluir_pool_automatico = true`, log `paraguas_abierto`.
Con `excluir_pool_automatico = true` el reaper y el pool dejan de tocarlo (el reaper solo opera
sobre `en_progreso`; el pool no reparte ítems con esa bandera). Cuando #802, #803 y #804 cierren,
el hook `saved` de "PARAGUAS — cierre en cascada" (`RoadmapItem.php:459-491`) encuentra al padre en
`aprobado_irving` sin hijos abiertos y lo completa solo — eso ya es código existente, no se tocó.

## Verificación

- `SELECT` directo (tinker) confirmó #802/#803/#804 siguen `aprobado_revisor`/`pending`/sin
  reclamar (nadie los tocó desde la descomposición original).
- Tras el intento de cierre de esta vuelta: `#778.estado_aprobacion == 'aprobado_irving'`,
  `excluir_pool_automatico == true`, log con evento `paraguas_abierto` y el conteo real de hijos
  abiertos (3).
- El trabajo técnico real (procesar los 24 ids y consolidar el JSON) sigue intacto y pendiente en
  #802, #803 y #804 — este item no lo duplica ni lo adelanta.

## Sin cambio de código de producto

Este cierre no toca código de negocio ni el motor de compensación/permisos/dinero. Solo ejecuta,
por primera vez para #778, el paso de cierre-intento que el propio guard de paraguas del sistema
ya esperaba — mismo patrón que #738 y #745, un nivel más abajo en la jerarquía de #624.
