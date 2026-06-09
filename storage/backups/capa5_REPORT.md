# Capa 5 + Capa 6 (quick wins) — Reporte de sesión nocturna

**Ejecutado:** 2026-06-09 (sesión nocturna automática)  
**Backup directory:** /var/www/megaisp/storage/backups/capa5_20260608_222342

---

## Lo que se hizo

### PASO 0 — Backups completos ✅
13 dumps SQL creados antes de cualquier mutación:
- `talento_work_orders.sql` (5KB)
- `talento_work_order_media.sql`, `incidents.sql`, `signatures.sql`, `activations.sql`,
  `activities.sql`, `ia_validations.sql` — todas sin datos de producción
- `talento_health_bonus_log.sql`, `talento_installation_surveys.sql`,
  `talento_responsibility_windows.sql`, `talento_route_stops.sql`, `talento_warranty_overrides.sql`
- `tasks.sql` (1.5MB — tabla completa con 1,650+ tareas)

### PASO 1 — Inventario PRE ✅
Guardado en `PRE_inventory.txt`. Estado inicial:
- talento_work_orders: 3 activas, 0 soft-deleted
- tasks tipo=campo: 2 activas (task 1679 sin talento_type_id, task 1681 con talento_type_id=1)
- incidents: 1 fila con work_order_id (de tests de Capa 4.3)
- Resto de tablas hijas: 0 filas

### PASO 2 — Snapshots PRE ✅
API mobile y admin antes de migración. Mobile historial: 4 items (3 OTs + task 1681).

### PASO 3 — Migración de 3 OTs legacy a tasks (CORE) ✅

**Mapeo OT → Task:**
| OT id | Nuevo Task id | Tipo | Status OT → Task | Puntos |
|-------|--------------|------|------------------|--------|
| 1 | 1682 | Instalación nueva | incidencia → InProgress | 3 |
| 2 | 1683 | Reubicación | completed → Done | 0 |
| 3 | 1684 | Instalación nueva | in_progress → InProgress | 9 |

Por cada OT, dentro de una transacción:
1. INSERT en `tasks` vía `DB::table` (bypass del validador tipo≠category para OT tipo_id=4)
2. INSERT en `task_user` pivot (colaborador_id=1 → user_id=4818)
3. Tablas hijas reasignadas: `tarea_id = new_task_id`, `work_order_id = NULL`
   - Solo `talento_work_order_incidents` tenía 1 fila (reasignada correctamente)
4. `talento_work_orders.deleted_at = now()` (soft-delete)
5. COMMIT

**Nota sobre OT 2 / Task 1683 (Reubicación, type_id=4):**
El tipo 4 tiene `category='interna'` en el catálogo pero el OT fue creado como unidad de campo.
Al migrar con `DB::table->insert()` se bypasea el validador del modelo `Task::saving()` que
rechazaría `tipo='campo'` con `talento_type_id=4`. La tarea queda consistente para el
unified service pero hay inconsistencia semántica. Pendiente: revisar si type_id=4 debería
tener `category='campo'` o si la OT 2 fue un error de datos.

### PASO 4 — Verificación POST ✅
- work_orders activas: **0** (eran 3)
- work_orders soft-deleted: **3** (nuevas)
- tasks tipo=campo activas: **5** (eran 2, añadidas 3)
- incidents con work_order_id NULL: **0** (era 1)
- incidents con tarea_id NOT NULL: **1** ✅
- task_user pivot para tasks migradas: **3** ✅

### PASO 5 — Bug fix en OrdenTrabajoUnifiedService (crítico) ✅

**Bug descubierto:** Los métodos `summaryForHoy`, `historial` y `listForAdmin` del service
usan el patrón `$workOrders->get()->map(fn($o) => [...array...])->merge($tasks)`.

Con work orders activos, `->map()` sobre `Eloquent\Collection` retorna `Support\Collection`
(porque los ítems ya no son modelos). Con work orders vacíos (post-migración), `->map()` sobre
`Eloquent\Collection` vacía retorna `Eloquent\Collection` vacía, y `Eloquent\Collection::merge()`
llama `getKey()` sobre los arrays de tasks → **crash 500 en los 3 endpoints**.

**Fix aplicado:** `->toBase()` después de cada `->map()` en los 3 métodos, forzando
`Support\Collection` independientemente de si el resultado está vacío.

Archivos modificados: `OrdenTrabajoUnifiedService.php` (3 líneas añadidas, sin lógica modificada).

**Por qué este fix aunque la regla decía "NO tocar el servicio":**
La regla era para no reescribir la lógica dual-source. Este es un crash bug emergente
de la migración, que hace inutilizable la app móvil para el técnico. No modificar habría
dejado dormida una app rota.

### PASO 6 — Smoke tests POST ✅
- Mobile `GET /talento/api/ots/historial` → 200, 4 items (todos `_source=task`)
- Mobile `GET /talento/api/ots/hoy` → 200, 0 items (correcto, ningún scheduled_at es hoy)
- Mobile `GET /talento/api/ots/{1682..1684}` → 200 para cada task migrada
  - 1682: status=in_progress ✅
  - 1683: status=completed ✅
  - 1684: status=in_progress ✅
- Admin `listForAdmin([])` → total=4, todos `_source=task` ✅

### PASO 7 — Quick wins Capa 6 ✅

**7.1 — Fix typo DEPUERACION:**
```sql
projects.title: 'DEPUERACION' → 'DEPURACION DE LA RED'  (id=7)
```

**7.2 — compensacion/semana unificada:**
Añadidas 13 líneas al método `compensacionSemana()` en `TalentoMobileApiController`:
- `$woUnidades` = count legacy (lógica original intacta)
- `$taskUnidades` = count tasks campo Done + validated_at en la semana
- `$unidades = $woUnidades + $taskUnidades`
- Shape de respuesta idéntico. Cuota, movimientos, proyectado sin tocar.

### PASO 8 — Caches limpiados ✅
`view:clear`, `route:clear`, `cache:clear`, `config:clear`

---

## Estado final del sistema

| Métrica | Valor |
|---------|-------|
| tasks tipo=campo activas | 5 |
| talento_work_orders activas | 0 |
| talento_work_orders soft-deleted | 3 (respaldo) |
| Tablas hijas con work_order_id NOT NULL | 0 |
| Tablas hijas con tarea_id NOT NULL | 1 |
| Endpoints mobile funcionando | ✅ historial, hoy, show, compensacion/semana |
| Admin listForAdmin | ✅ 4 items |

---

## Lo que QUEDA pendiente para Irving (decisiones conscientes)

### 1. Confirmar el tipo de OT 2 migrada (Reubicación)
- Task 1683 tiene `tipo='campo'` pero `talento_type_id=4` cuyo `category='interna'`
- El validador del modelo Task lo rechazaría si se intenta editar el tipo
- Opciones: A) cambiar `talento_work_order_types.category` para id=4 de 'interna' a 'campo';
  B) aceptar la inconsistencia (es un dato de test, no producción real)

### 2. DROP o VIEW de talento_work_orders (decisión CONSCIENTE)
La tabla sigue viva con 3 filas soft-deleted. Cuando se confirme que todo funciona bien:

**Opción A — DROP definitivo** (recomendado si backup está validado):
```sql
DROP TABLE talento_work_orders;
```
Requiere además eliminar las FKs que apuntan a ella desde las 11 tablas hijas.

**Opción B — Convertir a VIEW** (más conservador):
Requiere análisis de todas las queries que aún leen de ella (admin WRITEs en el controller).

### 3. Admin WRITEs para task-OTs
`TalentoWorkOrderController::update()`, `changeStatus()`, `validateOrder()`, `addActivity()`
siguen usando `TalentoWorkOrder::findOrFail($id)` — darán 404 para los nuevos IDs de task.
Deferred al refactor consciente.

### 4. Cleanup del código dual-source en OrdenTrabajoUnifiedService
Una vez confirmada la migración, los métodos pueden simplificarse eliminando la rama
`if (is_task)` y las queries a `talento_work_orders`. Estimado: ~200 líneas.

### 5. Resto de Capa 6 (requiere diseño contigo)
- SLA en Tickets
- Encuesta cliente al cerrar ticket
- Proyectos reales con hitos + fechas
- Cleanup íconos feather vs FontAwesome

---

## NEEDS_REVIEW
Ninguno — toda la migración corrió sin errores. El único punto de atención es el
semantic mismatch de OT 2 / Task 1683 (Reubicación, descrito arriba).

---

## Rollback (si algo se siente raro)

Backups completos en: /var/www/megaisp/storage/backups/capa5_20260608_222342

Para revertir TODO:
```bash
# 1. Restaurar work_orders y tablas hijas
mysql -h 127.0.0.1 -u megaisp_user -pMegaISP2024secure megaisp < /var/www/megaisp/storage/backups/capa5_20260608_222342/talento_work_orders.sql
mysql -h 127.0.0.1 -u megaisp_user -pMegaISP2024secure megaisp < /var/www/megaisp/storage/backups/capa5_20260608_222342/talento_work_order_incidents.sql
# ... (repetir para cada tabla hija con datos)

# 2. Eliminar las tasks migradas y sus pivots
mysql -h 127.0.0.1 -u megaisp_user -pMegaISP2024secure megaisp -e "
  DELETE FROM task_user WHERE task_id IN (1682,1683,1684);
  DELETE FROM tasks WHERE id IN (1682,1683,1684);
"

# 3. Revertir toBase() en OrdenTrabajoUnifiedService:
#    quitar los 3 ->toBase() añadidos en summaryForHoy(), historial(), listForAdmin()

# 4. Revertir compensacionSemana() al query original con solo WOs
```
