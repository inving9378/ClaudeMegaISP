## 2026-09-28 — Talento: dashboard + ficha por pestañas, a como está Vendedores

Reestructuración pedida por David: que "Talento" se vea y se navegue igual que
"Vendedores" — clic en Talento → dashboard con todos los colaboradores → clic en
uno → ficha por pestañas con TODAS sus secciones, en vez del menú actual de ~24
pantallas globales separadas.

**Rama exploratoria, sin mergear a main** (a pedido explícito): `talento/dashboard-ficha-por-pestanas`.

### Alcance confirmado por David antes de construir

- Solo lo **per-colaborador** entra como pestaña de la ficha. Los catálogos/vistas
  globales (Puestos, Niveles, Academia, Roadmap, Mapa en vivo, Sitios de checada,
  Paquetes de documentos, Dashboard de KPIs, Escalafón completo) se quedan
  **intactos** como pantallas separadas — no se tocaron.
- Flujo de campo, Cajas ODB, Rutas planta y Calidad de caja son pestañas
  **condicionadas al rol del colaborador visto** (solo aparecen si es técnico —
  `TECNICO`/`TECNICO_INSTALADOR`/`TECNICO_PLANTA`): "depende del rol de cada cual...
  si no, no tiene sentido que estén viendo cosas que no van a tocar" (David).
- Proyectos entra como pestaña visible por permiso, sin condición adicional de rol.
- El menú lateral actual (~24 links) **no se tocó** — el trabajo es aditivo, nada
  existente se rompe.

### Lo que se construyó

**Dashboard** (`TalentoColaboradores.vue`) — la tabla se convirtió a `q-table`
(mismo patrón visual/estructural que `VendedorListar.vue`: buscador, columnas,
nombre como link). El resto de la lógica (modal crear/editar con expediente RH,
permisos, paginación server-side) se conservó intacta — solo cambió la
presentación de la tabla.

**Ficha por pestañas** (`TalentoColaboradorFicha.vue`, nueva) — calcada de
`Menu.vue` de Vendedores: breadcrumb, prev/next entre colaboradores, barra "quién
se está viendo" con badge si está inactivo, `nav-tabs` con iconos `bi`, montaje
perezoso de paneles. Ruta `GET /talento/colaborador/{id}` →
`TalentoColaboradorController::ficha()`, gateada por `talento.view` (agregada a
`config/route_permission.php`). Las 16 pestañas se muestran/ocultan según flags de
permiso **resueltas server-side** en el controller (mismo criterio que `@can` en
el resto del proyecto) y pasadas a Vue ya calculadas — no se depende de un store
de permisos en el cliente.

**Pestaña "Información"** — nuevo componente `TalentoFichaInformacion.vue`, usa el
`show($id)` que ya existía.

### Las 16 pestañas — estado real de filtrado

| # | Pestaña | Estado | Detalle |
|---|---------|--------|---------|
| 1 | Información | ✅ Real | `GET /talento/api/colaboradores/{id}` (ya existía) |
| 2 | Órdenes de trabajo | ✅ Real | backend ya soportaba `colaborador_id`; solo faltaba mandarlo |
| 3 | Compensación | ✅ Real | reusa `/regla`+`/regla/historial`, mismo flujo que "asignar regla" |
| 4 | Liquidaciones | ✅ Real | backend ya soportaba `colaborador_id` |
| 5 | Asistencia | ✅ Real | backend ya soportaba `colaborador_id` |
| 6 | Flujo de campo | ✅ Real | reusa el mismo endpoint `/ordenes` que la pestaña 2 |
| 7 | Cajas ODB | ⚠️ Parcial | el **log de bono de salud** (`bonus-log`) sí filtra por técnico; el catálogo de cajas/baselines/config queda global A PROPÓSITO (es infraestructura compartida, no "de una persona") |
| 8 | Rutas planta | ✅ Real | backend ya soportaba `colaborador_id` |
| 9 | Calidad de caja | ✅ Real | **requirió agregar el filtro en backend** — la FK real es `inspected_by`, no `colaborador_id` (se descubrió con una prueba end-to-end real que tronó 500 antes de corregirlo — ver nota abajo) |
| 10 | Proyectos | ❌ Sin filtrar | `TalentoProjectController::data()` no tiene filtro por participante (solo por `status`/`search`); montado con la lista global tal cual, tal como autorizó David si resultaba complicado. Pendiente si se quiere de verdad: unir contra la tabla de participantes del proyecto. |
| 11 | Penalizaciones | ✅ Real | el componente ya tenía `pFilters.colaborador_id`, solo se pre-sembró |
| 12 | Credenciales | ✅ Real | el componente ya tenía la sub-pestaña "por colaborador" (`selectedColId`), se salta directo ahí |
| 13 | Préstamos/Finiquito | ✅ Real | el componente ya tenía `lFilters.colaborador_id` |
| 14 | Custodia | ✅ Real | reusa `GET /colaboradores/{id}/custodia` que ya existía, salta la grilla de selección |
| 15 | Dispositivos | ✅ Real | se cambió de "cargar 50 y filtrar en cliente" (con bug de paginación: no aparecía si no estaba en la primera página) a traer directo por id |
| 16 | Roles múltiples | ✅ Real | mismo fix que Dispositivos — trae directo por id en vez de la lista paginada de 20 |

**14 de 16 con filtrado real de punta a punta, 1 parcial por diseño (Cajas ODB), 1
sin filtrar (Proyectos, backend no lo soporta hoy).**

### Verificado en dev (llamadas HTTP reales, no solo lectura de código)

`GET /talento`, `GET /talento/colaborador/{id}` (200, monta el componente),
`GET /talento/api/colaboradores/{id}`, y los endpoints con `colaborador_id` de
ordenes/asistencia/liquidaciones/rutas/cajas-bonus-log/inspecciones — todos 200.
`npm run dev` compila limpio.

### Nota — bug real encontrado y corregido durante la verificación

Al agregar el filtro de "Calidad de caja" asumí la columna `colaborador_id` (por
consistencia con el resto), pero la tabla real `talento_caja_inspections` usa
`inspected_by` (ver `TalentoCajaInspection::colaborador()`). La prueba HTTP real
tronó 500 (`Column not found`) antes de mergear nada — corregido a `inspected_by`
y re-verificado 200. Queda como recordatorio de por qué probar de punta a punta
importa incluso en cambios que "deberían" ser triviales.

### Pendiente si se retoma este trabajo

- Filtrado real de Proyectos (requiere tocar el backend para unir contra
  participantes, no solo el líder).
- Botón "mostrar/ocultar columnas" del dashboard (Vendedores lo tiene, se omitió
  aquí por tiempo — no es parte del pedido central).
- Validación visual en navegador (esta pasada fue verificación por HTTP/tinker,
  no clic real en pantalla).

## 2026-09-28 (continuación) — corrección tras revisión en vivo de David

David probó la ficha en vivo como técnico y reportó dos problemas reales de la
pasada anterior:

### 1. Las pestañas "no técnicas" no se veían en la ficha propia

`ficha()` calculaba cada `permisos.xxx` con el permiso de STAFF puro
(`talento.compensation.view`, etc.) — pensado para que un admin vea a
CUALQUIERA, no para que un colaborador se vea a sí mismo. El rol TECNICO no
tiene otorgada la mayoría de esos permisos, así que un técnico viendo su
propia ficha casi no veía nada.

Fix: `$tieneAccesoAmplio` (uno mismo || su supervisor directo ||
`talento.employees.view`) se suma con OR a los 16 permisos. `ordenes_manage`
queda igual a propósito (verse a uno mismo no da de gratis "Nueva orden").

### 1b. Dos pestañas nuevas: "Paquetes de documentos" y "Academia"

David las pidió explícitas. Nuevos endpoints self/supervisor/manage-scoped
(`miFichaDocumentos()`/`miFichaAcademia()`, extraída la lógica de
autorización común a `resolverColaboradorAutoservicio()` — la reusan también
`miFicha()`) que delegan en la lógica YA construida
(`TalentoEmployeeDocumentController::forColaborador()` y
`TalentoAcademyController::progress/certificationsForColaborador()`) sin
duplicarla. 2 componentes nuevos de solo lectura (`TalentoFichaDocumentos.vue`,
`TalentoFichaAcademia.vue`) — distintos de las pantallas globales de
catálogo/gestión (`TalentoPaqueteDocumentos.vue`/`TalentoAcademia.vue`, que
siguen intactas, sin tocar).

De paso: typo real en `route_permission.php` (`/talento.api/...` con punto
en vez de barra) que hacía que `talento.academy.view` nunca desbloqueara
`/certifications` — corregido.

### 2. El listado `/talento` con 3 comportamientos según quién entra

David: "admin, desarrollador y mostrador si debe salirle la lista completa
menos los supervisores que debe salirles la lista de los que están a su
cargo."

- `talento.employees.view` (admin/DESARROLLADOR/Mostrador) → listado
  COMPLETO.
- Supervisor (subordinados vía `supervisor_id`, sin ese permiso) → mismo
  listado, apuntado al nuevo endpoint self-scoped `/talento/api/mi-equipo`
  (candado FORZADO `where('supervisor_id', ...)`, no opcional).
- Cualquier otro → redirige directo a su ficha (sin cambios).

**Hallazgo real durante la verificación:** `talento.employees.view` nunca
existió como fila real en `permissions` — solo se usaba por texto en
`route_permission.php` (~15 rutas). Nadie lo necesitó nunca porque admin/
DESARROLLADOR pasan por el bypass de `CheckRoutePermission::isAdmin()/
isDevelopment()` antes de cualquier chequeo de permiso. La migración de
Mostrador tuvo que crear la fila (`firstOrCreate`) antes de otorgarla — la
primera versión fallaba en silencio (`Role::hasPermissionTo()` con un
nombre inexistente lanza `PermissionDoesNotExist`, atrapado por el guard
`if ($role && $permiso...)`).

### Verificado con Playwright, los 4 puntos pedidos (cuentas desechables,
### borradas al terminar)

1. Técnico Demo (colaborador 43) en su propia ficha → ahora ve las 18
   pestañas completas (antes solo 7), confirmado contenido real (no solo el
   botón) en Compensación/Liquidaciones/Proyectos/Paquetes de
   documentos/Academia.
2. Supervisor de prueba (1 subordinado) en `/talento` → ve el listado (no
   redirige), filtrado a exactamente 1 fila (su subordinado), no las 29
   filas del roster completo.
3. Mostrador de prueba en `/talento` → ve el listado completo (25 filas,
   paginado igual que admin).
4. Admin/DESARROLLADOR en `/talento` → sigue viendo el listado completo sin
   cambios (25 filas).

### Commits de esta continuación

- `13eb2c85` — pestañas propias + 2 nuevas + listado por rol
- `28c6377f` — fix de `talento.employees.view` (creaba la fila real)

## 2026-09-28 11:09 — Proyectos: mismo criterio supervisor + arregla auto-aprobación sin revisión

David, revisando la pestaña Proyectos: "el proyecto lo crea un superior y el
técnico lo ve, o el técnico por código si podría crear proyectos?" → se
confirmó que `store()` ya exigía `talento.projects.manage` (solo admin/
DESARROLLADOR, un técnico NO podía por código), pero al investigar
`submitReport()` apareció un defecto real que David pidió corregir de paso
("sí, arregla eso también") + pidió ocultar el botón "Nuevo proyecto" para
técnicos.

**Defecto encontrado:** `ProjectActivityService::submitReport()` SIEMPRE
auto-aprobaba el reporte (nunca escribía `status='pending'`), dejando el
método `approve()` como código muerto. Inofensivo mientras solo un admin
podía llamar al endpoint (`talento.project_reports.create` era admin-only),
pero un riesgo real en cuanto un técnico reporta su propio avance de
proyecto (se autoacreditaría puntos sin revisión de nadie). Tampoco existía
validación de "pertenencia": nada impedía acreditarle avance a un
colaborador ajeno al que reporta.

**Fix (mismo criterio ya aplicado a Órdenes/Compensación/Cajas/Rutas — uno
mismo, supervisor directo, o staff):**
- `ProjectActivityService::submitReport()` — nuevo parámetro `$autoApprove`.
  Si es `false`, el reporte queda `status='pending'` en vez de auto-aprobarse
  (la cantidad aprobada se guarda como vista previa; `approve()` la
  recalcula de verdad al aprobar).
- `TalentoProjectController::submitReport()` — sin `talento.project_reports.create`,
  exige que TODOS los `colaborador_ids` reportados sean uno mismo o
  subordinados directos (nunca un colaborador ajeno). Auto-aprueba
  (`$autoApprove=true`) si quien reporta tiene el permiso de staff O es
  supervisor de verdad (tiene subordinados); si no, queda `pending`.
- `TalentoProjectController::approveReport()` — sin el permiso de staff,
  exige ser supervisor (tener subordinados) Y que TODOS los participantes
  del reporte sean uno mismo o subordinados directos. Un técnico sin
  subordinados nunca puede aprobar su propio reporte pendiente.
- `TalentoProjectController::store()` — agrega la misma excepción de
  supervisor que ya tienen Órdenes/Rutas: admin/DESARROLLADOR o CUALQUIER
  supervisor (no hay un `colaborador_id` puntual al crear el proyecto).
- `permisos.proyectos_manage` (nuevo, en `ficha()`) — controla el botón
  "Nuevo proyecto" en `TalentoProyectos.vue` (`puedeGestionar`/`puedeCrear`,
  mismo patrón que `TalentoRutas.vue`): oculto para técnico, visible para
  supervisor/admin/DESARROLLADOR.

**Gap de `route_permission.php` (mismo patrón recurrente de esta sesión):**
`/talento/api/proyectos/**` y `/talento/api/project-reports/**` no estaban
mapeados a ningún permiso que un técnico/supervisor tuviera — el middleware
bloqueaba la request ANTES de llegar al controller, dejando todo el fix
anterior inerte hasta agregarlos a `talento.view`. Detalle fino: `**` exige
un carácter después de la barra (`convertRouteToRegex` → `.+`), así que el
path pelón `/talento/api/proyectos` (listar/crear) necesitó su propia
entrada además del wildcard — mismo hallazgo que ya se documentó para
`/talento/api/rutas` en una vuelta anterior.

**Efecto secundario cerrado de paso:** al abrir `/talento/api/proyectos/**`
a autoservicio, `data()`/`show()` devolvían `bonus_amount`/`bonus_scale`
(dinero) a cualquier técnico que las alcanzara. Se agregó
`hideBonusFields()` (mismo patrón que `hideExpedienteFields()`): oculta esos
2 campos a quien no gestiona proyectos ni es supervisor.

**Verificado con Playwright** (cuentas desechables, borradas al terminar):
botón oculto para técnico sin equipo / visible para supervisor; técnico
reporta por sí mismo → queda `pending`; técnico reporta por un colaborador
ajeno → 403; técnico intenta autoaprobarse → 403; supervisor aprueba el
reporte de su subordinado → 200, pasa a `approved`; supervisor reporta por
su equipo → auto-aprobado directo; técnico crea proyecto vía API → 403;
supervisor crea proyecto vía API → 201. Los 12 checks pasaron limpio.

### Commits

- `036a7e7c` — supervisor crea/aprueba, técnico solo reporta lo propio, oculta botón
