# Módulo Security

> `app/Modules/Core/Security/` (servicios propios) + piezas hermanas transversales `app/Services/Security/`, `app/Support/Security/`, `app/Http/Middleware/SqlInjectionProtection.php` · módulo **core**, sin `module.json` propio (no expone rutas ni pantalla).

## 0. En simple
Es la caja de herramientas de seguridad interna del sistema: reparte permisos de forma segura, revisa que las contraseñas sean válidas, y vigila que nadie meta trucos peligrosos (SQL malicioso, nombres de clase inventados) en los formularios y búsquedas del sistema.

## 1. Qué es
No es un módulo con pantalla propia como CRM o Finanzas, sino un conjunto de **servicios y guardas de seguridad transversales** que otros módulos consumen: sincronización de permisos Spatie a roles base, verificación híbrida de contraseñas (legacy base64 → bcrypt), validación de identificadores en queries dinámicas, y un WAF ligero anti-inyección SQL aplicado a todo el tráfico.

## 2. Para qué sirve
Le resuelve a **todo el sistema** (no a un rol de negocio en particular) cuatro problemas de seguridad de bajo nivel que, si cada módulo los resolviera por su cuenta, se duplicarían o se harían mal:
- **Permisos consistentes**: cuando se crea un permiso nuevo (por install/update de un módulo, o por `module.json`), que llegue de forma automática y sin duplicados a `super-administrator`/`DESARROLLADOR`, y opcionalmente a los demás roles.
- **Migración de contraseñas sin downtime**: mientras el sistema convive con contraseñas legacy en base64 (reversible, riesgo de cumplimiento LFPDPPP) y las nuevas en bcrypt, un solo punto de verificación acepta ambos formatos y re-hashea al primer login correcto.
- **Queries dinámicas seguras**: controladores que arman consultas a partir de nombres de modelo/columna/scope que vienen del request (ej. `SearchModelController`) sin poder validar a mano cada caso.
- **Defensa en profundidad anti-SQLi**: un filtro global de firmas de inyección de alta precisión sobre el body/query/params de cada request, como segunda capa detrás de la validación por identificador.

## 3. Cómo funciona

### 3.1 Piezas clave
- **`PermissionSyncService`** (`app/Modules/Core/Security/Services/PermissionSyncService.php`) — servicio central idempotente:
  - `syncPermissionToBaseRoles($name)` — da el permiso a `super-administrator` + `DESARROLLADOR` siempre; opcionalmente (si `config('permission_sync.auto_grant_view_base_roles')`, default `false`) reparte los permisos `.view` a los demás roles, excluyendo los listados en `permission_sync.view_excluded_roles` (`client`, `conductor`, `PUBLICADOR`, `Socio`).
  - `syncAllPermissionsToBaseRoles()` — corre lo anterior sobre todos los permisos existentes en BD; devuelve un conteo por categoría.
  - `syncFromModuleManifests()` — lee `app/Modules/Addons/*/module.json`, crea en BD los permisos declarados que falten y los sincroniza.
  - Invalida la caché de permisos de Spatie (`PermissionRegistrar::forgetCachedPermissions()`) al terminar.
- **`SyncPermissionsCommand`** (`php artisan permissions:sync-roles [--manifests]`) — wrapper de consola sobre `PermissionSyncService`; al final vuelve a correr `RolePermissionRevocationSeeder` para que el sync no deshaga revocaciones de política de negocio ya aplicadas.
- **`PasswordService`** (`app/Services/Security/PasswordService.php`) — único punto de verdad para contraseñas:
  - `make()` siempre produce bcrypt (único método de escritura permitido).
  - `check()` acepta ambos formatos: si el valor almacenado es bcrypt usa `Hash::check()`; si es legacy compara `hash_equals($stored, base64_encode($plain))`.
  - `isHashed()` / `needsRehash()` — detectan si el valor sigue en legacy.
  - `legacyPlain()` — descifra el base64 SOLO si sigue en legacy (para la función transitoria "ver contraseña" del admin); para bcrypt devuelve `null` (irreversible por diseño).
- **`QueryGuard`** (`app/Support/Security/QueryGuard.php`) — validador de identificadores dinámicos, falla cerrado (`abort(422)`):
  - `model()` — el modelo debe empezar con `App\Models\` o `App\Modules\`, matchear `^[A-Za-z0-9_\\]+$` (sin `(`, `;`, `::`, espacios), existir y ser subclase de `Model`.
  - `identifier()` — nombre de columna simple `^[A-Za-z0-9_]+$`.
  - `relation()` — nombre de relación Eloquent, permite notación punto (`rel.columna`).
  - `scope()` — el nombre debe matchear `^[A-Za-z0-9_]+$` y existir como método `scope{Nombre}()` en el modelo.
- **`SqlInjectionProtection`** (`app/Http/Middleware/SqlInjectionProtection.php`, alias `sql_guard` no registrado como alias — se aplica **globalmente** en `app/Http/Kernel.php`) — escanea valores del body/query/route params contra una lista de firmas de alta precisión (UNION SELECT, queries apiladas destructivas tras `;`, funciones time-based, `LOAD_FILE`/`INTO OUTFILE`, tautologías `' OR 'a'='a`, `information_schema`/`xp_cmdshell`, etc.). Configurable en `config/sql_guard.php` (`SQL_GUARD_ENABLED`, `SQL_GUARD_BLOCK` — con `block=false` solo registra sin bloquear) y excluye rutas donde el usuario pega SQL/código a propósito (`webhooks/*`, `ia/*`, `chat-ia/*`, `devtools/*`, `configuracion/smart-import*`).

### 3.2 Flujo principal (alta de un permiso nuevo)
1. Un módulo se instala/actualiza (`ModuleLifecycleService::registerPermissions()`) o declara permisos en su `module.json`.
2. Se llama `PermissionSyncService::syncPermissionToBaseRoles()` (o `syncFromModuleManifests()` vía `permissions:sync-roles --manifests`).
3. El permiso llega a `super-administrator`/`DESARROLLADOR` de forma incondicional; a los demás roles solo si el flag de auto-grant está activo y es un permiso `.view`.
4. Se limpia la caché de permisos de Spatie.

### 3.3 Flujo de request (defensa por capas)
Cada request pasa primero por `SqlInjectionProtection` (global, capa de WAF); si un controlador arma una query dinámica a partir de nombres del request, pasa además por `QueryGuard` (capa primaria, específica del caso). Ambas capas son independientes: una no reemplaza a la otra.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Servicio** `App\Modules\Core\Security\Services\PermissionSyncService` — usado por `ModuleLifecycleService` (install/upgrade de módulos), `PermissionController::syncRoles` (endpoint admin de sincronización manual) y directamente por migraciones que crean permisos nuevos (ej. `Talento/.../create_portal_colaborador_permission.php`).
- **Comando artisan** `permissions:sync-roles {--manifests}`.
- **Servicio** `App\Services\Security\PasswordService` — usado por `LoginController` (auth), `UserController` (admin y core), `ClientController`/`ClientInformationController` (contraseña web del cliente), `MegaFamilia\ApiController`, `ClientMainInformationObserver` y el comando `auth:rehash-passwords` (`RehashPasswordsCommand`).
- **Servicio** `App\Support\Security\QueryGuard` — usado por `SearchModelController` (búsquedas dinámicas por modelo/columna/scope) y por `SqlInjectionProtection` (para su propia lista de excepciones de rutas/campos).
- **Middleware global** `SqlInjectionProtection` — registrado en el kernel HTTP, aplica a todas las rutas salvo las excluidas en `config/sql_guard.php`.
- **Config** `config/permission_sync.php` (flags de reparto de `.view` + roles excluidos) y `config/sql_guard.php` (encendido/bloqueo/excepciones del WAF).

**Consume**
- **Spatie Laravel-Permission** (`Role`, `Permission`) — toda la lógica de `PermissionSyncService` opera sobre sus modelos y su registrar de caché.
- **`RolePermissionRevocationSeeder`** (`database/seeders/`) — `SyncPermissionsCommand` lo re-ejecuta al final para no deshacer revocaciones de política de negocio ya aplicadas.
- **`Illuminate\Support\Facades\Hash`** — `PasswordService::make()`/`check()` delegan en el hashing bcrypt nativo de Laravel.
- **`module.json`** de cada addon (`app/Modules/Addons/*/module.json`) — fuente de permisos declarativos para `syncFromModuleManifests()`.
