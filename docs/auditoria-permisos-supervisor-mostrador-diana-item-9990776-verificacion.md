# Auditoría de permisos — rol "Supervisor de mostrador" (SUPERVISOR_MOSTRADOR) vs Diana (item #9990776)

**Alcance:** item de SOLO LECTURA. Diagnosticar, con datos reales de dev, por qué el rol de Diana
("Supervisor de mostrador") tiene permisos asignados que no se reflejan donde el administrador los
espera. No se corrigió nada — todas las consultas fueron `SELECT`, ninguna tabla de permisos/roles
se modificó.

## 1. Identificación del caso

- Usuario Diana: `users.id = 3`, `login_user = "Diana"`, `estado = activo`.
- Roles reales asignados a Diana (`model_has_roles`): **Mostrador** (`role_id = 5`) y **Vendedor**
  (`role_id = 6`).
- El rol que el nombre "Supervisor de mostrador" describe existe en el sistema con el nombre técnico
  **`SUPERVISOR_MOSTRADOR`** (`roles.id = 14`, creado 2026-05-27). Es un rol real, no un duplicado ni
  un nombre mal escrito — está referenciado en código vivo (`ExtensionController.php` de VoIP,
  `WarRoomSeeder.php` como `default_presenter_role`, la migración
  `2026_07_04_120000_grant_olt_permissions_to_mostrador.php` que le otorga permisos de OLT junto con
  Mostrador, y `RolePermissionRevocationSeeder.php`).
- **Diana NO tiene asignado el rol `SUPERVISOR_MOSTRADOR`.** Verificado además que, en todo el
  sistema, **ese rol tiene 0 usuarios asignados** (`model_has_roles` sin ninguna fila con
  `role_id = 14`) — no es un problema exclusivo de Diana, es un rol sin nadie dentro.

## 2. El rol SÍ tiene permisos configurados

`role_has_permissions` para `role_id = 14` trae **88 permisos** (verificado por query directa),
incluyendo `client_delete_client`, `finance_edit_payments`, `invoice_add_invoice`,
`user_view_user`, etc. — el rol está correctamente configurado del lado de permisos. El síntoma
reportado ("el rol tiene permisos asignados que no se reflejan") es literalmente cierto: esos 88
permisos existen en la base de datos, pero no producen ningún efecto observable porque **nadie está
en el rol** (ni Diana ni ningún otro usuario).

## 3. Cuánto de eso ya tiene Diana por otra vía, y cuánto le falta de verdad

Diana ya tiene, hoy, 208 permisos directos + los de sus roles Mostrador (144) y Vendedor (66).
Comparando esa unión contra los 88 de `SUPERVISOR_MOSTRADOR`:

- **78 de los 88 (89%) ya los tiene** por Mostrador/Vendedor/directos.
- **Le faltan de verdad, hoy, solo 10**:
  `client_delete_client`, `client_service_internet_delete_client`, `crm_delete_crm`,
  `crm_export_crm`, `dashboard_view_info_invoice_transaction`, `finance_edit_payments`,
  `invoice_add_invoice`, `scheduling_project_view_project`, `scheduling_task_update`,
  `user_view_user`.

Esto es consistente con la lectura de que alguien fue a editar el rol "Supervisor de mostrador"
para darle a Diana un puñado de capacidades extra (borrar clientes/CRM, exportar, editar pagos,
ver usuarios, etc.) — y esas capacidades no llegaron porque el paso de asignar el ROL a la CUENTA
de Diana nunca se hizo (o no se persistió). El rol quedó configurado; la membresía, no.

## 4. Se descartó que sea un problema de enforcement (la deuda de Fase 3a/3b)

Antes de concluir "falta la asignación", se verificó que el motor de permisos SÍ honra
correctamente rol+directos (no es la deuda histórica de `CheckRoutePermission`/`PermissionTrait`
documentada en `CLAUDE.md` §"Rectificación de permisos/roles — Fase 3a"):

- `app/Http/Traits/PermissionTrait.php::getPermissionForUserAuthenticated()` usa
  `$user->getAllPermissions()` (directos ∪ rol) — el flip de Fase 3a sigue vivo en el código.
- El endpoint que alimenta el frontend (`v-hasPermission`, sidebar dinámico),
  `PermissionController::userPermissions()` (`GET /permissions-auth`), también usa
  `getAllPermissions()`.
- Diana no es una cuenta espejo contaminada (no tiene el patrón `Meganet[0-9a-f]{8}` ni roles
  duplicados client+staff) — la deuda de Fase 2/2.5 no aplica a su cuenta.

## 5. Cruce con una investigación paralela ya en curso (mismo caso, ángulo complementario)

Existe un item hermano — **`#9990721` ("permisos")** — con una cadena de sub-items que ataca
exactamente este mismo caso desde otro ángulo: en vez de mirar el estado real de la BD, probaron
**"si a Diana SÍ se le asignara el rol `SUPERVISOR_MOSTRADOR`, ¿el motor de permisos lo aplicaría
bien?"**, dentro de una transacción con `rollback` forzado (sin dejar residuo):

- `#9990743` → `#9990757` (Fase 0+1a, **completado**) corrió `assignRole(14)` sobre Diana en
  `DB::transaction()` y midió `getAllPermissions()`: **`faltantes = 0`** — los 88 permisos del rol
  llegan completos. Evidencia cruda en `storage/app/auditoria/diff-supervisor-vs-diana-20260911-055615.txt`
  (dev, fuera de git).
- `#9990758` (Fase 0+1b, **completado**) clasificó el hallazgo como **RAMA B**: la asignación, si se
  hiciera, funcionaría correctamente vía Spatie — así que el síntoma que Irving reportó **no está en
  el motor de enforcement en el sentido que se sospechaba**; dejaron pendiente una "Fase 3" de
  enforcement bajo el propio `#9990721` (aún no ejecutada) por si hay un ángulo más fino (caché,
  un chequeo hardcodeado en algún controller puntual, etc.) que su prueba sintética no cubrió.
- `#9990746` ("Documento final `docs/auditoria-permisos-2026-09.md` + canal de respuesta") sigue
  `aprobado_irving`, **sin reclamar** — el documento consolidado de esa cadena todavía no existe.

**Las dos investigaciones no se contradicen — se completan.** `#9990721` confirmó que el motor
*aplicaría* bien el rol si se asignara (descartando un bug de enforcement). Esta auditoría
(`#9990776`) confirma el estado *real* de la base de datos hoy: el rol nunca se asignó a nadie, y
por diseño de Spatie eso es suficiente para explicar por completo el síntoma reportado, sin
necesitar ningún bug adicional de caché o de una capa oculta de autorización.

## 6. Causa raíz (resumen)

**No es la deuda ya documentada en `CLAUDE.md` (Fase 2/2.5/3a/3b)** — esa deuda es sobre cuentas
espejo contaminadas y el flip directos-vs-rol, ninguna de las dos aplica a la cuenta de Diana ni al
mecanismo que falla aquí.

**Es un caso nuevo, simple y verificado empíricamente:** el rol `SUPERVISOR_MOSTRADOR` tiene 88
permisos correctamente configurados en `role_has_permissions`, pero **0 usuarios en todo el sistema
están asignados a ese rol** (`model_has_roles`). Diana está en "Mostrador" + "Vendedor", no en
"Supervisor de mostrador". De los 88 permisos del rol, ya tiene 78 por otra vía; los 10 que le
faltan de verdad (borrar cliente/CRM, exportar CRM, editar pagos, agregar factura, ver usuarios,
actualizar tarea de proyecto, ver proyecto de agenda, ver info de factura/transacción en dashboard)
simplemente nunca llegaron a su cuenta porque el rol que los porta no tiene miembros.

## 7. Qué NO se hizo (fuera de alcance de este item)

- No se asignó el rol a Diana ni se tocó ningún permiso — el item es de solo lectura.
- No se decidió si la intención correcta es (a) asignarle el rol `SUPERVISOR_MOSTRADOR` a Diana (y a
  cualquier otro supervisor real de mostrador), o (b) que esas 10 capacidades debieron ir directo al
  rol `Mostrador` y el rol `SUPERVISOR_MOSTRADOR` es en realidad un rol huérfano que conviene
  fusionar/retirar. Es una decisión de negocio de Irving, fuera del alcance de esta auditoría —
  queda registrada como hallazgo para un item de corrección aparte.
