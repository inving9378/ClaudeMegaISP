# GestionRed OLT — mecanismos de autorización (item #287)

Diagnóstico: conviven **tres** mecanismos de autorización en el proyecto sin una
convención única. Este documento los mapea; no propone unificarlos (ver Opción C
descartada en el análisis de riesgo del item — refactor grande, no aditivo).

## 1. `check_route_permission` (middleware, gating por config)

- Aplica a **casi todas** las rutas (`routes/web.php` y los `routes.php` modulares
  bajo `Route::middleware(['web', 'auth', 'check_route_permission'])`).
- La granularidad vive en `config/route_permission.php`: cada permiso mapea a una
  lista de paths de ruta.
- Usado por: **GestionRed OLT completo** (`OLTsController`, `OLTsOnuController`,
  la mayoría de `Flotas`).
- Frágil porque la granularidad depende de mantener la lista de paths sincronizada
  con `routes.php` a mano; una ruta nueva sin entrada en el config queda sin
  gating real de permiso (aunque sigue exigiendo `auth`).

## 2. Middleware Spatie `permission:` por ruta

- Ejemplo: `Flotas` (`routes.php:154-176`) usa
  `->middleware('permission:fleet.subscriptions.manage')` en vez de depender de
  `check_route_permission`/`route_permission.php`.
- Es el mecanismo más directo (permiso amarrado a la definición de ruta), pero
  **no** es el que usa GestionRed OLT.

## 3. `authorize()` / `auth()->user()->can()` en el controlador

- Defensa en profundidad: revalida el permiso **dentro** del método, no solo en
  el middleware de la ruta.
- Ya existía en GestionRed antes de este item, en 3 sitios:
  - `OLTsProvisionController::preview()` — `auth()->user()->can('gestion-red.provision.preview')`.
  - `OLTsOnuController::changeWebUserPass()` — `auth()->user()->can('onu_edit')`.
  - `OLTsOnuController::setCATV()` — `auth()->user()->can('onu_edit')`.
- `Flotas` usa la variante `$this->authorize('fleet.xxx')` (trait
  `AuthorizesRequests`) en casi todos sus controllers — equivalente en efecto a
  `auth()->user()->can()` porque spatie/laravel-permission registra el check de
  permisos sobre el `Gate`, pero con un formato de respuesta distinto (excepción
  `AuthorizationException` en vez de un `return response()->json(..., 403)` manual).
- `OLTsController` (listado/acciones de ONU vía nombres como
  `enableDisableOnu`/`resyncOnuConfig`/`rebootOnu`/`moveOnu`) y el resto de
  `OLTsOnuController` **no** tenían esta capa — dependían 100% del middleware de
  ruta. Es el gap que cierra la FASE 2 de este item, acotado a
  `OLTsOnuController`.

## Mapa de uso por módulo (resumen)

| Módulo | Mecanismo 1 (middleware+config) | Mecanismo 2 (Spatie `permission:`) | Mecanismo 3 (`authorize()`/`can()` en controller) |
|---|---|---|---|
| GestionRed OLT | ✅ todas las rutas | ❌ no se usa | ⚠️ solo 3 métodos (antes de este item) |
| Flotas | ✅ la mayoría | ✅ solo suscripciones | ✅ prácticamente todos los métodos |

## FASE 2 aplicada — `OLTsOnuController`

Se agregó `auth()->user()->can(<permiso>)` (mismo patrón ya usado en
`changeWebUserPass`/`setCATV`/`OLTsProvisionController::preview`, **no** Policies
de Laravel — evita el riesgo señalado en el análisis de riesgo del item de que un
`$this->authorize()` con Policy inexistente falle cerrado) como red de respaldo en
los métodos de escritura que el middleware ya gatea vía
`config/route_permission.php`, usando **exactamente el mismo permiso** que la
ruta correspondiente para no cambiar el acceso efectivo de nadie:

| Método | Permiso (igual al de la ruta en `route_permission.php`) |
|---|---|
| `store` | `onu_add` |
| `remove` | `onu_remove` |
| `updateServicePort` | `onu_edit` |
| `configureEhernetPort` | `onu_edit` |
| `configureWifiPort` | `onu_edit` |
| `changeAttachedVlans` | `onu_edit` |
| `setOnuVoipPort` | `onu_edit` |
| `updateChannel` | `onu_edit` |
| `updateMgmtAndVoIp` | `onu_edit` |
| `updateMode` | `onu_edit` |
| `changeOnuType` | `onu_edit` |
| `updateExternalId` | `onu_edit` |

**Deliberadamente NO tocados:** `getMgmTIp`, `getIpAddress`, `sync`, `getFullStatus`,
`getRunningConfig` — aunque alguno persiste datos como efecto lateral, en
`config/route_permission.php` están clasificados bajo `olt_view` (lectura), no
bajo `onu_edit`. Agregarles el check `onu_edit` habría sido una restricción
**nueva** para usuarios que hoy solo tienen `olt_view` — fuera del alcance
aditivo/reversible del item (habría cambiado el acceso efectivo, no solo
añadido defensa en profundidad).

No se removió el middleware ni se tocó `config/route_permission.php`. Cambio
puramente aditivo y reversible (quitar las líneas `auth()->user()->can(...)`
restaura el estado anterior sin efecto en rutas ni permisos).
