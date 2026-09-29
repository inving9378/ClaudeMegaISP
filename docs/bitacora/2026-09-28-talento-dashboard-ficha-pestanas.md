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

## 2026-09-28 11:33 — Penalizaciones: técnico no puede penalizar (ni a sí mismo), solo ve; supervisor sí a su equipo

David: "los mismos técnicos no pueden penalizarse, eso no lo haría nadie
ahí, solo debe mostrarse las penalizaciones, lo mismo ocurre con los demás,
el superior es el que penaliza, no ellos mismos, en caso de tener
subordinados sí lo pueden hacer" — mismo criterio ya aplicado a Órdenes/
Compensación/Cajas/Rutas/Proyectos.

**Estado previo:** `applyPenalty()` exigía `talento.penalties.manage`
(solo admin/DESARROLLADOR, sin excepción de supervisor). `penaltiesIndex()`
y `showPenalty()` **no tenían NINGÚN chequeo de autorización** — quien
lograra alcanzar la ruta veía CUALQUIER penalización de CUALQUIER
colaborador. La ruta en sí bloqueaba a los técnicos por completo
(`talento.penalties.view` admin-only en `route_permission.php`), así que la
pestaña de la ficha propia quedaba inservible para autoservicio.

**Fix (mismo patrón supervisor-directo del resto del módulo):**
- `TalentoPenaltyController::applyPenalty()` — admin/DESARROLLADOR o
  supervisor DIRECTO del `colaborador_id` a penalizar. Como nadie es su
  propio supervisor (ya blindado en `update()`), esto hace **estructuralmente
  imposible** autopenalizarse — no hizo falta un candado aparte.
- Nuevo helper `puedeVerPenalizacionesDe()` (uno mismo/supervisor
  directo/staff) aplicado en `penaltiesIndex()` (con filtro `colaborador_id`
  vs. listado global — mismo criterio que `TalentoRouteController::data()`),
  `showPenalty()` y `serveEvidencePhoto()` — cierra el gap de que cualquiera
  podía ver la penalización de cualquiera.
- `submitAppeal()` — apelar TU PROPIA penalización es defenderte, no
  gestionar: se agregó excepción de autoservicio (colaborador propio ===
  colaborador de la penalización), **sin** tocarla para supervisores (la
  cola completa de apelaciones y `resolveAppeal()` siguen 100% admin-only,
  fuera de lo pedido).
- `permisos.penalizaciones_manage` (ficha()) — mismo patrón que
  `ordenes_manage`/`rutas_manage`: `talento.penalties.manage` O
  `$esSuSupervisor`, **sin** `$tieneAccesoAmplio` (verse a uno mismo nunca
  da de gratis el botón).
- `TalentoPenalizaciones.vue` — botón "Aplicar penalización" y tabs
  "Apelaciones"/"Catálogo de tipos" ocultos sin `puedeGestionar` (mismo
  patrón `puedeGestionar`/fallback `allViewHasPermission` de Rutas/
  Proyectos). "Solo debe mostrarse las penalizaciones" tomado literal: la
  cola completa de apelaciones de TODOS y la gestión del catálogo de tipos
  son EXCLUSIVAS de quien gestiona — el propio flujo de apelar TU PROPIA
  penalización sigue disponible dentro del modal "Ver" (no es lo mismo).
- `route_permission.php` — mismo gap recurrente: `/talento/api/penalties`
  (+`**`) y `/talento/penalty-evidence/{id}` abiertos en `talento.view`;
  `/talento/api/penalty-appeals/**` (cola completa + resolver) queda FUERA
  a propósito, sigue exigiendo el permiso de staff.

**Verificado con Playwright** (cuentas desechables, borradas al terminar,
incluyendo limpieza de `talento_ledger_entries` — `applyPenalty()` sí
escribe al ledger real): botón/tabs ocultos para técnico sin equipo,
visibles para supervisor en la ficha de su subordinado; técnico penaliza a
un ajeno → 403; técnico se autopenaliza → 403; supervisor penaliza a su
subordinado → 201; técnico ve su propia penalización → 200; otro técnico
ajeno NO la ve → 403; técnico apela su propia penalización → 201; técnico
pide el listado global (sin filtro) → 403. 10/10 (el único "fallo" inicial
fue un error del script de prueba — probaba el botón en la ficha PROPIA del
supervisor, donde correctamente no debe aparecer, no en la de su
subordinado).

### Commits

- `deb7cdcd` — técnico no puede penalizar(se), supervisor sí a su equipo

## 2026-09-28 11:40 — Penalizaciones: fix real — apelar la propia penalización estaba silenciosamente roto

David, tras la pasada anterior: "quitaste apelaciones de la pestaña
penalizaciones, cuando a alguien lo penalizan debe poder apelar". Aclaración
importante: la pestaña "Apelaciones" que se ocultó es la **cola completa de
apelaciones de TODOS** (para revisar/resolver — gestión, admin-only, sigue
así a propósito). Apelar TU PROPIA penalización es una acción distinta que
**siempre vivió** dentro del botón "Ver" de cada fila (`viewModal`), con un
formulario inline que aparece solo si `canAppeal` es verdadero.

**El bug real:** `canAppeal` dependía de `myColaboradorId`, resuelto en
`loadMyProfile()` vía `GET /talento/api/colaboradores?my_profile=1`. Ese
parámetro `my_profile` **nunca existió** en el backend
(`TalentoColaboradorController::data()` no lo lee) — la llamada devolvía
simplemente el colaborador con el **id más alto de toda la empresa**
(`orderBy('id','desc')` + `per_page:1`), no el del usuario logueado. Así que
`canAppeal` casi nunca coincidía de verdad — el botón de apelar llevaba
**tiempo silenciosamente roto para cualquiera**, no solo desde la pasada
anterior; con la pestaña "Apelaciones" visible antes, el síntoma pasaba
desapercibido porque nadie notaba que faltaba un botón puntual dentro de un
modal.

**Fix:** `loadMyProfile()` ahora llama `GET /talento/mi-ficha` (sin id) —
el mismo endpoint self-scoped por `Actor` que ya usa toda la ficha
(`resolverColaboradorAutoservicio(null)`), sin backend nuevo. Esto corrige
de una vez **dos** cosas: `canAppeal` (que el propio penalizado vea el
formulario de apelar) y `isApplier` (la regla de justicia en `resolveModal`
que impide que quien APLICÓ la penalización sea quien la resuelva — también
dependía del mismo dato roto).

**Verificado con Playwright** (cuentas desechables, borradas al terminar):
supervisor penaliza a tec1 → tec1 entra a su propia ficha → ve su fila en
la tabla → abre "Ver" → el formulario de apelar SÍ aparece → lo llena y
envía → sin error. Confirmado en BD: `status='appealed'`,
`appealed_by=<colaborador de tec1>`, `reason` guardado correcto.

### Commits

- `34c6ec6a` — apelar la propia penalización estaba roto (my_profile fantasma)

## 2026-09-28 11:55 — Credenciales: "Por colaborador" solo para superiores (sin excepción de autoservicio)

David, tras explicarle para qué sirve la pestaña "Credenciales" (control de
vencimiento de licencias del personal + fondo de ahorro forzoso para
renovación): "dentro de esa pestaña la pestaña de por colaborador debe
salirle solo a los superiores".

**Diferencia clave con el resto del módulo:** en Órdenes/Compensación/
Cajas/Rutas/Proyectos/Penalizaciones, uno mismo SIEMPRE conserva acceso de
LECTURA a lo suyo (solo la acción de gestionar se restringe al superior).
Aquí David pidió algo más estricto — ni siquiera autoservicio: "Por
colaborador" (registrar/editar credenciales, crear/autorizar/marcar-usado
fondos) es 100% de superiores, un colaborador no gestiona su propia
credencial/fondo desde aquí — se entera del vencimiento por el correo
automático que ya manda `talento:check-credential-expirations`.

**Estado previo (bug real encontrado):** `listForColaborador()` y
`listFunds()` **no tenían NINGÚN chequeo de autorización** — cualquiera
que alcanzara la ruta veía la credencial/fondo de cualquier colaborador
(IDOR). Las rutas de escritura (`store()`/`update()` de credenciales,
`storeFund()`) estaban además **inalcanzables incluso para admin normal**
— ningún bloque de `route_permission.php` cubría sus paths reales
(`/talento/api/credentials`, `/talento/api/credentials/{id}`,
`/talento/api/funds` sin trailing), así que solo funcionaban vía el bypass
de super-administrator/DESARROLLADOR, nunca para un supervisor.

**Fix:**
- Nuevo `esSuperiorDe()`/`esGestorDe()` en `TalentoCredentialController` —
  admin/staff O supervisor DIRECTO del colaborador, **sin** rama de
  autoservicio (a propósito). Aplicado en las 7 acciones: listar
  credenciales/fondos, registrar/editar credencial, ver el documento
  cifrado, crear/autorizar/marcar-usado fondo.
- `permisos.credenciales_manage` (ficha()) — mismo criterio: admin/staff o
  CUALQUIER supervisor (no está atado al colaborador de ESTA ficha, ya que
  "Por colaborador" trae su propio selector).
- `TalentoCredenciales.vue` — pestaña "Por colaborador" oculta sin
  `puedeGestionar`; su auto-selección al entrar desde la ficha también
  queda condicionada (sin gestión, no hay nada que auto-cargar ahí).
- `route_permission.php` — mismo gap recurrente: se abrieron los paths
  reales de escritura (antes ni admin normal los alcanzaba). ⚠️ Cuidado
  real detectado y evitado: `/talento/api/credentials/{id}` como patrón
  hubiera matcheado TAMBIÉN `/talento/api/credentials/expiring` y
  `/talento/api/credentials/funds-alert` (las listas globales, que deben
  seguir 100% admin-only) — `{id}` se traduce a `[^/]+` (cualquier
  segmento) y el middleware es method-agnostic. Se usó un regex numérico
  explícito (`/talento/api/credentials/[0-9]+`) en su lugar, verificado
  contra el propio `convertRouteToRegex()` real antes de aplicarlo.

**Verificado con Playwright** (cuentas desechables, borradas al terminar,
incluyendo limpieza de `talento_ledger_entries`): pestaña oculta para
técnico sin equipo / visible para supervisor en la ficha de su
subordinado; técnico bloqueado de ver SUS PROPIAS credenciales (sin
autoservicio, a propósito); supervisor ve/registra/edita las de su
subordinado; técnico bloqueado de crear un fondo para sí mismo; supervisor
crea y autoriza un fondo para su subordinado; y — regresión clave —
`/credentials/expiring` y `/credentials/funds-alert` siguen 403 para el
supervisor (el regex numérico no las abrió por accidente). 11/11.

### Commits

- `ee4ba949` — "Por colaborador" solo para superiores, sin autoservicio

## 2026-09-28 12:12 — Préstamos/Finiquito: qué es, permisos supervisor/técnico, y BUG DE DINERO real corregido

David pidió explicar para qué sirve la pestaña y **verificar que funcione
bien**. Es el módulo de: (1) **Préstamos** — adelantos de nómina al
colaborador, con saldo pendiente y descuento semanal, que por ley (LFT
Art. 110) no se puede descontar de nómina sin autorización por escrito; y
(2) **Finiquito** — el cálculo de cuenta corriente al separarse un
colaborador (créditos: salario/bonos pendientes + fondos de ahorro no
gastados; débitos: penalizaciones + saldo de préstamos + material dañado o
faltante de su custodia), que al **cerrarse (irreversible)** escribe los
asientos finales al ledger, salda los préstamos activos y regresa el
material devuelto al almacén.

### Permisos (mismo criterio ya aplicado al resto de la ficha)

- **Préstamos**: uno mismo puede VER su saldo (mismo criterio que
  Penalizaciones — se entera, no gestiona); registrar/autorizar un
  préstamo es del supervisor directo o staff, SIN autoservicio.
- **Finiquito**: a diferencia de todo lo demás, SIN excepción de
  autoservicio NI SIQUIERA para ver — calcular/cerrar tu propio finiquito
  no tiene sentido estando activo (mismo criterio que "Por colaborador" en
  Credenciales). Supervisor directo o staff.
- Igual que en Proyectos/Penalizaciones/Credenciales: `loansForColaborador()`,
  `showSettlement()` y `settlementsIndex()` **no tenían NINGÚN chequeo de
  autorización** (cualquiera que alcanzara la ruta veía el préstamo/finiquito
  de cualquiera). `/talento/api/loans` (listar/registrar) y `/talento/api/
  settlements/{id}` no estaban cubiertos por ningún bloque de
  `route_permission.php` real (solo alcanzables por el bypass admin) —
  mismo patrón recurrente de esta sesión.

### 🔴 BUG DE DINERO REAL encontrado y corregido (no era de permisos)

Verificando el flujo completo (registrar préstamo → autorizar → calcular
borrador → **cerrar**), el borrador calculaba bien (créditos $500, débito
préstamo $300, neto $200) pero **el finiquito CERRADO perdía el descuento
del préstamo** (quedaba en débitos $0, neto $500 — la empresa habría
pagado $300 de más). Causa: `SettlementService::close()` marcaba los
préstamos activos como `'paid'` **ANTES** de recalcular los totales
finales; `recalculate()` suma `active_loan_balance` consultando préstamos
`status='active'` — al ya estar en `'paid'`, el saldo desaparecía del
cálculo, mientras el préstamo quedaba "saldado" sin haberse descontado de
nada realmente. Corregido: se invirtió el orden — `recalculate()` corre
**antes** de marcar los préstamos pagados, así el neto congela el saldo
real que se está saldando. Verificado con dinero de prueba en transacción
real: antes del fix, cerrado quedaba en neto=$500 (incorrecto); después,
neto=$200 (correcto), préstamo igual queda `status=paid`.

### 🔴 BUG FUNCIONAL real encontrado y corregido (bloqueaba TODO cierre)

`SettlementService::getCustody()` — el JOIN a `inventory_categories`/
`i.category_id` apuntaba a una tabla/columna que **nunca existieron** en
este esquema (`inventory_items` no tiene `category_id`; el catálogo real
vive en `inventory_item_types`, FK `inventory_item_type_id`). Esto tronaba
**500 SIEMPRE** al calcular CUALQUIER borrador de finiquito — para
cualquier colaborador, tuviera o no material en custodia (el JOIN falla al
parsear la query, no al ejecutarla). Es decir: **la mitad "Finiquito" de
esta pestaña nunca funcionó, para nadie, desde que se escribió.** También
select-eaba `i.sku` (columna que tampoco existe) y `c.name as category`
(ninguno de los dos se usa en ningún lado, ni en el service ni en
`TalentoFiniquito.vue`) — se quitaron del query en vez de repararlos contra
un valor que nadie consume.

**Verificado con Playwright** (cuentas desechables, borradas al terminar,
con dinero real en transacción de prueba): pestaña/botones ocultos para
técnico sin equipo, visibles para supervisor en la ficha de su
subordinado; técnico ve su propio saldo de préstamos pero NO puede
registrar uno ni ver/calcular su propio finiquito; flujo completo real del
supervisor — registra préstamo → autoriza → calcula borrador (créditos y
débitos correctos) → cierra (irreversible) — funciona de punta a punta;
préstamo queda `paid`; finiquito cerrado conserva el neto correcto. 16/16
+ verificación aparte del fix de dinero.

### Commits

- `9502b68b` — permisos supervisor + BUG DE DINERO real en close()

## 2026-09-28 13:11 — Custodia: qué es, y BUG QUE LA TENÍA ROTA SIEMPRE + IDOR reales

David pidió explicar para qué sirve y **verificar que funcione bien**. Es
la vista de solo lectura de qué material/herramienta tiene un colaborador
asignado ahora mismo (taladros, cascos, cable, ONTs…) — reusa
`inventory_item_stocks` (custodia polimórfica por usuario) sin tabla
propia, más sus últimos 50 movimientos de entrada/salida.

### 🔴 BUG FUNCIONAL — la pestaña tronaba 500 SIEMPRE, para cualquiera

`TalentoCustodiaController::show()` hacía `LEFT JOIN inventory_categories
ON c.id = i.category_id` y seleccionaba `i.sku`/`i.unit` — tabla y
columnas que **nunca existieron** en este esquema (`inventory_items` no
tiene `category_id` ni `sku` ni `unit`; el catálogo real de categoría vive
en `inventory_item_types.categoria`, FK `inventory_item_type_id`). El JOIN
falla al **parsear** la query, así que tronaba 500 para CUALQUIER
colaborador, tuviera o no material en custodia — la pestaña nunca funcionó,
para nadie, desde que se escribió.

**Lo curioso:** el bug ya estaba documentado — un comentario en
`EmployeeDocumentPackageService::herramientasData()` (usado para el
finiquito/offboarding) dice textual: *"el controller referenciaba
i.sku/i.unit/i.category_id, columnas que no existen en el esquema
actual"* — alguien ya lo había descubierto al construir OTRA función que
necesitaba la misma custodia, la resolvió ahí con las columnas correctas,
pero **nunca volvió a corregir el original**. Mismo patrón exacto que el
bug de `SettlementService::getCustody()` que corregí en la pestaña
anterior (Préstamos/Finiquito) — es la MISMA suposición de esquema
equivocada, repetida en 2 sitios.

**Fix:** JOIN correcto a `inventory_item_types` (FK real
`inventory_item_type_id`), `i.serial_number` en vez de `i.sku` (columna
real, ya usada en el offboarding), y `t.categoria` como categoría real
(herramienta/material/equipo_cliente/equipo_red — el mismo campo de la
auditoría de inventario #218/#684/#1007). `i.unit` se quitó (no existe
ningún concepto de "unidad" real en el esquema). Frontend actualizado a
juego: columna "SKU" → "No. serie", categoría ahora muestra un valor real
con etiqueta legible, columna de unidad quitada.

### 🔴 IDOR real — cualquier técnico podía ver la custodia de CUALQUIERA

`show()` no tenía NINGÚN scoping propio (mismo patrón recurrente de esta
sesión), pero acá con un matiz: `talento.custody.view` está clasificado
**"context=portal"** en la migración `classify_portal_permissions`, con
comentario explícito de Irving — *"EXCLUSIVAS del colaborador en su
app/portal"* — y por eso lo tienen TECNICO/TECNICO_PLANTA/TECNICO_INSTALADOR
**directamente**. Sin scoping en el controller, ese permiso "exclusivo de
lo mío" terminaba abriendo `/talento/api/colaboradores/{id}/custodia` para
**cualquier** `{id}`, contradiciendo la propia clasificación de Irving —
cualquier técnico podía ver qué herramienta tiene cualquier otro colega.
Corregido: nuevo `puedeVerCustodiaDe()` (uno mismo, su supervisor directo,
o `talento.employees.view` — la señal real de "staff que ve a cualquiera";
NO se usó `talento.custody.view` para esa rama porque ES la que ya tienen
los técnicos y hubiera dejado el hueco intacto). Verificado que esto no
rompe a ningún rol admin real: super-administrator/DESARROLLADOR/Super
Administrador/Administrador pasan por el bypass de `CheckRoutePermission`
antes de llegar aquí; Mostrador sí tiene `talento.employees.view` directo.

**Verificado con Playwright** (cuentas + item de inventario de prueba,
borrados al terminar): técnico ve su propia custodia (antes 500, ahora 200
con datos reales — nombre del ítem, categoría real "herramienta", sin el
campo `sku` roto), la UI pinta la fila con la categoría legible, un técnico
AJENO no puede ver la custodia de otro (403), y el supervisor sí ve la de
su subordinado. 8/8.

### Commits

- `1e9ddffb` — bug que la tenía rota SIEMPRE (500) + IDOR real

## 2026-09-28 13:26 — Dispositivos: qué es, IDOR real, tab vacía por gap de permisos, y un hallazgo de diseño importante

David pidió explicar y **verificar que funcione bien**. La pestaña son en
realidad DOS cosas distintas en una misma pantalla:

1. **Descarga de la app** (QR + link) — muestra la última versión activa
   de "Talento Equipo" (`talento_app_releases`) para que el colaborador
   instale/actualice la app de campo. Público, sin sesión
   (`/talento/api/app/latest`, a propósito — hace falta poder descargar la
   app antes de poder loguearse). **Verificado funcionando**: hay una
   versión activa real (v1.7) y el APK existe en el servidor (200 real).
2. **"Dispositivos Vinculados"** — un registro de qué teléfono está
   autorizado a usar la cuenta de cada colaborador en la app, con
   aprobar/revocar.

### 🔴 Hallazgo de diseño importante — el "candado" de dispositivo no está conectado a nada

Investigando a fondo (nadie llama a `bind()`/lee `TalentoDevice` en ningún
otro lugar del código — ni en la app móvil, ni en el login, ni en ningún
middleware de autenticación): **este control NUNCA se activa solo, y
revocarlo NO bloquea nada de verdad.** El texto de la pantalla dice "al
vincular uno nuevo, el anterior se revoca automáticamente" — describe un
comportamiento que hoy **no ocurre en ningún lado**; la app móvil real no
llama a este endpoint al iniciar sesión. Es una tabla + pantalla completas
para un control de seguridad que quedó a medio conectar: el CRUD en sí
funciona (probado: crear/aprobar/revocar un registro no truena y guarda
bien), pero no impide que nadie use la app desde otro teléfono. Dejé una
nota de advertencia visible en la propia pantalla para que nadie confíe en
"Revocar" como si fuera un candado real. **Conectarlo de verdad requeriría
tocar la app móvil (repo aparte, `/home/meganet/TalentoEquipo`) — fuera de
alcance de esta verificación, queda documentado para que Irving decida si
vale la pena.**

### 🔴 IDOR real (mismo patrón que Custodia)

`forColaborador()` no tenía scoping propio. `talento.devices.view` lo
tienen TECNICO/TECNICO_PLANTA/TECNICO_INSTALADOR directo (necesario para
que vean la tarjeta de descarga del APK en `/talento/dispositivos`), pero
sin scoping ese permiso abría el listado de dispositivos de **cualquier**
colaborador para cualquier técnico. Corregido con el mismo
`puedeVerDispositivosDe()` (uno mismo/supervisor directo/
`talento.employees.view`) usado en Custodia.

### 🔴 Bug real — la pestaña se veía VACÍA en autoservicio (gap distinto a los anteriores)

A diferencia de Custodia (que arma el nombre del colaborador desde la
propia respuesta de su endpoint), `TalentoDispositivos.vue` hacía una
llamada APARTE a `GET /talento/api/colaboradores/{id}` solo para mostrar
el nombre — y ese path **nunca estuvo en `talento.view`** (solo en
`talento.employees.view`, admin-only). Un técnico viendo su propia ficha:
esa llamada 403eaba, el `try/finally` sin `catch` dejaba `cols` sin
asignar, y la tabla completa quedaba en "No hay colaboradores que mostrar"
— **pese a que el endpoint de dispositivos en sí ya funcionaba
correctamente**. Corregido sin tocar `route_permission.php` (más seguro
que abrir un endpoint compartido por medio proyecto): el componente ahora
recibe el nombre por prop (`colaborador-nombre`, mismo patrón que
`TalentoOrdenes.vue`/`TalentoRutas.vue`) en vez de pedirlo aparte.

**Verificado con Playwright** (cuentas + dispositivo de prueba —
crear/aprobar/revocar simulados directo en BD, borrados al terminar):
técnico ve su propio dispositivo (antes tabla vacía, ahora con datos
reales), la UI pinta la fila y el estado "Revocado" correctamente, técnico
ajeno bloqueado (403), supervisor ve el de su subordinado, y la descarga
del APK sigue pública sin sesión. 8/8.

### Commits

- `54324a96` — IDOR real + tab vacía en autoservicio + hallazgo de diseño

## 2026-09-28 13:34 — Custodia: quita el buscador de colaboradores a quien no es supervisor/staff

David: "a no ser que sea supervisor o los otros roles que te he dicho
quita de la vista el buscador de colaboradores" — el buscador+grilla de
"Custodia" (que deja navegar y ver la custodia de CUALQUIER colaborador)
debía ocultarse para un técnico simple.

**Dos lugares donde el buscador era alcanzable, ambos corregidos:**
1. **Ficha embebida** — un técnico self-viendo su propia ficha ya saltaba
   directo a su propia custodia (sin buscador visible de entrada), pero el
   botón **"Volver"** lo regresaba a la grilla COMPLETA de todos los
   colaboradores, exponiendo el buscador de todos modos. Botón oculto para
   quien no gestiona.
2. **Pantalla suelta `/talento/custodia`** — mostraba el buscador+grilla
   completos a CUALQUIERA con `talento.custody.view` (que, como ya se
   documentó, la tiene TECNICO directo). Ahora un técnico que entra ahí
   directo ve **su propia custodia de inmediato, sin buscador ni grilla**
   (mismo criterio que la ficha).

**Detalle técnico no trivial:** el permiso que gatea el buscador NO puede
ser `talento.custody.view` (es justo la que ya tienen los técnicos, no
distingue nada) ni tampoco un chequeo puramente de permisos Spatie en el
cliente para "¿soy supervisor?" — eso no es un permiso, es la relación
`talento_colaboradores.supervisor_id`. Nuevo `permisos.custodia_buscador`
(ficha(), servidor) = `talento.employees.view` o CUALQUIER supervisor. En
la pantalla suelta (sin ficha que lo resuelva), se reusa
`GET /talento/api/mi-equipo?per_page=1` (ya scoped a "mis subordinados
directos") para contestar "¿tengo equipo?" del lado del cliente — primer
intento solo miraba `talento.employees.view` y fallaba para un supervisor
sin ese permiso amplio (encontrado y corregido en la misma verificación).

**Verificado con Playwright** (cuentas desechables, borradas al terminar):
en la ficha, técnico sin equipo no ve buscador ni "Volver"; supervisor SÍ
ve ambos en la ficha de su subordinado. En la pantalla suelta, técnico
entra directo a ver SU PROPIA custodia sin buscador; supervisor sí ve el
buscador completo. 7/7.

### Commits

- `6c479ff6` — quita el buscador de colaboradores a quien no es supervisor/staff

## 2026-09-28 13:39 — Custodia: bug real que la pregunta de David encontró — Mostrador NO podía entrar

David preguntó "¿y el supervisor y mostrador no lo ven?" tras el fix
anterior. Supervisor ya estaba verificado (7/7 del commit previo), pero
**Mostrador nunca se había probado con una cuenta real** — y la pregunta
encontró un bug genuino: Mostrador quedaba **redirigida en silencio a
Dashboard** al entrar a `/talento/custodia` (denegación silenciosa de
navegación completa, el mecanismo estándar de `CheckRoutePermission`).

**Causa:** `talento.custody.view` — la ÚNICA que gatea `/talento/custodia`
(tanto a nivel middleware en `route_permission.php` como en
`TalentoCustodiaController::index()`) — nunca incluyó a Mostrador, solo
TECNICO/TECNICO_PLANTA/TECNICO_INSTALADOR y los roles admin con bypass.
El permiso que Mostrador SÍ tiene (`talento.employees.view`, otorgado
horas antes en esta misma rama) solo cubre el endpoint de DATOS
(`/talento/api/colaboradores/{id}/custodia`), no la ruta de la PÁGINA —
por eso el gap pasó desapercibido hasta probarlo con una cuenta real.

**Fix:** migración `2026_09_28_133500_grant_talento_custody_view_to_mostrador.php`
(mismo patrón que las 3 migraciones previas de esta rama para Mostrador —
`firstOrCreate` + `givePermissionTo`, idempotente, `down()` no revoca).

**Verificado con Playwright** (cuenta Mostrador desechable, borrada al
terminar): antes del fix, Mostrador entraba a `/talento/custodia` y
terminaba en `http://.../` (Dashboard) sin ningún error visible — solo
"desapareció" la pantalla que pidió. Después del fix: entra normal y ve el
buscador completo, igual que un supervisor.

### Commits

- `5cb193d2` — otorga talento.custody.view a Mostrador (no podía entrar a Custodia)

## 2026-09-28 13:45 — Roles múltiples: qué es, IDOR de dinero real, y un gap que rompía "Es vendedor" para todos

David pidió explicar y **verificar**. Es una vista de solo lectura que
cruza el expediente de Talento con OTROS dos sistemas del negocio: si el
colaborador es TAMBIÉN cliente/embajador (gana comisiones por referir
clientes nuevos) y/o TAMBIÉN vendedor (gana comisiones por ventas) —
útil porque, por ejemplo, un técnico puede referir clientes y ganar sus
propias comisiones de embajador sin que eso tenga nada que ver con su
trabajo de campo.

### 🔴 IDOR real (dinero) — el más serio de esta familia hasta ahora

`embajadorData()`/`sellerData()` solo exigían `talento.view` — el permiso
MÁS BÁSICO que absolutamente todo el mundo tiene con ficha. Sin ningún
scoping propio, **cualquier técnico podía consultar las comisiones de
referidos y de ventas de CUALQUIER OTRO colaborador** con solo cambiar el
id en la URL — datos de dinero real (comisiones pagadas, recompensas
pendientes, % de comisión de ventas). Corregido con el mismo
`puedeVerRolesMultiplesDe()` (uno mismo/supervisor directo/
`talento.employees.view` — NO `talento.embajadores.view`, que también la
tiene TECNICO directo).

### 🔴 Gap real — "Es vendedor" tronaba 403 SIEMPRE en autoservicio, para cualquiera

`talento.embajadores.view` cubría `/embajador-data` en
`route_permission.php` pero **`/seller-data` nunca estuvo en ningún bloque
que un técnico tuviera** — ni siquiera por accidente. Cualquier técnico
viendo su propia ficha (o la de cualquiera) SIEMPRE recibía 403 al
consultar si es vendedor, columna que quedaba en blanco. Agregado junto a
`embajador-data`.

### 🔴 Bug real — tab vacía en autoservicio (mismo patrón que Dispositivos)

`TalentoEmbajadores.vue`, igual que `TalentoDispositivos.vue` antes de
corregirlo, pedía el nombre/email/tipo del colaborador con una llamada
APARTE a `GET /talento/api/colaboradores/{id}` (fuera de `talento.view`).
Un técnico self-viendo: esa llamada 403eaba (con `.catch(()=>null)`, sin
crash pero con `items=[]`), y la tabla mostraba "Sin colaboradores
encontrados" pese a que `embajador-data`/`seller-data` ya funcionaban.
Corregido igual que Dispositivos: el componente recibe nombre/email/tipo
por props en vez de pedirlos aparte.

### Extra — pantalla suelta `/talento/embajadores-colabs`: mismo self-view fallback que Custodia

Un técnico entrando directo a la pantalla suelta no alcanza el listado
paginado completo (`/talento/api/colaboradores` bare, fuera de
`talento.view` — igual que en Custodia). En vez de dejarlo con una tabla
vacía, ahora ve directo SU PROPIA fila (reusando `GET /talento/mi-ficha`,
mismo patrón que el fix de Custodia). **A diferencia de Custodia, NO se
ocultó el buscador/tabla completa aquí** — el buscador en el contexto de
la ficha embebida ya era inerte de por sí (siempre ignora el texto
buscado cuando hay `colaboradorId`), y en la pantalla suelta el listado ya
estaba bloqueado para técnico por el gap de `/talento/api/colaboradores`.
Con el IDOR cerrado, no hay ninguna fuga real que ocultar — se lo dejo
avisado a David por si de todos modos prefiere ocultarlo por consistencia
visual con Custodia.

**Verificado con Playwright** (cuentas desechables, borradas al terminar):
técnico ajeno bloqueado de ambos cross-links de otro (403), técnico ve los
suyos propios (incluido `seller-data`, antes siempre roto), supervisor ve
los de su subordinado, la ficha ya no muestra "Sin colaboradores
encontrados" en autoservicio, y la pantalla suelta resuelve la fila propia
sin exponer la de un ajeno. 10/10.

### Commits

- `49e1c120` — IDOR de dinero real + seller-data roto siempre + tab vacía

## 2026-09-28 13:53 — Roles múltiples: oculta el buscador, igual que Custodia

David: "oculta el buscador aquí también, igual que en Custodia" — aunque
el IDOR de dinero de la pasada anterior ya cerraba la fuga real, se aplicó
el mismo tratamiento visual/consistente que Custodia.

**Cambios:** nuevo `permisos.embajadores_buscador` (ficha(), mismo
criterio que `custodia_buscador`: `talento.employees.view` o CUALQUIER
supervisor — no `talento.embajadores.view`, que técnico también tiene
directo). En la pantalla suelta se reusa la misma detección de "¿tengo
equipo?" vía `/talento/api/mi-equipo` que ya se construyó para Custodia.
La lógica de auto-resolver la fila propia (ya escrita en la pasada
anterior, sin condición) ahora queda detrás de `puedeGestionarResuelto`,
consistente con el mismo patrón en las otras 2 pestañas.

**Verificado con Playwright** (cuentas desechables, borradas al terminar):
en la ficha, técnico sin equipo no ve el buscador, supervisor sí lo ve en
la ficha de su subordinado; en la pantalla suelta, técnico entra directo a
su propia fila sin buscador, supervisor sí ve el buscador completo. 5/5.

### Commits

- `19f3fb87` — oculta el buscador de colaboradores, igual que Custodia

## 2026-09-28 14:06 — Calidad de caja: el controller con MENOS protección de toda la ficha

David pidió "verifica". "Calidad de caja" es el control de calidad de las
cajas de empalme (ODB) que construyen los técnicos en campo: fotografían
la caja, miden pérdida de fusión (dB) y potencia óptica (dBm), califican
la organización (1-10), pueden pedirle a una IA que analice la foto
(asesora, no decide), y **un supervisor valida** el resultado final —
igual o distinto al que sugirió la IA.

### 🔴 El hallazgo más grave de toda esta ronda — CERO autorización en casi todo el controller

De los 10 métodos de `TalentoQualityController`, **9 no tenían NINGÚN
chequeo de permiso** (ni `authorize()`, ni scoping propio, nada) — solo
`servePhoto()` tenía algo, y estaba mal (admin-only sin self-view). Esto
incluía:

- **`supervisorValidate()` — la acción que literalmente se llama
  "supervisor valida" — cualquiera, incluido el propio técnico que hizo la
  inspección, podía llamarla y auto-aprobar su propio trabajo.** Es el
  control de calidad entero, vaciado de sentido.
- `storeStandard()`/`updateStandard()`/`uploadStandardImage()`/
  `destroyStandard()` — cualquiera podía crear, editar, subir imagen o
  **borrar** un estándar de construcción del catálogo de la empresa.
- `runIaAnalysis()` — cualquiera podía disparar análisis de IA (cuesta
  dinero real) sobre la inspección de cualquier otro.
- `inspectionsIndex()`/`showInspection()` — sin scoping, cualquiera veía
  la inspección de cualquiera (fotos, mediciones, resultado).

**La única razón por la que esto no era explotable hoy:** ninguna de estas
rutas (`/talento/api/inspecciones`, `/talento/api/standards`) estaba
cubierta por NINGÚN bloque de `route_permission.php` — ni siquiera
`talento.quality.view`/`.manage` las incluían. El middleware bloqueaba
TODO antes de llegar al controller, para cualquiera que no fuera admin vía
bypass. **Efecto colateral: ni siquiera `storeInspection()` — que SÍ
estaba bien diseñado, self-scoped por `auth()->id()` — era alcanzable.
Ningún técnico pudo enviar una inspección de calidad desde que se escribió
esta pestaña.**

### Fix

- Agregado `authorize('talento.quality.manage')` a las 4 acciones de
  escritura del catálogo (crear/editar/imagen/borrar estándar) — política
  de empresa, admin-only a propósito, SIN excepción de supervisor (a
  diferencia del resto del módulo).
- Nuevo `puedeVerInspeccionDe()` (uno mismo/supervisor directo/staff) en
  `inspectionsIndex()`/`showInspection()`/`runIaAnalysis()`/`servePhoto()`.
- Nuevo `esSupervisorDeInspeccion()` (supervisor directo del inspector, o
  staff — **SIN autoservicio**) en `supervisorValidate()`: quien hizo la
  inspección no puede validar su propio trabajo, sea cual sea su otro
  permiso.
- `route_permission.php`: abiertas `/talento/api/standards` (+`**`) y
  `/talento/api/inspecciones` (+`**`) en `talento.view` — las 4 acciones
  de catálogo y `supervisorValidate()` quedan protegidas por su propio
  `authorize()`/scoping nuevo, así que abrir la ruta no las abre de
  verdad.
- `permisos.calidad_validar` (ficha()) = supervisor directo de ESTE
  colaborador o staff — controla si se muestra el bloque "Validación de
  supervisor" en el modal (quien inspeccionó nunca lo ve para su propia
  inspección).
- `permisos.calidad_estandares_manage` (ficha()) = solo
  `talento.quality.manage` — controla "Nuevo estándar"/"Editar"/"Subir
  imagen"; a propósito ni siquiera el supervisor lo ve (política de
  empresa, no gestión de equipo).
- El botón **"Nueva inspección" queda SIN gating** — es medición de campo
  (mismo criterio que el baseline de Cajas ODB), cualquier técnico puede
  registrar la suya.

**Verificado con Playwright** (cuentas desechables, borradas al terminar):
técnico crea su propia inspección (antes 403 SIEMPRE — la pestaña nunca
funcionó para nadie), técnico ajeno bloqueado de verla, técnico dueño la
ve, ni el propio técnico ni un ajeno pueden autovalidarla (403 en ambos
casos), el supervisor SÍ la valida correctamente, UI oculta "Nuevo
estándar" tanto a técnico como a supervisor (solo admin), "Nueva
inspección" visible para el técnico, catálogo de estándares legible en
autoservicio, y el listado global sin filtro sigue bloqueado para
técnico. 13/13.

### Commits

- `ca51f56d` — el controller con MENOS protección de toda la ficha

## 2026-09-28 14:11 — Cajas ODB: verificado catálogo/config, un IDOR menor encontrado y corregido

David pidió verificar específicamente el catálogo/config de esta pestaña.
Esta ya había sido trabajada con cuidado en una pasada anterior (misma
sesión): "registrar baseline" (medición real de campo, técnico puede
hacerlo) separado de "guardar settings" (política del bono, admin o
cualquier supervisor) — la distinción y el diseño ya estaban bien hechos,
con comentarios explícitos de la decisión de David.

**Verificado y confirmado correcto (sin cambios):** el catálogo de
cajas/baselines (`data()`/`latestPerCaja()`) es global a propósito
(infraestructura compartida, no "de una persona" — decisión ya
documentada); `getSettings()`/`updateSettings()` distinguen bien
admin/supervisor de técnico simple (`puede_gestionar` en la respuesta,
consumido correctamente por el frontend); "Registrar baseline" sigue sin
gate, como debe ser.

### 🔴 Encontrado y corregido: IDOR menor en el log de bono de salud

`bonusLog()` exigía solo `talento.health_bonus.view` — mismo patrón
recurrente de esta sesión: ese permiso lo tienen TECNICO/TECNICO_PLANTA/
TECNICO_INSTALADOR **directo** (para ver su propio log en su ficha), pero
sin scoping propio cualquier técnico podía pasar el `colaborador_id` de
CUALQUIER otro compañero y ver su historial de bonos (qué orden de
trabajo, cuánta pérdida óptica, si ganó bono y cuánto). Corregido con
`puedeVerBonusLogDe()` (uno mismo/supervisor directo/`talento.employees.view`
— no `talento.health_bonus.view`, que es justo la que ya tienen los
técnicos).

**Verificado con Playwright** (cuentas + log de bono de prueba, borrados
al terminar): técnico registra su propio baseline; técnico sin equipo NO
puede guardar settings (403 + `puede_gestionar:false`); supervisor SÍ
puede (200, monto quedó guardado); técnico ajeno bloqueado del bonus-log
de otro (403); técnico ve el suyo propio; supervisor ve el de su
subordinado; listado global sin filtro bloqueado para técnico; UI oculta
"Guardar settings" a técnico simple y lo muestra a supervisor. 14/14.

⚠️ Nota de limpieza: al revertir el monto de bono que la prueba cambió a
$35, se borró la fila de `settings` en vez de restaurar su valor previo
(no capturado antes de la prueba) — queda en el default del código ($30).
Dato de configuración de dev, bajo impacto, pero lo dejo anotado por
transparencia.

### Commits

- `7017a9a4` — IDOR menor en bonusLog(), resto del catálogo/config verificado OK

## 2026-09-28 14:15 — Dispositivos: se me había pasado el buscador (David lo notó)

David, en medio de la verificación de otra pestaña: "en dispositivos
vinculados sigue el buscar colaborador" — correcto, se me había quedado
pendiente aplicar el mismo tratamiento que ya tenían Custodia y Roles
múltiples.

Mismo patrón exacto que las otras dos: nuevo `permisos.dispositivos_buscador`
(ficha()) = `talento.employees.view` o CUALQUIER supervisor (no
`talento.devices.view`, que TECNICO/TECNICO_PLANTA/TECNICO_INSTALADOR
también tienen directo — necesaria para ver la tarjeta de descarga del
APK, no sirve para distinguir). Buscador oculto sin ese permiso; en la
pantalla suelta (`/talento/dispositivos`, alcanzable por técnico
precisamente por esa tarjeta de descarga) se reusa la misma detección
"¿tengo equipo?" vía `/talento/api/mi-equipo` ya construida para Custodia,
y sin permiso se resuelve directo la fila propia en vez del listado
completo. La tarjeta de descarga del APK (pública, sin gate) quedó
intacta.

**Verificado con Playwright** (cuentas desechables, borradas al terminar):
en la ficha, técnico sin equipo no ve el buscador, supervisor sí en la
ficha de su subordinado; en la pantalla suelta, técnico entra directo a
su propia fila sin buscador (y sigue viendo la tarjeta del APK),
supervisor sí ve el buscador completo. 6/6.

### Commits

- `eff94eeb` — oculta el buscador de colaboradores (pendiente de Custodia/Roles múltiples)

## 2026-09-28 15:30 — Paquetes de documentos: 403 para TODO técnico/supervisor (rota desde que se construyó)

David: "siguiente pestaña, paquetes de documentos, verifica".

**Para qué se usa:** pestaña de solo lectura en la ficha (`TalentoFichaDocumentos.vue`)
que muestra los documentos ya generados del expediente de un colaborador
(contrato, reglamento, etc.) — nombre, estado, fecha de generación y si
está firmado/pendiente de firma. No firma ni edita nada desde ahí (eso
vive aparte, en el Portal del colaborador — `PortalTecnicoController::
documentos()`/`firmarDocumento()`, ya construido en items previos).

**Bug encontrado (severidad alta — mismo calibre que Calidad de caja):**
`TalentoColaboradorController::miFichaDocumentos()` (self/supervisor/
manage-scoped vía `resolverColaboradorAutoservicio()`, ya bien construido)
delegaba en `TalentoEmployeeDocumentController::forColaborador()`, que
hace ADEMÁS `$this->authorize('talento.expediente.view')` — permiso que
**solo tienen super-administrator y DESARROLLADOR** (verificado contra
BD). Resultado: absolutamente NINGÚN técnico ni supervisor podía abrir
esta pestaña, ni siquiera para ver SUS PROPIOS documentos — la
autorización self-scoped de `resolverColaboradorAutoservicio()` quedaba
anulada por el candado de staff que venía después. La pestaña ha estado
rota al 100% desde que se construyó (item 1b, mismo día).

**Fix:** se separa el cuerpo real de `forColaborador()` en un método
nuevo `documentosDe($colaboradorId)` SIN el `authorize()` amplio.
`forColaborador()` (la ruta staff `/talento/api/colaboradores/{id}/
documentos`, protegida además a nivel de middleware por
`talento.employees.view`) sigue haciendo `authorize('talento.expediente.
view')` antes de delegar — sin cambio de comportamiento para ese camino.
`miFichaDocumentos()` ahora llama a `documentosDe()` directo: la
autorización para ESE colaborador puntual ya la resolvió
`resolverColaboradorAutoservicio()` (uno mismo, su subordinado directo,
o quien gestiona órdenes en general) — exigir el permiso de staff
completo encima de eso era el bug. Mismo patrón de "separar el authorize
amplio del cuerpo reusable" que `TalentoCajaController::
puedeVerBonusLogDe()` / `TalentoEmbajadoresController` ya usaban.

**Verificado con Playwright** (técnico + su supervisor, cuentas
desechables, sin colaborador con documentos generados — probado con
lista vacía, la lógica de mapeo de filas no se tocó, solo el candado de
autorización): ANTES del fix, las 4 combinaciones (técnico ve lo suyo,
técnico intenta ver a su supervisor, supervisor ve lo suyo, supervisor
ve a su subordinado) daban 403 — incluidas las dos que debían funcionar.
DESPUÉS del fix: técnico ve lo suyo (200), técnico intenta ver a su
supervisor — sigue en 403, correcto, no es su subordinado — supervisor
ve lo suyo (200), supervisor ve a su subordinado (200). 4/4.

### Commits

- `96ab06c2` — fix(talento): pestaña Paquetes de documentos 403eaba para TODO técnico/supervisor

## 2026-09-28 15:45 — Documentos: firma y huecos, "igual que en vendedor" (reemplaza el fix anterior)

David: "verifica que funcione igual que en vendedor para la parte de las
firmas y los campos faltantes en los documentos, que todo este igual que
ya aquello esta mas que probado y esta bien".

**Hallazgo:** el fix de las 15:30 (commit `96ab06c2`) solo arregló la
LISTA de documentos — pero la pestaña seguía usando un componente propio
de solo lectura (`TalentoFichaDocumentos.vue`), sin firmar ni completar
huecos. Vendedores (`InformationSeller.vue`) y el modal admin
(`TalentoColaboradores.vue`) ya usan un componente distinto, mucho más
completo y "más que probado": `talento-expediente-documentos`
(Ver/Imprimir/Firmar con pad+subida/Completar documento con huecos
agrupados). David pedía que la ficha tuviera ESO, no una versión
recortada aparte.

**Decisión de arquitectura:** en vez de duplicar el componente rico con
una copia self-scoped (lo que habría significado mantener dos veces la
misma UI de firma/huecos), se ensanchó el ÚNICO controller que ya
consumen Vendedores y el modal admin
(`TalentoEmployeeDocumentController`) para aceptar TAMBIÉN a uno mismo o
al supervisor directo, sin tocar el comportamiento de quien ya lo usaba
(staff sigue entrando exactamente igual, por los mismos permisos de
siempre). Se eliminó la ruta paralela `/mi-ficha/{id}/documentos` y el
componente `TalentoFichaDocumentos.vue` que el fix anterior había creado
— ya no hacían falta, la ficha llama al MISMO endpoint que Vendedores.

**Reglas de autorización aplicadas (documentadas en el código, mismo
criterio que el resto de la ficha esta sesión):**
- **Ver** (listar/mostrar/imagen de firma/huecos): uno mismo, su
  supervisor directo, o staff — el buscador/CRUD sigue igual.
- **Firmar:** uno mismo puede firmar, pero SOLO su(s) propio(s)
  recuadro(s) (`firmante_tipo='colaborador'`) — el recuadro de la
  EMPRESA sigue exclusivo de supervisor/staff, replicando la misma regla
  anti-escalada que ya tenía el autoservicio del Portal
  (`PortalTecnicoController::firmarDocumento`, "nunca queda accesible
  por autoservicio"). El modal de firma en la ficha, cuando el que mira
  no puede gestionar, ya ni siquiera OFRECE el recuadro ajeno (además
  del rechazo del servidor).
- **Completar documento (huecos):** NO se abre a uno mismo — esto edita
  CURP/RFC/NSS/domicilio del propio empleado o datos de la EMPRESA
  (compartidos por TODOS los colaboradores), mismo criterio que
  `credenciales_manage`/`settlement_manage` ya establecido esta sesión
  (identidad/datos compartidos = nunca autoservicio). Solo supervisor
  directo o staff. El botón se oculta en la ficha cuando no aplica.

**Verificado con Playwright real** (técnico + supervisor desechables,
plantilla real con 2 recuadros — empresa/admin y trabajador/colaborador
— más un documento con 1 campo faltante de prueba): técnico ve su
propia lista y firma su recuadro (200); intenta firmar el recuadro de
la empresa → 403 con el mensaje correcto; intenta completar huecos →
403; intenta ver los documentos de SU supervisor (no es su subordinado)
→ 403. Supervisor ve la lista de su subordinado (200), firma el
recuadro de la empresa del subordinado (200), completa huecos del
subordinado (200). 8/8.

### Commits

- `a95daae3` — reusa talento-expediente-documentos, reemplaza el enfoque del commit 96ab06c2

## 2026-09-29 — Roles múltiples: investigación a fondo, dos bugs reales encontrados (no el reportado literal)

David: "mira en roles multiples, verifica que no se vean roles de otros
usuarios en la tabla que de echo al dar en mostrar da error al cargar en
el modal en como embajador y como vendedor".

**Investigación:** se probó exhaustivamente para reproducir el error
literal del modal ("Error al cargar" en Como Embajador/Como Vendedor):
- Backend: los 30 colaboradores reales de la BD, llamando
  `embajadorData()`/`sellerData()` directo — 0 excepciones.
- Frontend con Playwright: cuenta staff (DESARROLLADOR) haciendo clic en
  "Mostrar" en las 31 filas reales de la pantalla suelta, página por
  página — 31/31 sin error. Técnico viéndose a sí mismo en la ficha —
  sin error. Supervisor viendo la ficha de su subordinado — sin error.
  Mostrador viendo la ficha de otro colaborador — sin error.
- **No se logró reproducir el error exacto del modal.**

**Dos bugs reales sí encontrados, en la misma pantalla:**
1. **Mostrador no podía ni abrir la pantalla suelta** `/talento/embajadores-colabs`
   — tiene `talento.employees.view` (puede ver la pestaña dentro de
   cualquier ficha) pero NO `talento.embajadores.view` (el permiso que
   gateaba esa pantalla suelta) → la redirigía al dashboard **sin
   ningún error visible**, solo "desaparecía". Corregido: `index()` y el
   middleware aceptan cualquiera de los dos permisos.
2. **Un supervisor sin `talento.employees.view` veía la tabla vacía**
   en la pantalla suelta — el buscador SÍ se mostraba (correcto), pero
   la carga de datos llamaba siempre al roster completo
   (`/talento/api/colaboradores`, 403 en silencio para él) en vez de
   `/talento/api/mi-equipo` (su equipo). Mismo patrón latente encontrado
   y corregido en las **3 pantallas** que comparten este código: Roles
   múltiples, Custodia y Dispositivos (las tres construidas hoy con el
   mismo molde).

**Verificado con Playwright:** Mostrador entra ahora a la pantalla
suelta y ve el roster completo (20 filas); un supervisor técnico ve
ahora a su subordinado activo en las 3 pantallas (antes: 0 filas en las
3 — confirmado con un colaborador real inactivo que correctamente
seguía sin aparecer en Custodia, que sí filtra por `status=active`).

**Pendiente:** no se identificó la causa exacta del error del modal que
David reportó — se le pidió confirmar con qué cuenta/rol lo vio para
poder reproducirlo con precisión.

### Commits

- `541fcb91` — Mostrador bloqueada + supervisores sin roster propio (Custodia/Dispositivos/Roles múltiples)

## 2026-09-29 (cont.) — Datos reales asignados a la cuenta demo para probar Documentos (firmas + huecos)

David: "ahora vamos a revisar bien los documentos, asígnale a este usuario
documentos para poder probar que se muestren bien, y que esté bien a parte
de los campos faltantes y las firmas". "Este usuario" = la cuenta demo
(`manual_demo_tecnico`, colaborador #43, roles Vendedor+TECNICO) que David
ya usó para revisar Roles múltiples.

**Qué se hizo (dato, no código):** el colaborador #43 no tenía `job_title`
apuntando a un puesto con plantillas asignadas (traía el texto libre
"Técnico de campo (cuenta demo)", sin match en el catálogo). El único
puesto con plantillas reales configuradas hoy es **Vendedor** (7 plantillas,
`talento_puesto_document_templates`) — coincide con uno de sus dos roles
reales. Se le asignó `job_title='Vendedor'` + `puesto_id=1` y se corrió el
comando oficial y seguro (upsert, no borra firmas):
```
php artisan talento:regenerar-documentos 43
```
→ **7 documentos reales generados**: Contrato Individual de Trabajo,
Entrega y Recepción de Herramientas, Responsiva de Activos Técnicos,
Formato de Alta de Empleado, Descripción de Puesto, Reglamento Interior de
Trabajo (el único con 4 recuadros de firma: empresa/trabajador +
comisión-empresa/comisión-trabajador) y Convenio Individual de Comisiones
— con una mezcla realista de campos faltantes (0 a 14 huecos por
documento) y recuadros de firma reales (empresa/trabajador) en los 7.

**Verificado (sin firmar nada a propósito — David quiere probar la acción
él mismo, no encontrarla ya hecha):**
- Autenticado como el propio usuario demo (sin tocar su contraseña):
  `forColaborador(43)` devuelve los 7 documentos con sus huecos/slots
  reales.
- `ficha(43)` con el demo autenticado: `documentos`=true (ve la pestaña),
  `documentos_gestionar`=false (correcto — el botón "Completar documento"
  queda oculto, y el modal de firma solo debe ofrecerle su recuadro
  'trabajador', nunca 'empresa' — misma regla ya verificada 8/8 la vuelta
  anterior con cuentas desechables).
- HTML renderizado de un documento real (Contrato Individual de Trabajo):
  9,255 bytes, HTML válido, 9 huecos marcados visibles — se ve bien.

**Listo para que David pruebe en su propia sesión:** entrar como
`manual_demo_tecnico`, ir a su ficha → pestaña "Paquetes de documentos" →
7 documentos reales para ver, firmar (su recuadro) y —si entra como
supervisor/staff de este colaborador— completar campos faltantes.

### Commits

- (sin cambio de código — solo datos: `job_title`/`puesto_id` del colaborador #43 + documentos generados vía el comando oficial `talento:regenerar-documentos`)

## 2026-09-29 (cont.) — Documentos: corrección real — "Completar documento" y todos los recuadros de firma, igual que Vendedores (sin carve-out)

David: "me imaginé que ibas a hacerlo mal, primeramente fíjate en
vendedores, los documentos que tienen campos faltantes se muestra un
botón completar documento... además se muestra un canvas por cada firma
que tenga el documento especificando de quién es la firma. Te repito
fíjate en vendedores como está hecho".

**El error real (commit `a95daae3`, vuelta anterior):** al abrir
"Documentos" en la ficha propia como autoservicio, se había restringido
de más — el botón "Completar documento" quedaba OCULTO por completo, y
el modal de firma solo mostraba el recuadro propio ('colaborador'),
quitando de la vista el recuadro de la empresa en vez de solo bloquear
su guardado. Esto rompía justo lo que David pedía verificar: en
Vendedores (staff viendo el expediente de un vendedor) SIEMPRE se ve el
botón cuando hay huecos y SIEMPRE se ven todos los recuadros de firma con
su etiqueta ("POR LA EMPRESA"/"EL VENDEDOR") — la ficha propia debía
verse EXACTAMENTE igual, no una versión recortada por su cuenta.

**Corrección:** se quitó el carve-out que excluía a "uno mismo" de poder
gestionar (firmar cualquier recuadro, completar huecos) su PROPIO
documento — ahora cuenta con el mismo criterio que su supervisor directo
o staff. `TalentoExpedienteDocumentos.vue` **no necesitó ningún cambio**
— con el flag correcto (`puede-gestionar=true` para uno mismo) su lógica
ya hacía exactamente lo que David describió, sin filtrar nada.

**Lo que SÍ se mantiene:** un colaborador sin relación con el dueño del
documento (no es él mismo, no es su supervisor) sigue completamente
bloqueado — ese candado (el anti-IDOR real, `esUnoMismoOSupervisorDe`) no
se tocó.

**Verificado con Playwright real:** técnico firma su propio recuadro
(200) y AHORA TAMBIÉN el de la empresa (200, antes 403); completa sus
propios campos faltantes (200, antes 403); el botón "Completar
documento" aparece en la UI real. Un colaborador ajeno sigue en 403 en
las 3 acciones, sin cambio.

**Los 7 documentos reales de la cuenta demo** (asignados en la entrada
anterior de hoy) siguen intactos, pendientes de firma — listos para que
David pruebe la experiencia completa ahora sí correcta.

### Commits

- `5049e389` — uno mismo gestiona su documento igual que Vendedores, sin carve-out

## 2026-09-29 (cont.) — Academia: el segundo controller más grave de la sesión (sin autorización real)

David: "ya lo revisé, sale bien, pasamos a academia".

**Para qué se usa:** catálogo de cursos de capacitación (Seguridad en
campo, Fusión óptica, Calidad de caja, Atención al cliente, Uso del
sistema), con exámenes, evaluaciones prácticas y certificaciones. La
pestaña de la ficha (`TalentoFichaAcademia.vue`) es una vista de solo
lectura del progreso propio — a propósito distinta de `TalentoAcademia.vue`
(catálogo completo/gestión), mismo patrón intencional que Portal vs.
admin en otras áreas — nada que corregir ahí.

**Hallazgo grave (segundo peor de la sesión, después de Calidad de
caja):** `TalentoAcademyController` no tenía NINGÚN `authorize()`/scoping
salvo `serveEvidencePractical()`. Dos huecos **ya explotables hoy**,
confirmados con Playwright antes/después:

1. **Cualquier técnico podía crear o editar cursos reales.**
   `talento.academy.view` (que TECNICO/TECNICO_PLANTA/TECNICO_INSTALADOR
   tienen directo, para poder VER el catálogo) cubre en
   `route_permission.php` las mismas rutas `/talento/api/courses` y
   `/talento/api/courses/{id}` que `storeCourse()`/`updateCourse()` — el
   middleware no distingue GET de POST/PUT. Confirmado: `POST /courses`
   devolvía **201** antes del fix (creó un curso real, "Curso hackeado
   por tecnico"); `PUT /courses/1` devolvía **200** (cambió el título de
   un curso real). Limpiado tras la prueba.
2. **Cualquier técnico podía ver las certificaciones y el progreso de
   capacitación de CUALQUIER otro colaborador** —
   `certificationsForColaborador()`/`progressForColaborador()` no tenían
   ningún candado de por-quién-pregunta. Confirmado: `GET
   /colaboradores/{ajeno}/certifications` devolvía **200** antes del fix
   (mismo patrón de fuga que "Roles múltiples" de esta sesión).

**Corrección:** scoping (uno mismo/supervisor directo/staff —
`talento.employees.view`, NO `talento.academy.view` que también la tiene
técnico y no distingue) en las 2 lecturas; `authorize()` con
`talento.academy.manage`/`talento.academy.evaluate` en los 8 métodos de
escritura que no tenían ninguno — incluidos `storePractical()`
(autoevaluación práctica, hubiera permitido auto-certificarse) y
`revokeCertification()`, que resultaron ya a salvo por un hueco de ruta
no listado (defensa en profundidad de todos modos, mismo criterio que
Calidad de caja esta sesión). Se agregaron también 4 rutas de
autoservicio construidas pero inalcanzables (tomar examen/enviarlo/ver
mis intentos/mis certificaciones — ya estaban self-scoped por `auth()->
id()`, solo faltaban en `route_permission.php`).

**Verificado con Playwright real, con contraprueba antes/después:**
11/11 casos correctos tras el fix (crear/editar curso→403,
autoevaluarse→403, revocar→403, ver lo propio→200, ver lo ajeno→403 ×2,
mis-certificaciones→200, ruta de examen alcanzable→404 en vez de 403,
lista de cursos sigue accesible); supervisor ve certificaciones/progreso
de su subordinado (200/200); UI real de la pestaña renderiza
correctamente. Datos de prueba (curso creado, título modificado, cuentas
desechables) limpiados/restaurados al terminar.

### Commits

- `6fd7e4b0` — Academia: crear/editar cursos + IDOR de certificaciones/progreso, cerrados

## 2026-09-29 (cont.) — Academia: la ficha propia ahora entra a los cursos (no solo el resumen)

David: "el detalle es que debes mostrar los cursos para que pueda entrar
no solo mostrar los que estan y el progreso debe poder empezarlos por
ahi mismo".

**El problema:** el fix inicial dejó la pestaña Academia de la ficha
como un resumen de solo lectura (progreso + certificaciones) — correcto
para cuando ALGUIEN MÁS ve tu ficha, pero incompleto para cuando eres
TÚ viéndote a ti mismo: no había forma de entrar a un curso ni de
presentar un examen desde ahí.

**Por qué no es "igual que Documentos":** `TalentoAcademia.vue` (el
catálogo completo — ver curso, material, presentar examen) está
diseñado SIEMPRE self-scoped por `auth()->id()` en el backend — a
diferencia de `TalentoExpedienteDocumentos.vue`, no admite apuntarlo al
"colaborador de la ficha" cuando ese no es quien mira. Por eso solo
tiene sentido montarlo cuando el que ve la ficha ES literalmente ese
colaborador — nunca su supervisor ("presentar el examen por él"
falsificaría el resultado, mismo principio que ya rige "Mi trabajo
(Portal)"). Se agregó una señal nueva, `esUnoMismoLiteral` (distinta de
`esPropia`, que también es true para el supervisor), desde el
controller hasta el componente: uno mismo monta el catálogo completo,
cualquier otro viewer sigue con el resumen de solo lectura.

**Bug de conexión encontrado al cablearlo:** `TalentoAcademia` está
registrado en el objeto `components:{}` gigante de `createApp()` en
`app.js` — en Vue 3 eso registra el componente LOCAL a ese componente
raíz, NO en cascada a los SFCs hijos como `TalentoColaboradorFicha.vue`
(a diferencia de `talento-expediente-documentos`, registrado vía
`app.component()`, que sí cascada a cualquier hijo). Sin import+registro
local aquí, Vue no podía resolver el tag — quedaba vacío en el DOM, sin
error visible más que un warning de consola. Corregido con el mismo
patrón que ya usan las otras 15 pestañas de este archivo.

**Dos bugs de más encontrados en el propio `TalentoAcademia.vue`**
(afectaban TAMBIÉN la pantalla suelta `/talento/academia`, preexistentes,
no introducidos hoy): `window.__talento_perms` nunca se asignaba en
ningún lado del código — `canManage` quedaba SIEMPRE `false` (la
pestaña "Admin" nunca aparecía, ni para un admin real) y `canEvaluate`
SIEMPRE `true` ("assume true for simplicity", literal en el código),
mostrando el panel de evaluación práctica a CUALQUIER técnico (ya
inofensivo tras el fix de autorización de la entrada anterior de hoy,
pero seguía siendo un botón que solo iba a fallar). Corregido con el
mecanismo real (`allViewHasPermission()`+`Permission.canDo()`) que usa
el resto de Talento.

**Verificado con Playwright real:** técnico viendo su propia ficha ve
ahora el catálogo completo (5 cursos reales), entra a uno y ve su
material (verificado con contenido real: "Seguridad en campo (NOM-001)"
+ su material de referencia); NO ve la pestaña Admin ni el panel de
evaluación práctica (correcto). Su supervisor, viendo la MISMA ficha,
sigue viendo el resumen de solo lectura sin cambio. Un DESARROLLADOR
real en la pantalla suelta ahora SÍ ve la pestaña Admin (antes nunca la
veía nadie).

### Commits

- `ae58e33f` — ficha propia monta el catálogo completo + fix de conexión + fix canManage/canEvaluate

## 2026-09-29 (cont.) — Academia: se conserva el resumen de progreso debajo del catálogo

David: "ok, ya se muestran los cursos, pero no estaría mal poner debajo
lo que estaba antes que era el progreso y las etapas del curso".

Ajuste simple: el catálogo completo (nuevo) no reemplaza al resumen de
solo lectura que ya existía (progreso general + tabla Curso/Examen/
Práctica/Certificado + certificaciones) — ahora se muestran los dos,
catálogo arriba, resumen abajo, para quien ve su propia ficha.
Verificado con Playwright: ambos bloques presentes.

### Commits

- `f14a5a75` — conserva el resumen debajo del catálogo completo

## 2026-09-29 (cont.) — Información: casi todo bien, un botón muerto para técnicos

David: "siguiente pestaña, veamos qué falta".

**Qué falta en la ficha (pestañas sin la ronda "para qué se usa +
verifica" todavía):** Información, Mi trabajo (Portal — reusa el módulo
Portal ya auditado aparte, fuera de alcance), Órdenes, Compensación,
Liquidaciones, Asistencia, Campo, Rutas — el resto (Cajas ODB, Calidad,
Proyectos, Penalizaciones, Credenciales, Finiquito, Custodia,
Dispositivos, Roles múltiples, Documentos, Academia) ya se cubrió hoy.
Se empezó por Información (la primera pestaña de la ficha).

**Qué es:** datos generales del colaborador (nombre, email, tipo,
departamento, puesto, supervisor, ingreso, status), roles del sistema,
subordinados, y un botón a la pantalla de administración de usuarios.
Casi toda presentacional — recibe el `colaborador` YA CARGADO por el
padre, sin fetch propio (el riesgo real de "quién puede ver estos
datos" vive en el endpoint de carga de la ficha, ya auditado).

**Hallazgo (menor, pero real):** el botón "Gestión de acceso
(Administradores)" (link directo a `/administracion/user/{id}/editar`)
se mostraba SIEMPRE, sin ningún gate — un técnico viendo su propia
ficha lo veía, y al hacer clic caía en el 403/redirect silencioso de
esa pantalla (exige `user_edit_user`, solo roles admin). Mismo patrón
recurrente de la sesión: botón visible que no lleva a nada para quien
lo ve.

**Corrección:** nuevo flag `informacion_gestion_acceso` en `ficha()`
(`user_edit_user`, a propósito SIN el criterio amplio de "verse a uno
mismo o ser supervisor" — la pantalla de usuarios es staff puro) oculta
el botón para quien no puede usarlo.

**Verificado con Playwright:** técnico viendo su propia ficha ya no ve
el botón; un DESARROLLADOR viendo la misma ficha sí lo ve.

### Commits

- `70dea4e2` — oculta "Gestión de acceso" a quien no puede usarlo

## 2026-09-29 (cont.) — Información: "Gestión de acceso" ahora es acceso real para el supervisor (frontera dura, confirmada con pregunta)

David, corrigiendo el fix anterior: "el botón gestión de acceso si es
solo para administradores quítalo de ahí y déjalo específicamente para
los superiores y desarrollador". Dado que esto toca autorización real
sobre cuentas de sistema (contraseña/rol) — una frontera dura — se
preguntó explícitamente qué alcance quería antes de tocar código: ¿solo
ocultar/mostrar el botón, o dar acceso real al supervisor? David
confirmó **acceso real**, con la descripción completa del riesgo puesta
por delante (el supervisor podría cambiar roles/permisos/contraseña de
su subordinado).

**Lo que se hizo:** `UserController` (módulo Core de Usuarios, FUERA de
Talento — la pantalla real `/administracion/user/{id}/editar`) no tenía
NINGÚN `authorize()`/scoping propio, dependía enteramente del permiso de
ruta `user_edit_user` (solo roles admin). Se agregó
`puedeGestionarUsuario($targetUserId)` = `user_edit_user` O ser el
supervisor Talento DIRECTO del usuario objetivo — aplicado a
`edit()`/`getData()`/`update()`. Las 4 rutas involucradas se agregaron a
`talento.view` (que todo técnico tiene) para que la URL sea alcanzable;
el candado real por-quién-es-el-objetivo vive en el controller, mismo
patrón usado toda la sesión.

**Hallazgo real al investigar antes de ampliar** (no reportado por
David — encontrado auditando el código antes de tocarlo, exactamente
para evitar introducir un hueco): `getRoles()` devolvía **todos** los
roles del sistema sin ningún filtro, y `update()` protegía contra
QUITARLE un rol de sistema a alguien que ya lo tenía, pero no contra
**asignarle** uno nuevo. Ampliar el acceso tal cual habría dejado que un
supervisor-técnico promoviera a su subordinado (y de ahí, indirectamente,
a sí mismo) a `super-administrator` desde el mismo selector que usa un
admin real — el mismo tipo de escalamiento de privilegios que se ha
estado cerrando toda la sesión, aquí en un módulo fuera de Talento.
Corregido con una lista de roles "solo-admin-asigna"
(`super-administrator`/`DESARROLLADOR`/`Super Administrador`/
`Administrador`/`ADMINISTRADOR_COMPLETO` — los que dan bypass total o ya
tienen `user_edit_user`): `getRoles()` los excluye del selector para
quien no es admin real, y `update()` los rechaza server-side sin
importar lo que mande el frontend (defensa en profundidad).

**Verificado con Playwright real** (cuentas desechables, borradas al
terminar): el supervisor ve el botón en la ficha de su subordinado,
entra, ve los datos reales, y guarda un cambio real (teléfono) — 200,
persistido. NO ve roles admin en el selector. Un intento de asignarle
`super-administrator` al subordinado (simulando el formulario, id de rol
real) fue rechazado por el servidor — verificado en BD que los roles del
subordinado quedaron intactos (`TECNICO, client`, sin cambio). Un
usuario AJENO (no su subordinado) → 403 en `get-data-user` y `update`.
Uno mismo viendo su propia ficha → el botón NO aparece (verse a uno
mismo no da acceso a editar la propia cuenta desde aquí). Un colega sin
relación de supervisión → tampoco lo ve. Un DESARROLLADOR real sigue
viendo todos los roles y el botón en cualquier ficha, sin cambio.

### Commits

- `4b090e29` — supervisor directo obtiene acceso real, con guard de escalamiento de roles

## 2026-09-29 (cont.) — Órdenes: IDOR real (cualquier técnico veía todas las órdenes del sistema)

David: "verifica órdenes".

**Qué es:** listado de órdenes de trabajo (instalaciones, soporte,
etc.) con crear/ver/validar/avanzar estado. Dentro de la ficha, un
técnico viendo su PROPIA ficha ni siquiera ve esta pestaña admin — ya
tiene sus órdenes en "Mi trabajo (Portal)" (self-service real, app
móvil incluida); esta pestaña es para supervisor/staff.

**Hallazgo grave, confirmado con contraprueba antes/después:**
`talento.work_orders.view` lo tiene TECNICO directo (para su propio
autoservicio vía "Mi trabajo"), pero `data()`/`show()` no tenían NINGÚN
scoping — un técnico en la pantalla suelta `/talento/ordenes` veía
**9 filas** (todas las órdenes del sistema, de cualquier colaborador:
cliente, teléfono, notas, agenda) en vez de solo la suya. Mismo patrón
de fuga que Roles múltiples/Academia esta sesión, aquí sobre datos
operativos de campo.

**Corrección:** `esStaffOrdenes()` (staff real = `talento.work_orders.
manage` o `talento.employees.view` — NO `work_orders.view` puro) +
`idsColaboradoresVisibles()` (uno mismo + su equipo directo). `data()`
rechaza un `colaborador_id` pedido fuera de esos ids, y SIN filtro se
fuerza a "yo + mi equipo" en vez de "todos" (nunca opcional). `show()`
verifica que la orden pertenezca a alguien visible. Se extendió
`OrdenTrabajoUnifiedService::listForAdmin()` para aceptar
`colaborador_id` como arreglo (necesario para que un supervisor con
varios subordinados vea a todo su equipo, no solo a uno).

**Bug de UI relacionado:** los botones "Validar"/"Iniciar"/"Completar"
se mostraban a cualquiera que viera la lista (solo el status de la
orden los condicionaba) — acciones reales de gestión
(`talento.work_orders.validate`/`.manage`, que ningún técnico
autoservicio tiene) que 403eaban en silencio al hacer clic. El técnico
avanza SU PROPIA orden desde la app móvil de campo, no desde esta
pantalla admin. Ahora ocultos con el mismo criterio que ya protegía
"Nueva orden".

**Verificado con Playwright real** (contraprueba antes/después): la
pantalla suelta pasó de mostrar 9 filas (todo el sistema) a 1 (solo la
propia); ver/listar la orden de un colaborador ajeno → 403 (antes 200);
botones Validar/Nueva orden ocultos para autoservicio. Un supervisor
viendo la ficha de su subordinado conserva el flujo completo (crea,
valida, avanza estado) sin ningún cambio.

### Commits

- `136ec14c` — IDOR de órdenes cerrado + botones de gestión ocultos para autoservicio

## 2026-09-29 (cont.) — Compensación: verificada, ya estaba bien construida (sin cambios de código)

David: "siguiente pestaña, compensación".

**Qué es:** reglas de compensación (sueldo base, cuota semanal, $/unidad,
marco KPI opcional para roles no-técnicos) + historial de asignaciones
por colaborador. El catálogo de reglas y "Asignar regla" son acciones de
GESTIÓN; el historial es lo que de verdad es "mi compensación".

**Resultado de la verificación:** esta pestaña ya se había corregido
correctamente en la primera pasada del día (commit `1ceb2e3b`, antes de
la ronda "para qué se usa + verifica" de las pestañas de la tarde) —
`TalentoCompensacionController` ya tenía `puedeVerCompensacionDe()`
(uno mismo/supervisor directo/staff) aplicado a `historyForColaborador()`
y `currentRule()`, y `assignRule()` ya restringido a staff o supervisor
directo (a propósito SIN uno mismo — "solo el superior... no uno
mismo"). Las rutas correspondientes ya estaban en `talento.view` con la
nota explícita de por qué (fecha 28-sep). El frontend
(`TalentoCompensacion.vue`) ya oculta "Nueva regla"/"Asignar regla" con
`v-if="!colaboradorId || puedeGestionar"`, dejando siempre visible el
historial de solo lectura.

**Verificado de todos modos con Playwright real** (no se dio por bueno
solo por leer el código — esta sesión ha encontrado sorpresas en código
que también se veía bien): técnico viendo su propia ficha NO ve "Nueva
regla" ni "Asignar regla", SÍ ve su historial; intento de auto-asignarse
una regla vía API directa → 403; intento de ver el historial de un
colega sin relación → 403. Supervisor viendo la ficha de su subordinado
SÍ ve "Nueva regla"/"Asignar regla", asigna una regla real al
subordinado (201, verificado y limpiado) y NO puede asignarle una regla
a un colaborador ajeno (403). 8/8 correcto — **sin cambios de código**.

### Commits

- (sin cambios — verificación confirmó que ya estaba correctamente protegida)

## 2026-09-29 (cont.) — Aterrizaje post-login: un técnico puro caía en /crm/listar en vez de su ficha

David, logueado como la cuenta demo (rol TECNICO): "al ir a talento si
no voy al dashboard nuevo no me muestra nada porque no tiene permisos
para ver clientes y me redirige a .../ y me intenta mostrar la lista de
clientes... debería ser que la ruta de talento cambie al nuevo
dashboard".

**Investigación:** no era un problema del enlace "Talento" del sidebar
(ese ya apunta a `/talento`, y `/talento` ya redirige correctamente a
la ficha propia — verificado). Era el ATERRIZAJE justo después de
iniciar sesión: `PostLoginRedirectService::FALLBACK_ROUTES` prueba una
lista de rutas EN ORDEN y aterriza en la primera para la que el usuario
tenga el permiso asociado — sin verificar que esa pantalla realmente
muestre algo útil. El rol "TECNICO" puro (a diferencia de
TECNICO_PLANTA/TECNICO_INSTALADOR, que sí tienen
`dashboard_view_dashboard`) no calificaba para `/` (dashboard general),
así que caía a la siguiente de la lista: `/crm/listar` (SÍ tiene
`crm_view_crm` — heredado, muchos técnicos también son Vendedor) — pero
esa pantalla de clientes no le muestra nada útil, y lo deja varado sin
poder ni llegar al sidebar para navegar a Talento por su cuenta.

**Corrección:** se sube `/talento` a la 2ª posición de la lista (justo
después de `/`) — `/talento` ya resuelve solo a la ficha propia o "mi
equipo" (construido hoy), siempre útil para quien tiene `talento.view`.

**Verificado que NO afecta a nadie más** (análisis de roles + Playwright
real con cuentas desechables): Mostrador/TECNICO_PLANTA/TECNICO_INSTALADOR
ya tenían `dashboard_view_dashboard` y seguían cayendo en `/` de todos
modos, sin cambio; Vendedor no tiene `talento.view` (de hecho SÍ tiene
`dashboard_view_dashboard`, así que tampoco llegaba nunca a `/crm/listar`
por este mecanismo) — sin cambio. Un técnico puro (cuenta desechable)
ahora aterriza directo en `/talento/colaborador/{id}` tras iniciar
sesión, en vez de `/crm/listar`.

### Commits

- `834f76d6` — /talento sube a 2ª posición en el aterrizaje post-login, solo afecta al rol TECNICO puro

## 2026-09-29 (cont.) — Aterrizaje post-login, segunda vuelta: un técnico que TAMBIÉN es Vendedor no quedaba cubierto

David reprobó el fix anterior: "lo volví a probar e igual me manda para
el dashboard de clientes, así que vamos a cambiarlo por el dashboard de
talento si es un técnico".

**Por qué el fix anterior no alcanzaba:** se verificó con la cuenta demo
real (`manual_demo_tecnico`, sesión de Playwright autenticada con su
contraseña, que David compartió directo para este diagnóstico). Esa
cuenta tiene roles **Vendedor+TECNICO**, y Vendedor SÍ trae
`dashboard_view_dashboard` — así que calificaba para `/` (dashboard
general) en la PRIMERA posición de la lista, antes de que el bucle
llegara siquiera a revisar `/talento`. El reorden de la vuelta anterior
solo cubría al técnico "puro" sin ningún otro rol con acceso a `/` —
exactamente el hueco que David seguía pisando.

**Corrección:** nuevo paso 0 en `PostLoginRedirectService::resolve()` —
si el usuario tiene rol TECNICO/TECNICO_INSTALADOR/TECNICO_PLANTA
(mismo set que `TalentoCajaController::ROLES_TECNICO`), aterriza
SIEMPRE en `/talento`, sin importar qué otro rol tenga también, y sin
importar `last_visited_route` (para no quedar atrapado en una ruta
vieja de una prueba anterior). Excluido a propósito si el usuario es
admin/Súper Administrador/DESARROLLADOR real — no se le fuerza nada a
una cuenta de desarrollo que traiga un rol técnico de prueba cargado.

**Verificado con Playwright real, con la cuenta demo real:** ahora
aterriza directo en `/talento/colaborador/43` tras iniciar sesión (antes
`/`). Un Vendedor puro (cuenta desechable) sigue aterrizando en `/` sin
cambio. Un DESARROLLADOR con rol técnico de prueba cargado (cuenta
desechable) sigue aterrizando en `/`, sin verse forzado a Talento.

### Commits

- `2f4ed1cc` — técnico aterriza siempre en Talento, sin importar otros roles (Vendedor incluido)

## 2026-09-29 (cont.) — Consolidación: un solo dashboard, se retira /talento/dashboard

David: "cuando acabes con eso, lo que está acá,
http://38.123.192.199:8081/talento/dashboard ponlo en el dashboard
nuevo para que haya uno solo y elimina ese del sidebar".

**Qué era `/talento/dashboard`:** una pantalla suelta previa a la
reestructuración de hoy — 4 tarjetas KPI (colaboradores activos,
asistencia hoy, órdenes hoy, alertas) + 4 pestañas: Producción diaria
(gráfica), Mi panel (cuota/pago proyectado de un colaborador elegido),
Calculadora de pago (simulador hipotético) y Mi equipo (resumen del
equipo de un supervisor elegido).

**Consolidación:** el mismo componente (`TalentoDashboard.vue`, sin
reescribir nada) se monta ahora ARRIBA de la tabla de colaboradores en
`TalentoColaboradores.vue` — el dashboard nuevo, `/talento`. Se retiró
la pantalla suelta completa: ruta eliminada, blade borrado, entrada
"Dashboard" quitada del menú del sidebar.

**Hallazgo real al consolidar (no reportado por David — encontrado
auditando antes de ampliar el alcance del dashboard):** `tecnicoPreview()`
/`simulatePay()`/`equipoPreview()` — que muestran CUOTA y **PAGO
PROYECTADO** (datos de nómina) de un colaborador elegido en un selector
— no tenían NINGÚN candado por colaborador, solo el permiso general
`talento.dashboard.view` (que TECNICO tiene directo). Además, los 5
endpoints de datos del dashboard no estaban en NINGÚN bloque de
`route_permission.php` — en la práctica el dashboard viejo solo
funcionaba de verdad para admin/DESARROLLADOR (bypass total); ningún
técnico pudo verlo jamás pese a tener el permiso. Al integrarlo ahora
al flujo normal (alcanzable por cualquier supervisor), se cierra el
scoping real con el mismo criterio de siempre: uno mismo/supervisor
directo/staff. El selector de colaborador de los 3 sub-paneles (antes
SIEMPRE pedía el roster completo de 200, sin importar quién mirara)
ahora respeta `soloMiEquipo`, igual que la tabla de abajo.

**Verificado con Playwright real:** DESARROLLADOR ve el dashboard
completo + la tabla en `/talento`; `/talento/dashboard` ahora da 404;
0 enlaces a esa URL en el sidebar. Un supervisor (cuenta desechable) ve
el dashboard + su lista propia, sin el botón "Nuevo colaborador" (no es
staff); intento de ver el pago proyectado de un colaborador ajeno →
403 (antes sin ningún candado); de su propio subordinado o su propio
equipo → 200.

### Commits

- `79eccc04` — dashboard viejo consolidado dentro de /talento, ruta suelta y entrada de sidebar retiradas, IDOR de pago proyectado cerrado

## 2026-09-29 (cont.) — Se me pasó un segundo enlace: el sidebar de Talento es hardcodeado, no dinámico

David: "dejaste el otro dashboard en el sidebar".

**Por qué se me pasó:** edité `module.json`'s `menu[]` (el mecanismo
dinámico) para quitar la entrada "Dashboard" — pero Talento sigue en
`$sidebarHardcoded` (`sidebar.blade.php`), así que su sidebar real se
arma desde un parcial dedicado propio
(`resources/views/module-sidebar/talento.blade.php`), completamente
aparte del `menu[]` de `module.json`. Mi primer arreglo no tocó ese
archivo.

Ese parcial traía un SEGUNDO ítem de menú "Dashboard" (distinto del
link principal "Colaboradores"/"Dashboard", que ya usa correctamente
`/talento` con la etiqueta cambiando según el permiso) apuntando
directo a `/talento/dashboard` — la ruta que se retiró en el commit
anterior. Quedó como enlace muerto (404) tras la consolidación. Se
quitó esa entrada duplicada.

**Verificado con Playwright real** (+ `view:clear`, gotcha ya
documentado en CLAUDE.md para este archivo): 0 enlaces a
`/talento/dashboard`; el submenú pasa de "Niveles" directo a
"Escalafón".

### Commits

- `bb0414f2` — segundo enlace "Dashboard" (hardcodeado) retirado del sidebar
