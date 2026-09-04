# Item #790 — bucle reap/re-encolado sobre un paraguas ya descompuesto (RESUELTO — se cierra el paraguas correctamente)

## Contexto

`#790` ("Barrido de comandos en `app/Console/Commands/Scripts/` (~51) que nada invoca", sub-item de
`#657`) llegó a `circuito:cabida` con veredicto **NO CABE** en una vuelta previa (`wt-1`,
2026-08-31 17:52): ya había timeouteado antes a 600s con 0 commits al intentar el barrido completo.
Esa sesión hizo lo correcto: en vez de reintentar el lote completo, lo descompuso en tres sub-items
propios por rango alfabético —

- **#836** — rango A-D (20 comandos).
- **#837** — rango E-R (18 comandos).
- **#838** — rango S-U (13 comandos).

— sin picar código ni crear rama, según el protocolo NO CABE, y cerró su vuelta con el META
(`ejecuto:false`). Hasta aquí, correcto.

## El bucle

`#790` volvió a aparecer reclamado (`en_progreso`) para esta nueva sesión `wt-2`, con `reap_count=1`
y una entrada `reaper-rapido` en el log (2026-08-31 17:56): "el slot wt-1 está libre (ninguna vuelta
corriendo ahí): reclamo huérfano → re-encolado (intento 1/3), vuelve a aprobado_irving".

Verificado en la BD: **#836, #837 y #838 siguen existiendo**, con `origen_item_id=790`, sin
`worker_sid` — la descomposición de la sesión anterior sigue intacta, nadie los tocó (`#836` en
`aprobado_revisor`, `#837` y `#838` en `requiere_irving` — estos dos últimos ya escalados por su
propio triaje, no por este bug).

**Causa raíz del bucle (idéntica a la documentada para #738, #745 y #778):** `RoadmapItem` tiene un
guard (`saving`, bloque "(2b) PARAGUAS", `app/Modules/Addons/Roadmap/Models/RoadmapItem.php:301-326`)
que SOLO aparca correctamente un paraguas (`estado_aprobacion → aprobado_irving` +
`excluir_pool_automatico = true`) cuando algo **intenta activamente poner
`estado_aprobacion = 'completado'`** sobre el padre y detecta hijos abiertos. La sesión anterior
nunca hizo ese intento — `circuito:sub-item` (que solo crea el hijo, no toca al padre) fue lo último
que corrió antes de terminar la vuelta con el META. El padre se quedó en `en_progreso` con el
`worker_sid` de esa sesión, sin nadie liberando el claim.

El **reaper** (`circuito:reap-stuck`, vía rápida por slot libre) solo sabe hacer una cosa con un
`en_progreso` huérfano: re-encolarlo a su estado previo (`aprobado_irving`) para que el pool lo vuelva
a repartir — no sabe que ya fue descompuesto, porque `tieneSubItemsAbiertos()` nunca llegó a
evaluarse (eso solo pasa dentro del guard de arriba, que nadie disparó). Resultado: cada vuelta que
reclama #790 encuentra el mismo estado (ya descompuesto, sin trabajo propio), no lo cierra
explícitamente, y dispara el mismo ciclo la próxima vez que el reaper lo libera.

## Corrección aplicada en esta vuelta

Sin tocar #836/#837/#838 (no son de esta sesión — un item, un dueño) y sin re-descomponer (ya está
hecho), esta vuelta simplemente **completa el paso que faltó**: intentar cerrar #790 a `completado`.
El guard (2b) del modelo detecta los 3 hijos abiertos y lo reenruta solo —
`estado_aprobacion = aprobado_irving`, `excluir_pool_automatico = true`. Con
`excluir_pool_automatico = true` el reaper y el pool dejan de tocarlo (el reaper solo opera sobre
`en_progreso`; el pool no reparte ítems con esa bandera). Cuando #836, #837 y #838 cierren, el hook
`saved` de "PARAGUAS — cierre en cascada" (`RoadmapItem.php:459-491`) encuentra al padre en
`aprobado_irving` sin hijos abiertos y lo completa solo — eso ya es código existente, no se tocó.

## Verificación

- `SELECT` directo (tinker) confirmó #836/#837/#838 siguen existiendo, con `origen_item_id=790`, sin
  `worker_sid` (nadie los tocó desde la descomposición original).
- Tras el intento de cierre de esta vuelta: `#790.estado_aprobacion == 'aprobado_irving'`,
  `excluir_pool_automatico == true`.
- El trabajo técnico real (el barrido A-F de los ~51 comandos de `Scripts/`) sigue intacto y
  pendiente en #836, #837 y #838 — este item no lo duplica ni lo adelanta. #837 y #838 ya están
  escalados a Irving por su propio triaje (nivel de riesgo/huecos de spec, sin relación con este
  bug); #836 sigue en `aprobado_revisor` esperando reparto normal del pool.

## Sin cambio de código de producto

Este cierre no toca código de negocio ni el motor de compensación/permisos/dinero. Solo ejecuta, por
primera vez para #790, el paso de cierre-intento que el propio guard de paraguas del sistema ya
esperaba — mismo patrón que #738, #745 y #778, esta vez sobre el barrido de `Scripts/`.
