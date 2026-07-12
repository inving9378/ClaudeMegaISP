# Módulo Reportes

> Addon vacío (`app/Modules/Addons/Reportes/`, slug `addon-reportes`, sin dependencias, activo) cuya única función real hoy es declarar un `module.json` que redirige la UI hacia el módulo `Core/Release` — no tiene controllers, modelos ni rutas propias.

**En simple:** es una tarjeta que dice "Reportes" en el panel de Administración y que, al hacerle clic, en realidad te lleva a las novedades del sistema (notas de versión), porque el módulo Reportes en sí nunca se terminó de construir.

## 1. Qué es

Un módulo addon **scaffold** (esqueleto de carpetas) creado junto con otros 8 addons de golpe (commit `1350e1f0`, generador masivo de módulos). Trae `Controllers/`, `Models/`, `Repositories/`, `Services/`, `views/` y `migrations/` con solo un `.gitkeep` cada uno, y un `routes.php` con el comentario `// Pendiente de implementar / migrar desde routes/web.php`. Nunca se implementó: la funcionalidad de "reportes operativos y novedades del sistema" que describe su `module.json` vive realmente en el módulo `Core/Release` (`app/Modules/Core/Release/`), migrado por separado (commit `0fbb028f`) desde controllers legacy (`app/Http/Controllers/Module/Release/`).

## 2. Para qué sirve

En la práctica, solo aporta dos cosas declarativas desde su `module.json`:
- Una tarjeta ("Reportes") en el panel `/admin/administracion`.
- Una sección de configuración ("Reportes — Novedades del Sistema") en el catálogo `/configuracion`.

Ambas apuntan a `/releases` — la pantalla real de notas de versión/changelog que sirve `Core/Release`. No resuelve ningún problema de negocio por sí mismo; es un alias de navegación que evita un 404 en esas dos superficies.

## 3. Cómo funciona

- **Descubrimiento**: `ModuleManagerService::discoverProviders()` hace `glob` sobre `app/Modules/{Core,Addons}/*/ModuleServiceProvider.php`, así que `Addons/Reportes/ModuleServiceProvider.php` (extiende `BaseModuleServiceProvider`, sin overrides) se auto-registra y arranca igual que cualquier módulo. Su `boot()` intenta cargar `routes.php` (vacío → no-op), `views/` (vacío) y `migrations/` (vacío) — no hay efecto en runtime más allá del registro.
- **Manifiesto (`module.json`)**: declara 6 permisos `release_*` (`release_view_release`, `release_add_release`, `release_edit_release`, `release_add_description`, `release_edit_description`, `release_delete_description`), un `admin_card` y un `config_section`, ambos con `url: "/releases"` y gateados por `release_view_release`. Esos mismos nombres de permiso son los que `config/route_permission.php` usa para proteger las rutas reales `/releases/*` servidas por `Core/Release` — es decir, **Reportes es quien declara los permisos que Release consume** (contrato cruzado no documentado en `docs/contratos/`).
- **Compilación**: `ModuleRegistry::compiled()` (servicio singleton de `ModuleManager`) recorre los manifiestos de todos los módulos activos y fusiona sus `admin_cards`/`config_sections`. El `admin_card` de Reportes convive con el propio `admin_card` de `Core/Release` ("Reporte del Sistema", mismo destino `/releases`) — no hay dedupe por URL, solo por slug, así que ambos aparecen como tarjetas separadas apuntando al mismo lugar.
- **Corrección de navegación (commit `294913c2`, item circuito #292)**: originalmente el `admin_card`/`config_section` de Reportes apuntaban a `/reportes` (ruta inexistente → 404, porque su `routes.php` nunca se implementó). Se repuntaron a `/releases` como parche mínimo — no se implementó el módulo, solo se evitó el enlace roto.
- **Sidebar**: `ModuleSidebarConfigSeeder` registra `module_key='reportes'` con `show_in_sidebar=false` — no genera entrada ni tarjeta sintética en el sidebar ni en el panel B de Administración.

## 4. Qué EXPONE / qué CONSUME

**Expone**
- 6 permisos Spatie `release_*` declarados en su `module.json` (ver arriba) — son la fuente de alta de esos permisos, aunque las rutas que protegen viven en otro módulo.
- Un `admin_card` y un `config_section` (ambos apuntando a `/releases`), consumidos por `Services\ModuleRegistry` de `Core/ModuleManager` y renderizados en `/admin/administracion` y `/configuracion`.
- Nada de código propio: sin controllers, modelos, servicios, vistas ni migraciones (todas las carpetas están vacías salvo `.gitkeep`).

**Consume**
- Indirectamente, la funcionalidad real de `Core/Release` (`ReleaseController`, `ReleaseDescriptionController`, `DeploymentController`, `AuditController`, rutas `/releases/*`) — Reportes no la invoca en código, solo la referencia por URL en su manifiesto.
- El descubrimiento/compilador genérico de `Core/ModuleManager` (`ModuleManagerService`, `Services\ModuleRegistry`) — igual que cualquier otro módulo del sistema.

**No hay entrada en el registro de contratos inter-módulos (`docs/contratos/`, item #308)** para este caso; el vínculo Reportes↔Release es solo el que documenta este archivo.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
