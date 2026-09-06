# MR-07 — Prueba de no regresión del módulo Mapas viejo (item #943)

Fecha: 2026-09-06. Entorno: DEV (`megaisp`). Objetivo: certificar que, después de MR-03 (esqueleto
del nuevo módulo `MapaRed`), MR-04 (esquema espejo `mapared_*`), MR-05 (copia de datos) y MR-06
(paridad funcional 1:1 sobre el espejo), el módulo **Mapas viejo** (`app/Modules/Addons/Mapas/`,
tablas `map_*`/`boxes`/`sites`/etc., rutas `/mapas` y `/maps/*`) quedó **exactamente igual** que en
el respaldo de referencia de MR-02 (item #938, `docs/mapas-mr02-backup-item-938-verificacion.md`).

Las 4 verificaciones pedidas por el DoD, contra el mismo universo de 38 tablas que MR-02 auditó:

## 1. Conteos por tabla (origen MR-02 vs. estado actual)

```
map_devices_ports=19229  map_fibers=17242         map_devices=8785
map_devices_ports_connections=8740  map_layers=6689  map_fibers_cut=3596
map_layers_routes=2130   map_proyects=1715  map_ports=17
box_zones=1  system_map_credentials=1
(27 tablas restantes: 0 c/u — Box*/Pole*/Site/Rack/Splitter/Transceiver/Trenche*/Tube*/...)
TOTAL = 68,145
```

Idéntico fila por fila y en total al respaldo de MR-02 (`docs/mapas-mr02-backup-item-938-verificacion.md`,
§2). **Cero diferencias.**

## 2. Checksum de esquema (`SHOW CREATE TABLE`, las 38 tablas)

Se extrajeron los 38 bloques `CREATE TABLE` del dump de MR-02
(`/var/backups/mysql/mapas-20260903-2007.sql.gz`) y se compararon contra `SHOW CREATE TABLE` en vivo
de cada tabla, normalizando solo el contador `AUTO_INCREMENT=N` (no es señal de regresión de
esquema, puede moverse por operaciones ajenas sin cambiar la estructura). **0 de 38 tablas con
diferencia** — columnas, tipos, índices, llaves foráneas y `ENGINE`/`CHARSET` byte a byte iguales.

## 3. `route:list` filtrado por `/mapas`

`php artisan route:list --path=mapas` → **131 rutas**, prefijos `mapas/*` y `maps/*` intactos
(`maps.tube_type.store`, etc.).

Verificación más fuerte que solo contar rutas: `git diff 56f65f3b..HEAD` (56f65f3b = merge de cierre
de MR-02, item #938) sobre los 3 archivos que declaran esas rutas
(`app/Modules/Addons/Mapas/routes.php`, `app/Modules/Addons/Mapas/module.json`, y las porciones de
`app/Modules/Core/Configuracion/routes.php`/`routes/web.php` que las incluyen) → **sin diferencias**.
De hecho, `git diff 56f65f3b..HEAD --stat -- app/Modules/Addons/Mapas/` sobre el directorio completo
del addon viejo (controllers, repos, vistas, migraciones, todo) → **0 archivos tocados**, de 349
archivos que sí cambiaron en el resto del repo entre MR-02 y HEAD (todos dentro de `MapaRed`,
`mapared_*`, docs y items del roadmap). No solo coincide el conteo: **el código fuente de las rutas
viejas no se movió ni una línea.**

## 4. Permisos existentes

- `Permission::where('name','like','maps_%')->count()` → **44 permisos** vigentes, mismos que
  declara `app/Modules/Addons/Mapas/module.json` (archivo sin diff desde MR-02, ver arriba).
- Ninguna migración corrida entre MR-02 y HEAD toca el namespace `maps_*` (las únicas migraciones de
  permisos nuevas en ese rango son `add_permission_to_module_sidebar_config`,
  `create_embajadores_accion_permissions`, `create_permission_scopes_table`,
  `backfill_permission_scopes_from_manifests`, `create_role_permission_scopes_table` — ninguna
  menciona `maps_` ni el addon `Mapas`).
- El módulo nuevo `MapaRed` usa su **propio** namespace de permiso (`mapa_red_view`, ver su
  `module.json`) — cero colisión ni reuso del namespace `maps_*` del módulo viejo.

## 5. Conclusión

**Cero diferencias en las 4 verificaciones del DoD.** El módulo Mapas viejo (esquema, datos, rutas,
permisos) quedó exactamente igual después de MR-03 a MR-06. No hubo que revertir nada — MR-03..MR-06
construyeron el módulo `MapaRed` como una **copia espejo aditiva** (`mapared_*`, `/mapa-red/*`,
`mapa_red_view`) sin tocar ni un archivo del addon `Mapas` original, confirmando que la estrategia de
"tablas espejo + rutas propias + permiso propio" seguida en MR-04/MR-06 cumplió su objetivo de
aislamiento total.
