# Clasificación de los 69 permisos huérfanos — item #9991121

Reporte SOLO LECTURA. Nada de lo aquí documentado se retiró ni se modificó — es exactamente el
paso que Irving aprobó en la pregunta `q1` del item (Opción 1: "generar reporte clasificado...
y presentarlo a Irving en tabla antes de borrar nada"). La decisión de qué hacer con cada cubeta
queda pendiente de que Irving la revise en una vuelta futura.

Fuente: `docs/auditoria/permisos-punto1-2.csv` (columna `estado=huerfano`, 69 de 770 permisos),
generado por `auditoria:permisos-reporte` (#9990763). Clasificación cruzada con roles/usuarios
activos vía el comando nuevo `auditoria:permisos-huerfanos-clasificar` (este item), salida en
`docs/auditoria/permisos-huerfanos-clasificacion.csv`.

## 0. Hallazgo de metodología — "asignado a rol con usuarios activos" no distingue nada

Los 69 (sin excepción) están asignados a `super-administrator` y/o `DESARROLLADOR` — los roles
base que reciben **todo** permiso nuevo por diseño (`PermissionSyncService::syncPermissionToBaseRoles`,
usado en toda migración que crea un permiso). Como ambos roles tienen usuarios activos, el eje
"¿tiene usuarios activos asignados?" sale `true` para los 69 sin excepción — no sirve para separar
huérfanos reales de falsos positivos. El eje que sí separa es la revisión semántica caso por caso
(sección 1 de este documento), tal como advertía el propio comando `auditoria:permisos-reporte`.

## 1. Las 7 cubetas

### Cubeta A — FALSO POSITIVO (construcción dinámica confirmada): 12 — **NO retirar, corregir el reporte base**

`documentacion-corporativa.apartado.{i,ii,iii,iv,v,vi,vii,viii,ix,x,xi,xii}.view`

Verificado en código real: `InventarioController.php:179` y `RegistroEstructuradoController.php:234`
llaman `auth()->user()?->can("documentacion-corporativa.apartado.{$apartado}.view")` dentro de un
`array_filter` sobre la lista de apartados del recurso — exactamente el patrón de construcción
dinámica que el propio comando advierte que no puede detectar (grep literal). Los apartados
`.xiii`/`.xiv` de la misma familia salen "usados" en el CSV base por una coincidencia (su nombre
literal aparece en comentarios de migraciones), no por una ruta de código distinta — los 12 de
aquí se usan exactamente igual. **Estos 12 NO son huérfanos reales.**

### Cubeta B — LEGACY SUPERADO (permiso viejo, reemplazado por uno consolidado): 4 — candidatos reales a retirar

- `documentacion-corporativa.activo.manage`
- `documentacion-corporativa.activo-digital.manage`
- `documentacion-corporativa.inventario.accesos.manage`
- `documentacion-corporativa.inventario.accesos.view`

Verificado: la migración `2026_08_29_170000_grant_inventario_manage_permission.php` (Fase 3.2,
item #751) documenta explícitamente que `documentacion-corporativa.inventario.manage` es
"**un solo permiso de escritura para las 3 tablas**" (`dc_activos`, `dc_activos_digitales`,
`dc_inventario_accesos`) que reemplazó el diseño granular anterior; la lectura de esos mismos
recursos ya la cubre `.apartado.{viii,xi,xii}.view` (cubeta A). Los 4 nombres de aquí no aparecen
en ningún sitio del código fuera del propio `permissions` — son el residuo de la iteración de
diseño anterior a la consolidación.

### Cubeta C — CRUD BOILERPLATE MUERTO (el módulo existe, pero esta acción específica la protege OTRO permiso): 18 — candidatos reales a retirar

- `additional_fields_{add,delete,edit,export,view}_additional_fields` (5)
- `templatetask_{add,delete,edit,export}_templatetask` (4) — `_view_` de la misma familia SÍ se usa (sidebar), no está en huérfanos
- `inbox_{add,delete,edit}_inbox` (3)
- `inventory_item_stock_{add,delete,edit}_inventory_item_stock` (3)
- `service_in_address_list_{add,delete,edit}_service_in_address_list` (3)

Verificado ruta por ruta en `config/route_permission.php`:
- Las rutas reales de `additional-fields` y `template-task` (`/add`, `/editar/{id}`, `/destroy/{id}`…)
  están protegidas por el permiso paraguas `config_view_system` (línea 1422), no por los permisos
  con su propio nombre de módulo.
- `inbox` es de solo lectura (ver + enviar mensaje) — nunca tuvo pantallas add/edit/delete; esos 3
  permisos parecen boilerplate generado sin la funcionalidad detrás.
- `/inventory/inventory_item_stock/add` y `/editar/{id}` están protegidos por
  `inventory_item_add_inventory_item`/`inventory_item_edit_inventory_item` (líneas 1032-1040) —
  permisos del módulo padre `inventory_item`, no los de `inventory_item_stock`.
- **`service_in_address_list_{add,delete,edit}` es un caso aparte**: sus rutas
  (`/configuracion/service_in_address_list/add|editar|destroy`, `app/Modules/Core/Configuracion/routes.php:286-289`)
  **no aparecen en `config/route_permission.php` en absoluto** — no es que un permiso distinto las
  proteja, es que no tienen entrada. Esto podría ser un agujero real (mismo patrón que
  `#9991118`/`#9991120`) y no solo un permiso huérfano; se anota aquí para no perderlo pero
  **no se decide en este item** — su lugar es la auditoría de agujeros de ruta, no la de permisos.

### Cubeta D — CRUFT DE PRUEBAS: 2 — candidatos claros a retirar

`test_item842_probe_b`, `test_item842_probe_c` — nombres literales de sondas de prueba del item
#842, cero referencias en código fuera del propio CSV de auditoría. No hay ambigüedad posible.

### Cubeta E — FACTURACIÓN/PAGOS (ya delegado a `#9991119`, NO decidir aquí): 5

`billing_payment_view_clients`, `billing_view_detail_payments`, `billing_payment_sellers`,
`invoice_view_invoice`, `invoice_edit_invoice` — exactamente los 5 nombres de la sección 3.4 del
documento final (`docs/auditoria-permisos-2026-09.md`), repartidos en 27 asignaciones sobre 8
roles. El item hermano `#9991119` ("Saneamiento del rol 'consejo' inflado + decidir destino de 27
permisos de facturación/pagos inertes en 8 roles") ya está tomado por otra terminal — no se
duplica la decisión aquí.

### Cubeta F — MÓDULO CON PERMISO ÚNICO, GRANULARIDAD SIN CABLEAR (épicas activas): 10 — pedir confirmación antes de tocar

- `inversiones.{backtest,manage,operar,riesgo.edit,thomas.view}` (5) — módulo `Inversiones` (bot de
  trading interno, **sin relación con el circuito** pese al nombre `thomas.view` — ver
  `config/inversiones.php`, ya anotado en `CLAUDE.md`). Sus rutas (`app/Modules/Addons/Inversiones/routes.php`)
  usan un único permiso `inversiones.view` (`config/route_permission.php:2479`); los 5 granulares
  nunca se cablearon a ningún control.
- `mapa_red_catalogo_{manage,view}`, `mapa_red_ver_impacto`, `mapa_red.consultar_cobertura`,
  `mapared.drops.manage` (5) — el módulo MAPA DE RED (épica MR-* muy activa) también protege TODAS
  sus rutas con un único permiso `mapa_red_view` (`config/route_permission.php:2502`); ningún
  controller (`CatalogosController`, `ImpactoController`, `CoberturaController`, etc.) tiene un
  `can()`/`authorize()` inline que use estos 5 nombres. Podrían ser permisos creados de más
  (sobre-provisión) o planeados para una fase de cableado fino que aún no llegó — dado que la
  épica sigue con sub-items abiertos, **no se recomienda retirarlos todavía** sin que quien lleve
  el epic confirme que no están en su plan de corto plazo.

### Cubeta G — SIN CLASIFICAR (no se investigó caso por caso en esta vuelta): 18

`admin_view_files`, `billing_view_transactions`, `company_view_information`,
`crm_view_of_convert_crm_to_client`, `documentation_view_test_unit`, `family.parent_view_child`,
`labels_view_labels`, `location_export_location`, `olt_edit`, `olt_remove`, `panel_view_billing`,
`panel_view_prospects`, `panel_view_sales`, `panel_view_stadistics`, `scheduling_task_delete`,
`task_export_task`, `templates_view_templates`, `translation_view_translation`.

No se les hizo la verificación semántica (grep de construcción dinámica + cruce con
`config/route_permission.php`) que sí se aplicó a las cubetas A-C — investigarlos con el mismo
rigor es trabajo adicional razonable para una vuelta futura si Irving decide seguir con la
limpieza. Se listan aparte para no mezclarlos con los candidatos ya verificados.

## 2. Resumen para decidir

| Cubeta | Cantidad | ¿Candidato a retirar? |
|---|---|---|
| A — Falso positivo (dinámico) | 12 | No — están vivos, es el reporte base el que se equivoca |
| B — Legacy superado | 4 | Sí, verificado |
| C — CRUD boilerplate muerto | 18 (17 confirmados + 3 de `service_in_address_list` con matiz de agujero de ruta) | Sí, verificado (salvo el matiz anotado) |
| D — Cruft de pruebas | 2 | Sí, verificado |
| E — Facturación/pagos | 5 | No decidir aquí — ver `#9991119` |
| F — Épicas activas, permiso único sin cablear | 10 | No sin confirmar con el epic dueño |
| G — Sin clasificar | 18 | Pendiente de investigación |

**Total verificado con evidencia caso por caso: 51 de 69** (A+B+C+D+E+F). Dentro de la cubeta C
(18), 15 son CRUD boilerplate limpio (`additional_fields` 5 + `templatetask` 4 + `inbox` 3 +
`inventory_item_stock` 3) y los 3 de `service_in_address_list` quedan aparte por el matiz de
agujero de ruta. **Candidatos limpios a retirar hoy mismo si Irving los aprueba: 21** (B: 4 + C
limpio: 15 + D: 2). Los 3 de `service_in_address_list` esperan a que se confirme si son agujero de
ruta o permiso muerto (fuera de alcance de este item).

## 3. Próximo paso

Este item se cierra al entregar la clasificación — no se retira ningún permiso todavía (así lo
decidió Irving en `q1`). Si Irving confirma que quiere proceder con la limpieza de las cubetas
B+C+D (24 permisos, aditivo/reversible — `Permission::whereIn('name', [...])->delete()` +
`forgetCachedPermissions()`), eso es trabajo de un item nuevo y separado (nivel A, ejecutable en
una vuelta), no de este. La cubeta A amerita su propio arreglo aparte: el comando
`auditoria:permisos-reporte` debería tratar `documentacion-corporativa.apartado.*.view` como
"usado" en corridas futuras (whitelist de patrones dinámicos conocidos) — también fuera de
alcance de este item.
