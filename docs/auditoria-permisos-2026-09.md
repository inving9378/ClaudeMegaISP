# Auditoría de permisos 2026-09 — documento final (item #9990721)

**La causa raíz confirmada, en llano:** el rol **"Supervisor de mostrador"** (nombre técnico
`SUPERVISOR_MOSTRADOR`, `roles.id = 14`) tiene sus 88 permisos correctamente configurados en la
base de datos — pero **nadie, en todo el sistema, tiene ese rol asignado** (`model_has_roles` está
vacío para `role_id = 14`). Diana está en los roles "Mostrador" y "Vendedor", no en "Supervisor de
mostrador"; de los 88 permisos del rol, ya tiene 78 por esa otra vía, y los 10 que de verdad le
faltan nunca llegaron porque el paso de vincular el ROL a su CUENTA nunca se completó (el rol quedó
bien configurado, la membresía no). No es un bug del motor de permisos: probado en `DB::transaction`
con `rollback` que, si a Diana se le asignara el rol hoy, el sistema aplicaría los 88 permisos sin
faltantes. El mecanismo exacto por el que esa asignación nunca se guardó (¿la UI, un `syncRoles()`
que la pisó, error humano?) **sigue sin confirmarse** — ver el canal de respuesta al final de este
documento.

Este documento consolida el trabajo de 3 sub-items del item padre `#9990721`
(`#9990743` Fase 0+1 diagnóstico, `#9990744` Fase 2/3 hipótesis — **no se ejecutó**, ver abajo — y
`#9990745` Fase 4 inventario/matriz) más una investigación independiente y paralela sobre el mismo
síntoma (`#9990776`) que llegó a la causa raíz por un ángulo distinto (estado real de la BD en vez
de una prueba sintética). Es solo lectura + redacción: **no se modificó ningún permiso, rol ni
asignación** para producirlo.

---

## 1. El diff exacto de Fase 1 (`#9990743`)

Método: dentro de `DB::transaction()` con `rollback` forzado (verificado después, fuera de la
transacción, que `model_has_roles` sigue en 0 filas para `role_id=14`), se asignó temporalmente el
rol 14 a Diana y se comparó su `getAllPermissions()` antes/después.

| Métrica | Valor |
|---|---|
| Roles de Diana ANTES | Mostrador, Vendedor |
| Roles de Diana DESPUÉS (dentro de la tx) | Mostrador, Vendedor, SUPERVISOR_MOSTRADOR |
| Permisos del rol SUPERVISOR_MOSTRADOR | 88 |
| Permisos directos de Diana (sin tocar) | 208 |
| Permisos efectivos de Diana ANTES (`getAllPermissions()`) | 255 |
| Permisos efectivos de Diana DESPUÉS | 265 |
| **Permisos del rol que NO llegarían a Diana** | **0** |
| Permisos del rol que Diana ya tenía (directos u otro rol) | 78 |
| Permisos netos nuevos que ganaría | 10 |

Los 10 permisos netos que le faltan de verdad: `client_delete_client`,
`client_service_internet_delete_client`, `crm_delete_crm`, `crm_export_crm`,
`dashboard_view_info_invoice_transaction`, `finance_edit_payments`, `invoice_add_invoice`,
`scheduling_project_view_project`, `scheduling_task_update`, `user_view_user`.

`assignRole()` es un `attach`, no un `sync`: asignar el rol es puramente aditivo, no hay ningún
escenario en que le quite algo a Diana de lo que ya tiene.

**Clasificación: RAMA B** — los conjuntos coinciden (si se asignara, funcionaría), así que el
diagnóstico original de `#9990721` apuntaba a "el fallo está en el enforcement" (Fase 3, H7-H10).
La investigación paralela `#9990776` (sección 5) encontró algo más simple: el fallo ni siquiera
llega a la etapa de enforcement, porque el rol nunca se vinculó a la cuenta de Diana en absoluto.

Reporte crudo con la evidencia completa: `storage/app/auditoria/diff-supervisor-mostrador-diana-2026-09-11.md`
(dev, fuera de git).

---

## 2. Tabla de hipótesis H1–H10

**Fase 2/3 (`#9990744`, "verificar hipótesis H1-H10") no se ejecutó** — timeouteó dos veces por
`max_turns` sin dejar un solo commit en su rama (`commits_rama: 0` en ambos timeouts, el segundo
2026-09-14 11:27). Las hipótesis que le tocaban a esa fase se reportan aquí como **SIN VERIFICAR**,
tal como pide este mismo item: no se dan por confirmadas ni descartadas sin evidencia.

| # | Hipótesis | Veredicto | Evidencia |
|---|---|---|---|
| H1 | Caché de Spatie no invalidada | **SIN VERIFICAR** — Fase 2/3 no se ejecutó. Nota: no explicaría por sí sola que `model_has_roles` tenga 0 filas para `role_id=14` (la caché no borra filas de la BD) |
| H2 | Mismatch de `guard_name` entre permisos/roles y el modelo | **DESCARTADA** — verificado en Fase 1: todos los permisos y roles relevantes usan `guard_name='web'`, igual que el modelo `User` |
| H3 | `model_type` incorrecto en `model_has_roles`/`model_has_permissions` | **DESCARTADA** — verificado en Fase 1: `model_type` es `App\Models\User` consistentemente |
| H4 | `PermissionSyncService` pisa con `syncPermissions()` lo asignado a mano al rol | **SIN VERIFICAR** — Fase 2/3 no se ejecutó. Dato indirecto: `role_has_permissions` para `role_id=14` tiene sus 88 permisos íntegros hoy (Fase 1), así que si hubo un sync destructivo en algún momento, no dejó el rol despoblado — pero no descarta que haya afectado la asignación ROL→USUARIO, que es un mecanismo distinto |
| H5 | La UI de asignación guarda solo lo visible (paginación/pestaña) y hace `sync` destructivo | **SIN VERIFICAR** — Fase 2/3 no se ejecutó. Es la hipótesis más compatible con el síntoma real (el rol existe configurado, la membresía no se guardó) — ver la nota del footgun de `UserController::update` en la sección 5 y el canal de respuesta |
| H6 | Permiso duplicado con el mismo nombre en guard distinto | **DESCARTADA** — verificado en Fase 1: no hay duplicados de ese tipo |
| H7 | `@can()` en Blade sin efecto (patrón no soportado en este proyecto) | **SIN VERIFICAR** — es Fase 3, nunca se corrió el `grep -rn "@can(" resources/views/ app/Modules/` que pedía el prompt original |
| H8 | Gating hardcodeado por rol (`hasRole(`/`hasAnyRole(`/nombre de rol literal) que ignora el permiso | **SIN VERIFICAR** — mismo motivo; el `grep` de H8 tampoco se corrió. La auditoría de huérfanos de Fase 4 (sección 3) usó una metodología distinta (grep del *nombre del permiso*, no del patrón `hasRole(`) y no cubre este caso |
| H9 | Deriva de nombres entre lo que muestra la pantalla y lo que checa el código | **SIN VERIFICAR** — Fase 3 no se ejecutó |
| H10 | Doble capa: existe una tabla/campo legacy de permisos aparte de Spatie | **SIN VERIFICAR** — Fase 3 no se ejecutó |

**Nota importante:** aunque H1/H4/H5/H7-H10 quedaron sin verificar formalmente, la investigación
paralela `#9990776` (independiente, con datos reales de la BD) ya explica el síntoma completo sin
necesitar ninguna de las hipótesis de enforcement (H7-H10): el fallo ocurre **antes** de llegar a
enforcement, en la asignación misma del rol a la cuenta. Ver sección 5.

---

## 3. Inventario de Fase 4 (`#9990745`)

### 3.1 Permisos por módulo y guard (punto 1)

**770 permisos** en `permissions`, todos `guard_name='web'`. Agrupados por módulo (usando
`permissions[]` de cada `module.json`, con fallback heurístico por prefijo del nombre cuando el
módulo no lo declara).

### 3.2 Huérfanos vs. usados en código (punto 2)

Comando `php artisan auditoria:permisos-reporte` (nuevo, solo lectura): grep literal de una sola
pasada del nombre de cada permiso contra `app/`, `resources/`, `routes/`, `config/`.

- **701 usados** / **69 huérfanos** (701 + 69 = 770, cuadra con el total).
- Listado completo: `docs/auditoria/permisos-punto1-2.csv`.
- Limitación reconocida por el propio comando: un permiso construido dinámicamente
  (`can('modulo.' . $accion)`) puede salir marcado huérfano sin serlo de verdad — el CSV no
  distingue eso, hay que revisar caso por caso antes de retirar cualquiera.

**Lista de `@can(` vivos y lista de gating hardcodeado por rol (H7/H8): NO DISPONIBLE.** Esos dos
entregables eran, literalmente, las hipótesis H7 y H8 de la Fase 3 — que es la fase que nunca se
ejecutó (`#9990744`). La auditoría de huérfanos de este punto 2 usa una metodología distinta (grep
del nombre exacto del permiso), no produce esas dos listas. Quedan pendientes si se decide
reabrir/reespecificar `#9990744` — ver canal de respuesta.

### 3.3 Rutas/acciones sin protección real (punto 3) — el agujero espejo

Auditoría completa (`#9990764`, descompuesta en 4 lotes: Core+`routes/web.php`, 7 addons grandes,
29 addons restantes, consolidado final), documento íntegro en
`docs/permisos-agujero-item-9990745-auditoria-final.md`.

**Total: 84 rutas en AGUJERO REAL** (sin `check_route_permission`, sin `role:`/`permission:`/`can:`
de Spatie, sin `authorize()`/scoping inline, y sin ser `PUBLIC_ROUTES` intencional):

| Riesgo | Cantidad | Concentración |
|---|---|---|
| 🔴 CRÍTICO | 12 | 8 en `routes/web.php` (control de cuentas ajenas, leak de passwords PPP de Mikrotik en texto plano, DDL arbitrario sobre `payments`, IDOR) + 4 en Marketing (`MarketingConversationController`: envía WhatsApp real suplantando al negocio, reasigna/cierra conversaciones, sin permiso) |
| 🟠 MEDIO | 38 | 20 en `routes/web.php` (IDOR, activity log completo sin paginar, búsquedas sin permiso) + 6 en MegaFamilia (datos de menores sin ownership) + 12 en Marketing (conversaciones + pilot campaigns) |
| 🟡 BAJO | 33 | 28 en `routes/web.php` + 3 en MegaFamilia (shells sin datos) + 2 en Marketing |
| ⚪ Ambiguo | 1 | `POST /get-options-team` — org interna, sin decidir BAJO o MEDIO |

`check_route_permission` es **fail-closed** para no-admins (confirmado contra el código real): si
ninguna entrada de `config/route_permission.php` matchea, la petición se deniega. El agujero real
nunca es "falta la entrada en el config" — es la ausencia total del middleware/guard en el grupo de
rutas. El resto del sistema auditado (29 addons completos + 5 de 7 addons grandes: Talento, Mapas,
Roadmap, GestionRed, MapaRed) **no tiene agujeros**.

Hallazgos secundarios documentados aparte (no son "agujero de permiso" pero se anotan para no
perderlos): 2 rutas rotas apuntando a métodos inexistentes, el API key de Google Maps expuesto a
cualquier staff autenticado (documentado como intencional), y 2 addons (VoIP, CobranzaBlaster) que
protegen 100% por `authorize()`/`can()` inline en vez de en `routes.php` (funciona, pero sin defensa
en profundidad si algún método nuevo olvida el check).

### 3.4 Matriz por rol: asignados vs. efectivos (punto 4)

Comando `auditoria:permisos-matriz-roles` corrido contra la BD real de dev (18 roles, 770 permisos
asignados en total). Hallazgos:

- **1 rol inflado:** `consejo` — 11 permisos asignados que son huérfanos (no surten efecto en
  ningún control real) contra solo 3 efectivos.
- **27 asignaciones de permisos de alto riesgo (facturación/pagos) que NO surten efecto**, repartidas
  en 8 roles: `ADMINISTRADOR_COMPLETO`, `CONTADOR`, `DESARROLLADOR`, `Mostrador`,
  `Super Administrador`, `super-administrator`, `SUPERVISOR_MOSTRADOR`, `Vendedor`. Los permisos
  involucrados: `billing_payment_view_clients`, `billing_view_detail_payments`,
  `billing_payment_sellers`, `invoice_view_invoice`, `invoice_edit_invoice` — están asignados en
  `role_has_permissions` pero, al no ser consultados por ningún control real (son parte de los 69
  huérfanos del punto 3.2), no le dan al rol ninguna capacidad efectiva de facturación/pagos pese a
  aparecer marcados en la UI de administración de roles.

⚠️ **Esta pieza (`#9990766`, pantalla Blade+Vue "Matriz de permisos por rol" en
`/admin/administracion` → tarjeta correspondiente) tiene el código completo y verificado, pero su
rama todavía no está mergeada a `main`** (nivel de riesgo C, esperando que Irving decida mergearla).
Los números de esta sección vienen del CSV generado por el comando (`docs/auditoria/permisos-matriz-roles.csv`
en esa rama, 3409 filas), no de la pantalla en vivo todavía.

---

## 4. Lista priorizada de arreglos

**Ninguno de estos se ejecutó en este item** — cada uno se propuso como sub-item de `#9990721`
(nacen `pendiente_revision`, el revisor decide su `nivel_riesgo` real):

| # | Arreglo | Item | Nivel sugerido | Prioridad |
|---|---|---|---|---|
| 1 | Cerrar los 12 agujeros CRÍTICOS de rutas (password Mikrotik en claro, DDL arbitrario, control de cuentas, WhatsApp real sin permiso) | `#9991116` | B | **Alta** |
| 2 | Blindar `UserController::update` contra su `syncRoles()` destructivo (footgun ya anotado en `CLAUDE.md`, mecanismo más plausible del incidente de Diana) | `#9991117` | B | **Alta** |
| 3 | Cerrar los 38 agujeros MEDIO de rutas (IDOR, activity log sin paginar, datos de menores en MegaFamilia, conversaciones de Marketing) | `#9991118` | B | Media |
| 4 | Saneamiento del rol `consejo` (11 huérfanos vs 3 efectivos) + decidir qué hacer con las 27 asignaciones de permisos de facturación/pagos inertes en 8 roles: ¿retirarlas (limpieza) o construir el enforcement que les falta? | `#9991119` | B | Media |
| 5 | Triar y cerrar los 33 agujeros BAJO + el 1 ambiguo de rutas, junto con los 2 hallazgos secundarios (rutas rotas a métodos inexistentes, `GET /register-vendor` sin documentar) | `#9991120` | B | Baja |
| 6 | Decidir destino de los 69 permisos huérfanos (¿retirar, o construir el control que falta?) — revisar antes caso por caso los que puedan ser de construcción dinámica (`can('modulo.'.$accion)`) | `#9991121` | A | Baja |

Los 6 sub-items ya fueron creados contra `#9990721` con el detalle exacto (archivo/documento fuente,
números, rutas) para que quien los tome no tenga que re-auditar nada.

**No se propone un arreglo nuevo para el caso puntual de Diana** — ya existe y está aprobado:
`#9990797` ("Ejecutar fix de Diana: asignar rol SUPERVISOR_MOSTRADOR + resync"), creado por la
investigación paralela `#9990776`/`#9990791`, bloqueado a propósito hasta que este mismo documento
(`#9990746`) cerrara.

---

## 5. Cruce con la investigación paralela `#9990776`

Existe un item hermano, `#9990776` ("Auditoría de permisos: rol con permisos asignados que no se
reflejan en el administrador — caso Supervisor de mostrador → Diana"), que atacó el mismo síntoma
desde el ángulo del estado real de la base de datos (en vez de una prueba sintética en transacción).
Encontró la pieza que le faltaba a esta cadena: **el rol `SUPERVISOR_MOSTRADOR` tiene 0 usuarios
asignados en todo el sistema** (`model_has_roles` vacío para `role_id=14`) — no es un caso aislado de
Diana, es que nadie, nunca, quedó vinculado a ese rol. Las dos investigaciones no se contradicen, se
completan: `#9990776` confirma que el rol nunca se asignó; la Fase 1 de esta cadena (`#9990743`)
confirma que, si se asignara, funcionaría sin problema. Detalle completo, con las mismas 88/78/10
cifras cruzadas de forma independiente, en
`docs/auditoria-permisos-supervisor-mostrador-diana-item-9990776-verificacion.md`.

Irving ya revisó ese hallazgo (`#9990791`) y decidió: asignarle el rol a Diana es la corrección
correcta (opción recomendada, ya ejecutable en `#9990797` una vez que este documento cierre), y usar
`#9990776` como insumo de este documento en vez de duplicar investigación — exactamente lo que se
hizo aquí.

---

## 6. Qué pantalla revisar con screenshot

Para reproducir el síntoma tal como lo reportó Irving:

1. `/administracion` → pestaña **Roles** → editar el rol **"Supervisor de mostrador"** (nombre
   técnico `SUPERVISOR_MOSTRADOR`) → el modal de asignación de permisos muestra sus 88 permisos
   marcados. Esto es lo que Irving vio y esperaba que Diana heredara.
2. `/administracion` → pestaña **Usuarios** → editar a **Diana** → su lista de roles muestra
   **Mostrador** y **Vendedor**, pero **NO** "Supervisor de mostrador" — es la evidencia directa de
   que el rol nunca quedó vinculado a su cuenta, pese a que el paso 1 sugiere que sí se configuró.
3. (Pendiente de merge, nivel C) La pantalla nueva **"Matriz de permisos por rol"** en
   `/admin/administracion` (rama `circuito/item-9990766-fase-4c-cerrar-vista-bladevue-de-la`, sin
   mergear) mostraría esto mismo de forma directa una vez que Irving decida integrarla, junto con el
   rol `consejo` inflado y las 27 asignaciones de facturación/pagos inertes de la sección 3.4.

---

## 7. Verificación contra los criterios de aceptación del padre

- ✅ El diff de Fase 1 está reportado con números exactos y nombres de permisos (sección 1).
- ⚠️ Cada hipótesis está marcada, pero **no todas CONFIRMADA/DESCARTADA con evidencia** — H1/H4/H5/H7-H10
  quedan **SIN VERIFICAR**, declarado explícitamente (sección 2), porque la fase que las probaría
  (`#9990744`) nunca se ejecutó. No se dieron por hechas.
- ✅ El documento existe, está commiteado con `git add` selectivo, y explica la causa raíz en
  lenguaje llano en el primer párrafo.
- ✅ No se modificó ni un permiso, rol ni asignación durante la redacción de este documento ni de
  ninguno de los sub-items que consolida.

---

## 8. Canal de respuesta

Este reporte contiene 3 puntos que no le tocan a este item decidir (si reabrir/archivar `#9990744`
dado que `#9990776` ya explicó el síntoma sin necesitar sus hipótesis; si los 12 agujeros CRÍTICOS
de rutas ameritan prioridad urgente frente al resto del pool; si vale la pena confirmar el mecanismo
exacto detrás del incidente de Diana antes de aplicar el fix preventivo). Consolidados en un solo
item `[RESPUESTA]`: **`#9991122`**.
