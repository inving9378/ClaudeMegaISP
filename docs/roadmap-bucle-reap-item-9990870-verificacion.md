# Item #9990870 — CIRC-04: bucle reap sobre paraguas ya descompuesto

## Contexto

`#9990870` ("CIRC-04 — Re-triaje de la cola: coherencia cancelados, basura y 430 items sin nivel
de riesgo") es sub-item de seguimiento de `#9990854`. Pide cuatro pasos de higiene de metadatos
sobre la tabla `roadmap_items`: (0) respaldo, (1) coherencia de cancelados, (2) reportar basura
sin ejecutar, (3) clasificar nivel de riesgo de items sin él, (4) proponer re-priorización sin
aplicarla.

Historial del item: aprobado por el revisor (`#338`, confianza alta), timeouteó por `max_turns`
sin commits en su primera corrida (`reap_count=0` en ese punto, escaló a Irving por
"DES-TRABE (Opus)" bajo la categoría `ejecutor_no_pudo`), Irving lo re-aprobó
(`irving:admin` → `aprobado_irving`), y `reaper-rapido` lo volvió a encolar como huérfano
(`reap_count=1`, slot `wt-4` libre) — llegando así a `wt-6` en esta vuelta.

## Verificación

Al leer el item con `RoadmapCircuitoService`/tinker, `circuito:cabida 9990870 --sid=wt-6` devolvió
`CABE [ya_descompuesto]`. Confirmado contra la BD: el item **ya tenía 4 sub-items**
(`origen_item_id=9990870`), creados por una vuelta anterior siguiendo el propio prompt del item
paso por paso:

| Sub-item | Paso | Estado al llegar a esta vuelta |
|---|---|---|
| `#9991008` | PASO 0+1 — Backup + confirmar coherencia cancelados (ya en 0) | `completado` |
| `#9991009` | PASO 2 — Reportar basura, incluye `#185` | `en_progreso`, reclamado activamente por `wt-3` |
| `#9991010` | PASO 3 — Confirmar nivel_riesgo de los 430 (ya en 0 pendientes en la cola viva) | `completado` |
| `#9991011` | PASO 4 — Propuesta de re-priorización (doc en `docs/`, sin aplicar) | `en_progreso`, reclamado activamente por `wt-4` |

La descomposición era correcta y completa (cubre los 4 pasos del prompt uno a uno). El problema
no era de contenido: **ninguna vuelta previa intentó cerrar al padre** tras descomponerlo — se
quedó `en_progreso` colgado con un `worker_sid` de una sesión ya terminada, así que
`reaper-rapido` lo detectó huérfano y lo repartió de nuevo sin que hubiera trabajo propio
pendiente. Mismo patrón documentado ya muchas veces en `CLAUDE.md` (familia
`#738/#745/#830/#816/#818/#848/#852/#905/#878/#906/#907/#924/#9990012/#917/#910/#936/#9990408/
#962/#9990554/#9990549/#9990624/#9990650/#9990807/#9990826/#9990836/#9990856/#9990892/#9990896/
#9990886/#9990893/#9990878`).

## Corrección aplicada

Se ejecutó el intento de cierre faltante:

```php
$i = RoadmapItem::find(9990870);
$i->estado_aprobacion = 'completado';
$i->save();
```

El guard de paraguas (`RoadmapItem.php`, bloque "(2b) PARAGUAS") detectó los 2 sub-items todavía
abiertos (`#9991009` y `#9991011`, ambos con dueño activo — `wt-3` y `wt-4` respectivamente, no
se tocaron por la regla de aislamiento un-item-un-dueño) y reenrutó el guardado:

- `estado_aprobacion` → `aprobado_irving`
- `excluir_pool_automatico` → `true`
- `worker_sid`/`claimed_at` → `null` (liberados)
- Log: evento `paraguas_abierto`, "le quedan 2 sub-item(s) abierto(s): no se completa. Queda
  como paraguas y cierra solo cuando el último de ellos cierre."

Esto saca a `#9990870` del pool/reaper hasta que el hook de cierre en cascada
(`RoadmapItem.php:459-491`) lo complete solo, en el instante en que tanto `#9991009` como
`#9991011` lleguen a `completado`.

## Conclusión

**Sin cambio de código de negocio.** El trabajo técnico real de CIRC-04 (reportar los items
basura del PASO 2 y redactar la propuesta de re-priorización del PASO 4) sigue en
`#9991009`/`#9991011`, a cargo de `wt-3`/`wt-4` respectivamente. Este item solo completó el
bookkeeping de cierre que faltaba sobre el paraguas ya descompuesto correctamente.
