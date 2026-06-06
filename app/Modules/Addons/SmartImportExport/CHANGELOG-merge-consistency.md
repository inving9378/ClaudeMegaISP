# SmartImport — Consistencia del merge inteligente

Bitácora de cambios al motor de merge (`Services/SmartImportService.php`). Objetivo del
modo "resolver inteligentemente" (SMART): upsert por identidad preservando relaciones —
si la tabla no existe en destino se crea (orden topológico para no romper FKs); si existe,
los registros que vienen en la importada **reemplazan** a los del destino con la misma
identidad, los nuevos se **insertan**, y los que solo están en el destino se **dejan
intactos**.

---

## 2026-06-06 — Fix: error 1062 al mergear tablas con llave de negocio (permissions/roles)

### Síntoma (reportado por Irving, log local)
Al importar en modo SMART, la tabla `permissions` abortaba con:

```
SmartImport raw merge chunk error [permissions]: SQLSTATE[23000]: Integrity constraint
violation: 1062 Duplicate entry 'maps_site_add-web' for key
'permissions.permissions_name_guard_name_unique'
... insert into `permissions` (`created_at`,`guard_name`,`id`,`name`,`updated_at`) values
(... , web, 373, maps_site_add, ...) on duplicate key update `id`=values(`id`), ...
```

### Causa raíz
`permissions` tiene **dos** llaves únicas: `PRIMARY (id)` y `UNIQUE (name, guard_name)`.
Su identidad real es `name+guard_name` (el `id` es local a cada instalación; se generan
incrementalmente en distinto orden por instalación). El merge resolvía bien la llave de
negocio (`['name','guard_name']`, vía `identity_priority => 'override'` en
`config/smart_import.php`), **pero `flushBulkMergeRows` seguía metiendo el `id` del dump
en el INSERT del upsert**. Como MySQL dispara `ON DUPLICATE KEY UPDATE` ante *cualquier*
única, el `id` del dump (p.ej. 373) chocaba contra la PRIMARY de un permiso distinto del
destino y/o intentaba renombrar la fila equivocada → colisión con la única de negocio →
**1062**. Quitar `id` solo del `UPDATE` no bastaba: el INSERT seguía cargando el `id`.

> Nota: el reporte inicial de huérfanos en `client_custom_services` resultó NO ser de
> SmartImport — esa BD es una restauración de dump plano y los huérfanos venían del
> origen (1134 bundles con `created_at` NULL, padres inexistentes). Descartado como
> evidencia. El único bug real de SmartImport era este 1062.

### Por qué NO se puede "quitar el id" globalmente
`resolveConflictIdentity()` ya prefiere la PRIMARY del destino para **todas** las tablas
salvo las marcadas `identity_priority => 'override'`. Es decir, `clients`, `bundles`,
`client_bundle_services`, `invoices`, etc. **ya mergean por `id`** y DEBEN preservarlo
(sus hijos referencian ese `id`; reasignarlo causaría huérfanos). Solo las tablas de
catálogo (`permissions`, `roles`, `migrations`, `system_settings`) mergean por llave de
negocio. Por eso el fix se limita naturalmente a esas.

### Cambio aplicado (`SmartImportService.php`)
1. **Nueva propiedad** `private array $primaryKeyColumnsCache = []` + reset en
   `resetAnalysisState()`.
2. **Nuevo helper** `primaryKeyColumns(string $table): array` — devuelve solo las columnas
   de la PRIMARY KEY del destino (`SHOW INDEX ... WHERE Key_name='PRIMARY'`), cacheado.
3. **`flushBulkMergeRows()`** — calcula
   `$primaryKeyToStrip = array_diff(primaryKeyColumns($table), $keys)`.
   Si NO está vacío (el merge va por una llave de negocio que no incluye la PK), descarta
   esas columnas de cada fila normalizada **antes del upsert** (y por ende del
   `$updateColumns`). Para tablas identidad-por-PK el array queda vacío → comportamiento
   idéntico al anterior, el `id` se preserva.

Resultado del upsert para `permissions`:
```
INSERT (name, guard_name, created_at, updated_at)            -- sin id
ON DUPLICATE KEY UPDATE name=..., guard_name=..., created_at=..., updated_at=...
```
→ permiso existente: matchea por `name_guard_name`, **conserva su id del destino**;
   permiso nuevo: `id` por auto-increment. Cero conflicto de PRIMARY, cero 1062.

### Verificación (tablas desechables `_si_merge_test_*`, creadas y borradas)
- **Caso A (llave de negocio):** destino con `maps_site_add` en id=500 y otro permiso en
  id=373; "dump" trae `maps_site_add` con id=373 + un permiso nuevo. Resultado:
  `imported=2, errors=0`, `maps_site_add` sigue 1 sola fila en id=500, el otro permiso
  intacto en 373, el nuevo insertado con id auto-increment, total=3. **PASS.**
- **Caso B (identidad por PK):** destino ids 1–3; "dump" sobreescribe 2,3 y agrega 4,5.
  Resultado: `1=dest_1, 2=dump_2, 3=dump_3, 4=dump_4, 5=dump_5`, sin regresión de
  preservación de id. **PASS.**

### Consecuencia conocida (no abordada — decisión pendiente de Irving)
Al no forzar el `id` del dump en `permissions`/`roles`, las pivotes del dump
(`role_has_permissions`, `model_has_permissions`, `model_has_roles`) traen
`permission_id`/`role_id` con la numeración vieja del dump, que puede no coincidir con el
`id` real del destino. En un merge sobre una instalación con casi los mismos permisos por
nombre, esto puede dejar asignaciones de rol desalineadas. Arreglarlo requeriría remapear
esos ids viejo→nuevo en las pivotes (no incluido en este fix).
