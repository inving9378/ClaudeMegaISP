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
