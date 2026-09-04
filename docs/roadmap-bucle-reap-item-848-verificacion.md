# Item #848 — cierre del bucle reap sobre paraguas ya descompuesto (Fase 2 del menú de una sola fuente de verdad)

## Contexto

`#848` ("Fase 2 - Menu de una sola fuente de verdad: ocultar modulo completo si 0 entradas
visibles") es sub-item de seguimiento de `#843`. Una vuelta previa (`wt-2`, 2026-09-01 15:56) ya
hizo el diagnóstico correcto:

1. Corrió `circuito:cabida 848` → **NO CABE** (el item ya había timeouteado 2 veces sin commits).
2. En vez de picar código directo, descompuso el trabajo real en 3 sub-items con spec precisa:
   - **#855** — envolver el `<li>`/`<ul>` completo del bloque hardcodeado en el `@if` de permiso
     (evita el shell `<li><ul>` huérfano vacío cuando ningún hijo es visible).
   - **#856** — los `dynamic_children` de `module_sidebar_config` no tienen chequeo de permiso
     individual por hijo (hallazgo nuevo: fila `gestion-red-mikrotik-sync` sin permiso ni guard de
     ruta encontrado).
   - **#857** — aplicar la decisión de Irving (respuesta a `q2`) sobre módulos con landing/índice
     propio cuando todas sus entradas hijas quedan ocultas.
3. Reportó la decisión (`circuito:reportar --tipo=decision`, texto truncado en el log por corte de
   proceso, pero los 3 sub-items ya estaban persistidos en BD antes del corte).

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca terminó el intento de
cerrar `#848` (el proceso se cortó a media escritura del reporte). El item se quedó `en_progreso`
con el `worker_sid` de esa sesión, sin que nadie liberara el claim. El reaper de huérfanos
(`reaper-rapido`) lo vio con el slot libre, lo re-encoló a `aprobado_irving`
(`reap_count=1`, log `huerfano_reencolado` 2026-09-01 16:00:03), y el pool lo volvió a repartir sin
que hubiera trabajo propio que hacer (el trabajo real ya estaba correctamente delegado a
`#855`/`#856`/`#857`) — mismo síntoma exacto que `#738`/`#745`/`#830`/`#816`/`#818`: un paraguas
correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de "no
completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- `circuito:cabida 848 --sid=wt-2` → `CABE [ya_descompuesto] — procede a implementar #848.` (no
  re-descompone; el candado de idempotencia `yaFueDescompuesto()` ya detecta los 3 hijos
  existentes).
- Query directa: los 3 hijos (`origen_item_id=848`) — `#855`, `#856`, `#857` — existen, todos
  `estado_aprobacion=requiere_irving`, esperando triaje/decisión propia. Ninguno se tocó.
- Intento de cierre: `RoadmapItem::find(848)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, y agregó al log el evento `paraguas_abierto`
  ("le quedan 3 sub-item(s) abierto(s): no se completa"). Confirmado leyendo `$item->log` tras el
  save (evento `paraguas_abierto` seguido del evento `flags` que audita el cambio de
  `excluir_pool_automatico`).

## Resultado

`#848` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
sus 3 hijos cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones anteriores de este mismo bug) completa `#848` solo, sin intervención
manual.

**Sin cambio de código de negocio.** El trabajo técnico real de la Fase 2 (ocultar módulos del
sidebar sin entradas visibles) sigue en `#855`/`#856`/`#857`, esperando triaje/aprobación antes de
que una terminal los reclame.
