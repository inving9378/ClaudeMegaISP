# Auditoría de permisos — Fase 2: Caso 0 reproducible (Alondra Nimy / Inventario > Almacenes)

**Roadmap:** item #846 (sub-item de #841). Generado: 2026-09-01. Solo lectura sobre datos
reales — cero cambios de código de negocio, cero migraciones, cero permisos/roles reales
tocados. Lo único que se escribió es un **usuario de prueba** (`prueba_auditoria_caso0_alondra`,
rol `Almacen`), nunca ALONDRA ni ningún dato real. **PRODUCCIÓN no se tocó.**

**Coordinación con Fase 1 (#841/#845):** el catálogo general permiso→qué protege vive en
`storage/app/auditoria/permisos-20260901-1433.md` (no comiteado a `docs/` todavía). Este
documento es un archivo **nuevo y específico** del Caso 0 dentro de `docs/permisos/` — no
duplica ese catálogo, lo complementa citando el permiso puntual (`inventory_store_view_inventory_store`)
que ya aparece ahí.

**Reproducible con:**
```bash
php artisan auditoria:permisos:caso0          # tabla + narrativa en pantalla
php artisan auditoria:permisos:caso0 --json   # + JSON en storage/app/auditoria/
```
Comando: `app/Modules/Core/Auditoria/Console/AuditoriaPermisosCaso0Command.php`.

---

## ⚠️ Nota sobre el guardrail (dev ≠ prod)

DEV y PROD son bases que divergieron (ver CLAUDE.md, "CRÍTICO — los `user_id` de DEV y PROD NO
coinciden"). **No existe ningún usuario "ALONDRA NIMY" en la base de dev** — se verificó por
`name LIKE '%ALONDRA%'`/`%NIMY%`, cero coincidencias exactas. Por lo tanto **no se puede leer su
rol/permisos reales de prod desde aquí**.

El guardrail del item pide crear "un usuario de prueba con el mismo rol y las mismas
asignaciones que ALONDRA" — como su rol real es desconocido en dev, se construyó un usuario de
prueba (`prueba_auditoria_caso0_alondra`, id=4871 en dev, rol `Almacen`) con el **mínimo que
replica el síntoma reportado tal cual se describe**: permiso de VISTA sobre Almacenes (para que
el menú y la ruta la dejen pasar) sin ser "responsable" de ningún almacén (para que la tabla
devuelva 0 filas). El comando `auditoria:permisos:caso0` lo crea/reutiliza de forma idempotente
y lo dice explícitamente en su salida. Si en algún momento se obtiene el rol real de Alondra en
prod, ajustar el rol de este usuario de prueba a ese rol exacto es el único cambio necesario
para una reproducción 1:1.

---

## Reproducción (verificada en dev, `auditoria:permisos:caso0`)

| Punto de control | Admin | Prueba (rol `Almacen`) |
|---|---|---|
| Menú "Inventario" visible | sí (bypass `isAdmin()`) | sí (por permiso) |
| Submenú "Almacenes > Listar" visible | sí (bypass `isAdmin()`) | sí (por permiso) |
| Ruta `GET /inventory/inventory_store` | pasa (`isAdmin()`) | pasa (permiso `inventory_store_view_inventory_store`) |
| ¿Es "responsable" de algún almacén? (`inventory_stores.user_id`) | n/a (bypass) | NO |
| Filas devueltas por `table()`/`count()` | **3** | **0** |
| Permiso `inventory_store_add_inventory_store` | sí (bypass `isAdmin()`) | NO |

Coincide exactamente con el reporte de producción: "el menú la deja pasar, la ruta la deja
pasar, y la tabla devuelve 'No hay elementos para mostrar'" — con Admin viendo 3 almacenes.

---

## 1) ¿Qué permiso protege la ruta y el menú de esta pantalla?

`inventory_store_view_inventory_store` — un único permiso gatea **ambas** cosas:

- **Ruta:** `config/route_permission.php:1072` → `['/inventory/inventory_store', '/inventory/inventory_store/table']`, evaluado por el middleware `App\Modules\Core\Auth\Middleware\CheckRoutePermission` (`handle()`, paso 4).
- **Menú:** `resources/views/module-sidebar/inventario.blade.php:21` → `@if(auth()->user()->can('inventory_store_view_inventory_store'))` alrededor del link "Almacenes > Listar".

Este permiso **no tiene ninguna relación con qué filas se ven** — solo decide si la pantalla
carga. Ese es precisamente el hueco: pasar el gate de pantalla no garantiza ver datos.

## 2) ¿Por qué la consulta devuelve 0 filas? (archivo y línea exactos)

**No es un scope del modelo.** `App\Models\InventoryStore` no declara ningún global scope ni
`scopeUserId` — su único scope (`scopeFilters`, ver `app/Models/InventoryStore.php:38-83`) es de
búsqueda/filtro de columnas, sin restricción por usuario.

**Es un `where` manual repetido en el datatable helper**, en las 4 rutas de consulta que usa la
tabla del listado:

`app/Http/HelpersModule/module/inventory/inventorystore/InventoryStoreDatatableHelper.php`

| Método | Línea | Código |
|---|---|---|
| `count()` | 22-23 (con filtros) y 28-29 (sin filtros) | `if (!auth()->user()->isAdmin() && !auth()->user()->isSuperAdmin()) { $query->where('user_id', auth()->user()->id); }` |
| `ordering_query()` | 42-43 (con filtros) y 52-53 (sin filtros) | ídem |
| `searching_query()` | 66-68 | ídem, pero **inalcanzable** — ver nota de código muerto abajo |
| `filtering_query()` | 76-77 | ídem |

`InventoryStoreController::table()` (`app/Modules/Addons/Inventario/Controllers/InventoryStore/InventoryStoreController.php`)
delega directo a este helper (`$this->helper->fetch_datatable_data($request)`), que internamente
llama a `count()`/`ordering_query()`/`searching_query()` según haya paginación/orden/búsqueda.

`inventory_stores.user_id` es la columna **"responsable"** del almacén: FK a `users`, **un solo
usuario por almacén**, sin tabla pivote (`app/Models/InventoryStore.php` — `fillable = ['name',
'description', 'user_id']`; migración `database/migrations/2025_01_22_114321_create_inventory_stores_table.php`).
`isAdmin()`/`isSuperAdmin()` (`app/Models/User.php:166-174`) son `true` únicamente para los roles
`super-administrator` / `Super Administrador` / `Administrador` / `DESARROLLADOR`.

**Conclusión:** cualquier usuario con el permiso de VISTA pero que:
1. no sea de esos 4 roles admin, **y**
2. no sea `user_id` (responsable) de ningún almacén,

...pasa el gate de pantalla y cae directo en el `where('user_id', auth()->id())` → 0 filas. En
dev hay 3 almacenes (`Generic Store`→user 1, `Bodega Santa Ana`→user 4128, `Oficina Santa Ines`→user 4128) —
ninguno pertenece a la usuaria de prueba, igual que (asumiblemente) ninguno pertenecía a Alondra
en prod.

*Nota menor de código muerto, no afecta el bug:* `searching_query()` (método completo en líneas
58-71) tiene un `return` en la línea 60 antes del `if`, así que su bloque `!isAdmin()...`
(líneas 66-70) nunca se ejecuta —
inofensivo porque `searching_query()` en la práctica ya recibe filas correctamente acotadas
porque `count()`/`ordering_query()` sí filtran, pero es una inconsistencia interna del archivo.
Fuera de alcance de este Caso 0 (diagnóstico, no remediación — decisión q1 del item).

## 3) ¿Este criterio de filtrado está representado como permiso asignable?

**NO.** Hallazgo tipo **"alcance de datos no asignable"**: el criterio que decide qué almacenes
ve cada usuario (ser su "responsable") **no es un permiso Spatie, ni un rol, ni tiene ninguna
pantalla de asignación**. Se resuelve leyendo directo `inventory_stores.user_id` en BD. No hay
forma —desde la UI de administración de roles/permisos— de saber que ese criterio existe, de
ver qué almacén "pertenece" a quién, ni de asignar a alguien como responsable salvo editando el
almacén uno por uno (campo `user_id` del formulario CRUD, sin ningún indicador de que ese campo
determina visibilidad de todo el resto del inventario).

Además, `inventory_stores.user_id` admite **un solo responsable**: no hay concepto de "equipo
del almacén" — si dos personas deben ver el mismo almacén, hoy es imposible sin que ambas sean
`isAdmin()`.

### Barrido: ¿todos los demás casos iguales en el sistema?

Se buscó (comando `auditoria:permisos:caso0`, sección "Barrido", + verificación manual) el mismo
patrón — **una consulta que recorta filas por usuario, activada solo cuando el usuario no es
admin, sin que ningún permiso lo gobierne** — en `app/Http/HelpersModule/` y
`app/Http/Traits/Models/` (las dos carpetas donde viven las queries de listados/scopes).

**Casos con el MISMO patrón (no gobernado — mismo hallazgo que Almacenes):**

| Archivo | Líneas | Detalle |
|---|---|---|
| `app/Http/HelpersModule/module/inventory/inventorystore/InventoryStoreDatatableHelper.php` | 22-77 | Caso 0 mismo (arriba). |
| `app/Http/HelpersModule/module/inventory/inventoryitemstock/InventoryItemStockDatatableHelper.php` | 30-48 (`count()`), replicado en `ordering_query()`/`searching_query()`/`filtering_query()` | **Pantalla "Artículos" del mismo módulo Inventario.** Mismo guard `if (!($user->isAdmin() \|\| $user->isSuperAdmin()))`, pero la forma de filtro es distinta: solo ve artículos con `modelable_type=User AND modelable_id=<yo>` (asignados a mi persona) **O** `modelable_type=InventoryStore AND modelable_id=<almacén del que soy responsable>` (vía `authUserEsResponsableDeAlmacenDevuelveAlmacen()`, línea 199-202, que a su vez hace `InventoryStore::where('user_id', auth()->id())->first()` — el mismo criterio "responsable" del Caso 0). **No lo detecta el barrido automático** del comando (busca literalmente `where('user_id', auth()...)`; este archivo filtra por `modelable_type`/`modelable_id`, forma distinta del mismo criterio) — encontrado por revisión manual del mismo archivo. |
| `app/Http/Traits/Models/Inventory/InventoryItemStock/ScopeInventoryItemStock.php` | 67-87 (`scopeFilters`) | Es el **scope real** que usa el modelo `InventoryItemStock` (`InventoryItemStock::filters(...)`, consumido por el helper de arriba) — mismo guard, misma doble condición (propietario directo O responsable del almacén). Confirma que el patrón vive tanto en el helper del datatable como en el scope del modelo para esta pantalla. |

**Caso con guard similar pero SÍ gobernado por permiso (no es un hallazgo — sirve de contraste):**

| Archivo | Líneas | Por qué SÍ está bien |
|---|---|---|
| `app/Http/HelpersModule/module/scheduling/task/TaskDatatableHelper.php` | 28-32, 37-41, 53-55, 70-74, 82-86 | El listado de Tareas (`Scheduling > Tareas`) tiene la MISMA forma de guard (`isAdmin()`/`isSuperAdmin()` + `where` por usuario), pero el bypass **exige además** `auth()->user()->can('task_view_full_task')` — sí existe un permiso Spatie asignable que gobierna quién ve las tareas de todos vs. solo las propias. Esta es la forma correcta del patrón; Inventario debería seguirla. |

**Caso relacionado pero de otra clase (no es "alcance de datos", es visibilidad de botón):**

| Archivo | Línea | Detalle |
|---|---|---|
| `app/Http/HelpersModule/module/network/NetworkDatatableHelper.php` | 68 (`if ($this->isAdmin())`) | No filtra FILAS — oculta la columna de acciones (editar/eliminar) del listado de Red para no-admin. Mismo estilo de gate hardcodeado (`isAdmin()` sin permiso), pero el efecto es UI (botones), no datos ocultos. Se anota por si se decide unificar el patrón en una fase de remediación futura, pero no es del mismo tipo que el Caso 0. |

**Alcance del barrido:** limitado a `app/Http/HelpersModule/` y `app/Http/Traits/Models/`
(carpetas estándar donde viven las queries de listados y los scopes de modelo en este proyecto).
No se barrieron controladores sueltos ni Livewire/Vue — coherente con el alcance del Caso 0
(decisión q2 del item: acotado a Alondra/Inventario Almacenes, con estructura reutilizable para
Casos 1..N). El barrido automático del comando es un **heurístico de regex**, no un analizador
estático: solo detecta la forma literal `where('user_id', auth()...)`; el caso de "Artículos"
usa `modelable_type`/`modelable_id` y requirió revisión manual — se deja documentado como
limitación conocida, no oculta.

## 4) ¿Por qué el botón "Agregar" sigue visible para un usuario que no ve filas?

`resources/js/components/module/inventory/inventory_store/InventoryStoreListar.vue:10-17`:

```html
<button type="button" class="btn btn-outline-primary waves-effect waves-light"
    data-bs-toggle="modal" data-bs-target="#crudinventorystore">
    Agregar
</button>
```

Es un `<button>` **estático**, sin `v-if` ni la directiva `v-hasPermission` que sí usan otros
CRUDs del sistema (Vuex carga los permisos del usuario en boot vía `GET /permissions-auth`,
justo para esto). Se renderiza igual para cualquiera que llegue a la pantalla, tenga o no
`inventory_store_add_inventory_store`.

**El backend sí está protegido** — verificado con la usuaria de prueba (sin ese permiso):
`POST /inventory/inventory_store/add` está gateado en `config/route_permission.php:1073`, y
`CheckRoutePermission` la bloquea: 403 JSON si la llamada es AJAX (el flujo real del modal, vía
axios), o redirect silencioso si fuera navegación de página completa
(`app/Modules/Core/Auth/Middleware/CheckRoutePermission.php:82-120`, política de denegación
silenciosa del item #537). **Es un gap de UX/consistencia visual, no un hoyo de seguridad**: el
peor caso es que la usuaria vea un botón que, al usarlo, le devuelve un error.

## 5) ¿El menú lateral sale de permisos o de una lista fija? (Admin vs. Alondra)

**Las dos cosas, en dos capas** (esto actualiza la nota de CLAUDE.md sobre el sidebar, que
describe una versión anterior "híbrida hardcoded + `$addonMenuItems`"; el estado actual —
verificado en `app/Modules/Core/Layout/views/sidebar.blade.php:1-70` — es un sistema más nuevo,
"Fase 2.2 dinámico", donde **todas** las entradas de primer nivel, incluidas Dashboard/Clientes/
Finanzas, ya salen de `$sidebarItems`, no solo los módulos nuevos):

- **Capa 1 — ¿qué entradas EXISTEN en el sidebar?** `app/Modules/Core/Layout/ViewComposers/SidebarComposer.php:23-48`
  lee la tabla `module_sidebar_config` (modelo `ModuleSidebarConfig::visibleInSidebar()`, cacheado
  60s) y arma `$sidebarItems` agrupado por `module_key`. **Esto es una lista fija de
  configuración** — no depende del usuario que mira la pantalla, solo de si el módulo está
  marcado visible en BD. Decide si "Inventario" existe como entrada en absoluto.
- **Capa 2 — ¿qué VE cada usuario dentro de esa entrada?** El partial
  `resources/views/module-sidebar/inventario.blade.php` envuelve cada rama en Blade
  `@canany`/`@can` (líneas 2, 14, 21, 31, 38, 48, 55, 62, 72, 79, 89, 96, 103, 110, 117, 134) —
  esto **sí es permisos Spatie reales**, evaluados usuario por usuario.

**Comparación Admin vs. usuaria de prueba (equivalente a Alondra):**

| | Admin | Alondra (prueba) |
|---|---|---|
| Ve la entrada "Inventario" (capa 1) | sí — módulo visible en `module_sidebar_config` para todos | sí — mismo, no depende del usuario |
| Ve "Almacenes" y su "Listar" (capa 2) | sí, por el **bypass** `isAdmin()`/`isSuperAdmin()` que Spatie resuelve automáticamente en cada `@can` (`Gate::before` efectivo vía los métodos de `User`) | sí, porque **sí tiene** el permiso `inventory_store_view_inventory_store` — el mismo permiso que abre la ruta |

**Conclusión:** el menú **no es la causa** del "0 filas" — el menú (y la ruta) hacen exactamente
lo que deben: dejar pasar a quien tiene el permiso de vista. El bug vive enteramente en el paso
siguiente (el `where` del datatable helper, punto 2).

---

## Resumen para `comentarios_claude`

Ver el registro de cierre del item #846 en la Hoja de Ruta — reproduce este mismo resumen en
lenguaje llano.
