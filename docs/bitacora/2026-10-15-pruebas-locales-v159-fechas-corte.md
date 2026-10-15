## 2026-10-15 — Pruebas locales de V1.59 (fechas de corte/pago) con el dump de producción

**Qué se probó:** el fix `a0ad7ebd` ("corte fijo de recurrentes + periodo de gracia solo tras contrato
cumplido") del tag `V1.59-14.10.2026`, contra el dump `megaisp-202610150245.sql.gz` (BD
`meganet_prod_claude`, ya con el release V1.59 registrado).

**Entorno (solo local, nada contra producción):**
- Worktree del tag en `/home/tati/PROJ/megaisp-v159-pruebas`; contenedor `megaisp-pruebas-db`
  (MySQL 8.4, puerto 3320). PHP 8.4 local (prod usa 8.2) — `composer install --ignore-platform-reqs`.
- En la copia local se neutralizaron routers, OLTs, troncales VoIP, WhatsApp e integraciones del Hub
  (IPs/URLs a direcciones inexistentes); se vaciaron los 477 jobs pendientes que traía el dump.
- Pagos simulados por el camino real: `PaymentApplicationService::applyPayment()` +
  `PaymentClientJob::handle()`; crons reales día por día (`tocaCobrar` 00:03 →
  `billingServicesByClient`, `tocaSuspender` 10:00 → `suspendServiceByClient`). Cada escenario en
  transacción revertida. Lo encolado (MikroTik/WhatsApp) quedó en la tabla `jobs` sin worker y se
  revirtió. Verificado al final: 0 pagos de prueba, 0 jobs.
- Scripts: `/home/tati/PROJ/megaisp-v159-pruebas-harness/` (`sim.php`, `masivo.php`, `historia.php`).

**Resultados:**
- Correctos: RECURRENT al corriente paga a tiempo (#17); activo con deuda paga (#6606); bloqueado paga
  deuda exacta (#7438, corte desde activity_log +1); pago parcial no reactiva; cron sin saldo ya no mueve
  el corte y suspende al vencer (#759); CUSTOM tarde y a tiempo (#820, #61). Tests unitarios 15/15.
- **Falla 1 — `fecha_pago` sigue anclándose al día del pago (BillingPaymentDateService, sin cambios en
  V1.59) mientras el corte ya es fijo:**
  - Si el día del pago es posterior al día de facturación → corte antes que fecha_pago → suspensión sin
    deuda antes del cobro. Caso REAL ya ocurrido en prod con V1.59: #5326 (pagó 15-oct 00:30; corte
    10-nov, fecha_pago 14-nov → se cortaría el 11-nov con saldo 0). Simulado: #6987 se cortaría el
    20-oct tras pagar el 15-oct. Masivo (882 RECURRENT bloqueados pagando deuda+1 hoy): 6 quedarían así.
  - Si el día del pago es anterior al día de facturación → el cron cobra el siguiente ciclo antes de
    tiempo (#7438: cobro el 15-nov en vez del 20-nov).
  - Cliente con pagos adelantados bloqueado a mano (#45): tras pagar 1 mensualidad, fecha_pago pasa de
    9-nov a 15-nov y el cron le cobra el 15-nov un periodo que ya tenía pagado (saldo -449).
- **Falla 2 — `clientes:reparar-cortes` (corrido en prod 14-oct 23:27, 191 clientes, NO está en el
  repo):** regresó el corte a 44 clientes que hoy no deben nada; al menos #7207 (saldo ajustado a mano
  a 0 el 6-oct) se suspende hoy a las 10:00 sin deuda.
- Pico de suspensión hoy 10:00: 143 (131 RECURRENT, 130 con deuda real; 12 CUSTOM prepago normales) vs
  15–27 diarios de las últimas semanas — efecto de la reparación.
- Datos residuales: #6987 quedó bloqueado sin deuda (re-suspendido por V1.58 el mismo día que pagó).

**Recomendación:** en RECURRENT quitar el anclaje por "pago tardío" de `getNewFechaPagoByClient()`
para que fecha_pago siga el día fijo igual que el corte; revisar #7207, #5326 y #6987 a mano; publicar
`reparar-cortes` en el repo y que considere ajustes manuales de saldo.

## 2026-10-15 — Fix propuesto (solo local, sin commit; se sube desde la máquina de test)

Parches en `.claude/fixes/` (aplican limpio sobre `V1.59-14.10.2026`):
1. `2026-10-15-parche-1-fecha-pago-dia-fijo.patch` — `BillingPaymentDateService`: en RECURRENT se quita
   el anclaje de `fecha_pago` al día del pago (fecha_pago siempre en el día fijo). `pagos:verificar-recurrentes`:
   los chequeos `recurrent_pago_tarde`/`recurrent_suspendido_pago_tarde` (que exigían el comportamiento viejo)
   pasan a `recurrent_fecha_pago_dia_fijo`/`recurrent_suspendido_paga_dia_fijo`. Probado que fallan con el
   código viejo y pasan con el nuevo.
2. `2026-10-15-parche-2-corte-derivado.patch` — `avanzarCorteRecurrente` deriva el corte de
   `fecha_pago − mensualidades adeudadas + tolerancia` (`CorteRecurrenteCalculator::cortePorFechaPago`) en vez de
   "corte guardado/activity_log + meses". Hallazgo que lo motiva: ~600 bloqueados tienen el corte guardado
   adelantado tantos meses como deben (cron viejo); con V1.59, 496 de 882 quedaban con meses regalados al liquidar
   (8,295 meses en total, máx. 30). +5 tests puros.

Resultados (simulación masiva: 882 RECURRENT bloqueados pagan deuda + 1 mensualidad):

| Métrica | V1.59 | + parche 1 | + parche 1 y 2 |
|---|---|---|---|
| fecha_pago fuera del día fijo | (no medido; por código, todo bloqueado que paga queda anclado al día del pago) | 0 | 0 |
| corte antes que fecha_pago (suspensión sin deuda) | 6 | 33 | 0 |
| corte regalado (meses de más) | (no medido) | 496 | 0 |
| corte ya vencido tras pagar | 0 | 0 | 0 |

Tests unitarios 20/20; verificador 13/13; escenarios E1–E12 correctos salvo E7 (#7207, dato dañado por
`reparar-cortes`: se arregla a mano, no con código). BD local verificada limpia (0 pagos de prueba, 0 jobs).

## 2026-10-15 — Decisión: volver el cálculo de fechas a V1.34 (se descartan los parches de arriba)

Decisión del equipo: que la facturación funcione como antes de los cambios de septiembre; mejoras después.
Alcance elegido: **solo fechas** (fecha_pago, fecha_corte, gracia), conservando correcciones que no son de fechas.
Cambios en el checkout (sin commit; se suben desde la máquina de test). Parche: `.claude/fixes/2026-10-15-revertir-fechas-a-v134.patch`.

- Idénticos a V1.34: `BillingPaymentDateService`, `BillingExpirationService`, `SuspendService`, `ClientBalanceObserver`.
- `ClientRepository`: `addPeriodoGracia` vuelve a `billing_configuration->grace_period` (sin 90 días fijos); difiere de V1.34 solo por un cambio de Flotas (061905c5).
- `ClientBillingService`: versión del 1-oct menos 9559074e (cobro sin saldo ya no mueve el corte) y 23cf6beb
  (billingForce vuelve a `removePeriodoGracia($client, true, N+1)`); sin nada de V1.59. Se conservan 3c203b6b, 08eac18c,
  6942bcf3, 1a31cddc, b584d74e, a4314c33.
- `VerificarPagosRecurrentesCommand`: se quitan 6 chequeos que exigían las reglas de fechas de septiembre (fallarían cada noche);
  quedan 7.

Pruebas: verificador 7/7; tests unitarios 15/15; escenarios E1–E12 sin errores; masivo 882/882 sin errores ni desfases.
Comportamientos de V1.34 que vuelven (esperados): pago tardío no recupera los días de atraso (E11); un pago parcial con gracia
vuelve a avanzar el corte (#6722). Los datos ya modificados en prod (V1.59 + `reparar-cortes`, p. ej. #7207) no se corrigen con
código.
