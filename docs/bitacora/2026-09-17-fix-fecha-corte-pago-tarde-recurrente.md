## 2026-09-17 10:xx — Fix: fecha de corte no se movía cuando un cliente recurrente pagaba tarde

**Reporte de Irving (dictado en sesión, sin ticket previo):** "soy el cliente y pagué el
servicio el día 15 del mes pasado, por tanto se cancela el servicio el 15 de este mes, y lo
pago el día 20, la cancelación debería moverse al día 20 del otro mes pero se está quedando
en el 15, hay que arreglar eso para evitar quejas de los clientes."

**Investigación (dos pasadas — la primera, vía fork, llegó con la polaridad de una condición
invertida; se re-verificó línea por línea a mano antes de tocar código, dado que es lógica de
dinero real):**

- `ClientBillingService::billingServicesByClient()` (el camino normal, corre cada vez que un
  pago cubre el balance de los servicios de un cliente) llama, en orden:
  1. `actionBilling()` → `BillingPaymentDateService::getNewFechaPagoByClient()` (calcula y
     guarda la nueva `fecha_pago`).
  2. `client->refresh()`.
  3. `BillingExpirationService::setNewFechaCorteForClient()` (calcula `fecha_corte` a partir
     de la `fecha_pago` YA actualizada — este paso en sí estaba bien, el problema era lo que
     `fecha_pago` traía).
- `BillingPaymentDateService::getNewFechaPagoByClient()`, rama `TYPE_OF_BILLING_PREPAID_RECURRENT`:
  siempre calculaba la nueva `fecha_pago` como *(fecha_pago anterior + N meses)*, snapeada al
  día de facturación **fijo** configurado en `billing_configuration.billing_date` — sin mirar
  en ningún momento qué día es "hoy" (cuándo llegó el pago realmente).
- **Reproducido limpio con datos reales** (cliente 17, `Carbon::setTestNow()`): pagando el 1
  de julio o el 20 de julio, el sistema calculaba **exactamente la misma** `fecha_pago` nueva
  (30 de julio) — el atraso no afectaba el resultado en absoluto.

**Fix** (`app/Modules/Core/Clientes/Services/BillingPaymentDateService.php`, solo la rama
`PREPAID_RECURRENT` — `CUSTOM`/`DAILY` sin tocar): si el pago llega **después de
`fecha_corte`** (el corte con gracia que ya le tocaba a ESE ciclo — deliberadamente NO se
compara contra `fecha_pago`, porque al momento de renovar "hoy" SIEMPRE es posterior a la
`fecha_pago` del ciclo anterior, así que esa comparación habría marcado TODO pago como
"tarde"), el nuevo ciclo se ancla al día real del pago (hoy) en vez de snapear al día fijo.
Pago a tiempo o adelantado: sin cambios, mismo comportamiento de siempre — no era lo
reportado y no hacía falta tocarlo. Clientes nuevos activándose por primera vez (sin
`fecha_pago` previa, ver `ClientMainInformationObserver`): excluidos explícitamente, sin
cambios.

**Verificado con transacción + rollback contra el cliente real 17** (sin tocar datos):
- A tiempo (corte 1 jul, paga 1 jul) → 30 jul — **igual que antes, sin cambio**.
- Tarde por 1 día (corte 1 jul, paga 2 jul) → 2 ago — antes daba 30 jul.
- **Escenario exacto de Irving** (corte 15 jul, paga 20 jul) → **20 ago**.
- Pipeline completo (`fecha_pago` + `fecha_corte`): con el fix, `fecha_corte` real queda en
  21 ago (20 ago + 1 día de `billing_expiration`) — coherente de punta a punta.
- Cliente nuevo sin `fecha_pago` previa → sin cambios (30 ago, igual que antes).
- Segundo consumidor (`ClientMainInformationObserver::updated`, reactivación con
  `fecha_pago` null) también queda intacto por el mismo guard (`$restarDia`).

**Commit:** `4f8806fb`, pusheado a `main`.

**Pendiente / no incluido a propósito:** no se escribió un test PHPUnit automatizado — el
proyecto no tiene `.env.testing`/`megaisp_test` configurado en este entorno ahora mismo
(`TestCase.php` corre `migrate:fresh --seed`, y crear esa DB estaba fuera del alcance de
este fix puntual). La verificación se hizo con transacción+rollback contra datos reales, que
para este caso es igual de confiable — pero si se decide invertir en cobertura automatizada
de billing más adelante, este es un buen primer caso a fijar en un test.

## 2026-09-17 (continuación) — Mismo bug en la rama CUSTOM + auditoría amplia de pagos

**Contexto:** Irving pidió una auditoría exhaustiva de todas las vías de pago ("verifica por
todas las formas posibles que no quede ningún error... si encuentras algo corrígelo") tras el
fix de arriba en la rama RECURRENT.

**Bug hermano encontrado y corregido:** `BillingPaymentDateService::getNewFechaPagoByClient()`,
rama `TYPE_OF_BILLING_PREPAID_CUSTOM` — mismo defecto que RECURRENT: sumaba N meses a la
`fecha_pago` ANTERIOR sin mirar cuándo llegó el pago realmente. Reproducido con cliente real
(id=19): a tiempo y tarde-por-10-días daban exactamente la misma `fecha_pago` nueva
(`2026-07-28`). Fix aplicado con el mismo patrón (ancla al día real del pago solo si
`Carbon::now() > fecha_corte`; pago a tiempo/adelantado y cliente nuevo sin `fecha_pago` previa
quedan intactos). Verificado con transacción simulada (`Carbon::setTestNow`) contra el cliente
real: a tiempo/adelantado sin cambio, tarde 10 días → `2026-08-09` (antes daba la misma fecha
que a tiempo). Commit `f7d7f969`.

`TYPE_OF_BILLING_PREPAID_DAILY` revisada y descartada — su fórmula es `fecha_pago + N días`,
sin referencia a `Carbon::now()` ni a un día fijo de calendario, estructuralmente no puede
sufrir esta clase de bug.

**Resto de la auditoría — sin cambios de código, todo verificado correcto:**
- `client_invoices.deleted_at` (error real en logs del 2026-09-11, `DomiciliacionCobrarCommand`)
  → ya estaba corregido por el commit histórico `f4223bad`, previo a esta sesión.
- `PaymentApplicationService::applyPayment()` — leído completo, arquitectura intencional y bien
  documentada (siempre `paymentable_type=Client`, `matchPendingInvoice()` deliberadamente
  desconectado por decisión de producto pendiente — items #191/#193). Sin bugs.
- Idempotencia de webhooks (SPEI/OpenPay) — el chequeo de duplicado por
  `(provider, external_id, status=processed)` corre ANTES de aplicar el pago. Correcto.
- `ClientRepository::removePeriodoGracia()` — llama a `setNewFechaCorteForClient(null, $mult,
  false, true)` (rama compleja, no anclada a `fecha_pago`), pero en el flujo real
  (`ClientBillingService::billingForce()`) ese valor se recalcula y SOBRESCRIBE justo después
  con la llamada correcta (bool `true` por default) que ya lee la `fecha_pago` recién
  actualizada por mi fix — el resultado final es correcto. Caso residual no confirmado como bug
  real: si `cuantasVecesSeLePuedeCobrar` fuera exactamente 0 en un cobro forzado, esa segunda
  llamada correctora no se ejecutaría y quedaría el valor de la rama vieja — no se tocó por ser
  especulativo (edge case estrecho, requiere decisión/confirmación humana antes de tocar el
  camino de reactivación de clientes suspendidos).
- `failed_jobs` — sin fallos relacionados a pagos/billing en la tabla (solo
  `RectifyClientsInRouterJob`, de MikroTik, no de pagos).
- Kill-switches confirmados en `false` en dev: `DOMICILIACION_COBRO_LIVE_ENABLED`,
  `PAGOS_RECURRENTES_CRON_ENABLED`, `payments.auto_apply_enabled`.

**Pendiente de decisión humana (no tocado):** el edge case de `removePeriodoGracia()` arriba —
si vale la pena blindarlo, es una decisión de Irving por tocar el camino de reactivación de
clientes suspendidos (dinero + estado del cliente).
