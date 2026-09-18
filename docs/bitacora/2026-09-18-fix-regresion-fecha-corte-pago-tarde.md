## 2026-09-18 — Regresión real en el fix de pagos tardíos (V1.35) — corregida y verificada

**Contexto:** Irving compartió un análisis hecho desde prod sobre los commits `4f8806fb`
(RECURRENT) y `f7d7f969` (CUSTOM) — los fixes de "pago tarde no movía la fecha de corte" del
2026-09-17. El análisis señaló 3 problemas. Se verificaron los 2 primeros con datos reales en
transacción+rollback (nunca contra la BD viva) y se confirmaron ambos como reales — el tercero
es una decisión de producto, documentada abajo sin tocar código.

### Problema 1 (confirmado) — CUSTOM: fecha_corte se desincroniza de fecha_pago

`BillingExpirationService::getFechaCorteForBillingPrepaidCustom()` calculaba
`fecha_corte_anterior + N meses`, sin mirar nunca `fecha_pago` (a diferencia de RECURRENT, que sí
deriva su corte de `fecha_pago + billing_expiration`). Cuando el fix del 17-sep adelanta
`fecha_pago` al día real de un pago tardío, `fecha_corte` seguía su calendario fijo de siempre —
podía terminar ANTES de la nueva fecha_pago.

**Reproducido con cliente real #19** (transacción+rollback): pago 5 días tarde →
`fecha_pago` nueva = 04-ago, `fecha_corte` (sin este fix) = 29-jul — **6 días de suspensión
prematura**, el cron de corte lo habría bloqueado antes de que le tocara volver a pagar.

**Fix:** `getFechaCorteForBillingPrepaidCustom()` ahora deriva de `fecha_pago + billing_expiration`
(igual patrón que RECURRENT). El multiplicador de meses deja de usarse ahí — ya viaja implícito en
`fecha_pago`, que `BillingPaymentDateService` ya calculó para N ciclos. Verificado que
`fecha_pago` está actualizada en memoria (`Eloquent::update()`) antes de este método en los 2
call sites reales de `ClientBillingService`.

**Re-verificado:** mismo cliente #19, pago tardío → `fecha_corte` (05-ago) ahora sí queda
**después** de `fecha_pago` (04-ago). Pago a tiempo (regresión): sigue igual que siempre.

### Problema 2 (confirmado, más grave) — RECURRENT: el fix NO se disparaba en el flujo real de un cliente suspendido

El escenario original que reportó Irving ("corte el 15, se suspende, paga el 20") pasa por
`ClientBillingService::billingForce()`, que llama `ClientRepository::removePeriodoGracia()`
**ANTES** de calcular la nueva `fecha_pago`. Esa llamada:
1. Ya venía de que `SuspendService::ifClientChangeToBlockedRemoveDateCorte()` puso
   `fecha_corte = null` al bloquearse el cliente (esto corre para TODOS los tipos de billing).
2. `removePeriodoGracia()` **reescribe** `fecha_corte` con un valor nuevo (calculado desde "hoy",
   sin relación con el corte original que sí aplicaba a ese pago).

Mi guard del 17-sep comparaba `Carbon::now() > $client->fecha_corte` — pero para cuando corre,
`fecha_corte` ya no es el corte real, es el valor recién reescrito por `removePeriodoGracia()`
(casi siempre en el futuro) → `$pagoTarde` daba `false` **incluso para el escenario exacto que
motivó el fix**.

**Reproducido con cliente real #17** (transacción+rollback, replicando el flujo COMPLETO:
suspender de verdad → activar período de gracia → pagar dentro de la ventana →
`removePeriodoGracia()` → guard): con solo la señal de `fecha_corte`, el guard daba
**2026-07-30** (❌ — el mismo resultado que sin ningún fix) en vez de **2026-08-06** (✅ — anclado
al día real del pago, 5 días tarde + 1 mes).

**Fix:** se agrega una segunda señal, independiente de `fecha_corte`: si el cliente está
**bloqueado en este momento** (`client_main_information.estado === 'Bloqueado'`, leído justo
antes de que `ClientBillingService::cobrarYActivarCliente()` lo reactive — eso corre DESPUÉS de
este método en la misma request), es un hecho que su corte ya pasó, sin importar qué diga
`fecha_corte` ahora mismo. No se necesita saber la fecha exacta del corte viejo para eso, solo
que sí pasó — y el cálculo del nuevo ciclo (`now() + N meses`) no depende de esa fecha exacta.

**Re-verificado:** mismo cliente #17, mismo flujo completo → ahora da **2026-08-06** ✅, coincide
con lo esperado. Casos sin regresión también verificados: pago a tiempo sin suspensión (sigue
dando el día fijo de facturación, sin cambio) y cliente nuevo sin `fecha_pago` previa
(`$restarDia` sigue ganando aunque `estado` sea Bloqueado — no se marca como "pago tardío" a un
alta nueva).

### Problema 3 (NO es bug, decisión pendiente de Irving — sin tocar código)

- El día extra que se le da a un cliente por pagar tarde se le "quita" en el ciclo siguiente si
  ese paga a tiempo (vuelve a pegar al día fijo de facturación) — un ciclo de ~26 días en vez de
  30/31, cobrado completo. Si se quiere que el cliente nunca pierda esos días, hay que decidir
  entre mover el aniversario de forma permanente o prorratear. **No se implementó nada — es una
  decisión de producto, no una corrección de bug.**
- Detalle menor relacionado: el guard también se dispara en el camino donde el cliente NO pagó
  pero el sistema le cobra el servicio de forma forzada por cron (`ClientBillingService.php`, rama
  "no tiene suficiente balance pero le cobro el servicio") si el corte ya venía pasado — mueve el
  aniversario y el log dice "pagó tarde" a un cliente que no pagó. Comportamiento preexistente
  (ya pasaba con solo la señal de `fecha_corte`, no es nuevo con el fix de `estado`), de bajo
  impacto (solo el texto del log es engañoso, la fecha calculada es defendible igual). No tocado.

### Verificación diaria automática fortalecida

El comando `pagos:verificar-recurrentes` (creado ayer) **no habría atrapado ninguno de los 2
problemas reales** — sus chequeos mutaban `fecha_corte`/`fecha_pago` en memoria sobre un cliente
intacto, sin pasar por el flujo real de suspensión. Se agregan 2 chequeos nuevos que SÍ replican
el flujo completo (con escrituras reales, siempre revertidas en una transacción con rollback en
su propio `finally`):
- `recurrent_suspendido_pago_tarde` — replica suspender de verdad + período de gracia +
  `removePeriodoGracia()` + el guard, verifica que ancle al día real del pago.
- `custom_corte_sincronizado_con_pago` — verifica que `fecha_corte` quede después de la nueva
  `fecha_pago` tras un pago tardío.

Los 6 chequeos (los 4 anteriores + estos 2) pasan en vivo contra datos reales de dev. Confirmado
que las transacciones de los 2 nuevos no dejan nada escrito (clientes #17 y #19 verificados
intactos tras correr el comando).

### Archivos tocados
- `app/Modules/Core/Clientes/Services/BillingPaymentDateService.php` (rama RECURRENT)
- `app/Modules/Core/Clientes/Services/BillingExpirationService.php` (`getFechaCorteForBillingPrepaidCustom`)
- `app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php` (+2 chequeos de regresión)

### Pendiente
- **Publicar en una versión nueva** — esta es una corrección de un bug real ya en producción
  (V1.35). Falta decisión de Irving sobre cuándo/cómo emitirla.
- Decisión de Irving sobre el Problema 3 (día extra que se recupera vs. se pierde el ciclo
  siguiente).
