# Verificación — Item #9990836: bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990836` ("Fase 3 (#9990833): buscador usa `serie_equipo_norm` para encontrar el SN en
cualquier formato") es un sub-item de seguimiento de `#9990833` (normalización de serie de
equipo). Mismo patrón documentado repetidamente en `CLAUDE.md`: un item que ya fue correctamente
descompuesto en sub-items por una vuelta previa, pero esa vuelta murió sin intentar **cerrar** al
padre, dejándolo colgado para que el reaper lo re-encolara sin trabajo propio pendiente.

## Cronología real (según el `log` del item)

1. `2026-09-11 15:44` — `wt-5` crea `#9990836` como sub-item de seguimiento de `#9990833`.
2. `2026-09-11 15:48` — Revisor autoriza (`aprobado_revisor`, confianza alta, nivel B).
3. `2026-09-11 17:06` — Timeout por `max_turns` sin commits en la rama → escalado a
   `requiere_irving`.
4. `2026-09-11 17:08` — Autopilot re-aprueba (confianza alta + reversible, política nivel C) →
   `aprobado_revisor`.
5. `2026-09-11 17:10` — La vuelta de **`wt-1`** corre `circuito:cabida` (NO CABE, ya había
   timeouteado antes) y descompone correctamente el trabajo en:
   - **`#9990847`** (Fase 3a: `config/clientes_busqueda.php` + `ClienteSearchService::normalizar()`
     con el case `sn_canonico`, backend).
   - **`#9990848`** (Fase 3b: listado de Clientes muestra `serie_equipo` + indicador de
     `serie_equipo_origen`, UI).
   Verificó antes que la dependencia (Fase 2, `#9990835`) ya estaba `completado` y mergeada, y que
   el backfill ya había corrido en dev (5153/5606 filas con `serie_equipo_norm` poblado, incluido
   `client_id=6990` usado como caso de prueba en los specs).
6. `2026-09-11 17:11` — La vuelta de `wt-1` **muere sin intentar cerrar al padre**
   (`claim_liberado_al_morir_la_vuelta`, "muerte del proceso: kill, OOM o freno a media vuelta").
   El item vuelve a la cola como `aprobado_revisor`.
7. `2026-09-11 23:12` — El pool reparte `#9990836` de nuevo, otra vez a `wt-1` (esta vuelta).

## Verificación de que la descomposición sigue vigente

```
9990847: estado_aprobacion=pendiente_revision worker_sid= origen_item_id=9990836
9990848: estado_aprobacion=pendiente_revision worker_sid= origen_item_id=9990836
```

Ambos sub-items siguen intactos, sin reclamar por ninguna otra terminal — la descomposición
original de la vuelta anterior seguía siendo correcta, nadie más la tocó.

## Corrección aplicada

Se ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990836);
$i->estado_aprobacion = "completado";
$i->save();
```

El guard de paraguas del modelo (`RoadmapItem.php`, bloque "(2b) PARAGUAS") lo detectó y lo
reenrutó automáticamente:

```
estado_aprobacion=aprobado_irving excluir_pool_automatico=true
worker_sid=NULL claimed_at=NULL
```

Log resultante:

```json
{"por":"paraguas","evento":"paraguas_abierto","motivo":"Este item se descompuso y le quedan 2 sub-item(s) abierto(s): no se completa. Queda como paraguas y cierra solo cuando el último de ellos cierre.","subitems_abiertos":2}
```

## Efecto

`#9990836` queda fuera del pool automático y del reaper (no vuelve a repartirse sin trabajo propio
pendiente) hasta que `#9990847` y `#9990848` cierren ambos. El hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo completará solo en ese momento.

## Sin cambio de código de negocio

El trabajo técnico real (columna normalizada en el buscador de clientes + indicador visual de
origen en el listado) sigue en `#9990847`/`#9990848`, pendientes de que una terminal los reclame.
