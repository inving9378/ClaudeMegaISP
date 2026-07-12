# Verificación de permisos OLT — item #414 (Fase 3a-bis)

El item #414 (derivado de la auditoría #57, hallazgo H5) partía de la premisa de que
`OLTsController`/`OLTsOnuController` usan un **verificador de permisos propio**, ajeno a
`config('route_permission')`, que lee solo permisos **directos** y por eso quedó fuera del
flip directos∪rol aplicado en Fase 3a (`PermissionTrait::getPermissionForUserAuthenticated`,
commit `708bcba0`).

**Esa premisa es incorrecta.** Investigación (2026-07-12):

## 1. Capa de ruta (mecanismo principal — cubre `olt_view`, `onu_add`, `onu_edit`)

Las rutas de `routes.php` de GestionRed van bajo el mismo middleware que el resto del
sistema:

```php
Route::middleware(['web', 'auth', 'check_route_permission'])->prefix('olts')->group(...)
```

`config/route_permission.php:450-500` mapea `olt_view` (~40 paths), `onu_add` y `onu_edit`
a rutas concretas de `OLTsController`/`OLTsOnuController`. `check_route_permission`
(`App\Modules\Core\Auth\Middleware\CheckRoutePermission`) consume
`PermissionTrait::getPermissionForUserAuthenticated()` — el **mismo trait compartido** que
ya se corrigió en Fase 3a (línea 24: `$user->getAllPermissions()`, directos∪rol). GestionRed
OLT no tiene su propio middleware/gate: usa el mecanismo 1 documentado en
`docs/gestionred-olt-mecanismos-autorizacion.md` (item #287), igual que el resto del sistema.
Como el trait es compartido, el flip de Fase 3a ya cubrió estas rutas sin cambio adicional.

## 2. Defensa en profundidad inline (`OLTsOnuController`)

Además del middleware, varios métodos de escritura revalidan el permiso dentro del
controlador (agregado en item #287, FASE 2):

```php
if (! auth()->user()->can('onu_add')) { ... }   // línea 107
if (! auth()->user()->can('onu_edit')) { ... }  // líneas 325, 356, 387, 418, 452, 483, 514, 559, 604, 666, 746, 770
```

`auth()->user()->can($permiso)` resuelve vía `Gate::before` que registra
`spatie/laravel-permission` (`PermissionRegistrar::registerPermissions`), el cual llama
`checkPermissionTo()` → `hasPermissionTo()`:

```php
// vendor/spatie/laravel-permission/src/Traits/HasPermissions.php:212
return $this->hasDirectPermission($permission) || $this->hasPermissionViaRole($permission);
```

Esto **siempre** evaluó directos∪rol — no es algo que dependiera del flip de Fase 3a (ese
flip era específico de `PermissionTrait`, que antes leía `$user->permissions`, propiedad
Eloquent de solo permisos directos). `can()`/`hasPermissionTo()` de Spatie nunca tuvo ese
bug.

**Verificado empíricamente en tinker** (transacción con rollback): usuario con un permiso
otorgado **solo vía rol** (sin grant directo) →
`$user->can('permiso_solo_por_rol')` devuelve `true`. Confirma que el mecanismo inline ya
honra rol, sin necesidad de cambio.

## Conclusión

No existe un verificador propio directos-only en el módulo OLT que alinear. El caso Alondra
(motivador original del flip) se resolvió en Fase 3a a nivel del trait compartido, y esa
corrección ya alcanza a las rutas OLT porque comparten el mismo middleware. La defensa en
profundidad de `OLTsOnuController` (item #287) nunca tuvo el problema porque usa el propio
mecanismo nativo de Spatie.

**Sin cambio de código.** Se corrige la entrada correspondiente en `CLAUDE.md` (que tenía la
misma premisa incorrecta y fue la fuente de este item) para no volver a generar el mismo
hallazgo falso.
