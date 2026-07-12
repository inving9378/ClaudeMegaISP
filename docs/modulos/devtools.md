# Módulo DevTools

> Panel split-screen de desarrollo: chat con Claude + terminal web (ttyd). `app/Modules/Addons/DevTools/` · slug `addon-devtools` · módulo addon, activo. Exclusivo del rol `DESARROLLADOR` (y `super-administrator`).

## 0. En simple
Es la cabina de mando del equipo de desarrollo: una pantalla dividida donde a la izquierda le preguntas cosas a Claude sobre el proyecto y a la derecha tienes una terminal real del servidor, sin necesitar SSH.

## 1. Qué es
Módulo **addon** que expone la pantalla standalone `/devtools`: un panel de dos (o tres) columnas — navegación rápida, chat con Claude (vía API de Anthropic) con contexto del proyecto pre-cargado, y una terminal web (ttyd) embebida por iframe. Solo lo pueden abrir usuarios con rol `DESARROLLADOR` o `super-administrator`.

## 2. Para qué sirve
Le da al **equipo de desarrollo** (Irving y afines) una herramienta interna para depurar, consultar el código/estado del repo y ejecutar comandos del servidor (artisan, git, etc.) sin salir del navegador ni abrir una sesión SSH aparte — todo en una sola pestaña, con Claude ya al tanto del `CLAUDE.md`, la rama actual y los últimos commits.

## 3. Cómo funciona
- **Vista standalone:** `views/index.blade.php` extiende `core-layout::master` (sin sidebar/topbar del sistema — página propia) y monta el componente Vue `<devtools-panel>` (`resources/js/components/module/devtools/DevtoolsPanel.vue`, ~1700 líneas, registrado en `app.js`).
- **`DevToolsController`** (`Controllers/DevToolsController.php`):
  - `index()` — sirve la vista, resuelve la URL de ttyd (`resolveTtydUrl()`: usa `TTYD_URL` del env si está seteada, si no cae al proxy nginx same-origin `/ttyd/`) y el token CSRF.
  - `context()` — arma y devuelve el "contexto del proyecto": contenido de `CLAUDE.md`, rama git actual, últimos 5 commits (`git log`) y slugs de módulos activos (desde `ModuleRegistry`, con fallback al discovery de `ModuleManagerService` si el registro está vacío).
  - `chat()` — endpoint stateless del chat: recibe el historial completo + mensaje nuevo (y attachments opcionales) desde el frontend, inyecta un system prompt fijo (reglas de estilo/idioma para el asistente) más un segundo bloque con el contexto del proyecto (marcado `cache_control: ephemeral` para abaratar llamadas subsecuentes ~5 min), y llama a la API de Anthropic (`config('services.anthropic.*')`, modelo default `claude-sonnet-4-6`). Soporta adjuntos: imágenes → bloque `image` (vision, base64), archivos de texto → bloque `text` citado (truncado a 50 KB), binarios sin extraer → placeholder de texto.
  - `navItems()` — devuelve una lista hardcodeada de accesos rápidos al resto del sistema (Dashboard, Clientes, Finanzas, Red, Tickets, etc.), filtrada por los permisos reales del usuario autenticado — alimenta la columna de navegación del panel (que al vivir en una página sin el layout estándar necesita su propio menú).
- **`GitController`** (`Controllers/Git/GitController.php`) — sub-namespace de Git tooling dentro de DevTools: `getTags()` devuelve los tags del repo (vía `App\Services\Git\GitService`) para la pestaña de Releases; gateado con `check_route_permission` (no `role:DESARROLLADOR`) para preservar acceso más amplio si el módulo que consume esa pestaña lo requiere.
- **Terminal (ttyd):** el panel embebe un `<iframe>` apuntando a la URL de ttyd resuelta por el backend; ttyd corre como proceso aparte fuera del código Laravel (no gestionado por este módulo).
- **Sin tablas propias:** el módulo no tiene migraciones ni modelos — es puramente controladores + vista + componente Vue sobre datos derivados en caliente (git, filesystem, API externa).
- **Sin permisos Spatie propios:** el gating de `/devtools/*` es por **rol** (`role:DESARROLLADOR|super-administrator`) directamente en `routes.php`, no por el sistema de permisos por URL (`check_route_permission`) — decisión explícita para que cualquier DESARROLLADOR entre sin depender de asignaciones de permiso adicionales.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web**, todas bajo middleware `['web', 'auth', 'role:DESARROLLADOR|super-administrator']`, prefijo `/devtools`:
  - `GET /devtools` — panel standalone.
  - `GET /devtools/context` — contexto del proyecto (JSON).
  - `POST /devtools/chat` — chat con Claude.
  - `GET /devtools/nav-items` — navegación filtrada por permisos.
- **Ruta adicional** `GET /git/get-tags` bajo `['web','auth','check_route_permission']` (gate distinto, ver arriba) — tags del repositorio.
- **Tarjeta de administración** y entrada de menú "DevTools" (`module.json` → `admin_cards`/`menu`), sin permiso asociado (`permission: null`), visible solo por el propio gate de rol de las rutas.
- No define eventos ni servicios reutilizables para otros módulos — es una herramienta terminal, de uso directo por el desarrollador, no un servicio compartido.

**Consume**
- **API de Anthropic (Claude)** — vía `Illuminate\Support\Facades\Http`, HTTP directo con `config('services.anthropic.key'/'endpoint'/'model')` (`CLAUDE_API_KEY` en `.env`). ⚠️ Esto es un cliente HTTP propio, no pasa por el módulo IA centralizado (`ia_proveedores`) que describe la convención "servicios compartidos únicos" del `CLAUDE.md` — es deuda a considerar si se quiere unificar.
- **`ModuleRegistry`** / `ModuleManagerService` (Core\ModuleManager) — para listar módulos activos en el contexto.
- **Git del repositorio** (vía `Illuminate\Support\Facades\Process`, comandos `git rev-parse`/`git log`) y **`CLAUDE.md`** en la raíz del proyecto — para construir el contexto que se inyecta al chat.
- **`App\Services\Git\GitService`** — para listar tags (usado por `GitController`).
- **Sistema de permisos** (`$user->can(...)`) — solo para filtrar `nav-items`, no para gatear las rutas del propio módulo.
- **ttyd** (proceso externo, servido vía proxy nginx `/ttyd/` o `TTYD_URL` del env) — terminal web embebida por iframe; el módulo no lo administra, solo apunta a su URL.

> _Servicios compartidos únicos: este módulo **NO** se conecta al adaptador de IA centralizado del sistema (llama a la API de Anthropic directo) — inconsistente con la convención de "IA solo vía los adaptadores existentes" documentada en `CLAUDE.md`; no se corrige aquí (fuera de alcance de esta documentación read-only)._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
