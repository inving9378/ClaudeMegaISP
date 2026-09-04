# Item #880 — cierre del bucle reap sobre paraguas ya descompuesto (Torre 24/7 · Pieza 4 — auto-corregir hardening de código)

## Contexto

`#880` ("Torre 24/7 · Pieza 4 — auto-corregir seguridad EN CÓDIGO sin preguntar, dejando
permisos/credenciales/dinero en la bandeja") pedía partir la clasificación de "seguridad" en dos
carriles (AUTO endurece sin tocar autorización; BANDEJA queda intacto para permisos/auth/dinero),
con criterio en `config/circuito.php` y un candado de regresión por test — siguiendo las 5 fases
del `prompt` del item.

Una vuelta previa (`wt-1`, entre 2026-09-03 12:29 y 12:44) ya hizo el trabajo correcto:

1. Corrió `circuito:cabida 880` y obtuvo **NO CABE** ("ya timeouteó antes" — el item ya había
   sufrido un `timeout_escalado` a las 12:27:04 con la rama sin commits).
2. Descompuso el trabajo en **3 sub-items**, siguiendo exactamente las fases del prompt:
   - **#918** — Fase 2: declarar el criterio AUTO/BANDEJA de hardening en `config/circuito.php`
     (sin wiring).
   - **#919** — Fase 3+4: activar el carril AUTO en `circuito:priorizar-seguridad` + candado de
     regresión (bloqueante, mismo merge — coherente con la exigencia del prompt de que el test es
     innegociable antes de cerrar).
   - **#920** — Fase 5: verificar el clasificador AUTO/BANDEJA contra items de seguridad reales ya
     cerrados.
3. Documentó la Fase 1 (lectura de la política actual) directamente en `comentarios_claude` del
   propio `#880`, para que ninguno de los 3 sub-items tenga que re-investigar el mapa de la
   válvula.

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó **cerrar**
`#880` después de crear los sub-items. El ítem se quedó `en_progreso` con el `worker_sid` de esa
sesión, sin que nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el
slot `wt-1` libre, lo re-encoló a `aprobado_irving` (`reap_count=1`, log `huerfano_reencolado`
2026-09-03 12:44:02) y el pool lo repartió de nuevo (a esta misma terminal, `wt-1`) sin que
hubiera trabajo propio que hacer — mismo síntoma exacto que
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#878`: un paraguas correctamente descompuesto que
nunca recibió el intento de cierre que activa el guard de "no completar mientras queden hijos
abiertos".

## Verificación de esta vuelta

- Query directa: los 3 hijos (`origen_item_id=880`) siguen intactos —
  `estado_aprobacion=requiere_irving`, `nivel_riesgo=B`, sin `worker_sid` (nadie los reclamó ni
  los tocó). La descomposición original seguía siendo la correcta.
- Rama `circuito/item-880-torre-247-pieza-4-auto-corregir-seg`: existe pero **sin commits propios**
  (su `git log` es idéntico al de `main` en ese punto) — consistente con el `commits_rama: 0` del
  evento `timeout_escalado` anterior. No hay trabajo a medias que rescatar de la rama.
- Intento de cierre: `RoadmapItem::find(880)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b "PARAGUAS", `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true` (+ `esperando_merge_irving=true`). Confirmado
  leyendo `$item->log` tras el save: última entrada `{"estado":"aprobado_irving","decision":"flags",
  "flags":{"esperando_merge_irving":{"antes":false,"despues":true},
  "excluir_pool_automatico":{"antes":false,"despues":true}}}`.

## Resultado

`#880` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#918`, `#919` y `#920` cierren los tres — en ese momento el hook `saved`
(`RoadmapItem.php:459-491`, ya existente y verificado en las sesiones anteriores de este mismo
bug) completa `#880` solo, sin intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (declarar el criterio AUTO/BANDEJA,
activar el carril AUTO con su candado de regresión, y verificar contra casos reales) sigue en
`#918`/`#919`/`#920`, pendiente de triaje/aprobación — los tres nacieron `requiere_irving` porque
heredan el `nivel_riesgo=B` de tocar la política de la válvula de fronteras duras.
