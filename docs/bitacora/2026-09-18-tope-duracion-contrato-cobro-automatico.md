## 2026-09-18 — Tope de cobro automático a la duración del contrato (RECURRENT)

**Contexto:** al reparar los 93 clientes RECURRENT bloqueados con `fecha_corte` congelada
(bitácora `2026-09-18-fix-regresion-fecha-corte-pago-tarde.md`, continuación), Irving confirmó
la política de negocio: los clientes prepago nunca acumulan adeudo automático; los recurrentes
sí, pero **solo hasta cubrir la duración de su contrato** — si contrató 12 meses y pagó 2, los
otros 10 sí se acumulan; del mes 13 en adelante, no.

**Hallazgo:** esa regla YA EXISTÍA en el código, pero desconectada del cobro real.
`ClientService::getDataPendingPayments()` (usado hoy solo para generar el documento de
contrato/finiquito en `ContractClientService.php`) ya calcula "meses del contrato − pagos reales
hechos = meses restantes que sí se adeudan", topado en 0. El cron que de verdad cobra
(`ClientBillingService::billingServicesByClient()`, rama "sin saldo suficiente pero se cobra
igual") nunca lo consultaba — por eso los 93 clientes acumularon hasta 26 meses de sobre-cobro
después de agotar su contrato.

**Fix:** nuevo método `ClientBillingService::clientHasReachedContractCap($client)`, que reusa
`ClientService::getDataPendingPayments()` (no duplica la fórmula), consultado al inicio de esa
rama — si `mesesRestantes <= 0`, se omite el cobro automático (se loguea, no se llama
`actionBilling()`). Sin `duration_contract` asignado → conservador, se trata como ya cubierto
(no se acumula nada sin ese dato).

**Verificado con datos reales** (transacción+rollback, saldo forzado insuficiente,
`billingServicesByClient()` corrido tal cual lo llama el cron):
- Cliente #167 (contrató 6 meses, pagó 2 con pagos reales, aún debe 4) → **sí** se encola el
  cobro (fecha_pago avanza).
- Cliente #17 (contrató 6 meses, 107 pagos reales — cubrió su contrato hace años) → **no** se
  encola nada (0 jobs, fecha_pago/fecha_corte intactas).

**Comando de verificación diaria** (`pagos:verificar-recurrentes`) ampliado a 8 chequeos: nuevo
`recurrent_tope_duracion_contrato` prueba ambos lados con clientes reales cada noche. El chequeo
hermano existente (`recurrent_balance_insuficiente_corte_avanza`) tuvo que ajustarse para excluir
clientes ya topados por contrato (si no, colisionaba con el cliente #17 y daba un falso
"fallo" — el comportamiento nuevo, no cobrarle, es correcto, el chequeo viejo simplemente
necesitaba un candidato distinto).

**Archivos:** `app/Modules/Core/Clientes/Services/ClientBillingService.php`,
`app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php`.

**Pendiente (fuera de alcance de este fix, explícitamente no tocado):** qué hacer
retroactivamente con lo ya sobre-cobrado en los 93 clientes (¿se revierte el saldo negativo de
más, o se deja como está?) — decisión de Irving, aparte de este fix que solo corta el sangrado
hacia adelante.

**Publicado en V1.38-18.09.2026.**
