# Item #841 — cierre del bucle reap sobre el paraguas #845/#846 (Auditoría de permisos)

## Contexto

#841 pedía una auditoría de permisos nivel A, solo lectura: volcar el catálogo real de
`permissions`/`role_has_permissions`/`model_has_permissions`, cruzarlo contra el código (rutas,
`CheckRoutePermission`, Blade, controladores), producir un `.md` con permisos huérfanos y recursos
desprotegidos, y además investigar el "Caso 0" reproducible (usuario ALONDRA NIMY viendo 0
almacenes en `/inventory/inventory_store` en producción mientras Admin ve 3).

Una vuelta previa (Des-trabe/Opus, 2026-09-01 13:48) ya hizo el diagnóstico correcto: el trabajo es
demasiado grande para una sola vuelta (catálogo completo + cruce de código + investigación de un
bug reproducible en 3 módulos distintos), así que lo descompuso en dos sub-items:

- **#845** — "Auditoría de permisos — Fase 1: catálogo + cruce con código + huérfanos/desprotegidos"
- **#846** — "Auditoría de permisos — Fase 2: Caso 0 reproducible (Alondra Nimy / Inventario Almacenes)"

Ambos nacieron `nivel_riesgo=C` y quedaron `requiere_irving`, sin reclamar (`worker_sid=null`).

Lo que faltó: esa vuelta nunca **intentó cerrar #841** tras descomponerlo. El item se quedó
`en_progreso` con el `worker_sid` de esa sesión, sin que nadie liberara el claim. El
`reaper-rapido` lo vio con el slot libre y lo re-encoló a `aprobado_irving`
(`reap_count=1`, evento `huerfano_reencolado`, 2026-09-01 14:18) — mismo síntoma que
#738/#745/#830/#816/#818: un paraguas correctamente descompuesto que nunca recibió el intento de
cierre que activa el guard de "no completar mientras queden hijos abiertos".

## Verificación de esta vuelta

- Query directa: `RoadmapItem::where('origen_item_id', 841)->get()` → dos hijos, **#845** y
  **#846**, ambos `estado_aprobacion=requiere_irving`, `nivel_riesgo=C`, `worker_sid=null` — siguen
  abiertos, esperando decisión de Irving (nivel C = decisión de diseño exclusiva de Irving, correcto
  que no se auto-ejecuten).
- Intento de cierre: `RoadmapItem::find(841)->estado_aprobacion = 'completado'; ->save();` → el
  guard `saving` (2b, `RoadmapItem.php` ~301-326) lo reenrutó automáticamente a
  `aprobado_irving` + `excluir_pool_automatico=true` (confirmado antes/después:
  `abiertos=2` → `excluir_pool_automatico` `false`→`true` tras el save; log del item registra el
  evento `paraguas_abierto` con `subitems_abiertos: 2`).

## Resultado

#841 queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
#845 y #846 cierren — en ese momento el hook `saved` (`RoadmapItem.php:459-491`, ya existente y
verificado en las sesiones de #738/#745/#830/#816) completa #841 solo, sin intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (catálogo de permisos + cruce con
código, e investigación del Caso 0 de Alondra Nimy) sigue en #845 y #846, pendientes de que Irving
los resuelva (ambos nivel C).
