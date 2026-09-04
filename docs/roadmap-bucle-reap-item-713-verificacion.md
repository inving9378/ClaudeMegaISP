# Item #713 — bucle reap/re-encolado sobre un paraguas ya descompuesto (RESUELTO — se cierra el paraguas correctamente)

## Contexto

#713 ("Thomas Parte 3 — Sugerencias proactivas en el chat", sub-item de seguimiento de #644)
llegó al Des-trabe (Opus) con veredicto de negocio: el ítem no traía plan concreto y tocaba
decisión de diseño/UX de Irving (cómo/cuándo/qué sugiere Thomas). Irving aprobó (`aprobado_irving`,
2026-08-28 18:30). En la primera vuelta que lo tomó (`wt-2`, 2026-08-29 ~10:42) `circuito:cabida`
marcó **NO CABE** (`ya_timeouteo_antes` — 600s sin commits en una vuelta previa). Esa sesión hizo
lo correcto: sin picar código ni crear rama, descompuso el ítem en dos sub-items propios —

- **#805** — Parte 3a: motor de detección de sugerencias (read-only, reusa el índice vivo #711).
- **#806** — Parte 3b: decidir dónde vive "el chat" de las sugerencias (brief de Irving, ya que
  el widget JARVIS está apagado a propósito y #711 dejó la UI fuera de alcance) y cablearlo.

— y cerró su vuelta con el META (`ejecuto:false`, resumen "#713 queda como paraguas"). Hasta ahí,
correcto.

## El bucle

#713 volvió a aparecer reclamado (`en_progreso`) para esta nueva sesión `wt-1`, con `reap_count=1`
y una entrada `reaper-rapido` en el log (2026-08-29 10:46): "el slot wt-2 está libre (ninguna
vuelta corriendo ahí): reclamo huérfano → re-encolado (intento 1/3), vuelve a aprobado_irving".
`circuito:cabida` esta vez devolvió `CABE [ya_descompuesto]` — la señal de que no hay que volver a
descomponer, solo completar el cierre.

Verificado en la BD: **#805 sigue `aprobado_revisor`/`status=pending`/`worker_sid` vacío** y
**#806 sigue `requiere_irving`/`worker_sid` vacío** — la descomposición de la sesión anterior
sigue intacta, nadie los tocó.

**Causa raíz del bucle (idéntica a la documentada para #738, #745 y #778):** `RoadmapItem` tiene
un guard (`saving`, bloque "(2b) PARAGUAS", `app/Modules/Addons/Roadmap/Models/RoadmapItem.php:301-326`)
que SOLO aparca correctamente un paraguas (`estado_aprobacion → aprobado_irving` +
`excluir_pool_automatico = true`) cuando algo **intenta activamente poner
`estado_aprobacion = 'completado'`** sobre el padre y detecta hijos abiertos. La sesión anterior
nunca hizo ese intento — `circuito:sub-item` (que solo crea el hijo, no toca al padre) fue lo
último que corrió antes de terminar la vuelta con el META. El padre se quedó en `en_progreso` con
el `worker_sid` de esa sesión, sin nadie liberando el claim.

El **reaper** (`circuito:reap-stuck`, vía rápida por slot libre) solo sabe hacer una cosa con un
`en_progreso` huérfano: re-encolarlo a su estado previo (`aprobado_irving`) para que el pool lo
vuelva a repartir — no sabe que ya fue descompuesto, porque `tieneSubItemsAbiertos()` nunca llegó
a evaluarse (eso solo pasa dentro del guard de arriba, que nadie disparó). Resultado: cada vuelta
que reclama #713 encuentra el mismo estado (ya descompuesto, sin trabajo propio), no lo cierra
explícitamente, y dispara el mismo ciclo la próxima vez que el reaper lo libera.

## Corrección aplicada en esta vuelta

Sin tocar #805/#806 (no son de esta sesión — un item, un dueño) y sin re-descomponer (ya está
hecho, y #806 sigue legítimamente esperando el brief de Irving), esta vuelta simplemente
**completa el paso que faltó**: intentar cerrar #713 a `completado`. El guard (2b) del modelo
detecta los 2 hijos abiertos y lo reenruta solo — `estado_aprobacion = aprobado_irving`,
`excluir_pool_automatico = true`, log `paraguas_abierto`. Con `excluir_pool_automatico = true` el
reaper y el pool dejan de tocarlo (el reaper solo opera sobre `en_progreso`; el pool no reparte
ítems con esa bandera). Cuando #805 y #806 cierren, el hook `saved` de "PARAGUAS — cierre en
cascada" (`RoadmapItem.php:459-491`) encuentra al padre en `aprobado_irving` sin hijos abiertos y
lo completa solo — eso ya es código existente, no se tocó.

## Verificación

- `SELECT` directo (tinker) confirmó #805/#806 siguen `aprobado_revisor`/`requiere_irving`, sin
  reclamar (nadie los tocó desde la descomposición original).
- Tras el intento de cierre de esta vuelta: `#713.estado_aprobacion == 'aprobado_irving'`,
  `excluir_pool_automatico == true`, log con evento `paraguas_abierto` y el conteo real de hijos
  abiertos (2).
- El trabajo real (motor de detección #805, y la decisión de diseño/UX #806 pendiente del brief
  de Irving) sigue intacto y pendiente en sus propios items — este item no lo duplica ni lo
  adelanta.

## Sin cambio de código de producto

Este cierre no toca código de negocio ni el motor de compensación/permisos/dinero. Solo ejecuta,
por primera vez para #713, el paso de cierre-intento que el propio guard de paraguas del sistema
ya esperaba — mismo patrón que #738, #745 y #778, aplicado aquí a la Parte 3 de #644.
