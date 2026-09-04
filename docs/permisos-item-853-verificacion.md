# Verificación — item #853 (Fase B: declarar permisos para los 13 módulos activos sin catálogo)

El item #853 pedía cruzar la lista de 13 módulos activos sin bloque `"permissions"` en su
`module.json` (inventario hecho por una vuelta previa de wt-3) contra la "tabla C" (recursos
desprotegidos) de la auditoría #845/#846, y **declarar los permisos faltantes** para los que
resultaran con rutas/pantallas reales sin protección — vía `PermissionSyncService`, siguiendo
la Opción 1 (recomendada, elegida por Irving en `q1`): solo permisos **ya en uso**, sin
inventar permisos nuevos.

**Conclusión tras el cruce: ninguno de los 13 módulos requiere declarar permisos.** No es que
falte hacerlo — es que, módulo por módulo, ya están protegidos por el mecanismo correcto (y,
para los `core-*`, el propio bloque `"permissions"` de `module.json` sería un no-op). Detalle:

## 0. La "tabla C" de #845/#846 (aún sin mergear a main, leída directo de sus ramas)

#845 y #846 están `aprobado_irving` con trabajo terminado en sus ramas
(`circuito/item-845-auditoria-de-permisos-fase-1-catalogo`,
`circuito/item-846-auditoria-de-permisos-fase-2-caso-0-r`) pero **sin mergear** — el flag
`esperando_merge_irving` está activo en ambos. `docs/permisos/AUDITORIA-PERMISOS-2026-09-01.md`
(rama #845) sí existe y trae la Tabla C (C1 rutas con sesión sin `check_route_permission`, C2
prefijos de permiso sin entrada en `config/route_permission.php`, C3 UI de asignación). Se leyó
con `git show <rama>:<archivo>` sin mergear nada — el contenido ya está escrito, solo falta el
merge de Irving, que es justo lo que #853 exige NO adivinar.

Cruzando los controladores/namespaces de los 13 módulos contra C1/C2: **DevTools, Localizacion,
CRM, Auditoria, Documentacion (core), Clientes y Release no aparecen** (0 matches) — sus rutas
ya pasan por `check_route_permission` con permisos ya existentes en `route_permission.php`
(confirmado leyendo cada `routes.php` directamente, no solo el grep del audit). ModuleManager,
Layout, Dashboard y Usuarios sí aparecen con algunas rutas puntuales — ver §2.

## 1. Los 2 módulos `addon-*` — protegidos por mecanismos DISTINTOS al catálogo central, a propósito

- **`addon-devtools`**: sus rutas usan `role:DESARROLLADOR|super-administrator` directo
  (`app/Modules/Addons/DevTools/routes.php:24`), con comentario explícito en el archivo:
  "NO se incluye `check_route_permission` para que cualquier DESARROLLADOR o
  super-administrator pueda entrar sin permisos por URL adicionales." Decisión de diseño ya
  tomada, no un hueco.
- **`addon-portal-cliente`**: usa el guard `cliente` (`auth.portal`), un sistema de
  autenticación/autorización completamente separado del admin (multi-tenant por `client_id`,
  documentado en CLAUDE.md §PORTAL CLIENTE). No participa del catálogo de permisos Spatie.

Ninguno de los dos tiene "permisos ya en uso" que declarar en el sentido que pide la Opción 1
— su protección no pasa por `Permission`/`Gate`.

## 2. Los 11 módulos `core-*` — ya protegidos por `check_route_permission`, y su `module.json` no alimenta ningún sync

Verificado archivo por archivo (`routes.php` de cada uno): `core-module-manager`, `core-layout`
(vía su bloque bajo `web.php`… ver abajo), `core-crm`, `core-dashboard`, `core-usuarios`,
`core-auditoria`, `core-auth`, `core-documentacion`, `core-clientes` y `core-release` protegen
su superficie principal con `Route::middleware(['web','auth','check_route_permission'])`, y los
permisos que cubren esas rutas (`admin_modules`, `role_view_role`, `user_view_user`,
`client_view_client`, `documentation_view_documentation`, etc.) **ya existen en BD y en
`config/route_permission.php`** — no hay nada nuevo que crear.

Las excepciones puntuales encontradas en C1 (rutas de estos módulos sin `check_route_permission`)
son, sin excepción, **defensa intencional documentada en el propio código**, no huecos:

| Ruta | Módulo | Por qué está fuera de `check_route_permission` |
|---|---|---|
| `/save-app-config-layout`, `/save-row-status-style`, `/get-config-tabs`, `/set-config-tabs` | core-layout | Preferencia de UI por-usuario (comentario en `Layout/routes.php`: preserva la distribución legacy tal cual, nunca estuvo en el grupo con permiso) |
| `/home` | core-dashboard | Comentario en `Dashboard/routes.php`: "estaba fuera del grupo de check_route_permission… se preserva esa distribución exactamente" — dashboard post-login para cualquier autenticado |
| `/api/modules/config-sections`, `/api/modules/config-moved-sections` | core-module-manager | Filtran su propio resultado **dentro del controller** (`AdminPanelController::configSections/configMovedSections`, closures `$roleOk`/`$can` que chequean rol/permiso por ítem antes de devolverlo) — defensa en profundidad ya presente, no exposición cruda |
| `/permissions-auth`, `/has-permission-to-view/{view}`, `/all-view-has-permission` | core-usuarios | Comentario explícito en `Usuarios/routes.php`: "son los endpoints que VERIFICAN permisos, así que no pueden depender de ellos" — problema del huevo y la gallina, exento por diseño |
| `/profile/password`, `/profile/change-password` | (controller vive en core-usuarios, pero la ruta está en `routes/web.php`, no en el módulo) | Comentario explícito: "Self-service: cambio de la PROPIA contraseña (cualquier usuario autenticado, sin check_route_permission)" |

Ninguna de estas necesita un permiso **nuevo** — inventar uno contradiría la Opción 1 elegida
(que descarta explícitamente la Opción 2, "declarar catálogo completo… crea permisos no
usados"), y todas están documentadas en el propio código como abiertas a propósito.

## 3. Hallazgo aparte: aunque se declarara `"permissions"` en un `module.json` de `core-*`, no tendría efecto

Revisando el único consumidor real del bloque `"permissions"` del manifest,
`PermissionSyncService::syncFromModuleManifests()` (`app/Modules/Core/Security/Services/
PermissionSyncService.php:88`):

```php
$manifests = glob(base_path('app/Modules/Addons/*/module.json'));
```

Solo escanea `Addons/*` — nunca `Core/*`. Y el otro consumidor de manifiesto,
`ModuleLifecycleService::install()`, rechaza módulos `core` explícitamente
(`"El módulo '{slug}' es core y se gestiona automáticamente."`, línea 62). Es decir: los 11
módulos `core-*` gestionan sus permisos **por migración directa** (el patrón real y ya
funcionando: `2026_05_15_000001_add_permission_admin_modules.php`,
`2026_06_02_…grant_conciliacion_manage_permission`, etc.) — declarar `"permissions"` en su
`module.json` sería un bloque inerte que nadie lee, no una mejora real de catálogo. No se tocó
`PermissionSyncService` (ampliar su glob a `Core/*` es aditivo y de bajo riesgo, pero está fuera
del alcance de #853 — que pide declarar permisos donde la auditoría confirma un hueco real, no
ampliar el propio mecanismo de sync; queda anotado aquí por si se retoma).

## Guardrails de #842 — respetados

No se borró ni revocó ningún permiso, no se tocó `role_has_permissions`/`model_has_permissions`,
no se auto-otorgó `.view` a roles base, no se declaró ningún `module.json` nuevo. Cero cambio de
código de negocio — el trabajo fue el cruce mismo que el item pedía, y su resultado es que no
hay nada que declarar.

**Sin cambio de código.**
