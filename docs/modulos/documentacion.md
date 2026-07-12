# Módulo Documentación

> Documentación interna jerárquica del sistema. `app/Modules/Core/Documentacion/` · slug `core-documentacion` · módulo core, activo.

**En simple:** es el manual interno del sistema, organizado en temas y subtemas, al que cualquier usuario puede entrar desde un botón en la parte de arriba de la pantalla para leer cómo funciona algo.

## 1. Qué es
Sistema de **documentación interna jerárquica** (Menú → Submenú → Contenido) para explicar el uso del sistema al propio equipo, con un árbol navegable accesible desde el topbar y una pantalla de administración para crear/editar esos contenidos.

## 2. Para qué sirve
Le da al equipo (y a quien tenga el permiso) un lugar único donde consultar "cómo se usa" cada parte de MegaISP sin salir del sistema: un tema (menú) agrupa varios subtemas (submenús), y cada subtema tiene uno o más bloques de contenido (texto/markdown). Sirve tanto para consulta rápida (dropdown del topbar) como para mantenimiento (pantallas CRUD en Administración).

## 3. Cómo funciona
- **Modelos** (`App\Models\`): `DocumentationMenu` (tabla `documentation_menus`, campo `title`) `hasMany` `DocumentationSubmenu` (tabla `documentation_submenus`: `documentation_menu_id`, `title`, `description`) `hasMany` `DocumentationContent` (tabla `documentation_contents`: `documentation_submenu_id`, `content`). Los tres usan soft deletes y auto-stampean `created_by`/`updated_by` en sus propios `boot()` (no vía `BaseModel`).
- **Controllers** (`app/Modules/Core/Documentacion/Controllers/`):
  - `DocumentationMenuController` — CRUD de menús (vía `CrudModalController` genérico) + `getById`, `getTitle` (título para filtros) y `getTree` (árbol completo Menú→Submenús para el dropdown del topbar).
  - `DocumentationSubmenuController` — CRUD de submenús + `show($id)`, que arma la vista con los contenidos de ese submenú (bandeja de lectura).
  - `DocumentationContentController` — CRUD de bloques de contenido (`store`/`update`/`destroy`) + `getContentsBySubmenuId` (listado ordenable usado por la ruta pública de contenidos).
- **Vistas:** Blade en `resources/views/meganet/module/administration/documentation/{menu,submenu,content}/` + componentes Vue en `resources/js/components/module/adminstration/documentation/` (`DocumentationMenuListar`, `DocumentationSubmenuListar`, `DocumentationContent`, y `DocumentationTreeMenu` — este último montado globalmente en el topbar, `app/Modules/Core/Layout/views/topbar.blade.php`, como `<documentation-tree-menu>`).
- **Rutas:** todas bajo `/administracion/documentation/*`, con `['web','auth','check_route_permission']` (el comentario en `routes.php` aclara que `web` se agrega a mano porque `loadRoutesFrom` no lo aplica solo).
- **Flujo:** un admin crea un Menú → le agrega Submenús → a cada Submenú le agrega uno o más Contenidos (texto). Cualquier usuario con el permiso de vista abre el árbol desde el topbar, entra a un submenú y lee sus contenidos (vista `content/show.blade.php`).

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web** (JSON, no API pública): `documentation_menu/` (index/add/update/destroy/table/getById/get-title/**tree**), `documentation_submenu/` (index/add/update/destroy/table/{id}), `documentation_content/` (add/update/delete/{submenuId}/contents).
- **4 permisos**: `documentation_view_documentation`, `documentation_add_documentation`, `documentation_edit_documentation`, `documentation_delete_documentation` (mapeados a esas rutas en `config/route_permission.php`).
- **Componente global** `<documentation-tree-menu>` — dropdown de navegación montado en el topbar de toda la app.
- **Card de administración** ("Documentación") en el panel `/administracion`, gateado por `documentation_view_documentation`.

**Consume**
- **Usuarios** (`created_by`/`updated_by`/`creator()`/`updater()` → `App\Models\User`) — autoría de cada menú/submenú/contenido.
- **Autenticación/permisos** — `check_route_permission` + spatie/laravel-permission para gatear todas sus rutas.

> _Servicios compartidos únicos: este módulo no monta WhatsApp/IA/Mapas propios; no aplica ninguno de los 3 servicios únicos designados (#308)._

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
