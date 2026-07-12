# Módulo Talento

> Control de personal unificado: identidad, órdenes de trabajo, compensación, ledger de pagos y roadmap. `app/Modules/Addons/Talento/` · slug `addon-talento` · addon activo. Incluye backend web (admin), API stateless para la app móvil "Talento Equipo" (React Native + Sanctum) y el Portal Técnico Web (PWA).

## 0. En simple
Es el sistema donde vive todo lo relacionado con el personal de campo (técnicos, vendedores, instaladores): sus órdenes de trabajo, sus asistencias, cuánto ganan por lo que hacen, sus préstamos y adelantos, sus cursos y certificaciones, y las alertas cuando algo no cumple el estándar de calidad.

## 1. Qué es
Módulo de gestión integral de colaboradores de campo: identidad y perfil (`TalentoColaborador`), órdenes de trabajo (OTs) con evidencia fotográfica anti-fraude, asistencia, un motor de compensación semanal con ledger contable, préstamos/fondos de ahorro forzoso, finiquitos, penalizaciones con apelación, academia (cursos/exámenes/certificaciones), niveles/escalafón, inspecciones de calidad de caja con IA, proyectos de planta externa con detección de desvíos de ruta/corredor, y dos superficies de consumo para el colaborador: la app móvil nativa y el Portal Técnico Web.

## 2. Para qué sirve
A RH/supervisión le sirve para administrar el ciclo de vida completo de un colaborador de campo: alta, asignación de OTs, validación de su trabajo (evidencia + lectura dBm + firma de cliente), cálculo automático de su pago semanal (`LiquidationService`) con bonos de salud de red y de proyecto, control de custodia de material (vía Inventario), penalizaciones con evidencia y apelación, préstamos/fondos con descuento de nómina, y trazabilidad completa en un ledger de asientos (`TalentoLedgerEntry`). Al colaborador le sirve para ver y ejecutar su trabajo diario desde el celular (app "Talento Equipo") o el navegador (Portal de Colaborador / Portal Técnico), y consultar su "Mi dinero" (cuenta, desglose, fondo, préstamos) sin acceso al panel admin.

## 3. Cómo funciona
- **Identidad:** `TalentoColaborador` (`talento_colaboradores`) liga un `User` existente con `type`/`department`/`supervisor_id` (jerarquía)/`level_id`/`status`. `TalentoColaboradorObserver` auto-asigna el permiso base `portal.colaborador` de forma **aditiva** (nunca `sync*`) según el estado del colaborador. `Support/Actor.php` es el punto único de "¿quién es este colaborador?" para el Portal — resuelve una vez por request (agnóstico de guard web/Sanctum), cada faceta (`talento()`, `seller()`, embajador…) memoizada.
- **Órdenes de trabajo (OTs):** dos fuentes se unifican en `OrdenTrabajoUnifiedService` — `TalentoWorkOrder` (`talento_work_orders`, con `TalentoWorkOrderType` que define puntos/`billable`/`inicia_garantia`) y `Task` (tipo=campo, mapeado por estado ToDo/InProgress/Done). El ciclo es iniciar → subir evidencia (`TalentoWorkOrderMedia`, gateada por matriz `talento_ot_type_evidence_requirements`) → lectura dBm con umbrales (`talento_dbm_thresholds`, bloquea cierre en sobrepotencia/baja señal) → firma (`TalentoWorkOrderSignature`) → completar/validar. `TalentoWorkOrderActivation` registra activaciones; `TalentoWarrantyController`/`WarrantyWindowService` clasifican garantía sobre reincidencias.
- **Compensación:** `TalentoCompensationRule` define reglas por colaborador/nivel; `LiquidationService::countBillableUnits()` es el **punto único de verdad** de "cuántas unidades cuentan para el pago" (OTs + tasks de campo + puntos de proyecto externo), compartido entre el cálculo real (`calculate()`) y el desglose que ve el colaborador (`breakdown()`) — nunca pueden divergir. `HealthBonusService`/`ProjectBonusService` añaden bonos. La ventana de pago semanal la resuelve `Support/PayWeek.php` (único punto de verdad: sábado 18:00 → sábado 18:00, con modo legacy anterior a un cutover configurable). `TalentoLiquidation` cierra la semana; cada movimiento queda en `TalentoLedgerEntry`.
- **Dinero adicional:** `TalentoFund` (ahorro forzoso con autorización de descuento), `TalentoLoan` (préstamos/adelantos), `TalentoSettlement`/`TalentoSettlementItem` (finiquitos).
- **Calidad y disciplina:** `TalentoCajaInspection` + `CajaInspectionService` (inspección de caja con IA sobre `TalentoConstructionStandard`); `TalentoPenalty`/`TalentoPenaltyType` con evidencia fotográfica y `TalentoPenaltyAppeal` (apelación resuelta por un revisor distinto del aplicador).
- **Formación:** `TalentoCourse`/`TalentoCourseMaterial`/`TalentoExam`/`TalentoExamQuestion`/`TalentoExamAttempt` (academia) y `TalentoCertification`/`TalentoPracticalEvaluation` (certificación práctica), que alimentan `TalentoLevel`/`TalentoLevelAssignment` (niveles y elegibilidad) y `TalentoEscalafonController` (escalafón por puntaje compuesto, `CompositeScoreService`).
- **Proyectos de planta externa:** `TalentoProject`/`TalentoProjectActivity`/`TalentoProjectActivityReport` con `ProjectActivityService`; `CorridorDeviationService`/`RouteDeviationService` detectan desvíos de ruta/corredor sobre `TalentoRoute`/`TalentoRouteStop`/`TalentoLocationPing`.
- **Credenciales y dispositivos:** `TalentoCredential` con alertas de vencimiento (`talento:check-credential-expirations`, cron); `TalentoDevice` vincula/revoca el dispositivo móvil del colaborador (aprobación requerida).
- **Sincronización:** `talento:sync-colaboradores` (comando) resuelve backfills de permisos por `login_user` (no por id, ids divergen entre entornos).
- **Rutas:** admin/SPA bajo `/talento/*` (`web`+`auth`+`check_route_permission`); app móvil bajo `/talento/api/*` (stateless, `auth:sanctum`, login público sin token); Portal Técnico Web bajo `/talento/portal/*` (`web`+`auth`+`can:portal.colaborador`, shell propio fuera del sidebar admin, PWA con manifest/service worker públicos).
- **Frontend:** SPA Vue 3 (Quasar) para el admin, ~48 referencias de componentes `talento-*` registradas en `resources/js/app.js`; la app móvil es un proyecto React Native separado (`TalentoEquipo`, fuera de este repo web) que consume la misma API Sanctum.

## 4. Qué EXPONE / qué CONSUME
**Expone**
- **Rutas web admin** bajo `/talento/*`: colaboradores, custodia, dispositivos, roadmap, órdenes, compensación, liquidaciones, asistencia, mapa en vivo, sitios, campo, cajas, rutas, proyectos, calidad, penalizaciones, credenciales, finiquito, academia, niveles, dashboard, escalafón, embajadores-colabs, config de evidencias — más sus respectivas API JSON bajo `/talento/api/*` (mismo prefijo, protegido por sesión web).
- **API stateless Sanctum** (`/talento/api/auth/login`, `/me`, `/asistencia/*`, `/ots/*`, `/compensacion/semana`, `/devices/token`) para la app móvil "Talento Equipo"; rutas públicas de arranque (`health`, `app/latest`, `app/branding`) sin auth.
- **Portal Técnico Web (PWA)** bajo `/talento/portal/*`: shell, preferencias de tema, asistencia, OTs del día/detalle, "Mi dinero" (cuenta/desglose/fondo/préstamos, solo lectura), "Mi material" (custodia, solo lectura), subida de evidencia con watermark server-side, firma, aceptar/activar OT.
- **41 permisos** `talento.*` + `portal.colaborador` (base de acceso al Portal de Colaborador).
- **Comandos artisan:** `talento:check-credential-expirations` (cron), `talento:sync-colaboradores`.
- Menú "Talento" en el sidebar admin (bloque `module.json` + registro en `sidebar.blade.php`).

**Consume**
- **Módulo Inventario** — `InventoryService` resuelve la custodia de material del colaborador (`inventory_item_stocks` con `user_id`, no hay tabla propia de custodia en Talento).
- **Módulo IA** — `FieldIaValidationService` y `CajaInspectionService` usan `ClaudeApiClient` (validación de evidencia de campo y análisis de inspección de caja).
- **Módulo Embajadores / Seller** — `TalentoEmbajadoresController` y `Support/Actor.php` hacen cross-link solo-lectura colaborador↔embajador/vendedor (`Seller` model).
- **Sistema de permisos Spatie** — todo el gating (`talento.*`, `portal.colaborador`) vive en las tablas estándar de roles/permisos; los cambios son siempre aditivos (`givePermissionTo`), nunca `syncRoles`/`syncPermissions`.
- **Guard `web` (Medussa)** para el admin y el Portal Técnico; guard `sanctum` (Bearer token) para la app móvil — ambos resuelven el mismo `Actor` a partir de `auth()->user()`.

---
_Doc viva generada por el Circuito CC (pool de documentación del sistema). Read-only sobre el código, aditiva. Sin secretos._
