# Auditoría final del agujero de permisos por ruta — Fases 4b-1 a 4b-4

Consolida los 3 lotes de auditoría del padre #9990745 → #9990764 → #9990745:

- **#9990769** (Fase 4b-1) — `docs/permisos-agujero-item-9990764-parte1-core.md` — Core + `routes/web.php` (15 archivos)
- **#9990770** (Fase 4b-2) — `docs/permisos-agujero-item-9990764-parte2-addons-grandes.md` — Talento / MegaFamilia / Mapas / Roadmap / Marketing / GestionRed / MapaRed (7 addons grandes)
- **#9990771** (Fase 4b-3) — `docs/permisos-agujero-item-9990764-parte3-addons-resto.md` — 29 addons restantes

**Este documento es #9990772 (Fase 4b-4), el ENTREGABLE final del punto 3 del prompt original de
#9990745**: *"lista completa (no truncada) de rutas sin ninguna protección real... clasificadas por
riesgo"*. SOLO LECTURA — no se tocó ninguna ruta, middleware, config ni permiso; es puro
compilado/recuento de lo ya documentado en los 3 lotes de arriba (ya mergeados a `main`).

## Cómo leer esto

Las 5 clasificaciones son las mismas en los 3 lotes (metodología idéntica):

1. **PROTEGIDA** — dentro de un grupo `check_route_permission`.
2. **GATEADA POR ROL/GUARD** — `role:`/`permission:`/`can:` de Spatie o guard dedicado.
3. **AUTHORIZE/SCOPING INLINE** — sin (1), pero el controller aplica su propio candado
   (`->authorize()`, `Gate::`, `can()` inline, auto-scoping fuerte por `auth()->id()`).
4. **AGUJERO REAL** — solo `auth` (o nada), sin 1/2/3. Riesgo CRÍTICO (modifica datos/dinero/
   credenciales) / MEDIO (lectura sensible o cruza usuarios) / BAJO (lectura pública-adyacente o
   auto-scoping débil sin literal `authorize`).
5. **PUBLIC_ROUTES / pública intencional** — a propósito, no es hallazgo.

`check_route_permission` = `App\Modules\Core\Auth\Middleware\CheckRoutePermission`, y es
**fail-closed** para usuarios no-admin (confirmado contra el código real en el lote 3): si ninguna
entrada de `config/route_permission.php` matchea el path, la petición se **deniega**, no pasa
gratis. Solo `isAdmin()`/`isSuperAdmin()`/`isDevelopment()` se saltan la verificación. El agujero
real nunca es "falta la entrada en el config" — es la **ausencia total del middleware/guard** en el
grupo de rutas, que es justo lo que clasifica la categoría 4.

---

## Resumen ejecutivo

### Alcance total de los 3 lotes

| Lote | Módulos cubiertos | Rutas con agujero real (clasificación 4) |
|---|---|---|
| #9990769 (Core + web.php) | 15 archivos | 57 (+ 1 de riesgo ambiguo) |
| #9990770 (7 addons grandes) | Talento, MegaFamilia, Mapas, Roadmap, Marketing, GestionRed, MapaRed | 27 (9 MegaFamilia + 18 Marketing; los otros 5 addons: 0) |
| #9990771 (29 addons restantes) | Flotas, PortalCliente, Inventario, Vendedores, Payments, DocumentacionCorporativa, IA, Embajadores, Planes, Finanzas, WhatsAppAgent, VoIP, PortalPago, WarRoom, Scheduling, Mensajes, Tickets, CobranzaBlaster, SmartImportExport, Domiciliacion, DevTools, EvaluadorEmpresarial, Manual, Empresa, VozMayorista, Hub, Demo, Inversiones, CentroProyecto | **0** |

**Total AGUJERO REAL en todo el sistema auditado: 84 rutas** (12 CRÍTICO + 38 MEDIO + 33 BAJO + 1
de riesgo ambiguo BAJO-MEDIO), concentradas en **3 focos**: `routes/web.php` (utilería legacy que
quedó fuera de la migración a módulos), `MegaFamilia` (lectura de datos de menores) y `Marketing`
(WhatsApp real de `MarketingConversationController`). El resto del sistema — 29 addons completos
del lote 3, y 5 de los 7 addons grandes del lote 2 (Talento, Mapas, Roadmap, GestionRed, MapaRed)
— **no tiene agujeros**.

### Totales por riesgo (dentro de la clasificación 4)

| Riesgo | Cantidad | Dónde |
|---|---|---|
| 🔴 **CRÍTICO** | **12** | 8 en `routes/web.php` (control de cuentas, leak de passwords PPP, DDL arbitrario) + 4 en Marketing (`MarketingConversationController`, WhatsApp real) |
| 🟠 **MEDIO** | **38** | 20 en `routes/web.php` (IDOR, activity log completo, búsquedas sin permiso) + 6 en MegaFamilia (datos de menores) + 12 en Marketing (conversaciones + pilot campaigns) |
| 🟡 **BAJO** | **33** | 28 en `routes/web.php` (metadata/auto-scoping débil) + 3 en MegaFamilia (shells sin datos) + 2 en Marketing (embed-code, catálogo) |
| ⚪ Ambiguo (BAJO-MEDIO, sin decidir) | 1 | `routes/web.php`: `/get-options-team` |

### Nota de consolidación — discrepancias encontradas en los resúmenes originales

Al recontar fila por fila las tablas ya mergeadas de #9990769/#9990770 (sin re-auditar código, solo
sumando lo ya documentado) se encontraron 2 desajustes entre el resumen ejecutivo de cada lote y su
propia tabla detallada. Se dejan anotados para que quien revise sepa que los números de este
documento final vienen del recuento fila-por-fila (más preciso), no de los encabezados originales:

- **Marketing (#9990770):** el resumen del lote dice "8 rutas en AGUJERO REAL"; la tabla detallada
  (sección 5.2) enumera **18** filas con clasificación 4 (4 CRÍTICO + 12 MEDIO + 2 BAJO). El
  resumen original solo contaba el bloque `MarketingConversationController` + `mark-as-read`; se
  le escaparon `getEmbedCode`, `download` y las 7 rutas de `PilotCampaignController`, que la propia
  tabla SÍ marca como clasificación 4.
- **`routes/web.php` (#9990769):** el listado "Los 7 CRÍTICOS" del lote no incluye
  `GET /information-dev` (línea 63), que su propia tabla de la sección 1.2 marca como CRÍTICO
  ("vuelca HTML con id/nombre/email/balance/billing de TODOS los clientes, sin paginar"). Se agrega
  aquí como el 8º CRÍTICO. Los conteos aproximados de MEDIO (~28) y BAJO (~20) del resumen del lote
  también resultaron invertidos frente al recuento real (20 MEDIO / 28 BAJO) — diferencia menor, sin
  reclasificar ninguna fila individual.

Ninguna de las dos correcciones cambia el diagnóstico central de ningún lote: solo ajusta los
totales del recuento a lo que sus propias tablas ya decían fila por fila.

---

## Lista completa de AGUJERO REAL (clasificación 4), agrupada por módulo

### 1. `routes/web.php` (item #9990769)

#### 🔴 CRÍTICO (8)

| Ruta | Controller@método | Riesgo |
|---|---|---|
| `POST /perfil/update/{id}` | `UserController@update` | Toma control de CUALQUIER usuario por id arbitrario — `authorize()` devuelve `true` a secas y `password` es `required` en cada llamada |
| `GET /information-dev` | `TestScriptController@getInformationDevelopment` | Vuelca id/nombre/email/balance/billing de TODOS los clientes, sin paginar |
| `GET /data-client-mikrotik/{id}` | `TestScriptController@getDataToMikrotikByClientId` | Leak del password PPP del secret Mikrotik del cliente, en texto plano |
| `POST /data-client-mikrotik-by-ip` | `TestScriptController@getDataToMikrotikByIp` | Mismo leak, buscando por IP (revela además a qué cliente pertenece) |
| `GET /script` | `TestScriptController@script` | `DROP TRIGGER`/`CREATE TRIGGER` sobre `payments` en cada request + `dd()` de debug |
| `GET /libera-ip/{id}` | `TestScriptController@liberaIp` | Libera cualquier IP (`used=false, client_id=null`) sin validar relación con el usuario |
| `POST /save-random-password` | `HelperController@saveRandomPassword` | Escribe un campo arbitrario (declarado password) en un registro arbitrario de un módulo arbitrario |
| `POST /perfil/get-perfil-by-id/{id}` | `UserController@getPerfilById` | IDOR: lee el registro `users` completo de cualquier otro usuario |

#### 🟠 MEDIO (20)

| Ruta | Controller@método | Riesgo |
|---|---|---|
| `GET /notification-email` | closure | Envía/renderiza mail de notificación sin ningún middleware |
| `GET /log-client` | `TestScriptController@logClient` | Activity log filtrado por fecha/cliente sin scoping ni permiso |
| `GET /admin/consumo` | `InternetConsumptionRadiusController@index` | Consumo RADIUS de TODOS los clientes |
| `GET /admin/consumo/{username}` | `@show` | Detalle de sesiones RADIUS por username arbitrario |
| `POST /get-data/{module}` | `HelperController@getData` | Datos de infraestructura de red (router) por id arbitrario |
| `POST /get-options-select` | `SearchModelController@search` | Búsqueda genérica sobre modelos allowlisted, sin gating de permiso |
| `POST /get-options-select/{id}` | `@searchWithoutId` | Igual |
| `POST /get-long-options-select` | `@longOptions` | Igual |
| `POST /get-options-client` | `@longOptionsClient` | Búsqueda de clientes sin permiso `client_view` |
| `POST /get-options-client-task` | `@longOptionsClientTask` | Igual |
| `POST /get-options-client-prospect` | `@longOptionsClientProspect` | Igual |
| `GET /perfil/{id}` | `UserController@show` | Shell de vista para id arbitrario, sin ownership |
| `GET /perfil/editar/{id}` | `@edit` | Igual |
| `POST /crm/send-notification/{id}` | `Utils\NotificationController@sendNotificationCrm` | Envía correo a cualquier dirección con datos del CRM — relay de spam/phishing |
| `POST /get-log-activities/{id}` | `Utils\LogActivityController@getLogActivities` | Activity log por id sin permiso |
| `GET /get-all-activities` | `@getAllActivities` | Vuelca el activity log COMPLETO del sistema, sin paginar |
| `GET /get-activities-by-user/{id}` | `@getActivitiesByUser` | IDOR: actividad de cualquier usuario |
| `POST /fullcalendar/get-task-events` | `Utils\FullcalendarController@getTaskEvents` | Eventos de calendario, alcance no verificado |
| `POST /get-crm-client-if-exist` | `HelperController@getCrmClientIfExist` | Oráculo de existencia — permite enumerar prospectos/clientes |
| `POST /save-or-delete-default-value` | `SharedDefaultValues@saveOrDeleteDefaultValue` | Escribe/borra un `DefaultValue`, alcance no claro |

#### 🟡 BAJO (28)

`POST /cliente/get-receipt-for-client`, `POST /get-payment-period`, `GET /setting-table/get/{table_id}`,
`POST /setting-table/post/{table_id}`, `POST /user/get-next-user`, `POST /fields-by-module`,
`POST /fields-by-module-and-relation`, `POST /fields-by-module-with-module-requested`,
`POST /fields-by-module/{id}`, `POST /fields-by-module/general/edited`, `POST /columns-by-module`,
`POST /column-expand-by-module`, `POST /set-column-expand-by-module`,
`POST /columns-by-module-except-columns`, `POST /all-columns-by-module`,
`POST /update-column-by-user`, `POST /request-random-password`,
`POST /request-generate-user/{username}`, `POST /request-generate-user-exist`,
`POST /perfil/update-image/{id}` (no-op, comentado), `POST /get-user-authenticated`,
`POST /get-default-value/{date}`, `POST /get-default-billing-date-for-client`,
`POST /cliente/get-user-for-client`, `POST /fullcalendar/get-billing-configuration`,
`POST /get-default-fields-value`, `GET /sin-modulos`, `GET /home` (duplicado de `/` ya protegida).
Todas metadata de formulario, auto-scoping débil, o contenido fijo sin datos de negocio — ver
detalle fila por fila en `docs/permisos-agujero-item-9990764-parte1-core.md`.

#### ⚪ Riesgo ambiguo (1)

`POST /get-options-team` (`HelperController@getOptionsTeam`) — lista equipos/usuarios agrupados
(org interna, no PII de clientes); el lote original lo etiquetó "BAJO-MEDIO" sin decidir.

---

### 2. MegaFamilia (item #9990770)

#### 🟠 MEDIO (6)

| Ruta | Controller@método | Riesgo |
|---|---|---|
| `GET /megafamilia/perfiles/data` | `PerfilesController@data` | Sin `guardOwnership` — si el usuario no tiene `ParentalAccount` propia, devuelve nombre/edad/nivel escolar de TODOS los perfiles de TODAS las familias cliente |
| `GET /megafamilia/perfiles/{id}` | `@show` | IDOR: perfil completo de un menor (devices/rules/appBlocks/webBlocks/schedules/tasks/alerts) sin ownership |
| `GET /megafamilia/tareas/data` | `TareasController@data` | TODAS las tareas de TODOS los perfiles, `profile_id` opcional |
| `GET /megafamilia/reportes/data` | `ReportesController@data` | KPIs/tiempo de pantalla/top-apps/actividad de TODOS los perfiles si no se filtra |
| `GET /megafamilia/reportes/profiles` | `@profiles` | Lista nombre/tipo/foto de todos los perfiles activos, sin scoping |
| `GET /megafamilia/reportes/export` | `@export` | Export CSV (screen time, apps, sitios bloqueados) de cualquier perfil o de todos |

#### 🟡 BAJO (3)

`GET /megafamilia/perfiles/`, `GET /megafamilia/tareas/`, `GET /megafamilia/reportes/` — solo shell
Blade sin datos (la exposición real está en sus `/data` hermanos, arriba).

---

### 3. Marketing (item #9990770)

#### 🔴 CRÍTICO (4)

| Ruta | Controller@método | Riesgo |
|---|---|---|
| `POST /api/marketing/conversations/{id}/send-message` | `MarketingConversationController@sendMessage` | Cualquier usuario autenticado envía un WhatsApp REAL a un cliente real suplantando al negocio |
| `POST /api/marketing/conversations/{id}/toggle-ai` | `@toggleAi` | Apaga/enciende el bot de IA en una conversación con cliente real, sin permiso |
| `POST /api/marketing/conversations/{id}/assign` | `@assign` | Reasigna la conversación a otro agente sin permiso |
| `POST /api/marketing/conversations/{id}/close` | `@close` | Cierra la conversación con el cliente sin permiso |

#### 🟠 MEDIO (12)

| Ruta | Controller@método | Riesgo |
|---|---|---|
| `GET /api/marketing/conversations` | `MarketingConversationController@index` | Lista TODAS las conversaciones de WhatsApp con nombre/teléfono del lead |
| `GET /api/marketing/conversations/{id}` | `@show` | Detalle de cualquier conversación |
| `GET /api/marketing/conversations/{id}/messages` | `@messages` | Mensajes reales del cliente |
| `POST /api/marketing/conversations/{id}/mark-as-read` | `@markAsRead` | Escritura de bajo impacto, sin gate |
| `GET /api/marketing/generated-content/{id}/download` | `MarketingGeneratedContentController@download` | Descarga el video real del disco por id, sin exigir `view-video-content` |
| `GET /api/marketing/pilot-campaigns` | `PilotCampaignController@index` | Sin gate (mitigado: opera sobre lista dummy documentada, nunca clientes reales) |
| `POST /api/marketing/pilot-campaigns` | `@store` | Igual |
| `GET /api/marketing/pilot-campaigns/{id}` | `@show` | Igual |
| `DELETE /api/marketing/pilot-campaigns/{id}` | `@destroy` | Igual |
| `POST /api/marketing/pilot-campaigns/{id}/dry-run` | `@dryRun` | Igual, no envía nada real |
| `POST /api/marketing/pilot-campaigns/{id}/send` | `@send` | Envía correos reales, pero a la lista dummy interna documentada |
| `POST /api/marketing/pilot-campaigns/{id}/sends/{sendId}/mark-converted` | `@markConverted` | Igual |

#### 🟡 BAJO (2)

`GET /api/marketing/lead-forms/{id}/embed-code` (`MarketingLeadFormController@getEmbedCode` —
snippet pensado para pegarse en sitio externo, igual que `/public/marketing/embed.js` ya público) y
`GET /api/marketing/lead-sources` (closure — catálogo de nombres de fuente de lead, sin PII).

---

### 4-11. Talento, Mapas, Roadmap, GestionRed, MapaRed (lote 2) + los 29 addons del lote 3

**0 agujeros reales.** Talento (262 rutas), Mapas (174), Roadmap (93, con 61 de 93 usando
`$this->authorize()` punto por punto — el addon con más defensa en profundidad del sistema),
GestionRed (116) y MapaRed (98) están, cada uno, 100% envueltos en `check_route_permission`,
`role:`/`permission:` o `authorize()`/token inline verificado. Los 29 addons del lote 3
(Flotas, PortalCliente, Inventario, Vendedores, Payments, DocumentacionCorporativa, IA,
Embajadores, Planes, Finanzas, WhatsAppAgent, VoIP, PortalPago, WarRoom, Scheduling, Mensajes,
Tickets, CobranzaBlaster, SmartImportExport, Domiciliacion, DevTools, EvaluadorEmpresarial,
Manual, Empresa, VozMayorista, Hub, Demo, Inversiones, CentroProyecto) confirmaron lo mismo —
incluidos 2 casos (`VoIP`, `CobranzaBlaster`) sin `check_route_permission` en `routes.php` pero
con `can()`/`authorize()` inline verificado al 100% de cobertura método por método (clasificación
3, no 4). El detalle completo, archivo por archivo, está en
`docs/permisos-agujero-item-9990764-parte2-addons-grandes.md` y
`docs/permisos-agujero-item-9990764-parte3-addons-resto.md`.

---

## Hallazgos secundarios (no son "agujero de permiso", se documentan para no perderlos)

- **`routes/web.php`:** 2 rutas rotas (`POST /getDataTable` → método inexistente;
  `POST /register-vendor/register` → método inexistente) y `GET /register-vendor` sin ningún
  middleware y sin documentar como pública a propósito.
- **`configuracion/credenciales-google-maps/render-config`:** expone el API key de Google Maps a
  cualquier staff autenticado — documentado como intencional en el propio archivo, riesgo
  BAJO-MEDIO (credencial de terceros con cuota facturable, no dinero/PII de cliente).
- **VoIP / CobranzaBlaster:** protección 100% por `can()`/`authorize()` inline en vez de en
  `routes.php` — se recomienda (fuera de alcance de esta auditoría) sumar
  `check_route_permission` como defensa en profundidad adicional, para que un método nuevo que
  olvide el check inline no quede expuesto por descuido.
- **`Hub`:** protege por middleware en el constructor del controller en vez de en `routes.php` —
  mismo efecto, relevante porque gestiona credenciales de proveedores externos (Anthropic,
  Evolution, Pexels).
- **`EvaluadorEmpresarial`:** comentario desactualizado que referencia `PUBLIC_ROUTES` para rutas
  que en realidad nunca pasan por ese middleware — sin impacto de seguridad real.

## Qué NO cubre esta auditoría (a propósito)

Ningún lote cruzó cada ruta protegida contra las 2490 líneas de `config/route_permission.php` para
verificar que exista una entrada — porque, con el modelo fail-closed confirmado, la ausencia de esa
entrada bloquea (bug funcional para no-admins), no abre (no es agujero de seguridad). Ese cruce
queda fuera de alcance de esta auditoría de permisos-por-ruta.

---

Este documento cierra el punto 3 del prompt original de #9990745. **No se cierran #9990764 ni
#9990745** — el cierre en cascada de esos dos paraguas es automático cuando todos sus sub-items
(incluido este) estén `completado`.
