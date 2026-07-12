# Módulo Layout

> Plantillas maestras, topbar, sidebar y modo oscuro. `app/Modules/Core/Layout/` · slug `core-layout` · módulo **core**, activo, sin dependencias.

## 0. En simple
Es el "marco" visual que rodea cada pantalla del sistema: la barra de arriba, el menú lateral, el pie de página y el interruptor de modo claro/oscuro — todas las pantallas de MegaISP se pintan dentro de este marco.

## 1. Qué es
Módulo **core** (no addon) que provee las plantillas Blade maestras (`master`, `master-without-nav`) y sus piezas — topbar, sidebar, menú móvil, panel lateral derecho, pie de página, scripts de vendor — que envuelven prácticamente toda vista del sistema. También guarda la preferencia visual de cada usuario (modo claro/oscuro, estilo de fila por estado, pestañas abiertas) en la tabla `app_layout_configurations`.

## 2. Para qué sirve
Le da a **todos los módulos** (no solo a un equipo específico) un armazón visual único y consistente: cualquier vista Blade nueva solo necesita `@extends('core-layout::master')` y hereda topbar, sidebar, menú móvil, notificaciones, ayuda contextual y el montaje de la SPA de Vue (`#init-vue`), sin reimplementar nada de eso. También resuelve la persistencia por-usuario del tema (claro/oscuro) y de detalles de UI (color de columnas de datatable, estilo de fila, pestañas recordadas), para que la experiencia visual sobreviva entre sesiones.

## 3. Cómo funciona

**Plantillas maestras (`views/`):**
- `master.blade.php` — la plantilla principal: `<head>` (vía `head.blade.php`/`title-meta.blade.php`), topbar, sidebar, menú móvil (`mobile-nav.blade.php`, solo ≤992px), el contenedor `#init-vue` donde monta la SPA de Vue con `@yield('content')`, modales compartidos (`modals.blade.php`), panel lateral derecho (`right-sidebar.blade.php`), pie de página (`footer.blade.php`), scripts de vendor (`vendor-scripts.blade.php`) y el panel flotante de ayuda contextual (`<help-float>`). El modo claro/oscuro se fija **por pestaña del navegador** vía `sessionStorage` (no compartido entre pestañas) para evitar parpadeo al cargar.
- `master-without-nav.blade.php` — variante sin topbar/sidebar, para pantallas standalone (ej. login, landings).
- **266 vistas** del sistema extienden `core-layout::master` hoy; **4 vistas legacy** aún extienden la ruta vieja `meganet.layout.master` (deuda de la migración documentada en `MIGRATION.md` de este módulo — pendiente de reapuntar).

**Sidebar (`views/sidebar.blade.php`, ~299 líneas):**
- Es **Blade estático** con `@can`/`@hasanyrole` por cada bloque de módulo — **NO** es dinámico desde `module.json` ni Vue. Agregar un ítem de menú de un addon nuevo requiere editar este archivo a mano (documentado también en `CLAUDE.md` raíz).
- Excepción parcial: `SidebarComposer` (ViewComposer registrado solo para la vista `core-layout::sidebar`) inyecta `sidebarItems` desde la tabla `ModuleSidebarConfig` (cacheada 60s, agrupa hijos `sub_item` bajo su padre `direct`) y `sidebarSubmenu`/`addonMenuItems` desde `ModuleRegistry::getMenu()`/`getSubmenuItemsFor('finanzas')` — este mecanismo sí es dinámico, pero conviven ambos (estático + dinámico) en el mismo archivo.

**Topbar (`views/topbar.blade.php`):** logo, botón de menú, badge "PRODUCCIÓN · {versión}" (solo si `config('updates.enabled')`, resuelto por `TopbarComposer` desde la última fila de `releases`, cacheado permanentemente e invalidado por el deploy remoto), panel de documentación, notificaciones (badge con conteo), dropdown de usuario (perfil, cambiar contraseña, link a "Mi portal" si el usuario tiene el permiso `portal.colaborador`, logout).

**Configuración visual por usuario (`Controllers/ConfigAppLayoutController.php` + `Models/AppLayoutConfiguration.php`, tabla `app_layout_configurations`):**
- `saveAppConfigLayout` — guarda `color_mode` (claro/oscuro) del usuario autenticado (upsert por `user_id`).
- `saveRowStatusStyle` — guarda `row_status_style` (`underline`\|`filled`, estilo de fila por estado en las tablas), leído en `master.blade.php` como atributo `data-row-style` del `<body>`.
- `getConfigTabs` / `setConfigTabs` — persisten qué pestaña estaba activa por panel (`tabs_json`), para recordar la pestaña abierta al volver a una pantalla.
- `AppLayoutConfigurationService::createOrUpdateClientDatatableColor()` (+ `AppLayoutConfigurationRepository`) — guarda `client_datatable_color`; su único consumidor real es `App\Http\HelpersModule\module\client\ClientDatatableHelper`.
- `ModuleServiceProvider::boot()` comparte dos closures globales a **todas** las vistas: `configLayout($userId)` (resuelve la config del usuario, usada en `master.blade.php` para los atributos `data-*` del `<body>`) y `logoMeganet()` (logo desde `CompanyInformation`, que según el propio código está pendiente de moverse a `Core/Configuracion`).

## 4. Qué EXPONE / qué CONSUME

**Expone**
- **Rutas web** (`routes.php`, middleware `['web','auth']` — **sin** `check_route_permission`, hereda esa exclusión de cuando vivían sueltas en `routes/web.php`): `POST /save-app-config-layout`, `POST /save-row-status-style`, `POST /get-config-tabs`, `POST /set-config-tabs`.
- **Vistas Blade con namespace `core-layout::`**: `master`, `master-without-nav`, `topbar`, `sidebar`, `mobile-nav`, `head`, `title-meta`, `footer`, `right-sidebar`, `vendor-scripts`, `modals` — consumidas por `@extends`/`@include` desde prácticamente todo el sistema (266 vistas).
- **View shares globales** (disponibles en cualquier vista): closures `configLayout` y `logoMeganet`, registrados en `ModuleServiceProvider::boot()`.
- **ViewComposers**: `SidebarComposer` (sobre `core-layout::sidebar`) y `TopbarComposer` (sobre `core-layout::topbar`) — solo corren cuando esas vistas específicas se renderizan.
- **Modelo `AppLayoutConfiguration`** (tabla `app_layout_configurations`) — consumido directamente por `master.blade.php` vía el share `configLayout`.

**Consume**
- **`ModuleRegistry`** (`Core\ModuleManager`) — `getMenu()`/`getSubmenuItemsFor('finanzas')`, para el menú dinámico parcial del sidebar.
- **`ModuleSidebarConfig`** (`app/Models/`) — filas de configuración del sidebar dinámico, cacheadas 60s.
- **`Release`** (`app/Models/`) + `config('updates.enabled')` — para el badge de versión en producción del topbar.
- **`CompanyInformation`** (`Core\Configuracion`, en camino a migrarse ahí según `MIGRATION.md`) — logo de la empresa.
- **Sistema de permisos Spatie** (`@can`/`@hasanyrole` en Blade) — gating de cada bloque del sidebar y de accesos condicionales del topbar (ej. `conciliacion.manage`, `portal.colaborador`).
- **Frontend Vue**: `resources/js/hook/appConfig.js` (POST a `/save-row-status-style`) y `resources/js/helpers/ConfigTabs.js` (POST a `/get-config-tabs`/`/set-config-tabs`) son los consumidores JS de las rutas de este módulo; `resources/js/shared/ModeVisualBody.vue` y `NotificationTopbar.vue` son componentes Vue que aún viven fuera del módulo (Mix compila un bundle global único, ver `MIGRATION.md`).

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
