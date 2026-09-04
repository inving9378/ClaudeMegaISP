# Item #830 — cierre del bucle reap sobre el paraguas #833 (Fase 1a-ii parte 2/2, ciclo fix-drift de 555 migraciones)

## Contexto

#830 es el sub-item de seguimiento que continúa exactamente donde se quedó #822 (Fase 1a-ii
parte 2/2 de la migración de referencia de esquema): correr `schema:rebuild-dryrun --force` sobre
las 555 migraciones, arreglando drift real conforme aparece (mismo patrón ya usado 2 veces:
`ab7804e0` crea la tabla `migrations` en dryrun antes del Migrator, `8c938dd1` agrega un guard
`Schema::hasTable` en `2026_05_27_140742_create_jobs_table`).

Una vuelta previa (también `wt-1`, 2026-08-31 17:36) ya hizo el trabajo de diagnóstico correcto:

1. Verificó que `ab7804e0` + `8c938dd1` (los fixes de #822) ya están en `main`.
2. Corrió `circuito:cabida 830` → **NO CABE** (`ya_timeouteo_antes`, el item ya había timeouteado
   una vez sin commits).
3. En vez de picar código directo, descompuso el siguiente tramo de trabajo (resolver la colisión
   `failed_jobs` — posible carrera con #831 sobre `megaisp_dryrun` compartida, o drift real que
   necesita el mismo guard `hasTable`) en el sub-item **#833**, con el diagnóstico exacto y la
   política de ejecución ya aprobada por Irving (lotes, un commit por fix, stop-and-escalate en
   frontera dura).
4. Reportó la decisión (`circuito:reportar --tipo=decision`) y terminó la vuelta con
   `ejecuto=false`.

Esa parte fue correcta. Lo que faltó: **esa vuelta nunca intentó cerrar #830** (ni a `completado`
ni de ninguna otra forma). El item se quedó `en_progreso` con el `worker_sid` de esa sesión, sin
que nadie liberara el claim. El reaper de huérfanos (`reap-rapido`) lo vio con el slot libre, lo
re-encoló a `aprobado_revisor`, el pool lo volvió a repartir sin que hubiera trabajo propio que
hacer (el trabajo real ya estaba correctamente delegado a #833), timeouteó otra vez a los 600s sin
commits, escaló de nuevo a `requiere_irving`, y el carril mecánico (`jarvis-mecanico`) lo
re-aprobó — mismo síntoma que #738/#745: un paraguas correctamente descompuesto que nunca recibió
el intento de cierre que activa el guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- `circuito:cabida 830 --sid=wt-1` → `CABE [ya_descompuesto]` (no re-descompone; solo dice
  "intenta cerrar, el guard de paraguas decide").
- Query directa: `RoadmapItem::where('origen_item_id', 830)->get()` → un único hijo, **#833**
  ("Fase 1a-ii parte 3/N: resolver colisión failed_jobs..."), `estado_aprobacion=requiere_irving`,
  `status=pending`, `worker_sid=null`, sin rama — sigue abierto, esperando que Irving resuelva sus
  preguntas estructuradas (política de lotes / frontera dura / commits).
- Intento de cierre: `RoadmapItem::find(830)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, y agregó al log el evento `paraguas_abierto`
  ("le queda 1 sub-item abierto: no se completa"). Confirmado leyendo `$item->log` tras el save.

## Resultado

#830 queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
#833 cierre — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y verificado
en las sesiones de #738/#745) completa #830 solo, sin intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (resolver la colisión `failed_jobs` y
seguir el ciclo fix-drift por el siguiente lote de migraciones) sigue en #833, esperando la
decisión de Irving sobre sus preguntas estructuradas (política de lotes / frontera dura / política
de commits) antes de que una terminal lo reclame.
