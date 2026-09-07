# Análisis read-only: migrar comisiones/pagos de Vendedores al motor de Talento

**Item roadmap:** #9990453 (insumo para la decisión #123 y su ejecución, nivel C en bandeja de Irving)
**Naturaleza:** SOLO LECTURA. No se modificó ningún código de dinero, permisos ni arquitectura en este
item. Este documento es el insumo para que Irving decida si/cómo/cuándo ejecutar la migración.
**Fecha:** 2026-09-07

---

## 0. Hallazgo previo — el "puente de visibilidad" que #123 dio por resuelto está roto

El item #123 original (ver `docs/talento-vendedores-bridge-item-123-verificacion.md`) documentó que
ya existía un puente de **solo lectura** entre Talento y Vendedores, vía
`TalentoEmbajadoresController::sellerData()` (`GET /talento/api/colaboradores/{id}/seller-data`,
`app/Modules/Addons/Talento/routes.php:349`). Verificado en este análisis con una consulta real
(`SELECT`, sin escribir nada): **el endpoint devuelve 500 siempre**, desde que se creó (commit
`52c50009`, Fase 8/9):

- Consulta `DB::table('sellers')->first(['id','name','commission_percentage'])`
  (`app/Modules/Addons/Talento/Controllers/TalentoEmbajadoresController.php:88-90`), pero la tabla
  real `sellers` **no tiene** columnas `name` ni `commission_percentage` (columnas reales: `id,
  user_id, status_id, type_id, balance, range` — el nombre vive en `users` vía `user_id`; no existe
  ningún campo de % de comisión en el esquema).
- Consulta `DB::table('transaction_sellers')` (singular, línea 100) pero la tabla real es
  `transactions_sellers` (plural, `app/Models/TransactionSeller.php:12`), y tampoco tiene columna
  `commission_amount`.

El frontend enmascara el error silenciosamente (`TalentoEmbajadores.vue:187,203`,
`.catch(() => null)`) — nunca se ve un 500, solo el badge de vendedor no aparece. Registrado como
sub-item **#9990464** para arreglarlo aparte (es un bug independiente de la decisión de migración).

**Lo que sí funciona:** `Support/Actor.php::seller()` (`app/Modules/Addons/Talento/Support/Actor.php:58-65`)
resuelve el vendedor vía Eloquent (`Seller::where('user_id', ...)->first()`, sin especificar
columnas) — alimenta el sidebar del Portal de Colaborador y sí está vivo.

---

## 1. Qué hace hoy cada pieza de Vendedores

### 1.1 El motor de cálculo real NO es el que sugiere el nombre del item

`CommissionRule`/`TransactionSeller`/`PaymentSeller`/`Commission` son los nombres "obvios", pero
**3 de esos 4 modelos están dormidos** (0-2 filas en la BD actual). El triángulo que **sí mueve el
dinero contable hoy** es:

- **`CalculateBalanceSellerService`** (`app/Services/CalculateBalanceSellerService.php`) — pipeline
  (`Illuminate\Pipeline\Pipeline`) de "Pipes" condicionales según `selected_fields` de la regla:
  `FixedSalaryPayment`, `SalesCommissionPayment` (% o $ fijo por venta, IVA opcional),
  `AdditionalSalesCommissionPayment`, `DistributorsCommissionPayment` (escalonado por tipo de
  contrato), `MonthlyBonusPayment`, `DiscountPayment`.
- **`PaymentByRule` + `PaymentByRuleDetails`** (tabla real `payment_by_rule` /
  `payment_by_rule_commissions`) — **72 pagos, $125,850.00 MXN acumulados**. Se crea desde
  `PaymentSellerController::store()`
  (`app/Modules/Addons/Vendedores/Controllers/Vendors/Billing/PaymentSellerController.php:320-390`),
  disparado **manualmente** por un admin desde la UI Vue. Tiene auditoría
  (`Spatie\Activitylog`/`LogsActivity`).
- **`HistorySellerRule`** (`history_sellers_rules`, 39 filas) — snapshot versionado (`data` JSON) de
  la regla vigente en el momento en que se le asignó a un vendedor, para que cambios futuros a
  `CommissionRule` no alteren pagos ya calculados.

### 1.2 Piezas nombradas en el item, y su estado real

| Pieza | Tabla | Estado | Rol real |
|---|---|---|---|
| `CommissionRule` | `commissions_rules` | Activa (config) | Define % / $ / condiciones; **no** define rangos de monto ni producto directamente. `RangeSale` (sector A/B/C) existe como catálogo pero **ningún Pipe lo referencia** — huérfano. |
| `RangeSaleController` | `ranges_of_sales_sectors` | Activo pero solo CRUD | No calcula nada; es catálogo de configuración desconectado del cálculo. |
| `TransactionSeller` | `transactions_sellers` | **Dormido (2 filas)** | Ledger de *saldo acumulado* (previous/new balance), no de comisiones individuales. Se escribe al registrar/eliminar un pago. |
| `PaymentSeller` | `payments_sellers` | **Dormido (0 filas)** | Flujo de pago paralelo con `belongsTo(Commission)`, código operativo pero sin uso real. |
| `Commission` | `commissions` | **Dormido (0 filas)** | Ídem — parte del mismo flujo dormido. |
| `PaymentClient` | — | No dispara comisiones | El cobro al cliente y el pago de comisión al vendedor son **procesos desacoplados**; no hay evento/observer que uno dispare al otro. `PaymentClientController` solo expone endpoints de *consulta* (`getRuleDataSeller`, `getMontlyCommissionsBySeller`) para mostrar cuánto se le debe a un vendedor. |

### 1.3 Disparo — sin cron

No hay ningún job/comando programado que calcule o pague comisiones de Vendedores (revisado
`app/Console/Kernel.php` completo + `app/Console/Commands/`). Todo el flujo es **manual**, disparado
por un admin desde la UI Vue de Vendedores.

---

## 2. Cómo funciona el motor de Talento y dónde encajaría

### 2.1 `TalentoLedgerEntry` — ya es un mecanismo de extensión genérico

`talento_ledger_entries` (`app/Modules/Addons/Talento/Models/TalentoLedgerEntry.php`) es un ledger
**inmutable** (nunca se actualiza/borra; se inserta una contra-entrada). Columnas clave: `colaborador_id,
type (credit|debit), concept, amount, reference_type/reference_id (polimórfico), period_start,
period_end`.

**`concept` es un `string(60)` libre, no un enum.** `LiquidationService::calculate()`
(`app/Modules/Addons/Talento/Services/LiquidationService.php:96-108`) trata **cualquier**
`TalentoLedgerEntry` del período cuyo `concept` no sea `salary_base`/`overproduction` como ajuste
genérico (`otherCredits`/`otherDebits`) que se suma/resta automáticamente al `grossPay`. Ya hay
precedente real de este patrón: fondo de ahorro (`fund_contribution`) y pago de préstamo
(`loan_repayment`) se insertan así vía `FundService`/`LoanService`, y el propio comentario de la
migración ya anticipa `'embajador'` como concepto futuro.

**Consecuencia directa:** una entrada `concept = 'sales_commission'`, `type = 'credit'`, con
`period_start`/`period_end` alineados a la ventana de pago del colaborador, **ya sería recogida
automáticamente** por la próxima liquidación — sin tocar el motor de `LiquidationService`.

### 2.2 `countBillableUnits()` — punto único de verdad de unidades (no de dinero)

`LiquidationService::countBillableUnits()` (líneas 227-266) cuenta OTs + tasks de campo validadas +
puntos de proyecto externo. Lo comparten `calculate()` (pago real) y `breakdown()` (lo que ve el
colaborador en el portal), así nunca divergen. **Comisiones de venta no encajarían aquí** — no son
"unidades a cuota semanal", son montos calculados por reglas propias. El punto de integración
correcto es el ledger genérico (2.1), no este contador.

### 2.3 `PayWeek` — vigente, sin cambios

Confirmado sin cambios respecto a lo documentado en CLAUDE.md: régimen `new` (Sáb 18:00 → Sáb 18:00,
7 días exactos) desde el cutover `2026-07-11 18:00`, régimen `legacy` replicado bit-exacto para
semanas pre-cutover. Único punto de verdad de la ventana de pago semanal.

### 2.4 `TalentoColaborador` ↔ `User` ↔ `Seller` — mapeo implícito, sin FK

`TalentoColaborador` solo tiene `belongsTo(User::class)`. **No hay FK directa a `sellers`.** El
mapeo es implícito por `user_id` compartido (así lo hacen `Actor.php::seller()` y el roto
`sellerData()`). Por diseño explícito de `Actor.php` (comentario propio), **todo colaborador "nace"
vendedor**; el esquema vendedor/embajador es excluyente, pero hoy no existe un campo de "esquema
activo" — siempre se resuelve `seller()` por default.

**Dato de solapamiento real (verificado):** de **28** `talento_colaboradores`, **23** (82%) tienen un
`user_id` que también existe en `sellers`. La migración no sería un caso raro — la mayoría de
colaboradores de Talento ya son "vendedores" en el esquema legacy.

---

## 3. Mapeo concepto por concepto

| Concepto en Vendedores | Equivalente en Talento | Notas |
|---|---|---|
| `CalculateBalanceSellerService` (pipeline de Pipes) | **Sin equivalente — no hace falta reescribirlo** | El cálculo condicional (%, $ fijo, IVA, escalonado, bono mensual) puede seguir viviendo tal cual; solo cambia el *destino* de la escritura. |
| Resultado de `PaymentByRule`/`PaymentByRuleDetails` (monto final calculado) | `TalentoLedgerEntry` con `concept='sales_commission'`, `type='credit'` | Mecanismo YA genérico (2.1) — no requiere cambios al motor de liquidación. |
| `HistorySellerRule` (snapshot versionado de regla) | `TalentoCompensationRuleHistory` | **Mismo patrón estructural exacto** (ambos: `data` JSON congelado + fecha de asignación). Paralelo directo, facilita la migración conceptual. |
| Semana de cálculo de `CalculateBalanceSellerService` (Domingo → Sábado, `getValidWeeks/getValidMonths`) | `PayWeek` (Sáb 18:00 → Sáb 18:00) | **Sin equivalente directo — desalineadas.** Requiere resolución explícita antes de escribir `period_start`/`period_end` en el ledger (ver riesgo 4.1). |
| `sellers.balance` (saldo acumulado del vendedor) | Suma de `TalentoLedgerEntry` del colaborador | Talento no tiene un campo "saldo" propio — se deriva sumando el ledger. Requeriría reconciliar el saldo histórico de `sellers.balance` contra la suma del ledger migrado. |
| `transactions_sellers` (ledger de saldo) | `TalentoLedgerEntry` (ledger inmutable) | Mismo *rol* (ledger), pero `transactions_sellers` está dormido (2 filas) — no es la fuente real de verdad a migrar. |
| `RangeSale`/`ranges_of_sales_sectors` (catálogo sector/rango) | **Sin equivalente — no lo necesita** | Ya está desconectado del cálculo real en Vendedores (ningún Pipe lo usa); no aporta migrar algo que ya es huérfano. |
| `payments_sellers`/`commissions` (flujo dormido) | — | **No migrar** — 0 filas, código muerto en la práctica. Candidato a limpieza aparte (fuera de alcance de este análisis). |

---

## 4. Riesgos y plan por fases

### 4.1 Riesgos identificados

1. **Desalineación de calendarios.** La "semana" de `CalculateBalanceSellerService` (Domingo→Sábado)
   y la de `PayWeek` (Sáb 18:00→Sáb 18:00) no coinciden. Escribir en el ledger sin resolver esto
   produce comisiones atribuidas a la semana de pago equivocada. **Debe resolverse explícitamente
   antes de escribir el primer registro real.**
2. **UI Vue atada directamente a las tablas viejas.** Las pantallas de pagos/comisiones/transacciones
   de vendedor (`resources/js/components/module/vendors/...`) leen `payment_by_rule`,
   `payment_by_rule_commissions`, `transactions_sellers`, `commissions_rules` vía los controllers de
   `Vendedores/Controllers/Vendors/Billing/`. Migrar el *sumidero de pago* sin re-cablear estas
   pantallas las dejaría mostrando datos obsoletos o incompletos.
3. **Trazabilidad histórica.** 72 pagos ($125,850 MXN) en `payment_by_rule` + 39 filas en
   `history_sellers_rules` deben migrarse (o convivir sin duplicarse) para no perder auditoría.
4. **El puente de visibilidad #123 está roto** (sección 0) — cualquier trabajo de migración debería
   arreglar primero ese endpoint (sub-item #9990464) para tener visibilidad real durante la
   transición.
5. **Sin dispersión bancaria automática en ninguno de los dos motores** (ver 4.2) — el riesgo de
   "dinero en tránsito real" es bajo, pero el riesgo de "reportes/decisiones administrativas basadas
   en datos incorrectos" durante la transición es real.

### 4.2 Nivel de riesgo de dinero — más bajo de lo que el nombre del item sugiere

**Ninguno de los dos motores mueve dinero real automáticamente.** No se encontró integración de
dispersión bancaria/SPEI ni de nómina real en ninguno de los dos módulos:

- `PaymentByRule`/`PaymentSeller` solo guardan el *método* de pago (efectivo, transferencia, cheque)
  — es un registro de que "se pagó de tal forma"; el movimiento de dinero real ocurre **fuera** del
  sistema, manual.
- En Talento, el propio roadmap interno del módulo (seed
  `2026_06_03_100300_seed_talento_roadmap_items.php:18`) lista *"Integración contable — exportación
  de nómina a sistemas contables"* como **backlog** (no construido) — Talento tampoco dispersa
  dinero real hoy.
- Ambos motores son 100% manuales/admin-disparados, sin cron.

Esto **no elimina** la frontera dura de dinero (ambos son registros contables que informan pagos
reales hechos por fuera), pero reduce el riesgo de "romper un pago automático en tránsito" —
el riesgo real está concentrado en el cálculo (Pipes condicionales) y en la trazabilidad histórica,
no en un flujo de dispersión que se pueda desincronizar.

### 4.3 Plan por fases (propuesto — decisión de Irving)

**Fase 0 — Prerrequisito (nivel A, ya registrado como #9990464):** arreglar `sellerData()` para
tener visibilidad real del solapamiento colaborador↔vendedor durante toda la migración.

**Fase 1 — Puente de escritura en paralelo (nivel B/C, sin quitar el camino viejo):**
Tras cada `PaymentSellerController::store()` exitoso, escribir además una entrada espejo en
`TalentoLedgerEntry` (`concept='sales_commission'`) para el `user_id` correspondiente, resolviendo el
`period_start`/`period_end` a la ventana `PayWeek` correcta (riesgo 4.1). El camino viejo
(`PaymentByRule`) sigue siendo la fuente de verdad — el ledger es solo un espejo de lectura por
ahora. **Kill switch:** un flag de config que desactive la escritura espejo sin afectar el pago real.

**Fase 2 — Conciliación al centavo:** correr ambos caminos en paralelo N semanas, comparando
`sum(payment_by_rule_commissions.amount)` por vendedor/período contra
`sum(talento_ledger_entries.amount WHERE concept='sales_commission')` para el mismo colaborador/
período. Cualquier discrepancia bloquea el avance a Fase 3.

**Fase 3 — Conmutación (kill switch):** cuando la conciliación sea consistentemente exacta, mover el
*disparo* del pago (no solo el espejo) a escribir directamente en `TalentoLedgerEntry`, dejando
`PaymentSellerController` como una vista de solo-lectura sobre el ledger (o re-cableando la UI Vue de
Vendedores para leer del ledger). Mantener un flag para revertir a la escritura dual si aparece un
problema.

**Fase 4 — Migración histórica y limpieza:** migrar (o dejar congelados con trazabilidad clara) los
72 pagos históricos y 39 reglas versionadas; evaluar si los modelos dormidos (`PaymentSeller`,
`Commission`, `TransactionSeller`) se retiran.

**Riesgo de dinero por fase:** Fase 0-2 = nivel A/B (aditivo, sin tocar el camino real de pago).
Fase 3 = nivel C (cambia dónde se origina el registro que informa un pago real — requiere decisión
explícita de Irving y ventana de prueba). Fase 4 = nivel B (limpieza, reversible mientras no se
borren datos sin respaldo).

---

## 5. Recomendación

1. Resolver primero el sub-item #9990464 (bug del puente de visibilidad) — es nivel A, aditivo, y
   necesario para tener datos reales durante cualquier fase de la migración.
2. El mecanismo de extensión de Talento (`concept` libre en `TalentoLedgerEntry`) ya soporta el caso
   de uso sin cambios de arquitectura — la migración es principalmente un problema de **dónde se
   origina la escritura** y de **alinear dos calendarios de semana distintos**, no de rediseñar el
   motor de liquidación.
3. El riesgo de dinero real en tránsito es más bajo de lo que sugiere el nombre del item (ningún
   motor dispersa dinero automáticamente hoy) — pero la trazabilidad histórica (72 pagos, 39 reglas)
   y el re-cableo de la UI Vue de Vendedores sí son trabajo real no trivial.
4. Plan de 4 fases (4.3) con kill switch y conciliación al centavo antes de conmutar — listo para que
   Irving apruebe el arranque de la Fase 0/1 cuando decida retomar #123.
