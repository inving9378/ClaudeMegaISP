# Auditoría de cobertura de permisos — barrido completo (todos los módulos)

**Entorno:** DEV · `192.168.105.11` · `/var/www/megaisp` · BD `megaisp` · **2026-07-10**
**Regla:** 100% solo lectura. Nada registrado/sincronizado/migrado. Entregable = este reporte + sets intermedios en `docs/auditorias/_sets_2026-07-10/`.
**Método:** 3 conjuntos — REGISTRADOS (tabla `permissions`, lo que muestra `catalog()`), DECLARADOS (`module.json permissions[]`), REFERENCIADOS (checks estáticos: `can()`, `->can()`, `permission:`/`can:`, `Gate::`, `authorize()`, `hasPermissionTo`, `canView()` Vue, **+ llaves de `config/route_permission.php`** = el gate URL dominante).

---

## Resumen ejecutivo

| Métrica | Valor |
|---|---|
| REGISTRADOS (tabla) | **673** (664 panel · 9 portal) |
| DECLARADOS (module.json) | 407 en 30 módulos |
| REFERENCIADOS (únicos, estáticos) | 571 |
| **GAP DURO** (referenciado, ni registrado ni declarado) | **38** → gates rotos |
| — de esos, *naming drift* (existe equivalente, corregir referencia) | 17 |
| — de esos, *genuinamente ausente* (candidato a crear/es código muerto) | 21 |
| **FALTA SYNC** (declarado en module.json, no en tabla) | **1** |
| HUÉRFANOS (registrado, sin referencia estática) | 141 (informativo, NO borrar) |
| Gap de diseño (escritura sin gate, candidatos reales) | ~16 |
| `!can()` invertidos (revisados) | **0 fail-open** (todos son guard `if(!can) abort` = fail-CLOSED correcto) |

**Top módulos con faltantes:** Marketing (14 gap-duro genuinos), VoIP (4 drift + escrituras controller-gated OK), Configuración/debitcustom (4), Talento (4 drift/missing), Clientes (5 drift + 1 missing), Cobranza (5 diseño).

---

# BLOQUE A — Objetivo (gap duro · falta-sync · huérfanos)

## 🔴 A0 — GAP DURO = **gates rotos = funciones inaccesibles para TODOS** (salvo roles-admin que bypassan `CheckRoutePermission` por nombre)

Cada uno es un check que exige un permiso **que no existe en la tabla ni está declarado**. Efecto:
- Vía `route_permission.php` o `!can(...) abort`: el usuario no-admin recibe **403** (o el botón Vue queda oculto por `canView` falso). Solo los 4 roles-admin (`super-administrator`/`DESARROLLADOR`/`Super Administrador`/`Administrador`) lo alcanzan.
- Vía **Spatie `permission:` middleware** (los de Marketing): si el permiso no existe, Spatie lanza `PermissionDoesNotExist` → puede ser **500 incluso para admin** (no hay `Gate::before` que rescate). ⚠️ el más severo.

### A1 — Naming drift (17): existe un permiso REGISTRADO equivalente → **corregir la referencia** (o crear alias), NO es permiso nuevo

| Referencia rota | Permiso real registrado | Origen |
|---|---|---|
| `talento.orders.view` | `talento.work_orders.view` | route_permission.php |
| `talento.compensations.view` | `talento.compensation.view` | route_permission.php |
| `talento.liquidations.view` | `talento.liquidation.view` | route_permission.php |
| `voip.extensiones.manage` | `voip.extensiones.{view,create,edit,delete,provision,test}` | route_permission.php (los controllers usan las granulares) |
| `voip.grupos.manage` | `voip.grupos.{view,create,edit,delete}` | route_permission.php |
| `voip.troncales.manage` | `voip.troncales.*` | route_permission.php |
| `voip.ia-bot.manage` | `voip.ia-bot.{config,kb,...}` | route_permission.php |
| `warroom.manage` | `warroom.view` (+ granulares `warroom.meeting.*`) | route_permission.php |
| `view_integration_hub` | `config_view_integrations` | route_permission.php |
| `user_permision_user` | `user_permission_user` (**TYPO**, falta la 's') | route_permission.php |
| `vendedor_add` | `seller_add_seller` | Blade `seller/index.blade.php:14` |
| `crm_document_add` | `crm_document_add_crm` | route_permission.php + Vue `DocumentCrmCrud.vue` |
| `client_service_bundle_add` | `client_service_bundle_add_client` | route_permission.php + Vue `BundleService.vue` |
| `client_service_custom_add` | `client_service_custom_add_client` | route_permission.php + Vue `CustomService.vue` |
| `client_service_internet_add` | `client_service_internet_add_client` | route_permission.php + Vue `InternetService.vue` |
| `client_service_voz_add` | `client_service_voz_add_client` | route_permission.php + Vue `VozService.vue` |
| `olt_zones` | (sin exacto; registrados `olt_{view,add,edit,remove}`) | route_permission.php |

> **Nota:** el drift está mayormente en `config/route_permission.php`, que quedó con nombres que no siguieron la evolución de los permisos reales. El fix natural es **alinear las llaves** de ese config a los nombres registrados (no crear permisos nuevos). No requiere aprobar nombres — es corrección de referencias.

### A2 — Genuinamente ausente (21): no hay equivalente registrado → **candidato a crear** (o es código muerto a retirar)

| Referencia rota | Módulo | Origen / evidencia | Nota |
|---|---|---|---|
| `view-leads` | Marketing | `MarketingLeadController.php:16` (**Spatie `permission:`**) | CRUD de leads |
| `create-leads` | Marketing | `MarketingLeadController.php:17` | |
| `update-leads` | Marketing | `MarketingLeadController.php:18` | |
| `delete-leads` | Marketing | `MarketingLeadController.php:19` | |
| `assign-leads` | Marketing | `MarketingLeadController.php:20` | |
| `score-leads` | Marketing | `MarketingLeadController.php:21` | |
| `view-marketing-forms` | Marketing | `MarketingLeadFormController.php:13` | CRUD de formularios |
| `create-marketing-forms` | Marketing | `:14` | |
| `update-marketing-forms` | Marketing | `:15` | |
| `delete-marketing-forms` | Marketing | `:16` | |
| `configure-brand-kit` | Marketing | `MarketingBrandKitController.php:17` + sidebar | |
| `view-conversations` | Marketing | `module-sidebar/marketing.blade.php:16` | |
| `marketing_campaigns_view` | Marketing | `module-sidebar/marketing.blade.php:22` | ⚠️ nombre snake distinto del declarado kebab `create-marketing-campaigns` |
| `marketing_templates_manage` | Marketing | `module-sidebar/marketing.blade.php:25` | |
| `recurring_debts_payments_custom_view` | Configuración | route_permission.php:1615 → `/configuracion/debitcustom` | pantalla de débitos recurrentes custom |
| `recurring_debts_payments_custom_add` | Configuración | :1616 → `/debitcustom/add` | |
| `recurring_debts_payments_custom_edit` | Configuración | :1617 | |
| `recurring_debts_payments_custom_destroy` | Configuración | :1618 | |
| `client_statistics_view_tab_client` | Clientes | `ClientController.php:275` (`hasPermissionTo`) | pestaña de estadísticas del cliente |
| `talento.catalog.view` | Talento | route_permission.php:2103 | (¿= `talento.activity_types.view`?) |
| `talento.employees.view` | Talento | route_permission.php:1957 | sin equivalente registrado |

> ⚠️ **Marketing es el hallazgo más grave**: su gestión de leads/formularios/marca está gateada por **permisos que nunca se crearon**, y como usa **Spatie `permission:` middleware**, un acceso puede reventar en `PermissionDoesNotExist` (500) incluso para admin. Los nombres además están en **tres convenciones distintas** (kebab `view-leads`, kebab-manage `create-marketing-campaigns` en el manifest, snake `marketing_campaigns_view` en el sidebar). Requiere decisión de Irving: **crear** los permisos (¿qué convención?) o **retirar** el código si esa UI está muerta.

## 🟡 A3 — FALTA SYNC (1): declarado en module.json pero no en la tabla

| Permiso | Módulo | Acción |
|---|---|---|
| `config_view_messages` | Configuracion | Está en `module.json`; falta correr `permissions:sync-roles` (o registrar el módulo) para materializarlo. No es permiso nuevo. |

## ⚪ A4 — HUÉRFANOS (141): registrados pero sin referencia estática — INFORMATIVO, NO se borran

Registrados que ningún check estático usa. Pueden ser legítimos (uso dinámico, portal, o legacy). **No se elimina nada en esta reforma.** Distribución (top): client 10, finance 9, seller 7, warroom 6, partners 5, location 5, additional_fields 5, templatetask 4, store 4, scheduling 4, plan 4, panel 4, inventory 4, crm 4, billing 4… Lista completa en `_sets_2026-07-10/huerfanos.txt`.

---

# BLOQUE B — Propuestas de diseño (acciones de escritura sin gate de permiso)

De 1,273 rutas de escritura: 200 sin gate de **middleware**; tras excluir categorías intencionalmente abiertas (portal guard-cliente, webhooks, `api/*` sanctum, login/registro, helpers de formulario y preferencias de UI propias) y las **controller-gated** (VoIP y Talento sí validan con `if(!can) abort` / Actor dentro del controlador), quedan estos candidatos reales — la mayoría **ya tienen permiso registrado pero la ruta no lo exige**:

| Ruta (escritura) | Controlador | Permiso propuesto | context | Nota |
|---|---|---|---|---|
| `POST /cobranza/campanas` | CampanaController | `cobranza.manage` (**ya existe**) | panel | crear campaña de cobranza sin gate |
| `DELETE /cobranza/campanas/{id}` | CampanaController | `cobranza.manage` | panel | |
| `POST /cobranza/campanas/{id}/activar` | CampanaController | `cobranza.manage` | panel | |
| `POST /cobranza/campanas/{id}/pausar` | CampanaController | `cobranza.manage` | panel | |
| `POST /cobranza/voip/configuracion` | VoipConfiguracionController | `cobranza.configure` (**ya existe**) | panel | config VoIP de cobranza |
| `POST /megafamilia/perfiles` | PerfilesController | `megafamilia_admin` (**ya existe**) | panel | verificar si depende solo de tenant-scope |
| `PUT/DELETE /megafamilia/perfiles/{id}` | PerfilesController | `megafamilia_admin` | panel | |
| `POST/DELETE /megafamilia/tareas`, `/{id}/approve`, `/{id}/reject` | TareasController | `megafamilia_admin` | panel | aprobar/rechazar tareas |
| `POST /evaluador-empresarial/guardar` | EvaluadorEmpresarialController | `evaluador_*` (verificar existentes) | panel | |
| `POST /evaluador-empresarial/enviar-email` | EvaluadorEmpresarialController | `evaluador_*` | panel | |
| `POST /crm/send-notification/{id}` | NotificationController | proponer `crm_notification_send` | panel | envía notificación al cliente |
| `POST /ia/chat` | IAChatController | **decisión Irving**: ¿gate `ia.chat_use` o dejar abierto a todo staff? | panel | costo por uso de IA |

**Revisadas y consideradas intencionalmente sin permiso** (no gap): webhooks (`spei`, `evolution`, `meta-ads`), `login/logout/register`, helpers de formulario (`get-options-*`, `fields-by-module`, `columns-by-module`…), preferencias de UI propias (`save-app-config-layout`, `save-row-status-style`, `set-config-tabs`), `profile/change-password`, y todo `talento/api/*`, `talento/portal/*`, `voip/*` (gated en el controlador).

---

## Convención observada (para nombrar propuestas)
- Core clásico: `snake_case` con módulo+acción+objeto (`client_service_bundle_add_client`, `seller_add_seller`).
- Addons modernos: `dotted` `modulo.recurso.accion` (`fleet.rules.manage`, `talento.work_orders.view`, `cobranza.manage`).
- `context`: **panel** por default (regla B3: en duda → panel; toda administración → panel). **portal** solo self-service del colaborador (hoy 9).

## Checkpoint
CC se detiene aquí. Irving aprueba: (1) para A1 → alinear referencias en `route_permission.php`/Blade/Vue; (2) para A2 → qué crear vs retirar + convención de nombres (sobre todo Marketing); (3) para B → gate de las escrituras + `context`. El registro posterior será **additive** vía el hook de módulos + `permissions:sync-roles` + set de `context`, respetando la reforma (asignación solo por rol).
