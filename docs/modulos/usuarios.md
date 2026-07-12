# Módulo Usuarios

> Alta, edición y baja de cuentas de staff (administradores/vendedores/técnicos/etc.), roles y permisos del panel admin. `app/Modules/Core/Usuarios/` · slug `core-usuarios` · módulo core, activo.

**En simple:** es la oficina de recursos humanos digital del sistema — aquí se da de alta a cada empleado que usa el panel (con su usuario y contraseña), se le asigna un rol (vendedor, técnico, mostrador, administrador...) y se decide qué puede ver y hacer cada rol dentro del sistema.

## 0. En simple
Es donde se administra al personal que usa el panel de MegaISP: crear/editar/bloquear cuentas de empleados, definir los roles (Vendedor, Técnico, Mostrador, Administrador, etc.) y decidir qué permisos tiene cada rol.

## 1. Qué es
Módulo core que administra las **cuentas de staff** del sistema (guard `web`, tabla `users`), sus **roles** y los **permisos** asociados a cada rol, sobre `spatie/laravel-permission`. Es distinto del módulo Auth (que valida el login) y del Portal Cliente (guard `cliente`, cuentas de clientes finales).

## 2. Para qué sirve
A quien administra el sistema (super-administrator/DESARROLLADOR) le permite dar de alta/editar/desactivar empleados, asignarles un rol de trabajo y curar qué permisos tiene ese rol — sin esto nadie nuevo podría entrar al panel ni se podría ajustar qué ve cada tipo de empleado (evita, por ejemplo, que un Vendedor vea pantallas de Finanzas). También es la barrera que impide que una **cuenta espejo de cliente** (creada para el Portal Cliente) se convierta en cuenta de staff por accidente.

## 3. Cómo funciona
- **4 controllers** en `Controllers/`: `AdministracionController`, `UserController`, `RolController`, `PermissionController`.
- **`AdministracionController`** — dashboard de entrada (`/administracion`, tarjetas definidas en `module.json` → `admin_cards`) más varios **procesos batch legacy** sin relación directa con usuarios (suspender clientes, facturación, importar clientes a Mikrotik, scripts) que quedaron aquí por herencia histórica del módulo "Administración".
- **`UserController`** — CRUD de cuentas de staff (`/administracion/user`, tabla `users`). `store`/`update` validan los datos, hashean la contraseña con `PasswordService` (híbrido bcrypt/legacy base64 — ver `CLAUDE.md`), asignan el rol elegido y, si `is_seller`, crean/actualizan el registro `Seller` asociado. `getData` **nunca** devuelve la contraseña en claro al frontend (solo indica si es legacy). `destroy` no permite eliminar al último `super-administrator`.
  - **Guard "cuenta-cliente no puede volverse staff"** (`enforceClientStaffGuard`/`isClientOnlyAccount`): si el `login_user` pertenece a una ficha de `client_main_information` y la cuenta solo tiene el rol `client`, se bloquea cualquier intento de asignarle un rol de staff (HTTP 422), salvo que un `super-administrator` lo fuerce explícitamente (`promote_client_to_staff=1`, queda auditado con `Log::warning`).
  - `update()` reemplaza roles con un **diff dirigido** (agrega/quita solo lo que cambió) en vez de `syncRoles` destructivo, y nunca quita los roles protegidos `super-administrator`/`DESARROLLADOR`/`ADMINISTRADOR_COMPLETO`/`client`.
- **`RolController`** — CRUD de roles (`/administracion/rol`): crear, renombrar, listar (datatable), editar los permisos de un rol (`givePermissionTo`/`revokePermissionTo` por cada permiso enviado) y eliminar. `destroy` bloquea roles protegidos (`PROTECTED_ROLES`: `super-administrator`, `DESARROLLADOR`, `ADMINISTRADOR_COMPLETO`, `client`) y roles con usuarios asignados.
- **`PermissionController`** — catálogo de permisos (`/administracion/permisos/catalog`, alimenta la pestaña dinámica "Otros" de la UI de roles), obtener/actualizar los permisos de un rol, y una sincronización masiva de permisos faltantes a roles base (`syncRoles`, solo `super-administrator`, vía `PermissionSyncService`). También expone los endpoints **globales** que el resto del sistema usa para resolver qué puede ver el usuario autenticado (unión permisos directos ∪ permisos del rol — el "flip" de la Fase 3a documentado en `CLAUDE.md`).
  - La asignación de permisos **individuales por usuario** fue retirada (Reforma de permisos B1.3): el rol es la única fuente de verdad. Los endpoints `get/update-permission-for-user` quedaron como stubs que responden `410 Gone`.
- **Frontend Vue** (`resources/js/components/module/adminstration/`): `rol/ListarRol.vue`, `rol/PermissionRole.vue` (asignar permisos a un rol), `user/PermissionUser.vue`, `PermissionAssignmentModal.vue`. Vistas Blade del CRUD de usuario en `resources/views/meganet/module/administration/user/{listar,add,edit}.blade.php`.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- Rutas web bajo `/administracion/*` (grupo `web`+`auth`+`check_route_permission`): dashboard y procesos batch, `/administracion/user/*` (CRUD de usuarios + roles/promociones disponibles), `/administracion/addresses/*` (states/municipalities/colonies para el form de alta), `/administracion/rol/*`, `/administracion/permisos/*`.
- Rutas **globales** (sin `check_route_permission` a propósito — son las que resuelven permisos, no pueden depender de ellos): `GET /permissions-auth`, `POST /has-permission-to-view/{view}`, `POST /all-view-has-permission`. Las consume el resto del sistema (Vuex store en el boot del frontend, directiva `v-hasPermission`, y cualquier módulo que necesite chequear permisos del usuario autenticado).
- El modelo `App\Models\User` (tabla `users`) y las tablas de `spatie/laravel-permission` (`roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`) — base de identidad y autorización que consume prácticamente todo el sistema.

**Consume**
- `App\Services\Security\PasswordService` — único punto de hashing/verificación de contraseñas de staff (híbrido bcrypt/legacy base64).
- `App\Models\Seller` — alta/baja del registro de vendedor cuando un usuario se marca `is_seller`.
- `App\Modules\Core\Security\Services\PermissionSyncService` — sincronización masiva de permisos a roles base.
- `spatie/laravel-permission` (`Role`/`Permission`).
- `App\Models\{State,Municipality,Colony}` — catálogos geográficos para el formulario de alta/edición de usuario.
- `App\Models\Promotion` — lista de promociones disponibles por código (endpoint auxiliar `avaiablesPromotions`).

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios y no aplica aquí._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
