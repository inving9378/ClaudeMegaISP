# Catálogo de nomenclaturas de permisos — legacy vs. nueva

Documento de referencia (aditivo, sin cambio de código). Cataloga las convenciones de nombre de
permiso (`permissions.name`, tabla de spatie/laravel-permission) que hoy **coexisten** en MegaISP,
para que cualquiera pueda ubicar de un vistazo a qué "familia" pertenece un permiso antes de
tocarlo. **No renombra ni migra nada** — es solo el mapa.

Snapshot tomado en DEV vía tinker (`Permission::pluck('name')`) el 2026-09-01. Total: **741
permisos**.

## Decisión ya tomada (Irving, item #842 / #852)

> El legacy se queda tal cual + tabla de mapeo documentada. La nomenclatura nueva (`modulo.recurso.accion`)
> es **solo para permisos nuevos** de aquí en adelante. **No se unifican nombres ya existentes** —
> renombrar un permiso vivo en BD implica reasignarlo en `role_has_permissions`/`model_has_permissions`
> de cientos de cuentas, alto blast radius para cero beneficio funcional.

## Las 4 familias encontradas

| Familia | Patrón | Conteo | % |
|---|---|---:|---:|
| **Snake-case plano** (legacy, mayoría) | `modulo_recurso_accion` | 515 | 69.5% |
| **Dotted** (nomenclatura nueva) | `modulo.recurso.accion` | 182 | 24.6% |
| **Mixto** (dotted con algún segmento snake) | `modulo.recurso_con_guion.accion` | 18 | 2.4% |
| **Plano sin separador jerárquico** (kebab o una sola palabra) | `accion-recurso` | 26 | 3.5% |

### 1. Snake-case plano (legacy) — 515 permisos, la mayoría del sistema

Patrón `modulo_recurso_accion` o variantes, sin puntos. Es la convención **original** del sistema,
usada en casi todos los módulos de negocio (clientes, CRM, facturación, OLT, permisos jerárquicos
del item #538).

Ejemplos reales:
```
add_data_plan_promotion
add_sucursal
additional_fields_add_additional_fields
client_edit_client
client_view_dashboard
```

**Sub-familia — permisos jerárquicos `_view_` (convención oficial item #538, CLAUDE.md):**
patrón `{modulo}_view_dashboard` / `{modulo}_view_block_{seccion}` / `{modulo}_view_card_{tarjeta}`
/ `{modulo}_view_info_{x}`, con guion bajo (no punto) **a propósito** — decisión de Irving en su
momento: cero riesgo de renombrar permisos ya asignados a roles en BD por algo estético. 14
permisos hoy (Dashboard es el piloto). Ejemplos:
```
client_view_dashboard
dashboard_view_block_client
dashboard_view_block_finance
dashboard_view_card_client_inline
dashboard_view_card_device_not_responding
```

### 2. Dotted (nomenclatura nueva) — 182 permisos

Patrón `modulo.recurso.accion`, sin guion bajo. Es la convención que se usa en los módulos/addons
**más recientes** (declarados en `module.json`) y en la que deben nacer los permisos nuevos de
aquí en adelante.

Ejemplos reales:
```
auditoria.senales.view
centro-proyecto.view
circuito.decidir
circuito.disparar
circuito.pause
```

Módulo completo de ejemplo (Flotas, 18/18 permisos dotted — 100% consistente):
```
fleet.view
fleet.assign
fleet.gps.view
fleet.gps.manage
fleet.geofences.view
fleet.geofences.manage
fleet.notifications.view
fleet.notifications.manage
```

### 3. Mixto (dotted + snake en el mismo nombre) — 18 permisos

Un módulo adoptó el separador de punto para la jerarquía principal, pero conserva guion bajo dentro
de algún segmento (recurso compuesto o legado interno del módulo). No es un tercer estándar
deliberado, es una convivencia dentro del mismo módulo.

Ejemplos reales (lista completa, son solo 18):
```
family.parent_view_child
talento.activity_types.manage
talento.field_flow.accept
talento.health_bonus.view
talento.ia_validation.override
talento.media.view_sensitive
talento.project_reports.approve
talento.work_orders.validate
torre.terminales.editar_avatar
warroom.action_items.assign
```

### 4. Plano sin separador jerárquico (kebab-case o una palabra) — 26 permisos

Sin puntos ni guion bajo como separador de jerarquía (guion medio en su lugar, o una sola palabra).
Concentrados casi todos en el módulo Marketing (fase de captura de leads / video / integraciones).

Ejemplos reales (lista completa, son solo 26):
```
connect-meta-account
create-marketing-campaigns
delete-marketing-campaigns
generate-video-content
manage-integrations
publish-content
rotate-integration-keys
usar-ia-chat
view-integrations
view-publishing-dashboard
```

## Cómo se resuelven en tiempo de ejecución (ambas conviven sin fricción)

Ninguna capa de autorización distingue por el separador del nombre — spatie/laravel-permission
trata el `name` como una cadena opaca, sea `client_edit_client` o `fleet.view`. Los tres puntos de
consumo reales:

- **Blade:** `@can('permiso.con.punto')` y `@can('permiso_con_guion_bajo')` funcionan idénticos —
  Laravel's `Gate::check()` no parsea el string.
- **Rutas:** `CheckRoutePermission` (middleware) y `config/route_permission.php` mapean **ruta →
  nombre de permiso** por comparación exacta de string; el formato del nombre es irrelevante para
  el middleware.
- **`auth()->user()->can('...')`** (defensa en profundidad inline, ej. `OLTsOnuController`) — mismo
  mecanismo de Spatie (`hasDirectPermission() || hasPermissionViaRole()`), agnóstico al separador.

## Al agregar un permiso nuevo

Seguir la convención **dotted** (`modulo.recurso.accion`), consistente con los módulos recientes
(Flotas, Talento, Circuito/Roadmap, WarRoom). Usar `PermissionSyncService`
(`app/Modules/Core/Security/Services/PermissionSyncService.php`) para propagarlo a los roles base
— es el punto ya existente que usan las migraciones de permisos nuevos (ej. `permisos_torre_salud`,
`permiso_avatar_terminales`), no reinventar el alta.

**No** hay que migrar/renombrar los 515 snake-case ni los 26 kebab-case existentes — quedan como
están, documentados aquí.
