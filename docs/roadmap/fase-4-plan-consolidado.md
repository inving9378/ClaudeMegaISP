# Plan final consolidado — estrategia de datos vacíos en dev (item #9990478)

Item #9990482 (Fase 4, sub-item de #9990478). **SOLO LECTURA** — este documento no importó, sembró,
reparó ni borró nada; consolida en un solo plan lo ya investigado y verificado por:

- **Fase 1** (#9990479, `docs/estrategia-datos-dev-item-9990478-fase1-inventario.md`) — inventario
  mecánico de las 223 tablas vacías, bucketing por patrón de nombre.
- **Fase 2** (#9990480, `docs/estrategia-datos-dev-item-9990478-fase2-catalogos.md`) — veredicto de
  código de los 13 catálogos, con 6 que rompen formularios activos hoy.
- **Fase 3** (#9990481, `docs/estrategia-datos-dev-item-9990478-fase3-ambiguas.md`) — clasificación
  fina por código de las 73 tablas `ambigua-fase3` (MUERTA / FALTA-IMPORTAR / ACTIVA-SIN-DATOS-EN-DEV).

Este documento **no reinvestiga ninguna tabla** — solo ordena lo que las 3 fases ya determinaron en
un plan único, ejecutable, con orden seguro y con la frontera AUTO/IRVING marcada. Estructura y
criterios según lo que Irving aprobó en el brief del item (todas las opciones recomendadas):

- **Agrupación:** tabla maestra por módulo, con semáforo de riesgo por fila (criterio de q1).
- **Orden de ejecución:** aditivas puras → backfills idempotentes → cambios de código → limpieza,
  cada paso con `down()` y verificación de regresión (criterio de q2).
- **AUTO vs IRVING:** dinero, permisos, prod, destructivo o negocio → `[IRVING]`; aditivo, reversible
  o puramente técnico → `[AUTO]` (criterio de q3).
- **Este doc es la única fuente de verdad** del plan, versionado en git, con changelog al pie
  (criterio de q4).

> Nota de nomenclatura: el item #9990482 nombraba el entregable como
> `docs/estrategia-datos-dev-item-9990478-plan-final.md`, pero la pregunta q4 del propio brief —
> respondida y aprobada por Irving— fijó explícitamente esta ruta
> (`docs/roadmap/fase-4-plan-consolidado.md`). Se siguió la decisión aprobada por ser la más
> específica y la última en el tiempo; queda registrada como decisión de esta vuelta.

---

## Semáforo de riesgo (leyenda)

| Semáforo | Significado |
|---|---|
| 🔴 | Rompe algo funcional **hoy** en dev (formulario, INSERT, o excepción de backend) |
| 🟡 | Rompe en código, pero la ruta/flujo no tiene adopción real (bajo impacto) |
| 🟢 | No rompe nada; el vacío es su estado correcto o esperado |
| ⚪ | Informativo — no aplica semáforo (ya resuelto en item previo, o fuera del alcance de dato) |

## Etiqueta AUTO / IRVING (criterio aplicado)

- **`[IRVING]`** — cualquier fila que toque **dinero** (comisiones, pagos, cobros), **permisos/auth**,
  **producción** (importar un dump real de prod), sea **destructiva** (DROP/retiro de tabla) o sea una
  **decisión de negocio** (qué priorizar, si vale la pena construir algo).
- **`[AUTO]`** — aditivo, reversible y puramente técnico: documentar, ejercitar un flujo ya existente
  en dev con datos de prueba controlados, o correr un comando idempotente ya construido.

En la práctica, dado que **todo import real desde prod cae bajo `[IRVING]`** (toca datos de
producción) y **todo retiro de tabla cae bajo `[IRVING]`** (destructivo), la enorme mayoría de las
acciones concretas de este plan requieren aprobación explícita de Irving antes de ejecutarse — el
circuito no puede autoejecutar imports de prod ni drops por diseño (frontera dura ya vigente, ver
`CLAUDE.md` §"CIRCUITO DE MEJORA CONTINUA"). Lo que **sí** cae en `[AUTO]` es "ejercitar en dev"
(correr un comando/flujo ya construido con datos sintéticos) y la documentación misma.

## Orden de ejecución seguro (aplica a cualquier cambio de código/esquema derivado de este plan)

1. **Aditivas puras** — nuevas columnas nullable, nuevas tablas, índices (ninguna prevista en este
   plan salvo que Irving decida reparar `commissions`/`distribution_commission_sales*`, ver Onda 4).
2. **Backfills idempotentes** — imports vía SmartImport (modo SMART, upsert por identidad) o scripts
   de backfill re-ejecutables sin duplicar filas.
3. **Cambios de código** — descomentar/activar el listener de comisiones, agregar consumidores, etc.
4. **Limpieza** — retiro de tablas MUERTA, solo al final y solo con `down()` reversible cuando sea
   posible (un `DROP TABLE` no es reversible sin respaldo previo — exige respaldo + confirmación
   explícita de Irving, no basta con la etiqueta `[IRVING]` genérica).

Cada paso lleva verificación de regresión antes de avanzar al siguiente (mismo patrón que usa el
propio pipeline de deploy con `migrate_dryrun`, `CLAUDE.md` §"Deploy remoto").

---

## Riesgos conocidos de SmartImport (citados explícitamente, spec del item)

Cualquier import de las ondas 1 y 3 (abajo) pasa por `SmartImportExport\Services\SmartImportService`
y hereda estos riesgos ya documentados en `CLAUDE.md` §"Importación de BD de producción" y
§"SmartImport — consistencia del merge inteligente":

1. **`bundles.title` NOT NULL → cascada de fallos.** Si el dump de prod trae bundles con `title`
   nulo, la fila se rechaza → `client_bundle_services` falla (`Attempt to read property "id" on
   null`) → `client_custom_services` falla por FK (1452). Ninguna tabla de este plan depende
   directamente de `bundles`, pero si el import se corre en una sola pasada junto con otras tablas
   del dump completo, este riesgo sigue vigente y requiere el `ALTER TABLE bundles MODIFY COLUMN
   title VARCHAR(255) NULL` documentado, o filtrar el import a solo las tablas de este plan.
2. **Modo SMART = merge por identidad (PK) vs. llave de negocio.** Tablas identidad-por-PK (la
   mayoría de las de este plan: `brands`, `box_types`, `sites`, `system_users`, etc.) mergean por
   `id` y lo preservan; tablas de catálogo marcadas `identity_priority=>override` en
   `config/smart_import.php` mergean por llave de negocio. El fix 1062 (descarte de PK del dump
   cuando el merge es por llave de negocio) ya está aplicado — no requiere acción nueva, solo
   verificar que ninguna tabla de este plan necesite agregarse a esa lista de override si su
   naturaleza es catálogo puro (ninguna de las candidatas aquí lo es; ya se filtraron en Fase 2/3).
3. **IDs divergentes dev/prod.** Ninguna tabla de este plan debe resolverse por `id` copiado a mano
   entre entornos — SmartImport en modo SMART preserva el `id` del dump tal cual (por diseño, para
   catálogos identidad-por-PK), así que el riesgo real es **no** copiar manualmente un id fuera del
   flujo de import (p. ej. para "arreglar" una FK a mano). Si alguna fila de este plan requiere
   resolución manual (no automatizable por SmartImport), debe resolverse siempre por una clave de
   negocio (nombre, login, referencia), nunca por id.

---

## PLAN MAESTRO — por módulo, con orden y etiqueta

### Onda 1 — Catálogos de Mapas que rompen HOY `[IRVING]` (import de prod)

Módulo **Mapas** (activo). Orden estricto por dependencia FK (`brand_id` NOT NULL en los 4
catálogos hijos):

| # | Tabla | Semáforo | Motivo (Fase 2) | Etiqueta |
|---|---|---|---|---|
| 1 | `brands` | 🔴 | Raíz de la cascada — 5 catálogos hijos con `<select required>` vacío | `[IRVING]` — import de prod |
| 2 | `box_types` | 🔴 | `<select required>` bloquea alta de Caja/NAP | `[IRVING]` — import de prod, depende de `brands` |
| 3 | `trenche_types` | 🔴 | FK NOT NULL revienta INSERT de zanja | `[IRVING]` — import de prod, depende de `brands` |
| 4 | `active_equipment_types` | 🔴 | Crash de backend (`$type->ethernet_ports` sin guard) | `[IRVING]` — import de prod, depende de `brands` |
| 5 | `passive_equipment_types` | 🔴 | Crash de backend (`$type->ports` sin guard) | `[IRVING]` — import de prod, depende de `brands` |
| 6 | `colors` | 🟡 | Rompe "Trazar ruta de fibra", pero ruta sin adopción real (0 filas históricas) | `[IRVING]` — import opcional, menor prioridad, misma pasada si se decide |

Sin PII ni dinero — bajo riesgo relativo, pero sigue siendo `[IRVING]` por ser import real de prod
(frontera dura de datos de producción, no negociable por el circuito).

### Onda 2 — Infraestructura de red física sin mapear `[IRVING]` (import de prod, tras Onda 1)

Módulo **Mapas** — bucket `RED SIN MAPEAR` de Fase 1 (21 tablas), candidatas naturales de import
porque representan inventario físico real de la red de fibra (equipos, puertos, postes, cajas,
splitters). Mismo criterio de bajo-riesgo (sin PII/dinero) que Onda 1, van después porque varias
tienen FK hacia los catálogos de Onda 1 (`brand_id`, `*_type_id`):

`fibers`, `points`, `point_accessories`, `poles`, `pole_accessories`, `ports`, `racks`, `boxes`,
`box_inputs`, `buffers`, `cut_fibers`, `cuts_observations`, `modems`, `passive_equipments`,
`active_equipments`, `active_equipment_peripherals`, `splitters`, `transceivers`, `trays`,
`trenches`, `tubes` — más las de Fase 3 en la misma familia: `map_links`, `map_routes`, `sites`,
`cards`, `equipment_links` (todas `FALTA-IMPORTAR` con consumidor real confirmado en código).

Etiqueta: `[IRVING]` — import de prod, sin dato de dinero/PII pero sigue siendo dato real de
producción.

### Onda 3 — Módulos de negocio con `FALTA-IMPORTAR` confirmado `[IRVING]` (import de prod)

De la Fase 3 (34 tablas con consumidor real confirmado, agrupadas por familia funcional):

| Módulo | Tablas | Etiqueta |
|---|---|---|
| **Finanzas/CFDI/Facturación** | `billing_addresses`, `client_fiscal_data`, `client_payment_promises`, `client_payment_services`, `client_serviceables`, `payment_accounts`, `payment_promises`, `payments_details` | `[IRVING]` — toca dinero/facturación |
| **CFDI en construcción (caso especial)** | `client_cfdi_invoices` — stub `NullTimbradoService` ya listo, falta decidir proveedor PAC e implementar adaptador antes de que tenga sentido importar nada | `[IRVING]` — decisión de negocio (elegir PAC), no es solo import |
| **CRM** | `deal_crms`, `quote_crms` | `[IRVING]` — datos de clientes/negocio |
| **Vendedores** | `payments_sellers`, `credential_images`, `discounts`, `discounts_sales` | `[IRVING]` — toca dinero (comisiones/descuentos) |
| **MikroTik** | `mikrotik_client_hostpot_users` | `[IRVING]` — datos de clientes en producción |
| **Planes** | `change_plan_voz_clients`, `plan_custom_client`, `client_contratable_subscriptions` | `[IRVING]` — datos de clientes |
| **Sistema** | `system_users`, `tables`, `user_column_dt_expand`, `work_flows` | `[IRVING]` — import de prod (bajo riesgo, sin dinero, pero sigue siendo dato de prod) |
| **Evaluador Empresarial** | `evaluaciones_empresariales` | `[IRVING]` — datos de clientes empresariales |

### Onda 4 — Reparación de integridad (código + backfill) `[IRVING]` — decisión de negocio/dinero

No es un import: son cálculos de dinero que se diseñaron para persistirse y terminaron implementados
de otra forma (listener con cuerpo comentado / cálculo en memoria).

| Tabla | Situación (Fase 1/3) | Acción propuesta | Orden interno (criterio q2) |
|---|---|---|---|
| `commissions` (+ `commissions_details`, 566,780 filas huérfanas reales) | Listener `CalculateClientCommission`/`CalculateProspectCommission` tiene el cuerpo comentado. Ya escalado por #9990471: no se puede afirmar si es "falta importar de prod" o "descontinuada tras jul-2024" sin que Irving confirme el estado de `commissions` en prod | **Pendiente de que Irving decida** (import vs. reparar el listener vs. descontinuar formalmente) | 1) confirmar estado en prod (dato) → 2) si se repara: activar el listener (cambio de código) → 3) backfill idempotente de lo histórico si aplica → 4) verificación de regresión sobre `ComissionController::getCommissionsBySeller` |
| `distribution_commission_sales` / `distribution_commission_sales_amount` | Modelos existen, cero consumidor real; el cálculo real vigente (`DistributorsCommissionPayment`) vive **en memoria**, no persiste aquí | **`[IRVING]`** — decidir si vale la pena persistir el cálculo (mejora) o formalizar como MUERTA (Onda 6) | Si se decide construir: 1) aditivo (ninguno nuevo) → 2) cambio de código (`DistributorsCommissionPayment` escribe a la tabla) → 3) backfill opcional → 4) verificación |

### Onda 5 — Ejercitar en dev (sin import, sin frontera dura) `[AUTO]`

Bucket `ACTIVA-SIN-DATOS-EN-DEV` de Fase 3 (25-28 tablas) + los módulos completos del bucket
`NUNCA USADA EN DEV` de Fase 1 (80 tablas, por prefijo `ia_`/`whatsapp_`/`marketing_`/`parental_`/
`cobranza_`/`talento_`). Ninguna requiere un dump de prod — se llenan corriendo el flujo/comando ya
construido con datos de prueba:

- **Circuito/Auditoría interna:** `auditoria_minero_cursores`, `auditoria_senales` → correr
  `auditoria:minar-bitacora`. `jarvis_conversaciones`/`jarvis_mensajes` → usar el chat de JARVIS.
  `migration_logs`, `vigilante_discrepancias`, `release_snapshots`.
- **Flotas:** `fleet_device_events` (requiere GPS real/simulado), `fleet_document_ocr_runs` (correr
  OCR sobre un documento), `fleet_driver_push_tokens`.
- **Domiciliación/Payments:** `client_recurring_cards`, `enrollment_links`,
  `recurring_charge_attempts`, `conciliation_settings`, `reconciliation_tickets`, `payment_clabes`,
  `payment_instruments`, `payment_receipts`, `payment_webhooks_log`, `reported_payments` — todas con
  flujos ya verificados end-to-end en `CLAUDE.md` §Payments, solo faltan corridas que dejen filas
  persistentes en esta instancia de dev.
- **Notificaciones/Push:** `push_tokens`, `general_notifications`, `fleet_driver_push_tokens`.
- **MegaFamilia:** `task_closures`, `user_relationships` — dar de alta relación padre-hijo y cerrar
  una tarea desde ese flujo.
- **Portal Cliente:** `portal_profile_change_log` — editar un perfil desde el portal.
- **Embajadores:** `referral_share_logs` — compartir un link de referido.
- **Seguridad:** `role_permission_scopes` (item #865) — configurar un rol con scope "propio".
- **RADIUS:** `radius_sessions` — integración externa opcional (`DB_RADIUS_*`), no importable desde
  un dump MySQL.
- **Módulos completos "nunca usados en dev" (80 tablas por prefijo):** IA (`ia_*`), WhatsApp
  (`whatsapp_*`), Marketing (`marketing_*`), MegaFamilia/parental (`parental_*`,
  `megafamilia_settings`), CobranzaBlaster (`cobranza_*`, `voip_configuracion`), Talento
  (`talento_*`). **Nota importante para Talento:** por la auditoría de #907990353 y la sección
  "ARRANQUE LIMPIO" de `CLAUDE.md`, **prod también tiene estas tablas en 0 filas** (el sistema de
  compensación nunca se usó en producción) — para Talento no hay nada que importar; la única vía es
  ejercitar el flujo real en dev/prod conforme opere el negocio.

Todas `[AUTO]` porque son datos sintéticos/de prueba generados dentro de dev, sin tocar prod ni
dinero real ni permisos — el circuito puede ejecutarlas sin esperar aprobación previa, quedando el
resultado disponible para que Irving lo revise después (misma regla de oro que gobierna al circuito).

### Onda 6 — Retiro de tablas MUERTA `[IRVING]` (destructivo, requiere respaldo)

Del bucket MUERTA de Fase 3 (11 tablas) + `demo_items` (addon de ejemplo, no es dato de negocio):

| Tabla | Reemplazada por / motivo |
|---|---|
| `setting_table` | Duplicado/typo de `setting_tables` (plural), que sí es real |
| `password_resets` | Nunca adoptada; el sistema usa `PasswordService` (base64→bcrypt) |
| `manual_pages` | Diseño anterior abandonado del addon Manual; sucesor real: `manual_sections` |
| `manual_screenshots` | Mismo caso, sin sucesor 1:1 |
| `client_invoice_cfdi` | Diseño antiguo del dominio CFDI; sucesor real: `client_cfdi_invoices` |
| `invoice_serviceables` | Pivot paralelo sin usar; el real es `client_serviceables` |
| `distribution_commission_sales` / `_amount` | Ver Onda 4 (decisión pendiente: reparar o formalizar como muerta) |
| `mikrotik_client_hostpot_radius` | Lado RADIUS del hotspot nunca se conectó a código (hermana `_users` sí está activa) |
| `sales`, `prospects` | Ya resueltas por #9990471 — descontinuadas, UI real usa `client_main_information`/CRM |

**Ninguna se borra en este item.** El retiro (`DROP TABLE`) es irreversible sin respaldo previo — cae
bajo `[IRVING]` con requisito adicional de respaldo antes de ejecutar (no basta la etiqueta genérica;
es la única acción de todo el plan que además exige un paso de seguridad extra: `backup_db:process`
o dump puntual de esas 11 tablas antes del `DROP`).

### Sin acción — dejar vacía a propósito `[AUTO]` (documentar, no ejecutar nada)

- **EN CONSTRUCCIÓN** (36 tablas, prefijo `mapared_`/`ipv6_`/`dc_`) — features activamente en
  desarrollo; importar/sembrar sería prematuro y generaría datos falsos sobre un esquema que aún
  puede cambiar.
- **Catálogos sin acción** (Fase 2, 7 tablas): `contratable_packages`, `contratable_services`,
  `ipv6_policies`, `positions` (autopoblada por diseño), `project_types`, `social_providers`,
  `tube_types` — vacío es su estado correcto hoy.
- **`demo_items`** — tabla de ejemplo del addon Demo, no representa dato de negocio real; no aplica
  ninguna de las 5 acciones del spec.
- **`commissions` (HUÉRFANA)** — mientras Irving no confirme el estado en prod (Onda 4), se deja tal
  cual; no se ejecuta nada sobre ella todavía.

---

## Resumen ejecutivo por acción (para la aprobación de Irving)

| Acción | # tablas | Ondas | Etiqueta dominante |
|---|---:|---|---|
| Importar de prod vía SmartImport | 61 (6 Onda1 + 21 Onda2 + 34 Onda3, con solape de 5 entre Onda2/Fase3) | 1, 2, 3 | `[IRVING]` |
| Reparar integridad (código + backfill) | 3 (`commissions` + 2 `distribution_commission_sales*`) | 4 | `[IRVING]` |
| Ejercitar en dev (sin import) | ~105 (25-28 Fase3 + 80 módulos completos Fase1) | 5 | `[AUTO]` |
| Retirar (DROP, con respaldo previo) | 11 | 6 | `[IRVING]` + respaldo |
| Dejar vacía a propósito / sin acción | ~44 (36 EN CONSTRUCCIÓN + 7 catálogos + `demo_items`) | — | `[AUTO]` (solo documentar) |

(Los totales no suman exactamente 223 por solapes de conteo entre fases, ya señalados como
inconsistencias menores de redacción en la propia Fase 3 — no se corrigen aquí porque no cambian
ninguna clasificación ni acción recomendada, solo la aritmética de un resumen.)

## Cierre del paraguas #9990478

Con esta Fase 4, las 4 fases del item padre #9990478 quedan completas: Fase 1 (inventario), Fase 2
(catálogos prioritarios), Fase 3 (ambiguas) y Fase 4 (este plan consolidado). El paraguas #9990478
puede darse por completado — este documento es la última pieza que le faltaba. La ejecución
selectiva de cada onda (imports reales, reparaciones, retiros) queda como trabajo **nuevo y
separado**, a iniciarse solo cuando Irving apruebe onda por onda desde este plan.

## Changelog

- **2026-09-07** — Creación del documento (item #9990482, Fase 4). Consolida Fases 1-3 (#9990479,
  #9990480, #9990481) en un plan único por módulo con orden seguro y etiqueta AUTO/IRVING.
