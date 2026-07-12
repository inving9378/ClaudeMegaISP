# Módulo Auth

> Login, roles y permisos del panel admin (guard `web`, Spatie Permission). `app/Modules/Core/Auth/` · slug `core-auth` · módulo core, activo.

**En simple:** es la puerta de entrada del sistema — revisa que el usuario y la contraseña sean correctos, que la cuenta esté activa y que tenga permiso de estar ahí, antes de dejarlo pasar al panel de administración.

## 0. En simple
Es la puerta de entrada del sistema: valida usuario/contraseña, revisa que la cuenta no esté bloqueada y decide a qué pantallas puede entrar cada quien.

## 1. Qué es
Módulo core que implementa el **login del panel administrativo** (guard `web` de Laravel) y el **middleware de autorización por ruta**, apoyado en `spatie/laravel-permission` para roles/permisos.

## 2. Para qué sirve
Es la barrera de entrada de todo el sistema: sin pasar por aquí nadie llega al panel admin (`/dashboard`, `/cliente`, `/finanzas`, etc.). Resuelve dos problemas: (a) verificar identidad (usuario/contraseña) y (b) decidir, ruta por ruta, si el usuario autenticado tiene permiso de verla — evita que un Vendedor entre a pantallas de Finanzas, que un Técnico entre a Configuración, etc. También es el punto donde se bloquea a las **cuentas espejo de cliente** (rol `client`) para que no puedan autenticar en el panel admin aunque conozcan su Contraseña WEB.

## 3. Cómo funciona
- **Login** (`Controllers/LoginController.php`, trait `AuthenticatesUsers` de Laravel): acepta email **o** `login_user` como identificador (detecta cuál es con `filter_var(...,FILTER_VALIDATE_EMAIL)`). Busca el usuario por `email` o `login_user`, verifica la contraseña con `App\Services\Security\PasswordService::check()` (híbrido: acepta bcrypt y el legacy `base64_encode`, ver `CLAUDE.md`), exige `estado === 'activo'`, y aplica un **gate de rol**: solo usuarios con alguno de los `STAFF_ROLES` (`super-administrator`, `DESARROLLADOR`, `Super Administrador`, `Administrador`, `Vendedor`, `Mostrador`, `TECNICO`, `Almacen`) pueden autenticar — cualquier otro (p.ej. una cuenta espejo con rol `client`) recibe el mismo error genérico de credenciales inválidas (no revela que la cuenta existe). Si la contraseña seguía en base64 legacy, se re-hashea a bcrypt en el momento (upgrade-on-login).
- **Redirección post-login**: `App\Services\PostLoginRedirectService::resolve()` — 1) última ruta visitada si el usuario aún tiene permiso, 2) primera ruta de una lista de fallback por permiso (dashboard, CRM, clientes, tickets, etc.), 3) `/sin-modulos` si no tiene ningún módulo accesible.
- **Autorización por ruta** (`Middleware/CheckRoutePermission.php`, registrado como alias `check_route_permission` en `app/Http/Kernel.php`, envuelve casi todas las rutas de `routes/web.php`): por cada request compara la URL contra `PUBLIC_ROUTES` (whitelist que salta el chequeo, p.ej. webhooks y lookups de ayuda) y contra `config('route_permission')` (mapa permiso → patrones de URL). Deja pasar directo a `isAdmin()`/`isDevelopment()`/`isSuperAdmin()`; para el resto resuelve los permisos vía `PermissionTrait::getPermissionForUserAuthenticated()`, que devuelve la **unión de permisos directos ∪ permisos del rol** (`$user->getAllPermissions()`) — el "flip" de la Fase 3a documentado en `CLAUDE.md`. Sin sesión → redirige a `login`; cuenta `isNotActive()` → logout + redirige a `login`; sin permiso → vista 403.
- **`Middleware/Authenticate.php`**: extiende el `Authenticate` estándar de Laravel (alias `auth`), solo sobreescribe `redirectTo()` para mandar a `route('login')` en vez del default.
- **Rutas registradas** (`routes.php`, 9 endpoints, réplica explícita de lo que emitía `Auth::routes()` de Laravel UI): `login` (GET/POST), `logout` (POST), `password/reset` + `password/email` + `password/reset/{token}` + `password/update` (flujo de recuperación por `ForgotPasswordController`/`ResetPasswordController`, traits estándar `SendsPasswordResetEmails`/`ResetsPasswords` de Laravel). Envueltas explícitamente en `Route::middleware(['web'])` porque `loadRoutesFrom()` no aplica ese grupo automáticamente.
- **`register` deshabilitado a propósito** (item Roadmap #219): la ruta responde `abort(404)` — el alta de staff se hace solo desde `/administracion/user` (panel con permisos Spatie), porque el scaffolding de registro quedaba público sin autenticación e insertaba en `users` sin `login_user`/`PasswordService`.
- **Controllers sin ruta registrada (scaffolding muerto de Laravel UI, no wireados):** `RegisterController` (create() usa `Hash::make` directo, no `PasswordService` — nunca se ejecuta porque `register` está deshabilitado), `VerificationController` y `ConfirmPasswordController` (verificación de email y confirmación de password no están habilitadas en este sistema).
- **Roles/permisos**: sobre `spatie/laravel-permission` (tablas `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`); ver el detalle de la rectificación de permisos (fases 1-3b) en `CLAUDE.md`.
- **Vistas** (`views/`): `login.blade.php`, `register.blade.php` (sin uso — ruta 404), `passwords/{email,reset,confirm}.blade.php`, `verify.blade.php` (sin uso — sin ruta).

## 4. Qué EXPONE / qué CONSUME
**Expone**
- Rutas web `GET/POST login`, `POST logout`, `GET/POST password/reset`, `POST password/email`, `GET password/reset/{token}`, `POST password/update` (grupo `web`, sin `check_route_permission` — son el punto de entrada antes de tener sesión).
- Ruta `register` neutralizada (`abort(404)`), reservada para no romper `route('register')` en el código que aún la referencie.
- Middlewares/alias de `app/Http/Kernel.php`: `auth` → `Authenticate`, `check_route_permission` → `CheckRoutePermission`. Son consumidos por casi todo `routes/web.php` y por los `routes.php` de los demás módulos.
- `PermissionTrait::getPermissionForUserAuthenticated()` (`app/Http/Traits/PermissionTrait.php`) — usado también por `allViewHasPermission`/`hasPermissionToView` fuera de este módulo (ver `CLAUDE.md`, Fase 3a).

**Consume**
- `App\Models\User` (tabla `users`, campo de login `login_user`, roles/permisos vía Spatie).
- `App\Services\Security\PasswordService` — único punto de verificación/escritura de contraseñas (híbrido bcrypt/base64 legacy).
- `App\Services\PostLoginRedirectService` — decide a dónde redirigir tras login.
- `config('route_permission')` — mapa de permisos a patrones de URL, consumido por `CheckRoutePermission`.
- Sistema de permisos `spatie/laravel-permission` (roles/permisos, tablas `roles`/`permissions`/pivotes).

**Nota — no confundir con el login del Portal Cliente**: `/portal/*` usa un guard **distinto** (`cliente`, provider `portal_clients`) definido e implementado en `app/Modules/Addons/PortalCliente/` (ver `CLAUDE.md`), con su propio `AuthController` y su propia columna de credencial (`client_main_information.password`). No comparte código con este módulo, solo el mismo mecanismo de hashing híbrido (`PasswordService`).

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios y no aplica aquí._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
