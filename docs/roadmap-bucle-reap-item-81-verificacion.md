# Item #81 — cierre del bucle reap sobre paraguas ya descompuesto (documentos CRM huérfanos)

## Contexto

`#81` ("Limpieza: identificar y limpiar documentos CRM huérfanos") tuvo una historia larga y con
varios giros de criterio (ver `comentarios_claude` del propio item), pero la **decisión oficial
vigente** de Irving —confirmada dos veces, la última hoy 2026-09-04 05:54 por `irving:CARLOS`— es
clara: solo **identificar y reportar**, nunca borrar (`q1` opción 1), con el criterio "archivo
físico no existe en disco" (`q2` opción 1), expuesto en comando artisan **+** vista admin Quasar
con export CSV (`q3` opción 2).

Una vuelta previa (`wt-2`/`wt-1`, 2026-09-03 21:39-21:47) ya hizo lo correcto: descompuso el
alcance completo de esa decisión en dos sub-items:

- **#9990084** (backend: `crm:documentos-huerfanos`, q1/q2) — implementado, verificado contra la
  BD real (`php -l` limpio, comando corrido, 99 huérfanos reales detectados —no solo la fila
  id=74— porque casi todos los archivos físicos de `document_crms` se perdieron, no solo esa
  fila), **completado y mergeado** (`merge_commit=ffc522a2`).
- **#9990085** (frontend: vista admin CRM "Documentos huérfanos", tabla Quasar + export CSV, q3) —
  triaje lo marcó nivel C (falso positivo por mención de "permiso", ya señalado por el propio
  Opus des-trabe: es solo el gate de acceso al panel admin, no toca permisos reales), con brief de
  decisión ya generado. Estado `aprobado_irving`, `worker_sid` vacío — listo para tomarse.

Esa descomposición cubre el 100% del alcance de la decisión oficial (q1+q2 → #9990084, q3 →
#9990085). Lo que faltó: nadie intentó **cerrar** `#81` después de crear los sub-items. El item
quedó reclamable (`veces_timeouteo=2`, sin `excluir_pool_automatico`) y el pool lo volvió a
repartir — esta vez a `wt-4` — sin que hubiera trabajo propio que hacer, el mismo síntoma exacto
que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`/`#852`/`#905`/`#878`/`#906`/`#907`/`#924`/
`#9990012`/`#917`: un paraguas correctamente descompuesto que nunca recibió el intento de cierre
que activa el guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `#9990084` (`origen_item_id=81`) — `estado_aprobacion = completado`,
  `merge_commit = ffc522a2efd32c1e8485b0566da25c07444ef108` — confirmado en `main`
  (`git log main -- app/Console/Commands/Scripts/CrmOrphanDocumentsReportCommand.php` muestra el
  commit `9e87a57d`, ya integrado).
- Query directa: `#9990085` (`origen_item_id=81`) — `estado_aprobacion = aprobado_irving`,
  `worker_sid = null` — sigue abierto y sin reclamar; nadie más lo tocó, la descomposición
  original seguía siendo correcta.
- Intento de cierre: `RoadmapItem::find(81)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (bloque 2b PARAGUAS, `RoadmapItem.php` ~301-332) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true`, confirmado leyendo el `estado_aprobacion` y
  `excluir_pool_automatico` tras el `save()`.
- Se liberó `worker_sid`/`claimed_at` a mano tras el intento de cierre, para que el item no quede
  marcado como "en progreso" de esta terminal mientras espera a su único hijo abierto.

## Resultado

`#81` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
`#9990085` cierre — en ese momento el hook `saved` (`RoadmapItem.php:459-491`) completa `#81` solo,
sin intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real que falta (la vista admin Quasar de
documentos huérfanos con export CSV) sigue en `#9990085` (`aprobado_irving`, listo para que una
terminal lo reclame).
