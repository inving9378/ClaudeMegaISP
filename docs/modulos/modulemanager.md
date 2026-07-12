# Módulo ModuleManager

> Registro y orquestación dinámica de módulos Core y Addons. `app/Modules/Core/ModuleManager/` · slug `core-module-manager` · core, sin dependencias, siempre activo (se fuerza a `true` en `moduleIsActive()` porque el resto del sistema depende de él para arrancar).

**En simple:** es el "panel de control" que sabe qué módulos tiene instalados el sistema, cuáles están prendidos o apagados, y arma automáticamente el menú, las tarjetas de administración y las secciones de configuración a partir de esa lista — sin que cada módulo tenga que registrarse a mano en el menú.

## 1. Qué es

Módulo Core que (a) descubre en disco todos los módulos del sistema (`app/Modules/Core/*` y `app/Modules/Addons/*`) leyendo su `module.json`, (b) lleva el registro de cuáles están instalados/activos en la tabla `module_registry`, y (c) compila los manifiestos de los módulos activos en colecciones listas para el frontend (menú, tarjetas, secciones de configuración, endpoints, contexto IA, pestañas de cliente, ayuda por pantalla).

## 2. Para qué sirve

Le resuelve a todo el sistema el problema de "¿qué módulos existen y qué declaran?" en un solo lugar, para que:
- El sidebar y el panel de Administración (`/admin/administracion`) no tengan que hardcodear cada módulo nuevo (salvo el sidebar Blade, ver nota abajo).
- Un módulo addon se pueda instalar/actualizar/desinstalar desde la UI (`/admin/modules`) sin tocar código: corre sus migraciones, registra sus permisos Spatie y los sincroniza a los roles base.
- Otros módulos (Manual, IA, ficha de Cliente) consuman de forma uniforme lo que cada módulo declara en su `module.json` (ayuda contextual, contexto para el asistente IA, pestañas de la ficha de cliente) sin acoplarse entre sí.

## 3. Cómo funciona

- **Descubrimiento** (`Services/ModuleManagerService.php`): `discoverProviders()` hace `glob` sobre `app/Modules/{Core,Addons}/*/ModuleServiceProvider.php`; `manifests()` lee el `module.json` que vive junto a cada provider encontrado. `isActive($slug)` consulta el mapa slug→activo cacheado en memoria de request, resuelto desde `module_registry` (si la tabla aún no existe —pre-migración— falla abierto y trata todo como activo).
- **Registro/estado** (`Models/ModuleRegistry.php`, tabla `module_registry`): una fila por módulo instalado — `slug`, `name`, `installed_version`, `type` (`core`/`addon`), `active`, `installed_at`. Los módulos core no requieren fila para funcionar (el descubrimiento por filesystem basta); la fila existe sobre todo para addons instalables y para el toggle activo/inactivo.
- **Ciclo de vida** (`Services/ModuleLifecycleService.php`): `install()`/`upgrade()`/`uninstall()` de un addon. Corre las migraciones del módulo (`{modulo}/migrations/`) **fuera de transacción** (DDL en MySQL hace commit implícito) y registra cada migración corrida en `module_migrations` (`Models/ModuleMigration.php`) para poder hacer rollback dirigido en la desinstalación; el resto (alta de permisos Spatie, fila de `module_registry`, hook `ModuleDefinition::install/upgrade/uninstall` si el módulo lo define, bitácora en el log `stack`) va en una transacción DML aparte. Valida dependencias declaradas (`dependencies[].slug`/`min_version`) antes de instalar y dependientes activos antes de desinstalar. `previewUninstall()` es de solo lectura: cuenta roles/usuarios que perderían permisos.
- **Compilador de áreas declarativas** (`Services/ModuleRegistry.php`, singleton por request, distinto del modelo Eloquent del mismo nombre): recorre los manifiestos de los módulos **activos** y arma, en una sola pasada cacheada, las colecciones `menu`, `admin_cards`, `config_sections` (agrupadas por `type`) / `config_sections_flat`, `api_endpoints`, `ai_context`, `client_tabs` / `client_tabs_deferred`, `service_types`, `screens` (ayuda por pantalla) y `sidebar_submenu` (children de módulos con `sidebar.location=submenu`, ej. Marketing dentro de Finanzas). `clearCache()` se llama tras install/upgrade/uninstall.
- **UI de administración** (`Controllers/ModuleManagerController.php`, vista `/admin/modules`): tabla de módulos con versión de manifiesto vs. instalada, si está "migrado" (heurística: tiene `.php` reales en `Controllers/`), tamaño en disco, y botones de instalar/actualizar/desinstalar/activar-desactivar que llaman al `ModuleLifecycleService`. Incluye además una función **no relacionada con el ciclo de vida**: `migrate()` envía el `MIGRATION.md` de un módulo (si existe) a la API de Claude para pedir un plan de refactor, y registra costo/tokens en `migration_logs` (`Models/MigrationLog.php`) — es una herramienta de apoyo para la migración manual de módulos legacy, no algo que corra solo.
- **Panel central de Administración** (`Controllers/AdminPanelController.php`, `/admin/administracion`): combina `admin_cards` declaradas en cada `module.json` con tiles **sintéticos** para módulos ocultos del sidebar (`ModuleSidebarConfig.show_in_sidebar=false` sin `admin_card` propia), dedupe por slug pelado de prefijo `core-`/`addon-`. Todo se filtra server-side por el permiso `.view` del módulo antes de devolverse. `configSections()` hace lo mismo para las secciones de Configuración, con gating adicional opcional por `role`.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **UI/rutas** bajo `admin/modules/*` (listar, migrar-con-IA, toggle, historial, preview/install/upgrade/uninstall, `registry/data`) y `admin/administracion/*` (panel + tarjetas), todas bajo `['web','auth','check_route_permission']`. Además `GET api/modules/config-sections` bajo `['web','auth']` (sin `check_route_permission`, filtra por permiso/rol dentro del controller).
- **Permisos** `admin_modules`, `admin_modules_migrate`, `admin_modules_toggle` (solo `super-administrator` + `DESARROLLADOR`).
- **Servicio compilador compartido** `Services\ModuleRegistry` (singleton, inyectable o `::instance()`) — es el punto único que traduce los `module.json` de todos los módulos activos a datos consumibles. Métodos públicos: `getMenu()`, `getAdminCards()`, `getConfigSections()`/`getConfigSectionsFlat()`, `getApiEndpoints()`, `getAiContext()`, `getClientTabs()`/`getClientTabsDeferred()`, `getServiceTypes()`, `getSubmenuItemsFor($parent)`, `getScreenHelp($url = null)`.
- **Tabla `module_registry`** (estado instalado/activo por módulo) y `module_migrations` (qué migración corrió para qué módulo, usada para rollback dirigido en desinstalación).
- **Consumidores reales de `Services\ModuleRegistry`** (grep en el repo):
  - `Core/Layout/ViewComposers/SidebarComposer.php` → `getMenu()` (variable `addonMenuItems`, hoy **no iterada** por `sidebar.blade.php`, que es Blade estático — ver nota) y `getSubmenuItemsFor('finanzas')` (sí se renderiza, para insertar hijos como Marketing dentro del menú Finanzas).
  - `Core/Clientes/Controllers/ClientController.php` → `getClientTabs()` para las pestañas dinámicas de la ficha de cliente.
  - `Http/Controllers/IA/IAChatController.php` → `getAiContext()` para inyectar sugerencias/contexto por módulo al asistente IA (solo lectura, no ejecuta acciones).
  - `Addons/Manual/Controllers/ManualController.php` → `getScreenHelp()` y `getConfigSectionsFlat()` para la ayuda contextual por pantalla del Manual de Usuario.
  - `AdminPanelController` (este mismo módulo) → `getAdminCards()`/`getConfigSectionsFlat()`.

**Consume**
- **Filesystem de módulos**: lee `module.json` y `ModuleServiceProvider.php` de cada módulo bajo `Core`/`Addons` — depende de que todo módulo siga esa convención de carpeta.
- **`App\Models\ModuleSidebarConfig`** — para los tiles sintéticos de módulos ocultos del sidebar en el panel de Administración.
- **`PermissionSyncService`** (`Core/Security`) — para sincronizar los permisos de un módulo recién instalado/actualizado a los roles base (`super-administrator`/`DESARROLLADOR` siempre; `.view` a todos los roles).
- **API de Claude** (`CLAUDE_API_KEY`/`CLAUDE_MODEL` de `.env`) — solo en la función de apoyo `migrate()`, para generar un plan de refactor a partir de `MIGRATION.md`; no es una integración crítica del ciclo de vida.
- Middleware estándar `check_route_permission` (mismo gating que el resto del sistema, sin verificador propio).

> **Nota de arquitectura (ya documentada en CLAUDE.md):** el sidebar visual (`app/Modules/Core/Layout/views/sidebar.blade.php`) es Blade **estático** con `@can`/`@hasanyrole` por módulo — el `getMenu()` de este compilador **no se itera ahí** (solo `getSubmenuItemsFor()` sí se usa, para los hijos de `location:submenu`). Cada módulo nuevo con entrada de menú propia debe agregarse a mano al Blade; `module.json.menu` por sí solo no basta para aparecer en el sidebar principal.
>
> Servicios compartidos únicos respetados: ModuleManager no monta cliente HTTP propio de IA salvo el uso puntual y documentado de Claude en `migrate()` (vía `.env`, no vía el módulo IA — deuda menor, no evaluada en este doc). Sin entrada propia en `docs/contratos/auto_apply.md` al momento de esta doc.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
