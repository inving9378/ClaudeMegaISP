## 2026-09-21 — Talento Fase D: comisiones (UI replicada, mismo motor que Vendedores)

**Contexto:** Fase D, la última y más sensible del plan `ethereal-fluttering-simon.md`
(Vendedores→Talento). Decisión ya tomada por Irving en la planeación: replicar la
UI de comisiones dentro de Talento **sin tocar el motor de dinero** — mismo
`CalculateBalanceSellerService`, mismas tablas (`payment_by_rule`,
`payment_by_rule_commissions`, `discounts`, `discount_sales`,
`commissions_rules_sellers`). Si un colaborador no tiene fila en `sellers`,
**bloquear con 422 + mensaje claro** (decisión de Irving) — nunca crear el
Seller al vuelo.

**Identidad — `SellerResolver`** (nuevo, `Support/`): mismo puente
`sellers.user_id == talento_colaboradores.user_id` ya usado por `Actor::seller()`
y `TalentoEmbajadoresController::sellerData()`.

**Backend — `TalentoComisionSellerController`** (nuevo): reusa **TAL CUAL**
`CalculateBalanceSellerService`, `PaymentByRule`, `PaymentByRuleDetails`,
`Discount`, `DiscountSale`, `Seller::getTotalDebtBySales()/getTotalDiscountBySales()/
getDebtBySales()`. `registerPayment()`/`saveDetails()` son el mismo cuerpo que
`PaymentSellerController::store()`/`saveDetails()` — único cambio real: cada
escritura agrega `colaborador_id` (columna ya aditiva en `payment_by_rule` y
`discounts`, sin observer que la llene sola). Las plantillas Blade de recibo
PDF (`meganet.module.sellers.payment_receipt`, `...pdf.discount_by_type`) se
reusan sin duplicar. **Alcance deliberadamente acotado** frente al original
(que tiene ~20 endpoints, varios sobre el mecanismo legado `Commission`/
`PaymentSeller`/`TransactionSeller` confirmado **muerto, 0 filas** — ver
`docs/identidad-vendedores-diagnostico-8-seller-id-item-9990802.md`): se
replicaron los 12 endpoints del mecanismo moderno/vivo (`PaymentByRule`) —
reglas, estado de cuenta, pendientes, historial de pagos, registrar pago,
recibo PDF, deudas, descuentos, cobrar deuda, recibo de descuento PDF,
catálogo de métodos de pago.

**Permisos nuevos** (`talento.comisiones.view`/`.manage`): roles espejo EXACTO
de los que ya tienen acceso a este mismo dinero en Vendedores (verificado en
BD real, no una suposición) — `.view` = `seller_view_all_payments_for_seller`/
`seller_follow_payment_client` (Mostrador/Vendedor/TECNICO/Almacen/Socio +
super-administrator/DESARROLLADOR); `.manage` = `seller_add_payment` (solo
super-administrator/DESARROLLADOR).

**Bugs reales encontrados y corregidos en el camino (no en mi código nuevo —
en el motor compartido, afectando también la pantalla real de Vendedores):**
1. **`ClientMainInformation::getPaymentData()`/`hasBeenDiscount()`** usaban
   `DiscountSale::` y `HistoryGeneralConfigurationRule::`/`PaymentByRuleDetails::`
   sin `use` — al vivir en el namespace `App\Modules\Core\Clientes\Models`,
   PHP los resolvía ahí (donde no existen) en vez de `App\Models\*` →
   `Class not found` en cuanto un vendedor con ventas reales activaba ese
   código (`Seller::getTotalDebtBySales()`/`getTotalDiscountBySales()`).
   **Esto rompía `PaymentSellerController::statementAccount()`/`debtAccount()`/
   `discountAccount()` del lado de Vendedores TAMBIÉN**, confirmado
   reproduciendo el error contra el controller original antes de tocar nada.
   Corregido con 3 imports agregados (aditivo, sin tocar lógica).
2. **`PaymentSellerController::statementAccount()`** calculaba "expenses" con
   `PaymentByRuleDetails::with(['payment' => fn($q)=>$q->where('seller_id',...)])
   ->get()->sum('amount')` — `with()` NO filtra el query base, solo constriñe
   la relación cargada; el `->get()->sum()` sumaba el monto de **TODOS los
   vendedores**, no solo el consultado (confirmado: los números de "income"/
   "expenses" de Vendedores venían inflados respecto a los de Talento hasta
   corregirlo). Corregido a `whereHas(...)->sum('amount')` (patrón que
   `expensesAccount()`, un método hermano en el mismo archivo, ya usaba
   correctamente). Verificado: tras el fix, ambos controllers devuelven
   números **idénticos** para el mismo vendedor.

**Hallazgo de rendimiento, documentado, NO corregido (fuera de alcance —
"reusar tal cual" el motor, no rediseñarlo):** `CalculateBalanceSellerService`
recorre semana a semana y mes a mes TODO el histórico desde 2024 para calcular
el saldo — el "estado de cuenta" tarda **~40 segundos** en calcularse (medido
directo, sin overhead de HTTP/Debugbar) — confirmado **idéntico** en el
controller original de Vendedores (mismo tiempo, mismo motor). Por HTTP real
en dev (con Debugbar activo) el tiempo se dispara aún más y puede exceder el
límite de ejecución de PHP (500 sin log — el proceso se mata antes de que
Laravel pueda registrar la excepción). **Decisión de diseño tomada aquí:** el
frontend de Talento ya NO bloquea la pantalla en este cálculo — usa un
chequeo de identidad rápido (`reglas`, <1s) para decidir si el colaborador
tiene vendedor, y carga el resumen de KPIs **en segundo plano**, sin tumbar
el resto de la pantalla si tarda o falla (reglas/pendientes/pagos/deudas
funcionan de inmediato, independientes del resumen). Esto es una mejora de
UX legítima sobre el patrón original (que si bloquea toda la pantalla), sin
tocar el cálculo en sí. Vale la pena, como trabajo futuro fuera de este
alcance, mover el cálculo a un job en cola o cachearlo — no se hizo aquí.

**Frontend — `TalentoComisiones.vue`** (nuevo, tema Torre desde el día uno):
selector de colaborador → tabs (Reglas asignadas, Pendientes de pago,
Historial de pagos, Deudas) + resumen de KPIs cargado aparte. Modal para
registrar pago (a partir de una fila "pendiente" — el motor recalcula y
sobrescribe el monto en el servidor, el front no envía un monto adivinado) y
modal para cobrar deuda.

**Verificado (datos reales de dev — seller real de Diana, colaborador_id=6,
67 pagos históricos, deuda real):**
- `rules()`, `pendingPayments()`, `payments()`, `discounts()`, `pendingDebt()`
  — todos devuelven datos reales correctos vía llamada directa al controller.
- `registerPayment()`: probado con dinero real en **transacción con
  rollback** — el motor recalculó el monto correcto ($300, coincide con lo
  mostrado en "pendientes"), `colaborador_id`/`seller_id` correctos, detalle
  creado correctamente. Rollback aplicado, nada quedó en la BD real.
- `collectDebt()`: mismo patrón, `colaborador_id` correcto, `DiscountSale`
  creado. Rollback aplicado.
- Bloqueo 422 para colaborador sin `Seller`: verificado con mensaje real y en
  **navegador real** (captura confirma la alerta ámbar con el mensaje).
  correcto.
- `statementAccount()`: verificado que devuelve **números idénticos** al
  controller de Vendedores ya corregido (mismo seller, misma data).
- PDF de recibo de pago: generado, 266KB, magic `%PDF` correcto.
- Navegador real: pestaña "Reglas" carga en **293ms**; "Historial de pagos"
  muestra 67 pagos reales con folio/monto/método/fecha/botón PDF; modo
  oscuro verificado (tabla, badges, alertas, todo legible).
- `npm run dev` compiló limpio.

Rama `feat/talento-fase-d-comisiones`, mergeada a `main`. Migración de
permisos corrida en `main` tras el merge.

---

**CIERRE DEL PLAN VENDEDORES→TALENTO:** con esto quedan completas las Fases
A (ranking admin-wide), B (tema Torre en las 25 pantallas), C (caja diaria de
efectivo) y D (comisiones). Pendiente del plan original: Fase E (verificación
funcional cruzada de lo que ya se solapaba con Vendedores antes de este
trabajo — Colaboradores vs InformationSeller, Mis ventas/Mis prospectos vs
sus equivalentes admin) — no se abordó en esta sesión, queda como siguiente
paso natural si se retoma.
