# Item #911 — cierre del bucle reap sobre paraguas ya descompuesto (pre-filtro de módulo / detector de colisiones)

## Contexto

`#911` ("Aflojar el pre-filtro de módulo para usar las 6 terminales, cerrando antes los 2 puntos
ciegos del detector de colisiones") es el ítem que pedía, en 6 fases explícitas del propio prompt,
inventariar el estado compartido real entre terminales, cerrar los dos puntos ciegos del detector
de colisiones (footprint en vivo + serialización de migraciones) y solo entonces aflojar de forma
gradual y reversible el pre-filtro de módulo, verificando con números.

Una vuelta previa (`wt-2`, 2026-09-03 12:32) ya hizo el trabajo correcto: corrió `circuito:cabida`
(NO CABE, histórico ~37881s) y descompuso el trabajo en 6 sub-items, uno por fase del propio
prompt, cada uno con spec citando archivo/línea exactos (`RoadmapCircuitoService.php:2439/2487/
2590/2613`, `SchedulerCommand.php:74`, `IntegrarItemCommand.php:45`):

- **#912** Fase 1 — inventario del estado compartido real
- **#913** Fase 2 — footprint en vivo del árbol de trabajo (cerrar ciego 1)
- **#914** Fase 3 — aviso temprano al perdedor de una colisión
- **#915** Fase 4 — serializar migraciones/esquema de BD compartida (cerrar ciego 2)
- **#916** Fase 5 — aflojar el pre-filtro (perilla gradual y reversible; bloqueado hasta que #913 y
  #915 cierren)
- **#917** Fase 6 — verificación con números (requiere ventana real post-#916)

Esa descomposición fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca intentó
**cerrar** `#911` después de crear los sub-items. El ítem se quedó `en_progreso` con el `worker_sid`
de esa sesión, sin que nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con
el slot `wt-2` libre, lo re-encoló a `aprobado_revisor` (`reap_count=1`, log
`huerfano_reencolado` 2026-09-03 12:36:02), y el pool lo repartió de nuevo (a esta misma terminal,
`wt-2`) sin que hubiera trabajo propio que hacer — mismo síntoma exacto que
`#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#878`: un paraguas correctamente descompuesto que nunca
recibió el intento de cierre que activa el guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa a los 6 hijos (`origen_item_id=911`): los 6 existen intactos, ninguno con
  `worker_sid` (nadie los reclamó ni los tocó). #912/#913/#914/#917 en `aprobado_revisor`; #915/#916
  en `requiere_irving` (consistente con el spec original: Fase 4 y Fase 5 tocan fronteras que el
  propio prompt marca para decidir/justificar, no para ejecutar a ciegas). La descomposición
  original seguía siendo la correcta — nadie más la tocó.
- Intento de cierre: `RoadmapItem::find(911)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (bloque "(2b) PARAGUAS", `RoadmapItem.php`) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, agregando al log el evento
  `paraguas_abierto` ("le quedan 6 sub-item(s) abierto(s): no se completa. Queda como paraguas y
  cierra solo cuando el último de ellos cierre"). Confirmado leyendo `$item->log` tras el save.

## Resultado

`#911` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
los 6 sub-items (#912-#917) cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya
existente y verificado en las sesiones anteriores de este mismo bug) completa `#911` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (inventario del estado compartido,
footprint en vivo, aviso temprano, lock de migraciones y el aflojo gradual de la perilla) sigue en
#912-#917, en el orden de dependencias que el propio prompt original ya declaró (la Fase 5/#916
bloqueada hasta que #913 y #915 cierren; la Fase 6/#917 requiere ventana real post-#916).
