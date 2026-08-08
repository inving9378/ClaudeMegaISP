# Doble fuente de "módulos" — diagnóstico (item #167)

Alcance de esta sesión (decidido por Irving el 2026-07-15, respuestas del brief
multi-pregunta #432 Fase 3): **q2 = Opción 1 "Solo diagnóstico"** — inventariar quién
lee/escribe cada tabla, documentar en Manual/docs y dejar plan escrito, **sin tocar
código**. La Fase 2 (ejecución de migración, q3) queda explícitamente **fuera de
alcance** para este item.

## Hallazgo principal: NO son la misma data duplicada

`modules` (legacy) y `module_registry` (nuevo) **no son dos copias de lo mismo**. Son
catálogos de **dominios distintos** que solo coinciden en la palabra "módulo":

| | `modules` (legacy) | `module_registry` (nuevo) |
|---|---|---|
| **Qué es** | Catálogo DB-driven de **formularios/campos** por entidad de negocio (CRM, clientes, tickets, planes, etc.) — el modelo `App\Models\Module` + `field_modules` que arma cada CRUD dinámicamente | Registro de **activación on/off de addons** (`app/Modules/{Core,Addons}/*/module.json`), leído por `ModuleManagerService` |
| **Filas hoy (dev)** | 117 (92 `is_main`) | 43 (41 activas) |
| **Quién lo llena** | Seeders/migraciones por entidad de negocio (`ModuleRepository`, seeders de Marketing, etc.) | `ModuleManagerService::manifests()` descubre `module.json` por convención de carpeta; el registry solo guarda el flag `active` por `slug` |
| **Consumidor típico** | `getfields()` → arma el formulario de un CRUD | `ModuleManagerService::isActive($slug)` → gatea rutas/registro de un addon completo |
| **Consumidores backend** | ~45 `*DatatableHelper` (`app/Http/HelpersModule/module/**`), `HelperController` (`getFieldsByModule`/`getColumnsByModule`/...), `ModuleRepository::MODULES_FOR_IMPORT`, seeders de Marketing, `SmartImportExport` | `ModuleManagerService`, `PortalPago\ModuleServiceProvider` (gate de rutas), `DevToolsController` (UI de addons), `SmartImportExport` (catálogo), `ModuleListCommand` |
| **Consumidores frontend** | **97 archivos** en `resources/js` llaman `/fields-by-module*` (backbone de ~60 CRUDs, ver `CLAUDE.md` "Catálogo de módulos (form config DB-driven)") | Ninguno directo — el efecto (addon activo/inactivo) se ve indirecto vía rutas/sidebar, no hay UI que lea `module_registry` como formulario |
| **Criticidad de tocarlo** | **Muy alta** — es la columna vertebral de la generación de formularios de todo el sistema | **Media** — gating de addons; si se rompe, un addon completo deja de cargar, pero no afecta el resto |

**El único punto donde ambas fuentes se tocan** es
`ManualGeneratorService::loadActiveModules()` (`app/Modules/Addons/Manual/Services/ManualGeneratorService.php:139-160`):
concatena las filas de `modules` (prioridad) + las filas de `module_registry` cuyo
`moduleSlug()` calculado **no** coincida ya con alguna de `modules` — solo para tener
UNA lista de "cosas documentables" al generar el Manual (`/manual`). No hay overlap de
datos reales: un addon activado por `module_registry` (p. ej. Flotas, CobranzaBlaster)
normalmente **no** tiene fila en `modules` (esa tabla es de formularios de negocio, no
de addons), así que ambos listados son casi disjuntos y la "fusión" es solo para no
perder ningún módulo al documentar.

## Consumidores de `modules` (legacy) — detalle

- **Modelo:** `app/Models/Module.php` — `getfields()`, `getfieldsRelation()`,
  `getColumnsDatatable()`; relación `fields()` → `field_modules` (896 filas hoy).
- **Backend (form config):** ~45 clases `app/Http/HelpersModule/module/**/*DatatableHelper.php`
  (una por entidad: CRM, clientes, tickets, planes, red, finanzas, administración...) +
  `app/Http/Controllers/.../HelperController.php` (`getFieldsByModule`,
  `getColumnsByModule`, `getColumnDtExpandByModule`, `setColumnDtExpandByModule`, 8
  guards de null documentados en `CLAUDE.md`).
- **Rutas:** `routes/web.php:141-144` (`/fields-by-module`,
  `/fields-by-module-and-relation`, `/fields-by-module-with-module-requested`).
- **Frontend:** 97 archivos en `resources/js` (`ComponentFormDefault.vue` y las ~60
  pantallas CRUD que lo envuelven).
- **Import/export:** `ModuleRepository::MODULES_FOR_IMPORT`, seeders de Marketing
  (`database/seeders/Marketing/MarketingModuleSeeder.php`), `SmartImportExport`
  (catálogo `modules`/`field_modules` en el merge inteligente).
- **Manual:** `ManualGeneratorService::loadActiveModules()` (prioridad) y
  `ManualController.php:37` (`$modulesMeta`).

## Consumidores de `module_registry` (nuevo) — detalle

- **Modelo:** `app/Modules/Core/ModuleManager/Models/ModuleRegistry.php` (`$table =
  'module_registry'`).
- **Servicio de activación:** `ModuleManagerService::manifests()` (descubre
  `module.json` en `app/Modules/{Core,Addons}/*/`) + `isActive($slug)` (fail-open a
  `true` si la tabla no existe o el slug no tiene fila — ver gap de Talento abajo).
- **Gate de addon:** `app/Modules/Addons/PortalPago/ModuleServiceProvider.php` (no
  registra rutas si el addon está inactivo).
- **UI de administración de addons:** `app/Modules/Addons/DevTools/Controllers/DevToolsController.php`.
- **Auto-regeneración del Manual al activar un addon:**
  `app/Modules/Addons/Manual/Observers/ModuleObserver.php` (dispara
  `RegenerateManualSectionJob` la primera vez que un módulo pasa a `active=true`).
- **Import/export:** `SmartImportExport` lo trata como catálogo por llave de negocio
  (`slug`), igual que `permissions`/`roles` (`config/smart_import.php:58`,
  `identity_priority=>override`).
- **CLI:** `app/Modules/Core/ModuleManager/Console/ModuleListCommand.php`.

### Gap confirmado (ya documentado en `CLAUDE.md`, verificado aquí)
`Talento` tiene `module.json` (`app/Modules/Addons/Talento/module.json`) pero **0
filas** en `module_registry` y **0 filas** en `modules` → nunca se documenta vía
Manual (ni por la ruta legacy ni por el registry). No es un bug de esta doble fuente,
es un módulo que nunca se dio de alta en ninguna de las dos tablas. Fuera de alcance
de este item (no se creó la fila — eso sería tocar datos, y el alcance de hoy es
solo diagnóstico).

## Respuesta a la pregunta de arquitectura de Irving (q1)

Irving eligió **"Opción 3: dejar ambas pero definir contrato claro (una maestra, la
otra proyección/caché sincronizada)"**. Con el hallazgo de arriba, ese contrato se
puede escribir así, **ajustado a la realidad** (no hay datos duplicados que
sincronizar, porque no son la misma data):

- **`modules` es maestra de su dominio** (formularios/campos de entidades de negocio)
  — nadie debe leerlo esperando addons, y nada debe escribirle basado en
  `module.json`.
- **`module_registry` es maestro de su dominio** (activación on/off de addons) — se
  reconstruye/sincroniza SOLO desde los manifiestos `module.json` en disco
  (`ModuleManagerService::manifests()` ya hace esto en cada request, no hay job de
  sync que mantener).
- El único acoplamiento real (`ManualGeneratorService::loadActiveModules()`) queda
  documentado como lo que es: un **merge de lectura, no de datos** — construye una
  lista de "qué documentar" tomando `modules` con prioridad y agregando de
  `module_registry` solo lo que `modules` no cubre ya (por `moduleSlug()`). No hay
  "caché" que se desincronice porque no persiste nada nuevo; se recalcula en cada
  `generate()`.
- **No aplica** ningún job de sincronización entre las dos tablas (q3 quedaría sin
  objeto si se ejecutara hoy) — no hay filas "faltantes" que copiar de una a la otra,
  porque representan cosas distintas. Si en el futuro se quisiera un catálogo único
  de "todo lo que el sistema puede documentar", la vía aditiva sería un adapter de
  **lectura** (como el que ya existe en `ManualGeneratorService`), no una migración de
  datos entre tablas.

## Plan si en el futuro se decide ejecutar algo (NO ejecutado, fuera de alcance hoy)

1. Si el objetivo real es "que el Manual documente el 100% de los módulos" (el
   síntoma que originó este item), el gap conocido es Talento (y cualquier addon
   nuevo sin fila en ninguna tabla) — la solución sería una migración aditiva
   `firstOrCreate` que dé de alta en `module_registry` los manifiestos que aún no
   tienen fila (patrón ya usado en `2026_07_01_000000_restore_document_template_client_module.php`
   para el mismo tipo de gap en `modules`). Esto es una migración de **alta de fila
   faltante**, no de "sync entre dos fuentes".
2. `moduleSlug()` no maneja acentos (`gesti-n-de-red`, `auditor-a`) — cambiarlo
   rompería slugs ya persistidos en `manual_sections.module_slug`; si se toca,
   requiere migración de rename de slugs existentes, no solo el cambio de la función.
3. Ninguno de los dos puntos anteriores se ejecuta en este item — quedan como
   hallazgos para un item futuro, si Irving decide que valen la pena.

## Cómo se auditó (reproducible)

```bash
# Consumidores de la tabla legacy `modules`:
grep -rn "table('modules')\|from('modules')" app/ resources/ routes/ config/ database/
grep -rl "\\Module::\|use App\\Models\\Module;" app/ resources/js | wc -l

# Consumidores de `module_registry`:
grep -rln "module_registry" app/ resources/ routes/ config/ database/

# Consumo del endpoint de formularios dinámicos desde el frontend:
grep -rl "get-fields\|getfields\|GetFieldsByModule" resources/js | wc -l   # 97

# Conteos en BD:
php artisan tinker --execute='echo DB::table("modules")->count();'              # 117
php artisan tinker --execute='echo DB::table("module_registry")->where("active",1)->count();'  # 41
php artisan tinker --execute='echo DB::table("field_modules")->count();'        # 896

# Gap de Talento (module.json sin fila en ninguna de las dos tablas):
php artisan tinker --execute='echo DB::table("module_registry")->where("slug","like","%talento%")->count();'  # 0
php artisan tinker --execute='echo DB::table("modules")->where("name","like","%alento%")->count();'            # 0
```

## Conclusión

Diagnóstico completo, sin cambios de código ni de datos. La "doble fuente" no es una
deuda de datos duplicados sino dos catálogos de dominios distintos con un solo punto
de merge de **lectura** (`ManualGeneratorService`), ya documentado y funcionando
correctamente. El contrato "una maestra, otra proyección" de la Opción 3 se traduce
en: cada tabla es maestra de su propio dominio, sin job de sync necesario. Los gaps
reales encontrados (Talento sin alta, acentos en `moduleSlug()`) quedan registrados
como posibles items futuros, no como parte de este diagnóstico.
