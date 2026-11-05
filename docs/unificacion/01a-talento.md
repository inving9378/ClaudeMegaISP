# UNI-01a · Auditoría del módulo Talento

Item #680 · épica UNI · 2026-10-24 · solo lectura (sin cambios de código, migraciones ni permisos).
Alcance: `app/Modules/Addons/Talento` + `resources/js/components/module/talento`. Conteos = BD de dev, `COUNT`/`GROUP BY` únicamente.
Convención de evidencia: `archivo:línea` o ruta/consulta. «No verificado» = no se pudo comprobar en esta pasada.

## 0. Resumen

- Talento ya es el centro de operación de campo y de pago semanal: 356 rutas, 63 tablas `talento_*`, 59 modelos, 28 servicios, 7 comandos.
- **Una cuenta por colaborador ya se cumple en la práctica**: 39 colaboradores (35 fichas vivas + 4 borradas; *Actualizado tras auditoría UNI-T A5 (2026-10-26)*), 0 sin `user_id`, 0 usuarios con dos fichas.
- La ficha propia (`TalentoColaboradorFicha.vue`) ya reemplazó al Portal y tiene 21 pestañas: es la base natural de «Mi Espacio».
- Lo que **no existe** para el contrato: calendario/agenda, notificaciones in-app, cierre semanal con aprobación (hoy solo `draft`→`closed`), estado «pagado», atribución de ventas versionada, causa de garantía, objetivos departamentales, auditoría uniforme.
- Riesgos de seguridad vistos: tokens Sanctum sin vencimiento y sin *abilities*; login de la app sin `throttle` en la ruta; `validarAdmin` sin guard de auto-validación.

## 1. Inventario

### 1.1 Tablas `talento_*` (conteo en dev)

| Dominio | Tablas (filas) |
|---|---|
| Identidad/org | `colaboradores` 39 (20 activos / 19 inactivos), `role_departments` 4, `puestos` 4*, `levels` 4*, `level_assignments` 0* |
| Órdenes de trabajo | `work_orders` 208, `work_order_types` 5, `work_order_activities` 1, `work_order_incidents` 1, `work_order_media` 0, `work_order_signatures` 0, `work_order_activations` 0, `work_order_ia_validations` 0, `evidence_types` 15, `ot_type_evidence_requirements` 23, `dbm_thresholds` 10, `installation_surveys` 0 |
| Garantía | `responsibility_windows` 0, `warranty_overrides` 0 |
| Compensación | `compensation_rules` 3, `compensation_rule_history` 7, `ledger_entries` 12, `liquidations` 5, `health_bonus_log` 11, `funds` 1, `loans` 0, `settlements` 0, `settlement_items` 0, `comisiones_espejo` 0 |
| Desempeño | `weekly_rules` 7, `weekly_rule_evaluations` 17, `penalties` 2, `penalty_types` 14, `penalty_appeals` 0, `escalafon_config` 0 |
| Planta externa | `projects` 0, `project_activities` 0, `project_activity_reports` 0, `project_deviations` 0, `activity_types` 5, `routes` 0, `route_stops` 0, `route_deviations` 0, `work_sites` 3, `construction_standards` 7, `caja_inspections` 0, `caja_baselines` 0 |
| Asistencia/dispositivos | `attendances` 4, `shift_extensions` 0, `location_pings` 0, `devices` 1, `device_tokens` 0, `app_releases` 4 |
| Capacitación | `courses` 5, `course_materials` 2, `exams` 0, `exam_questions` 0, `exam_attempts` 0, `certifications` 0, `practical_evaluations` 0 |
| Expediente | `document_templates` 12, `document_template_versions` 12, `document_template_signature_slots` 26, `puesto_document_templates` 1, `employee_documents` 1, `employee_document_signatures` 0, `employee_document_reaperturas` 0, `document_reminders` 0, `credentials` 1 |
| Otros | `portal_preferences` 0, `roadmap_items` 12, `reconciliacion_log` 0 |

\* Tablas de niveles/puestos retiradas el 21-oct (`migrations/2026_10_21_120000_retira_permisos_talento_levels.php`, `…120100_paquetes_por_rol_y_retira_permiso_puestos.php`): las tablas y las columnas `level_id`/`puesto_id` siguen en `talento_colaboradores`; solo se quitaron permisos y UI.

Lectura: la **producción real** está en OT (208) y compensación (5 liquidaciones en borrador, 12 asientos de ledger). Planta externa, rutas, exámenes, préstamos, finiquitos y cajas están construidos pero con 0 filas en dev (no probados con datos; en producción no verificado).

### 1.2 Código

| Capa | Cantidad / ubicación |
|---|---|
| Modelos | 59 en `Models/` |
| Controladores | 32 en `Controllers/` (el mayor: `TalentoMobileEquipoController`, API de la app) |
| Servicios | 28 en `Services/` (motor: `LiquidationService`, `HealthBonusService`, `ProjectBonusService`, `PenaltyService`, `WarrantyWindowService`, `OrdenTrabajoUnifiedService`, `FieldFlowService`) |
| Vue | 43 componentes en `resources/js/components/module/talento/` |
| Permisos | ~55 declarados en `module.json` (`talento.*` + `portal.colaborador`); documentos usan además `talento.documentos.ver-propios/firmar-propios/ver-todos` y `talento.expediente.paquetes.manage` (aparecen en rutas/menú) |
| Comandos | `talento:evaluar-reglas-semanales` (dom 04:00), `talento:check-credential-expirations` (diario 07:00), `talento:cerrar-encuestas` (diario 06:30) — programados en `app/Console/Kernel.php:157,208,217`. Sin programar: `sync-colaboradores`, `regenerar-documentos`, `conciliar-comisiones-vendedor`, `reconciliar-comisiones-espejo` (manuales) |

### 1.3 Rutas (`php artisan route:list --path=talento`, 356)

| Familia | Rutas | Middleware |
|---|---|---|
| Admin web `/talento/*` y `/talento/api/*` (sesión web) | 259 | `auth` + `check_route_permission` (conteo exacto por middleware) |
| `/talento/portal/*` (pestañas de la ficha) | 28 | `auth` + `can:portal.colaborador` (+`documentos.*-propios` en 4) — `routes.php:606` |
| `/talento/api/*` Sanctum (app) | 58 | `auth:sanctum` — `routes.php:514` |
| Públicas | 5 | login app, `app/health`, privacidad, `sw.js`, credencial firmada (`signed`) |

## 2. Identidad

- **Vínculo**: `talento_colaboradores.user_id` → `users.id`. Dev: 39/39 con usuario, 0 usuarios con más de una ficha (consulta `GROUP BY user_id HAVING count>1` = 0). No hay colaboradores huérfanos ni usuarios duplicados.
- **Quién es «colaborador activo»**: `Actor::talento()` exige `status='active'` (`Support/Actor.php:44-54`). Un colaborador inactivo conserva su usuario pero pierde ficha, portal y login de la app (`TalentoMobileApiController.php` ~60-66 devuelve 403).
- **Actor** (`Support/Actor.php`): identidad inmutable por request, agnóstica de guard (web o Sanctum). Facetas: `talento()`, `seller()` (25 de 39 colaboradores tienen fila en `sellers`), `embajador()` (inerte: devuelve null para todos), `custodia()`, `trackPrincipal()` (deriva de roles: técnico planta interna/externa, mostrador, vendedor, genérico), `tieneEquipo()`, `esSuperAdminTalento()`.
- **`Actor::sections()`** (`Actor.php:198-225`): catálogo de 11 secciones con bandera `built` y regla `aplica`. Agrupa en «Mi Talento / Mi trabajo / Supervisión / Cuenta». `panel` (Fases D/E) sigue `built=false`.
- **Puesto = rol de Administración**: `TalentoColaborador::rolesDePuesto()` toma los roles Spatie del usuario menos `ROLES_QUE_NO_SON_PUESTO` (`TalentoColaborador.php:50,71-77`). 6 colaboradores tienen 2 o más roles de puesto (p. ej. técnico + vendedor): el sistema los soporta pero `trackPrincipal()` solo elige uno.
- **Departamento**: derivado, no capturado: tabla `role_departments` (4 filas: Planta externa ×2, Planta interna, Mostrador). El campo `department` de la ficha es texto.
- **Supervisor**: `supervisor_id` autorreferenciado. **38 de 39 colaboradores no tienen supervisor** → la pestaña «Mi equipo» y las confirmaciones de penalización dependen de un dato casi vacío.
- **Seguridad de la cuenta**: la app y el web usan el mismo `PasswordService` (bcrypt con *upgrade-on-login*, `TalentoMobileApiController.php` ~44-66).

## 3. Ficha propia (candidata a «Mi Espacio»)

Archivo: `TalentoColaboradorFicha.vue` (518 líneas). La visibilidad mezcla dos mecanismos: `secciones` (de `Actor::sections()`) y `permisos.*` (banderas calculadas en el backend). Pestañas (`:57-180`):

| Pestaña | Visible si | Datos / endpoint | Permiso de ruta |
|---|---|---|---|
| Información | siempre | ficha, credenciales (compacto), tablero de calidad | ficha: `talento.view` o propia |
| Mi día | sección `mi_dia` | `/talento/portal/asistencia/hoy`, `/portal/ots/hoy`, `/portal/reaperturas` | `portal.colaborador` |
| Mi dinero | sección `mi_dinero` | `/portal/dinero/{cuenta,desglose,fondo,prestamos}` | `portal.colaborador`; solo lectura, self-scoped |
| Mis prospectos | sección `prospectos` | `/portal/prospectos` (CRM del vendedor) | `portal.colaborador` |
| Mi equipo | `equipo` | `/portal/mi-equipo-progreso`, `/portal/equipo/penalizaciones` | `portal.colaborador` + equipo propio |
| Órdenes | `mostrarTabOrdenes` | `/talento/api/ordenes` | `talento.work_orders.view` |
| Compensación | `permisos.compensacion` | reglas y asignaciones | `talento.compensation.view` |
| Liquidaciones | `permisos.liquidaciones` | `/talento/api/liquidaciones` | `talento.liquidation.view/totales` |
| Asistencia | `permisos.asistencia` | `/talento/api/asistencia` | `check_route_permission` |
| Campo, Cajas, Rutas, Calidad | `permisos.* && esTecnico` | `/campo`, `/cajas`, `/rutas`, `/inspecciones` | `talento.quality.*`, etc. |
| Proyectos | `permisos.proyectos` | `/proyectos` | `talento.projects.view` |
| Penalizaciones | `permisos.penalizaciones` | `/penalties` | `talento.penalties.view` |
| Finiquito/Préstamos | `permisos.finiquito` | `/settlements`, `/loans` | `talento.settlement.view`, `talento.loans.view` |
| Custodia | `permisos.custodia` | `/portal/material` (inventario; *Actualizado tras auditoría UNI-T A5 (2026-10-26): la ficha de colaborador no lo usa*) | `talento.custody.view` |
| Dispositivos | `permisos.dispositivos` | `/devices` | `talento.devices.view` |
| Roles múltiples | `permisos.roles_multiples` | `portal/roles-multiples` (API app) | — |
| Documentos | `permisos.documentos` | `/portal/documentos` (+firma, PDF; *la ficha no lo usa*) | `talento.documentos.ver-propios` |
| Academia | `permisos.academia` | `/courses`, `/exams`, `/my-certifications` | `talento.academy.view` |

Observaciones:
- Las pestañas «Mi día/dinero/prospectos/equipo/documentos» son **propias** (se auditan por `Actor`); el resto es **la misma pantalla de administración** embebida en la ficha de un colaborador, filtrada por permiso. Una persona con permiso administrativo ve datos de otros desde la misma interfaz que usa para ver los suyos.
- No existen en la ficha: agenda/calendario, notificaciones, metas/objetivos, evaluaciones, comisiones propias del colaborador no vendedor, bonificaciones estimadas. («Mis ventas» y «Comisiones» existen como pantallas de menú, no como pestaña de la ficha.)
- `sections()` etiqueta tres secciones distintas igual («Mi progreso»: `calidad`, `ventas`, `mostrador`), mutuamente excluyentes por track (`Actor.php:208-210`).

## 4. Operación de campo

- **OT** (`talento_work_orders`, 208): estados reales en dev `validated` 206, `completed` 1, `incidencia` 1. Tipos y puntos: Instalación nueva (9 pts) 184; Soporte/reparación-garantía (3 pts) 23; Reubicación 1; Cambio de equipo y Baja sin uso (0 pts, no facturables). Columnas relevantes: `accepted_at`, `activation_*`, `survey_completed`, `validated_by`, `nota_tecnico`, `caja_id`, `olt_onu_id`.
- **Dos fuentes de «orden de campo»**: `talento_work_orders` y `tasks` con `talento_type_id` (5 de 1,666 tareas). `OrdenTrabajoUnifiedService` las presenta como una sola (mapa `TASK_STATUS_MAP`, `Services/OrdenTrabajoUnifiedService.php:~22`) y `countBillableUnits` suma ambas. Es un puente funcional, no una fusión: cada fuente conserva su estado propio.
- **Evidencias**: catálogo `evidence_types` (15) + matriz `ot_type_evidence_requirements` (23) + umbrales dBm (10). Cierre bloqueado si faltan obligatorias o dBm fuera de rango (`completarOT`). Reglas anti-basura: una por tipo, GPS, justificación en «Otro». `work_order_media` y `signatures` en 0 filas en dev: nunca se ejercitaron con datos reales aquí.
- **Firma, incidencias, validación**: firma como evidencia tipo `es_firma`; incidencia → estado `incidencia` (1 caso); validación por permiso `talento.work_orders.validate` (`TalentoWorkOrderController.php:306-310`). **No se encontró guard de auto-validación** en `validarAdmin` (`OrdenTrabajoUnifiedService.php:1032-1054`): depende solo del permiso. En dev 0 órdenes validadas por su propio ejecutor.
- **Garantía**: `WarrantyWindowService` crea una ventana de 6 meses (`WINDOW_MONTHS = 6`, `:13,19`) por colaborador+cliente y desactiva la anterior; clasifica OT de soporte (`classifyWarrantyOrder`) y permite *override* con motivo/evidencia. 0 ventanas y 0 *overrides* en dev aunque hay 23 OT de soporte: el flujo existe pero hoy no se ve ejecutado en esta base (no verificado si corre en producción). **No hay causa de la falla** (defecto/material/tercero/…): solo atribuye a la ventana.
- **Asistencia**: `AttendanceService` (check-in/out, 4 registros), `shift_extensions`, `location_pings` (0). Mapa en vivo existe (`TalentoMapaVivo.vue`).
- **Custodia**: no hay tabla propia; es `inventory_item_stocks` por usuario, leída vía `InventoryService` (solo lectura).
- **Planta externa**: proyectos/actividades/reportes/desviaciones (0 filas). `ProjectActivityService::submitReport` tiene `$autoApprove` parametrizado desde el controlador (`:22-66`, cambio del 28-sep). La pertenencia del reportante al proyecto no se validó en esta pasada.
- **Frente a Tareas/Scheduling**: no se audita aquí (otros items). Sí consta que Talento no tiene calendario propio: el campo `scheduled_at` de la OT existe, sin vista de agenda.

## 5. Desempeño e incentivos (solo descripción)

| Pieza | Qué calcula | Quién aprueba | Qué registra |
|---|---|---|---|
| Ventana de pago `PayWeek` (`Support/PayWeek.php`; test `tests/Unit/Talento/PayWeekTest.php`) | Sáb 18:00 → Sáb 18:00; corte `2026-07-11 18:00` | — | Fuente única de la ventana |
| `LiquidationService::countBillableUnits` (`:272`) | Unidades = OT + tasks de Talento (de campo **e internas**, desde 2026-10-26) + puntos de proyecto | — | Alimenta `calculate` y `breakdown` (el portal muestra lo mismo que se paga) |
| Liquidación | Base + sobreproducción + créditos/débitos del ledger | `talento.liquidation.manage` calcula y cierra (`routes.php:182-184`) | `liquidations.status` = `draft`/`closed` (dev: 5 `draft`). **No hay estado «pagado»** ni confirmación de pago |
| Bono de salud de red | Por lectura dBm (umbrales) | automático | `health_bonus_log` 11 + ledger |
| Bono de proyecto | Por puntos de proyecto | **manual** (`TalentoProjectController` → `ProjectBonusService::award`), sin llave única en el asiento: no es idempotente (*Actualizado tras auditoría UNI-T A5 (2026-10-26)*) | ledger |
| Ledger `talento_ledger_entries` | Asientos `credit`/`debit` (12: 10 crédito, 2 débito) | los escriben 8 servicios (`LiquidationService`, `HealthBonusService`, `ProjectBonusService`, `PenaltyService`, `FundService`, `LoanService`, `SettlementService`, `TalentoWeeklyRuleEvaluationService`) | Sin columna de estado ni de idempotencia: es append-only por convención, no por restricción |
| Penalizaciones | Catálogo de 14 tipos; aplica con evidencia | aplica `penalties.manage`; apela el afectado; resuelve revisor distinto (`penalties.resolve`, `PenaltyService::resolveAppeal`) | `penalties` 2, ledger débito |
| Reglas de desempeño semanal | 7 reglas por combinación de roles (mostrador, técnico, ventas, calidad de señal; 2 marcadas DEMO) | Domingo 04:00 deja penalizaciones **pendientes**; las confirma un supervisor (`Kernel.php:153-157`) | `weekly_rule_evaluations` 17 |
| Fondos, préstamos, finiquitos | Ahorro forzoso, adelantos, finiquito | `*.manage` | `funds` 1, `loans` 0, `settlements` 0 |
| Encuestas de instalación | Cierre automático a los 7 días valida la OT | automático | `installation_surveys` 0 (sin uso en dev) |
| Escalafón | Puntaje compuesto | `escalafon.manage` | `escalafon_config` 0 |

Lo que **no existe**: metas/objetivos individuales o departamentales, evaluaciones de desempeño formales, políticas de comisión por persona/puesto/campaña con versión, libro de incentivos con estados (estimado→autorizado→pagado), idempotencia por evento, reapertura auditada de período. La atribución de ventas vive en Vendedores (`sellers`, `transaction_sellers`); Talento solo la lee (puente `TalentoEmbajadoresController::sellerData`, pestañas de ventas/comisiones; `comisiones_espejo` 0).

## 6. Organización

- **Departamentos**: derivados del rol por `role_departments` (4 filas); no hay catálogo editable de departamentos ni objetivos por departamento.
- **Puestos**: = roles Spatie (Administración). `puestos`/`levels` retirados de UI y permisos; tablas y columnas residuales.
- **Equipos/cuadrillas**: no hay entidad de cuadrilla. Solo `supervisor_id` (38/39 vacío) y participantes en reportes de actividad (`activity_report_participants`, 0).
- **Roles múltiples**: soportados (6 casos). `Actor::trackPrincipal()` fija una sola etiqueta para la vista; las reglas de desempeño sí consideran combinaciones.

## 7. App de campo (APK Talento)

- **Auth**: `POST /talento/api/auth/login` (público, sin middleware, `routes.php:492`). Valida con `PasswordService`, exige colaborador activo, borra el token anterior del mismo nombre (un dispositivo activo) y emite `createToken('talento-mobile')` (`TalentoMobileApiController.php:77`).
- **Hallazgos**: `config/sanctum.php:33` `expiration => null` → **el token no vence**; no se pasan *abilities* (acceso total); la consulta de rutas del login muestra middleware vacío → **sin `throttle` en la ruta** (puede haber limitación global no verificada). Esto choca con el contrato §12 («sin tokens permanentes»).
- **Endpoints Sanctum (58)**: `me`, asistencia (`hoy/checkin/checkout`), `ots/*` (historial, hoy, show, evidencia, iniciar, completar, incidencia, nota), `compensacion/semana`, `embajador/resumen`, `devices/token`, y ~35 bajo `portal/*` (equipo, progreso, información, liquidaciones, cajas, rutas, proyectos, calidad, penalizaciones, credenciales, documentos+firma, academia/exámenes, custodia, dispositivos, credencial PDF firmada). Cada uno acepta `?colaborador_id=` con el mismo criterio `puedeVer()` (comentario `routes.php:~541`).
- **Formato de respuesta**: JSON libre por controlador; errores como `{message}` con código HTTP. Sin envoltorio común ni paginación uniforme (no revisados uno a uno).
- **Duplicación**: `TalentoMobileEquipoController` (Sanctum) y `PortalTecnicoController` (sesión) exponen casi los mismos datos por dos familias de rutas; ambas delegan en los mismos servicios (`OrdenTrabajoUnifiedService`, `AttendanceService`, `LiquidationService`), así que la lógica no se duplica, pero sí los contratos.
- **Aprovechable para la app futura**: la capa de servicios, `Actor` (ya agnóstico de guard), el gate de evidencia/dBm en servidor, la credencial con URL firmada (10 min), el service worker de baja del portal. A endurecer antes: vencimiento y *abilities* del token, `throttle`, envoltorio de respuestas.

## 8. Tabla funciona / incompleto / duplicado / roto

| Funcionalidad | Estado | Nota |
|---|---|---|
| Identidad colaborador↔usuario | Funciona | 39/39 enlazados, sin duplicados |
| Ficha propia con pestañas | Funciona | Mezcla vistas propias y de administración |
| OT: creación, evidencia, dBm, cierre, validación | Funciona | 206 validadas; sin guard de auto-validación |
| Orden de campo (OT + tasks) | Duplicado | Dos fuentes unificadas por capa de traducción |
| Garantía a 6 meses | Incompleto | Ventana sí; causa, relación con la instalación original y responsable de revisión no |
| Asistencia / mapa en vivo | Funciona | Pocos datos (4 registros) |
| Custodia de material | Funciona (lectura) | Sin vínculo a adeudo/nómina |
| Planta externa (proyectos, rutas, cajas) | No verificado con datos | Código completo, 0 filas |
| Motor de pago semanal (PayWeek, unidades, bonos) | Funciona | 5 liquidaciones en borrador |
| Cierre semanal con aprobación y bloqueo de período | Incompleto | Solo `draft`→`closed`; sin observar/aprobar/reabrir |
| Estado «pagado» / pre-nómina | Falta | No hay confirmación del sistema que paga |
| Idempotencia del ledger | Incompleto | Sin clave única por evento ni estado en el asiento |
| Penalizaciones y apelaciones | Funciona | Revisor distinto del aplicador |
| Reglas semanales de desempeño | Funciona | 2 reglas DEMO activas |
| Metas / objetivos / evaluaciones | Falta | Solo reglas de cuota semanal |
| Comisiones de vendedor en Talento | Parcial | Puente de lectura; la fuente es Vendedores; espejo vacío |
| Calendario / agenda | Falta | Solo `scheduled_at` en OT |
| Notificaciones in-app (leído/no leído) | Falta | Solo recordatorios de documentos y push de dispositivo |
| Niveles / puestos | Residual | Tablas y columnas sin UI |
| Supervisor / equipo | Incompleto | 38/39 sin supervisor; sin cuadrillas |
| API de la app: auth | Riesgo | Sin vencimiento, sin abilities, sin throttle en ruta |
| Portal PWA | Retirado | Queda `sw.js` de baja y rutas `/talento/portal/*` como API |
| Auditoría de cambios | Incompleto | Ledger y OT sin trazado de valor anterior/nuevo (no verificado si hay `activity_log` en cada modelo) |

### Cruce contra el contrato

| Sección | Ya existe | Falta |
|---|---|---|
| §2 Experiencia por tipo | Track por rol en `Actor`; tablero técnico, mostrador y vendedor; «Mi equipo» | Mi Espacio unificado; vista de planta externa semanal por día/hora; panel de supervisor con asignación y reprogramación |
| §3 Talento como centro | Productividad, calidad (dBm, inspecciones), penalizaciones, reglas | Objetivos, evaluaciones, atribución y comisión como motor, estadísticas de garantía, pre-nómina de incentivos |
| §4 Mi Espacio | Pestañas Mi día, Mi dinero, Mis prospectos, Mi equipo, Documentos | Agenda, resultados/metas, incentivos por estado, notificaciones |
| §10 Bonificaciones | Bonos de salud y de proyecto, ledger, reglas con historial (`compensation_rule_history`) | Libro con estados, fuentes adicionales, idempotencia, versionado de reglas por beneficiario |
| §11 Cierre/pre-nómina | Ventana PayWeek, `simular`, liquidación, cierre | Observar/aprobar/bloquear/exportar/reabrir con auditoría; ajustes autorizados |

## Preguntas para Irving

1. **Dueño del «Mi Espacio»**. A) Reusar la ficha con una vista «Mi Espacio» que concentre solo lo propio. B) Mantener la ficha como está y solo añadir pestañas nuevas. *A*: separa lo propio de lo administrativo y evita que alguien con permisos vea datos de otros en la misma pantalla; cuesta reordenar pestañas. *B*: menos trabajo, pero mezcla persiste. **Recomiendo A.**
2. **Estado «pagado»**. A) Agregar estado y quien lo confirma (hoy solo `draft`/`closed`). B) Dejarlo fuera: Talento solo prepara. *A* cumple §10; requiere decidir quién confirma. *B* respeta que el pago es externo. **Recomiendo A**, con confirmación manual.
3. **Endurecimiento de tokens de la app**. A) Vencimiento y *abilities* ahora. B) Esperar a la app nueva. *A* cierra el riesgo hoy pero obliga a re-login en la APK actual. **Recomiendo A**, con plazo largo al principio.
4. **Supervisores**. 38 de 39 sin supervisor. A) Capturarlos antes de construir supervisión. B) Derivarlos del puesto/departamento. **Recomiendo A**, dato mínimo y claro.
5. **Tablas residuales de niveles/puestos**. A) Dejarlas hasta la fase de retiro. B) Retirarlas ya. **Recomiendo A**: coincide con §13 (no borrar sin autorización).
6. **Reglas DEMO activas** (2 de 7). A) Desactivar. B) Dejarlas. **Recomiendo A**, previa revisión suya.
