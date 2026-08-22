# Inventario de botones de la Torre (Paso 0)

> **Medido el 2026-08-21 en dev**, verificado con grep exhaustivo de `resources/js/` +
> `app/Modules/Addons/Roadmap/Controllers/RoadmapController.php` +
> `RoadmapMemoryController.php` (y, para el candidato fuera de dominio, `AuditController.php`).
> Objetivo: dejar mapeado **botón → endpoint → controller/método → permiso → confirma →
> migrado**, más el before/after de permisos, como base para los sub-items de migración al
> catálogo `ConsolaAccion` (fundamento en la rama `circuito/item-876-consola-fase-3-catalogo-de-acciones`,
> **todavía sin mergear a main** — ver «Bloqueante conocido» abajo).
>
> Esta vuelta es **solo documentación**: no se tocó ningún `authorize()`, ninguna ruta ni ningún
> permiso. El before y el after de la tabla 1 son **idénticos** a propósito — es la prueba de que
> migrar al catálogo no afloja nada (mismo permiso, solo cambia el punto donde se declara).

**Leyenda ¿Migrado?** · `no` = sigue con `authorize()` inline en el controller (estado actual de
TODOS los botones reales) · `n/a` = excluido a propósito, no aplica migración.

**Leyenda Confirma** · `no` = ejecuta directo al click · `sí` = `window.confirm()` siempre ·
`condicional` = solo pide confirmación en un camino específico (ver columna Notas).

---

## 1. Botones reales con UI (con `authorize()` inline — sin cambio de comportamiento al migrar)

### 1.1 Bandeja de decisiones (`TorreControl.vue`)

| Botón / acción | Endpoint | Controller::método | Permiso (before) | Permiso (after, migrado) | Confirma | Notas |
|---|---|---|---|---|---|---|
| Elegir una opción de pregunta | `POST /api/roadmap/circuito/elegir-opcion` | `RoadmapController::elegirOpcion` | `circuito.decidir` | `circuito.decidir` | no | Guarda por clave estable, no bloquea si falla (reintenta al recargar) |
| ✓ Aprobar | `POST /api/roadmap/circuito/decidir` (`accion:aprobar`) | `RoadmapController::decidir` | `circuito.decidir` | `circuito.decidir` | condicional | Confirma solo si el item tiene un freno humano desbloqueable (`d.desbloqueable`) — pide confirmar quitar el freno y aprobar |
| ✕ Rechazar | `POST /api/roadmap/circuito/decidir` (`accion:rechazar`) | `RoadmapController::decidir` | `circuito.decidir` | `circuito.decidir` | no | — |
| 💬 Comentar | `POST /api/roadmap/circuito/decidir` (`accion:comentar`) | `RoadmapController::decidir` | `circuito.decidir` | `circuito.decidir` | no | — |
| ✔ Cerrar | `POST /api/roadmap/circuito/decidir` (`accion:cerrar`) | `RoadmapController::decidir` | `circuito.decidir` | `circuito.decidir` | no | — |
| ⊘ Cancelar | `POST /api/roadmap/circuito/decidir` (`accion:cancelar`) | `RoadmapController::decidir` | `circuito.decidir` | `circuito.decidir` | no | — |
| Validación funcional → Aprobar | `POST /api/roadmap/validacion/aprobar` | `RoadmapController::validacionAprobar` | `circuito.decidir` | `circuito.decidir` | no | — |
| Validación funcional → Reportar problema | `POST /api/roadmap/validacion/reportar` | `RoadmapController::validacionReportar` | `circuito.decidir` | `circuito.decidir` | no | Pide texto de comentario, no es un `confirm()` |
| Deshacer decisión automática | `POST /api/roadmap/items/{id}/deshacer-decision` | `RoadmapController::deshacerDecision` | `roadmap_manage` | `roadmap_manage` | condicional | Confirma solo si hay 409 (otra terminal ya lo trabaja) |

### 1.2 Integración de ramas (`IntegracionRamas.vue`)

| Botón / acción | Endpoint | Controller::método | Permiso (before) | Permiso (after, migrado) | Confirma | Notas |
|---|---|---|---|---|---|---|
| ✓ Mergear a dev | `POST /api/roadmap/integracion/merge` | `RoadmapController::integracionMerge` | `circuito.decidir` | `circuito.decidir` | sí | `window.confirm` con el número de item |
| ↩ Revertir | `POST /api/roadmap/integracion/revert` | `RoadmapController::integracionRevert` | `circuito.decidir` | `circuito.decidir` | sí | `window.confirm` con el número de item |
| ✕ Rechazar | `POST /api/roadmap/integracion/rechazar` | `RoadmapController::integracionRechazar` | `circuito.decidir` | `circuito.decidir` | sí | `window.confirm` con opción de borrar la rama |
| ✓ Archivar (individual) | `POST /api/roadmap/integracion/archivar` (`id`) | `RoadmapController::integracionArchivar` | `circuito.decidir` | `circuito.decidir` | no | — |
| 🗂 Archivar lo mergeado (masivo) | `POST /api/roadmap/integracion/archivar` (`todos_mergeados:true`) | `RoadmapController::integracionArchivar` | `circuito.decidir` | `circuito.decidir` | sí | `window.confirm` — mismo endpoint que el individual, distinto payload |
| ↩ Traer al radar | `POST /api/roadmap/integracion/desarchivar` | `RoadmapController::integracionDesarchivar` | `circuito.decidir` | `circuito.decidir` | no | — |
| Marcar versión | `POST /api/roadmap/integracion/marcar-version` | `RoadmapController::integracionMarcarVersion` | `circuito.decidir` | `circuito.decidir` | no | — |
| Cambiar modo (radar/historial) | `POST /api/roadmap/integracion/modo` | `RoadmapController::integracionModo` | `circuito.decidir` | `circuito.decidir` | no | — |
| 🔊 Escuchar / cambiar voz / velocidad | `POST /api/roadmap/integracion/voz` | `RoadmapController::integracionVoz` | `circuito.decidir` | `circuito.decidir` | no | Solo persiste preferencia; el habla es client-side (`speechSynthesis`) |

### 1.3 Gestión de items del roadmap (`RoadmapTab.vue`)

| Botón / acción | Endpoint | Controller::método | Permiso (before) | Permiso (after, migrado) | Confirma | Notas |
|---|---|---|---|---|---|---|
| Agregar item (modal) | `POST /api/roadmap/items` | `RoadmapController::store` | `roadmap_manage` | `roadmap_manage` | no | — |
| Guardar prompt | `PATCH /api/roadmap/items/{id}` | `RoadmapController::update` | `roadmap_manage` | `roadmap_manage` | no | — |
| Lanzar | `POST /api/roadmap/items/{id}/start` | `RoadmapController::start` | `roadmap_manage` | `roadmap_manage` | no | — |
| Marcar completado (`cycleStatus`) | `POST /api/roadmap/items/{id}/complete` | `RoadmapController::complete` | `roadmap_manage` | `roadmap_manage` | no | — |
| Cambiar automatización (override) | `POST /api/roadmap/item/{id}/override` | `RoadmapController::itemOverride` | `circuito.decidir` | `circuito.decidir` | condicional | Confirma solo si el backend devuelve 422 pidiendo confirmar subir la automatización |

### 1.4 Config y salud de la Torre

| Botón / acción | Endpoint | Controller::método | Permiso (before) | Permiso (after, migrado) | Confirma | Notas |
|---|---|---|---|---|---|---|
| Guardar (Config de la Torre) | `POST /api/roadmap/torre/config` | `RoadmapController::torreConfigGuardar` | `torre.config.edit` | `torre.config.edit` | condicional | `TorreConfigPanel.vue` pide confirmación extra («¿Subir la automatización?») cuando el cambio afloja el techo |
| Reintentar trabajos fallidos | `POST /api/roadmap/torre/salud/reintentar-fallidos` | `RoadmapController::saludReintentarFallidos` | `torre.salud.manage` | `torre.salud.manage` | sí | `window.confirm` |
| Recalentar cachés | `POST /api/roadmap/torre/salud/recalentar-caches` | `RoadmapController::saludRecalentarCaches` | `torre.salud.manage` | `torre.salud.manage` | no | — |
| Cambiar foto de terminal (worker avatar) | `POST /api/roadmap/circuito/worker-avatar` | `RoadmapController::workerAvatar` | `torre.terminales.editar_avatar` | `torre.terminales.editar_avatar` | no | Upload de archivo (`multipart/form-data`) |

### 1.5 Terminales — reclamos huérfanos (`TorreTerminales.vue`) — ⚠️ no estaban en el inventario original

Encontrados en esta verificación (grep exhaustivo de `axios.post` en `TorreTerminales.vue`); el
item padre (#909) y la investigación previa no los habían listado. Se agregan aquí porque son
botones reales con `authorize()` inline, igual que los demás — el Paso 0 pide el inventario
completo, no solo el que ya se había visto.

| Botón / acción | Endpoint | Controller::método | Permiso (before) | Permiso (after, migrado) | Confirma | Notas |
|---|---|---|---|---|---|---|
| Liberar reclamo (reclamo huérfano) | `POST /api/roadmap/items/{id}/liberar-reclamo` | `RoadmapController::liberarReclamo` | `roadmap_manage` | `roadmap_manage` | no | Suelta el `worker_sid` sin tocar `estado_aprobacion` |
| Reasignar reclamo a otra terminal | `POST /api/roadmap/items/{id}/reasignar-reclamo` | `RoadmapController::reasignarReclamo` | `roadmap_manage` | `roadmap_manage` | sí | `window.confirm` con el destino elegido |

---

## 2. Ya migrado al catálogo `ConsolaAccion`

| Botón / acción | Endpoint | Permiso | Estado |
|---|---|---|---|
| Agregar seguimiento IA | `POST /api/roadmap/circuito/seguimiento` | `torre.seguimiento.crear` | ✅ Migrado en #876, **en rama sin mergear** (`circuito/item-876-consola-fase-3-catalogo-de-acciones`, `esperando_merge_irving`) — no está en `main` todavía |

---

## 3. Excluidos a propósito — NO tocar (familia del kill switch)

Comparten la frontera dura `circuito.pause` / `circuito.disparar` (arranque/parada del circuito
completo). Fuera de alcance de cualquier sub-item de migración sin decisión explícita de Irving.

| Botón / acción | Endpoint | Controller::método | Permiso |
|---|---|---|---|
| Pausar/reanudar circuito (kill switch) | `POST /api/roadmap/circuito/toggle` | `RoadmapController::toggleCircuito` | `circuito.pause` |
| Jalar trabajo ahora (disparar vuelta) | `POST /api/roadmap/circuito/disparar` | `RoadmapController::disparar` | `circuito.disparar` |
| Marcar item urgente | `POST /api/roadmap/items/{id}/urgente` | `RoadmapController::urgente` | `circuito.disparar` |
| Cancelar disparo (ventana de deshacer 15s) | `POST /api/roadmap/items/{id}/cancelar-disparo` | `RoadmapController::cancelarDisparo` | `circuito.disparar` |

---

## 4. Fuera de dominio Roadmap — candidato aparte, NO migrar en este item

`AuditReport.vue` → `AuditController` (`app/Modules/Core/Release/Controllers/AuditController.php`).
Verificado: **sin `authorize()`/`->can()` inline** en `planToggle()` ni `planNote()` — su única
protección es la ruta bajo `Route::middleware(['web','auth','check_route_permission'])->prefix('releases')`
(`app/Modules/Core/Release/routes.php:19-35`), y `config/route_permission.php` **no tiene entrada**
para `releases/audit/plan*` (verificado con grep). Migrarlos al catálogo exigiría **declarar un
permiso donde hoy no existe ninguno** — eso es endurecer seguridad donde no la había, una decisión
de diseño, no un cambio mecánico. Se documenta aquí; no se migra ni se le agrega `authorize()` en
este item.

| Botón / acción | Endpoint | Controller::método | Permiso inline | Notas |
|---|---|---|---|---|
| Alternar item del plan de auditoría | `POST /releases/audit/plan/{id}/toggle` | `AuditController::planToggle` | ninguno (solo gate de ruta) | Candidato a su propio sub-item si se decide abordarlo |
| Nota sobre item del plan de auditoría | `POST /releases/audit/plan/{id}/note` | `AuditController::planNote` | ninguno (solo gate de ruta) | Ídem |

### Resolución del sub-item (#965, 2026-08-21) — DECISIÓN: opción (a), excepción documentada

Se retomó el candidato de arriba como su propio sub-item (#965) para decidir tratamiento. Re-verificado
en esta vuelta (grep fresco, nada cambió desde #960): sigue sin haber entrada en
`config/route_permission.php` para `releases/audit/plan*`, y la rama del catálogo (#876) sigue
`esperando_merge_irving=true`, sin mergear a main.

**Hallazgo adicional que cambia el diagnóstico:** el "solo gate de ruta" de la tabla de arriba **no es
un hueco abierto** — es más restrictivo de lo que parece. `CheckRoutePermission::handle()` (paso 3,
`app/Modules/Core/Auth/Middleware/CheckRoutePermission.php:59`) deja pasar de largo a
`isAdmin()||isDevelopment()||isSuperAdmin()` **antes** de mirar `config/route_permission`; para
cualquier otro usuario, como la ruta no tiene entrada ahí, el paso 4 nunca encuentra permiso y cae al
403/redirect del paso 5. Es decir: **hoy, `planToggle`/`planNote` ya son alcanzables SOLO por
`super-administrator`, `DESARROLLADOR`, `Super Administrador` o `Administrador`** (los 4 roles que
bypasean el middleware) — no por "cualquier autenticado", como podría leerse de "sin permiso".

Esto reabre la pregunta de fondo que el propio #965 señalaba (y que sigue con una consulta a Irving
sin respuesta explícita, ver `comentarios_claude` del item: si el permiso nuevo va también a
`DESARROLLADOR`): declarar aquí un permiso Spatie nuevo y gatearlo solo a `super-administrator` +
`DESARROLLADOR` (que es lo único que `PermissionSyncService::FULL_ACCESS_ROLES` garantiza
automáticamente) sería **más angosto** que el acceso de hoy si algún usuario real tiene el rol
`Super Administrador` o `Administrador` sin también tener los otros dos — un cambio de comportamiento
real, no mecánico, y exactamente el tipo de decisión de roles que ya está en la bandeja de Irving sin
cerrar.

**Se elige la opción (a) del propio item**: dejar los dos botones **fuera** del catálogo por ahora,
sin tocar `AuditController` ni crear ningún permiso — la excepción ya documentada arriba (tabla de la
sección 4) queda como está, y esta nota deja registrado que se revisó y se decidió NO migrar todavía.
Motivo: es más simple, no cambia nada, no depende del fundamento aún sin mergear (#876), y no compite
con la decisión de roles que Irving tiene pendiente. Si Irving resuelve esa pregunta (¿DESARROLLADOR
también?) y decide seguir adelante, el trabajo real queda acotado a: crear el permiso, agregar
`$this->authorize()` inline en `planToggle`/`planNote` (mecánico una vez resuelto lo anterior), y
opcionalmente sumarlo a `config/route_permission.php` para message de 403 consistente con el resto del
sistema — abrir como su propio sub-item cuando eso pase.

---

## 5. Endpoints sin consumidor de UI hoy — verificar código muerto antes de decidir

Grep completo de `resources/js/` (todo `axios.get/post/patch/delete` contra `/api/roadmap/...`)
**sin resultados** para estos endpoints. No requieren migración al catálogo (no hay botón que
mover). Si se confirma que tampoco los usa nada más (tests, MCP, otro módulo), son candidatos de
limpieza aparte — no se borran en este item (Paso 0 es solo inventario).

| Endpoint | Controller::método | Permiso |
|---|---|---|
| `POST /api/roadmap/items/{id}/cancel` | `RoadmapController::cancel` | `roadmap_manage` |
| `DELETE /api/roadmap/items/{id}` | `RoadmapController::destroy` | `roadmap_manage` |
| `PATCH /api/roadmap/items/{id}/subtasks` | `RoadmapController::updateSubtasks` | `roadmap_manage` |
| `POST /api/roadmap/items/{id}/subtasks/{index}/toggle` | `RoadmapController::toggleSubtask` | `roadmap_manage` |
| `POST /api/roadmap/items/{id}/log` | `RoadmapController::addLog` | `roadmap_manage` |
| `POST /api/roadmap/circuito/worker-nombre` | `RoadmapController::workerNombre` | `circuito.decidir` |
| `GET /api/roadmap/items/{id}/memory` | `RoadmapMemoryController::show` | `roadmap_manage` |
| `GET /api/roadmap/items/{id}/memory/prompt` | `RoadmapMemoryController::generatePrompt` | `roadmap_manage` |
| `POST /api/roadmap/items/{id}/memory/report` | `RoadmapMemoryController::appendReport` | `roadmap_manage` |
| `POST /api/roadmap/items/{id}/memory/raw` | `RoadmapMemoryController::replaceRaw` | `roadmap_manage` |

> Nota: los 4 endpoints de `/memory/*` no tenían ni siquiera sus lecturas (`show`/`generatePrompt`)
> consumidas — ampliación sobre lo que decía la investigación previa (solo marcaba `report`/`raw`
> como sin consumidor).

---

## Bloqueante conocido para los sub-items de MIGRACIÓN

El fundamento del catálogo (`ConsolaAccion` / `ConsolaAccionCatalogo` / `ConsolaAccionExecutor`)
**solo existe en la rama** `circuito/item-876-consola-fase-3-catalogo-de-acciones`. El item #876
sigue `aprobado_irving` con fusión manual pendiente (`esperando_merge_irving=true`) — **no está en
`main`**. Este inventario (Paso 0) no lo necesita, pero **cualquier sub-item de migración de las
tablas 1.1–1.5 sí depende de que #876 esté mergeado primero**.

## Before/after de permisos — garantía de esta vuelta

Esta vuelta **no cambió ningún permiso**. Las columnas «Permiso (before)» y «Permiso (after,
migrado)» de la tabla 1 son intencionalmente idénticas: documentan que **mover el `authorize()`
inline al catálogo `ConsolaAccion` es un cambio mecánico de ubicación, no de alcance** — el mismo
permiso Spatie sigue siendo el gate, solo cambia el archivo/patrón donde se declara. Ningún botón
queda más laxo. Los únicos dos casos sin permiso declarado (tabla 4, `AuditController`) se
documentan explícitamente como **fuera de alcance** de este item — endurecerlos es una decisión de
diseño de Irving, no un efecto secundario de un inventario.
