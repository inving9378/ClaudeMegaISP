# Item #852 — cierre del bucle reap sobre paraguas ya descompuesto (Item 2 Fase A — permisos por módulo)

## Contexto

`#852` ("Item 2 Fase A — fix columna description + comando permissions:sync-roles + registrar los
34 módulos que YA declaran permisos") es sub-item de seguimiento de `#842`. Una vuelta previa
(`wt-3`, 2026-09-01 15:59) ya hizo el diagnóstico correcto:

1. Corrió `circuito:cabida 852` → **NO CABE** (el item ya había timeouteado antes: ver log
   `timeout_escalado` 2026-09-01 15:23:05, "La vuelta se cortó a los 600s y la rama no tiene
   commits").
2. En vez de picar código directo, descompuso el trabajo real en 4 sub-items con spec precisa:
   - **#858** — fix de la columna `permissions.description` faltante + backfill + extender el
     glob de `PermissionSyncService::syncFromModuleManifests()` a `app/Modules/Core/*/module.json`
     (hoy solo cubre `Addons/*`).
   - **#859** — correr `permissions:sync-roles --manifests` en dev tras el fix + verificar diff.
   - **#860** — exponer `description` en el endpoint del catálogo de permisos
     (`/administracion/permisos/catalog`, consumido por `PermissionAssignmentModal.vue`).
   - **#861** — documentar `docs/permisos/permisos-legacy-mapping.md` (convivencia de
     nomenclaturas `modulo.recurso.accion` vs `modulo_recurso_accion`, sin unificar).
3. Registró un hallazgo importante en el proceso: la premisa original de que el comando
   `permissions:sync-roles` **no existía** era falsa — ya existe
   (`app/Modules/Core/Security/Console/SyncPermissionsCommand.php`, registrado, con flag
   `--manifests`). El bug real (falta la columna `permissions.description`) sí se confirmó con
   `Schema::getColumnListing`. La corrección de esa premisa quedó explícita en el spec de `#859`
   para que el ejecutor de ese sub-item no repita la investigación.

Esa parte fue correcta y **no se repite**. Lo que faltó: esa vuelta nunca terminó el intento de
cerrar `#852`. El item se quedó `en_progreso` con el `worker_sid` de esa sesión (`wt-3`), sin que
nadie liberara el claim. El reaper de huérfanos (`reaper-rapido`) lo vio con el slot libre, lo
re-encoló a `aprobado_irving` (`reap_count=1`, log `huerfano_reencolado` 2026-09-01 16:10:03), y
el pool lo volvió a asignar (esta vez a `wt-2`) sin que hubiera trabajo propio que hacer (el
trabajo real ya estaba correctamente delegado a `#858`/`#859`/`#860`/`#861`) — mismo síntoma
exacto que `#738`/`#745`/`#830`/`#816`/`#818`/`#848`: un paraguas correctamente descompuesto que
nunca recibió el intento de cierre que activa el guard de "no completar mientras queden hijos
abiertos".

## Verificación de esta vuelta

- Query directa: los 4 hijos (`origen_item_id=852`) existen intactos, ninguno reclamado
  (`worker_sid=null`, `status=pending`):
  - `#858` — `estado_aprobacion=aprobado_revisor`
  - `#859` — `estado_aprobacion=aprobado_revisor`
  - `#860` — `estado_aprobacion=aprobado_revisor`
  - `#861` — `estado_aprobacion=aprobado_irving`
- Intento de cierre: `RoadmapItem::find(852)->estado_aprobacion = 'completado'; ->save();` → el
  guard "(2b) PARAGUAS" (`RoadmapItem.php`) lo reenrutó automáticamente a `aprobado_irving` +
  `excluir_pool_automatico=true`. Confirmado leyendo el estado tras el save.

## Resultado

`#852` queda **fuera del pool de reclamo** (no más timeouts ni re-escalaciones en bucle) hasta que
sus 4 hijos cierren — en ese momento el hook de cierre en cascada (`RoadmapItem.php:459-491`, ya
existente y verificado en las sesiones anteriores de este mismo bug) completa `#852` solo, sin
intervención manual.

**Sin cambio de código de negocio.** El trabajo técnico real (fix de columna, comando de
sincronización, registro de los 34 módulos, UI del catálogo, documentación de nomenclaturas) sigue
en `#858`/`#859`/`#860`/`#861`, pendiente de que una terminal los reclame en su propia vuelta.
