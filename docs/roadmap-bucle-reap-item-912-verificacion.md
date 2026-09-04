# Item #912 — cierre del bucle reap sobre paraguas ya descompuesto (Inventario estado compartido, Fase 1 de #911)

## Contexto

`#912` (sub-item de seguimiento de `#911`, inventario read-only de qué recursos comparten
realmente las 6 terminales del circuito) tuvo una vuelta previa (`wt-4`, 2026-09-03 16:07) que ya
hizo el trabajo correcto:

1. Corrió `circuito:cabida` → **NO CABE** (`historico_excede_umbral`).
2. Descompuso el trabajo en 4 sub-items secuenciales:
   - **#1000003** — Fase 1a: inventario BD, cache y colas por terminal.
   - **#1000004** — Fase 1b: `storage/` symlinks y locks de archivo.
   - **#1000005** — Fase 1c: semáforo npm y colisión de puertos.
   - **#1000006** — Fase 1d: consolidación final (tabla del entregable), depende de las 3 anteriores.

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta terminó (murió) sin intentar
**cerrar** `#912` después de crear los sub-items. El mecanismo de liberación de claim al morir la
vuelta (`soltar-claim`, log `claim_liberado_al_morir_la_vuelta`, 2026-09-03 16:07:42) devolvió el
item a `aprobado_revisor` — pero eso solo libera el reclamo, no completa el cierre — y el pool lo
repartió de nuevo (a esta terminal, `wt-1`) sin que hubiera trabajo propio que hacer: mismo síntoma
exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#905`/`#878`/`#906`/`#907`/`#909`: un paraguas
correctamente descompuesto que nunca recibió el intento de cierre que activa el guard de "no
completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa (`origen_item_id=912`): `#1000003` y `#1000004` siguen `requiere_irving`;
  `#1000005` y `#1000006` siguen `aprobado_revisor`. Los 4 sin `worker_sid` (nadie los reclamó).
  Ninguno fue tocado por nadie más — la descomposición original seguía siendo la correcta.
- Intento de cierre: `RoadmapItem::find(912)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (bloque 2b PARAGUAS, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, agregando al log el evento `paraguas_abierto`
  ("le quedan 4 sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el
  último de ellos cierre"). Confirmado leyendo `$item->log` tras el save (evento `flags` que audita
  el cambio de `excluir_pool_automatico` de `false`→`true`), y `worker_sid`/`claimed_at` liberados
  por el mismo bloque.

## Resultado

`#912` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#1000003`, `#1000004`, `#1000005` y `#1000006` cierren — en ese momento el hook `saved`
(`RoadmapItem.php:459-491`, ya existente y verificado en las sesiones anteriores de este mismo bug)
completa `#912` solo, sin intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (inventario del estado compartido
entre terminales) sigue en `#1000003`/`#1000004` (`requiere_irving`, pendientes de que Irving los
apruebe) y `#1000005`/`#1000006` (`aprobado_revisor`, listos para que otra terminal los tome —
`#1000006` depende de que los otros tres cierren primero, según su propia spec).
