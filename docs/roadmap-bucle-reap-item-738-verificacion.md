# Item #738 — bucle reap/re-encolado sobre un paraguas ya descompuesto (RESUELTO — se cierra el paraguas correctamente)

## Contexto

#738 ("Deriva de esquema #216 — Fase 1: esquema de referencia desde migraciones en BD desechable")
llegó a `circuito:cabida` con veredicto **NO CABE** hace varias horas (2 corridas previas
timeouteadas sin commits). Una sesión `wt-1` anterior (2026-08-29 ~10:09) hizo lo correcto: en vez
de intentarlo todo de nuevo, descompuso el trabajo en dos sub-items propios —

- **#797** — Fase 1a: comando que vacía `megaisp_dryrun` y corre TODAS las migraciones desde cero.
- **#798** — Fase 1b: exportar el esquema resultante a `storage/schema/reference.sql`
  (`schema:build-reference`).

— y cerró su vuelta con el META (`ejecuto:false`), dejando el padre como "paraguas". Hasta aquí,
correcto.

## El bucle

`#738` volvió a aparecer reclamado (`en_progreso`) para una NUEVA sesión `wt-1` seis horas después,
con `reap_count=2` y dos entradas de `reaper-rapido` en el log (10:08 y 10:16) que lo re-encolaron a
`aprobado_revisor`. `circuito:cabida` esta vez devolvió `CABE [ya_descompuesto]` — que según el
propio comentario de `JarvisService::caberEnVuelta()` significa "no vuelvas a descomponer, el guard
de paraguas del modelo ya se encarga de que no se complete mientras tenga hijos abiertos".

Verificado en la BD: **#797 y #798 siguen existiendo, `aprobado_revisor`, `status=pending`,
`worker_sid` vacío** — la descomposición de la sesión anterior sigue intacta, nadie los tocó.

**Causa raíz del bucle:** `RoadmapItem` tiene un guard (`saving`, bloque "(2b) PARAGUAS",
`app/Modules/Addons/Roadmap/Models/RoadmapItem.php:301-326`) que SOLO aparca correctamente un
paraguas (`estado_aprobacion → aprobado_irving` + `excluir_pool_automatico = true`) cuando algo
**intenta activamente poner `estado_aprobacion = 'completado'`** sobre el padre y detecta hijos
abiertos. La sesión anterior nunca hizo ese intento — `circuito:sub-item` (que solo crea el hijo,
no toca al padre) fue lo último que corrió antes de terminar la vuelta con el META. El padre se
quedó en `en_progreso` con el `worker_sid` de esa sesión, sin nadie liberando el claim.

El **reaper** (`circuito:reap-stuck`, vía rápida por slot libre) solo sabe hacer una cosa con un
`en_progreso` huérfano: re-encolarlo a su estado previo (`aprobado_revisor`) para que el pool lo
vuelva a repartir — no sabe que ya fue descompuesto, porque `tieneSubItemsAbiertos()` nunca llegó a
evaluarse (eso solo pasa dentro del guard de arriba, que nadie disparó). Resultado: cada vuelta que
reclama #738 encuentra el mismo estado (ya descompuesto, sin trabajo propio), no lo cierra
explícitamente, y dispara el mismo ciclo la próxima vez que el reaper lo libera. Mismo patrón que
documentó #745 sobre #664, pero un nivel más abajo: ahí el paraguas SÍ estaba bien aparcado
(`aprobado_irving`) y el problema era que nadie despachaba a los hijos; aquí el paraguas nunca
llegó a aparcarse.

## Corrección aplicada en esta vuelta

Sin tocar #797 ni #798 (no son de esta sesión — un item, un dueño) y sin re-descomponer (ya está
hecho), esta vuelta simplemente **completa el paso que faltó**: intentar cerrar #738 a
`completado`. El guard (2b) del modelo detecta los 2 hijos abiertos y lo reenruta solo —
`estado_aprobacion = aprobado_irving`, `excluir_pool_automatico = true`, log `paraguas_abierto`.
Con `excluir_pool_automatico = true` el reaper y el pool dejan de tocarlo (el reaper solo opera
sobre `en_progreso`; el pool no reparte ítems con esa bandera). Cuando #797 y #798 cierren, el hook
`saved` de "PARAGUAS — cierre en cascada" (`RoadmapItem.php:459-491`) encuentra al padre en
`aprobado_irving` sin hijos abiertos y lo completa solo — eso ya es código existente, no se tocó.

## Verificación

- `SELECT` directo confirmó #797/#798 siguen `aprobado_revisor`/`pending`/sin reclamar (nadie los
  tocó desde la descomposición original).
- Tras el intento de cierre de esta vuelta: `#738.estado_aprobacion == 'aprobado_irving'`,
  `excluir_pool_automatico == true`, log con evento `paraguas_abierto` y el conteo real de hijos
  abiertos (2).
- El trabajo técnico real (Fase 1a/1b del esquema de referencia) sigue intacto y pendiente en #797
  y #798 — este item no lo duplica ni lo adelanta.

## Sin cambio de código de producto

Este cierre no toca código de negocio ni el motor de compensación/permisos/dinero. Solo ejecuta,
por primera vez para #738, el paso de cierre-intento que el propio guard de paraguas del sistema
ya esperaba.
