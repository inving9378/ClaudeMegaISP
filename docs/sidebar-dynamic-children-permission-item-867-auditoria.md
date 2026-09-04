# Auditoría #867 — `permission` en los `dynamic_children` de `module_sidebar_config`

Sigue a #856 (agregó la columna nullable `permission` a `module_sidebar_config` + el filtrado con
herencia del padre en los 14 partials de `resources/views/module-sidebar/`, fail-open cuando ni
hijo ni padre la declaran). Este item ejecuta el **paso (a)** aprobado por Irving en la pregunta
`q1` del propio item: auditoría read-only primero, poblado después y solo con su visto bueno.

## Alcance

El propio texto de origen del item acota el trabajo real a la **única fila `sidebar_location=sub_item`**
que existe hoy en la BD de dev (`gestion-red-mikrotik-sync`). Los 34 módulos `direct` (parents de
primer nivel) quedan **fuera de alcance** — así lo declaró el item desde su creación ("los demás
parents (14 módulos) quedan sin `permission` de forma segura (fail-open) hasta que un item futuro
los declare"). Se listan igual más abajo para tener el inventario completo, pero no se audita
permiso por permiso para ellos en esta vuelta.

## Metodología

1. `SELECT * FROM module_sidebar_config` completo (35 filas: 34 `direct` + 1 `sub_item`).
2. Para la fila `sub_item` (`gestion-red-mikrotik-sync`, `sidebar_parent=gestion-red`,
   `sidebar_url=/red/mikrotik-sync`): grep de `mikrotik-sync` sobre `routes/web.php`, sobre
   `routes.php`/`module.json` de todos los addons, y sobre `config/route_permission.php` — sin
   resultados en ningún caso.
3. Grep de `mikrotik` en general para descartar que la funcionalidad exista bajo otro nombre:
   únicamente aparecen rutas de configuración de un router Mikrotik individual
   (`/red/router/mikrotik/*`, en `config/route_permission.php`) y tres comandos de consola
   (`app:mikrotik-sync-command` cada 5 min, `mikrotik:sync-consumption` cada 10 min,
   `mikrotik:sync-ping` cada 5 min, todos en `app/Console/Kernel.php`) — ninguno expone un
   endpoint HTTP ni un controlador web.

## Hallazgo

| Fila | `sidebar_parent` | `sidebar_url` | `permission` actual | ¿Ruta registrada? | ¿Permiso Spatie equivalente? |
|---|---|---|---|---|---|
| `gestion-red-mikrotik-sync` | `gestion-red` | `/red/mikrotik-sync` | `NULL` | **No** (404 confirmado — cero coincidencias en `routes/web.php` ni en ningún addon) | **Ninguno** — no hay controlador/ruta que respalde el enlace |

No existe ningún consumidor real de esa URL: es un enlace visible en el sidebar bajo "Gestión de
red" → "Sync Mikrotik" que hoy da 404 a quien haga click. La única funcionalidad real de
sincronización con Mikrotik en el sistema son los tres comandos de consola de arriba (automáticos,
sin UI).

## Decisión aplicada (según `q2`, ya resuelta por Irving en este mismo item)

Al no existir un permiso Spatie equivalente claro (la fila es ambigua: no hay ruta que mapear), se
aplica la Opción 1 elegida por Irving para casos ambiguos: `permission` queda en `NULL` (ya lo
estaba — **sin cambio de dato**), sin ocultar la fila ni forzar un permiso genérico. El fail-open
de #856 sigue vigente y es seguro: hoy cualquier usuario autenticado que vea "Gestión de red" ve
también este enlace roto, exactamente igual que antes de #856/#867 — **no hay regresión de
seguridad ni de visibilidad**, y tampoco había nada que poblar en la BD en esta vuelta (0 de 1
filas con permiso mapeable).

## Qué queda pendiente (no bloqueante)

Decisión de producto que solo le compete a Irving (ya señalada desde el propio texto de origen del
item): o bien (a) se construye la pantalla/ruta real de "Sync Mikrotik" con su guard de permiso, o
bien (b) se acepta que la fila es residual y se desactiva (`show_in_sidebar=false`) o se borra. Esa
disyuntiva es una decisión de alcance de producto, no una auditoría — queda documentada aquí para
cuando Irving la resuelva. No se abre un ítem de seguimiento automático para esto (evitar sumar al
patrón de "bucle reap sobre paraguas sin trabajo propio" ya visto en #738/#745/#830/#816/#848); si
se decide avanzar, se abre bajo criterio explícito de Irving.

## Los 34 módulos `direct` (fuera de alcance de #867)

`dashboard, planes, crm, clientes, gestion-red, finanzas, inventario, olts, mapas, cobranza-blaster,
megafamilia, scheduling, embajadores, flotas, talento, warroom, marketing, voip, administracion,
centro-proyecto, configuracion, devtools, ia, reportes, vendedores, tickets, payments, mensajes,
whatsapp-agent, smart-import-export, manual, hub, evaluador-empresarial, demo`. Ninguno tiene
`permission` poblado; sus eventuales `dynamic_children` heredan del padre (fail-open de #856), así
que quedan cubiertos de forma segura hasta que un item futuro los audite uno por uno.

## Nota técnica — dependencia con #856 no mergeada a `main`

Al momento de esta auditoría, la columna `permission` en `module_sidebar_config` **ya existe** en
la base de datos compartida de dev (confirmado con `Schema::hasColumn`) y la migración
`2026_09_01_164304_add_permission_to_module_sidebar_config` figura corrida en la tabla
`migrations` — pero el archivo de esa migración y el resto de los commits de #856 (`b8852793`,
`46040d52`, `41148e7e`) viven únicamente en la rama `circuito/item-856-fase-2b-sidebar-los-dynamic-children`,
que todavía **no** está mergeada a `main` (`estado_aprobacion=aprobado_irving`, sin `merge_commit`,
sin dueño reclamado). Esta auditoría se hizo contra el estado real de la BD compartida (que ya
tiene la columna), no contra el árbol de `main` de este worktree — no se tocó la rama de #856 (no
es de este item). Cuando #856 se integre a `main`, este hallazgo sigue siendo válido sin cambios.
