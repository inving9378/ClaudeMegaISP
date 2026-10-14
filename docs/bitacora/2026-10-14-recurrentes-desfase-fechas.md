## 2026-10-14 — Recurrentes con la fecha de pago/corte un mes (o más) adelantada

**Estado:** diagnóstico + lista de reparación propuesta. **No se escribió nada en producción.**

### Qué se reportó
Clientes recurrentes (`billing_configurations.type_billing_id = 1`) que pagan y su corte salta un mes de más
(ej. 3712: paga el 14/10, corte 14/11 → 14/12; 6538 pagó 2 mensualidades y quedó en enero, se corrigió a mano).
Regla confirmada por el equipo (solo recurrentes): el corte sigue el día fijo de facturación y avanza un mes
por cada mensualidad pagada; la fecha de pago sí puede moverse al día real del pago.

### Causa (deducida de activity_log + transactions, no reproducida)
- El código actual suma los meses pagados a la fecha YA guardada; si esa fecha ya iba un mes adelantada, el
  pago la deja un mes de más y el desfase se arrastra para siempre.
- Los clientes afectados traían el mes de más desde antes de julio (6538 y 3712: en mayo el cobro automático
  cobró el ciclo y el pago de 3 días después, con el código viejo, adelantó otra vez las fechas — la falla
  corregida el 30-sep/01-oct, cliente #6722). El arreglo de código no reparó los datos ya dañados.
- 7656, 7663, 7664: sin desfase (corte 5/11 y 3/11 = una mensualidad desde el cobro automático de octubre).
- No se cambió código. Se descartó quitar el avance de corte del cobro automático (fix del 18-sep, #7408).

### Lista propuesta
`storage/app/reportes/recurrentes-desfase-2026-10-14.csv` (solo lectura; no versionado).
Criterio: último pago de los últimos 75 días cuyo vencimiento anterior (activity_log) estaba >= 20 días
adelante de la fecha del pago. Resultado: 61 clientes (56 con 1 mes de más, 3 con 2, 1 con 4, 1 con 6);
5 ya fueron modificados después del pago (revisar a mano, p. ej. 6538 ya corregido).
Límites: 678 recurrentes con pago reciente no tienen señal (el pago no cambió fechas); un pago adelantado a
propósito también cumple el criterio — la lista se aprueba cliente por cliente.

### Antes de aplicar nada en producción (regla de CLAUDE.md)
Entrada de bitácora previa con comando, base, respaldo tomado y quién lo corre; respaldo de `clients`
(fecha_pago, fecha_corte) de los ids aprobados.

## 2026-10-14 (tarde) — Regla de corte de recurrentes implementada en código (sin commit, sin datos tocados)

Regla confirmada por el equipo (solo `type_billing_id = 1`): el corte es fijo (billing_date + billing_expiration),
avanza un mes por cada mensualidad PAGADA desde el corte anterior, y el cobro automático sin saldo solo acumula deuda.
- `CorteRecurrenteCalculator` (nuevo, puro) + `BillingExpirationService::avanzarCorteRecurrente()`.
- `ClientBillingService`: `billing()` calcula mensualidades saldadas (saldo antes/después del pago); `moverCorteTrasCobro()`;
  la rama "cobro igual" del cron YA NO mueve fecha_corte (revierte el efecto del fix del 18-sep).
- `VerificarPagosRecurrentesCommand`: el chequeo "sin saldo pero se cobra" ahora exige corte intacto.
- Pruebas: `tests/Unit/Modules/Clientes/CorteRecurrenteCalculatorTest.php` (PHPUnit puro, 8 tests OK).
- NO probado contra BD/pagos reales. NO hecho: periodo de gracia (solo tras contrato cumplido, 90 días, sigue cobrando,
  luego inactivo+address list), semántica de fecha_pago, reparación de datos de los 61 clientes, `queue:restart`.

## 2026-10-14 — PRUEBA del flujo de pago (bitácora PREVIA)

- **Qué:** simular pagos sobre el cliente de pruebas #17 (recurrente, costo $420) ejecutando `PaymentClientJob::created`
  y `billingServicesByClient` reales.
- **Contra qué base:** `meganet_prod_claude` (la única base de la app; `APP_ENV=production`, `QUEUE_CONNECTION=sync`).
- **Protecciones:** todo dentro de UNA transacción que siempre termina en `ROLLBACK`; en el proceso se fakean
  `Queue`, `Mail`, `Notification`, `Http` y `Bus` para que no salgan reconexiones MikroTik ni avisos (la cola es `sync`:
  sin el fake se ejecutarían de verdad). Se verifica después que #17 quedó idéntico (fecha_corte, fecha_pago, saldo, estado).
- **Respaldo:** no se persiste ningún cambio; se anotan los valores originales de #17 antes y se comparan después.
- **Quién lo corre:** Claude Code, a petición de quien reportó el caso.

### Resultado de la prueba (todo con ROLLBACK; #17 y #7664 verificados idénticos después)
Cliente #17, costo $420, billing_date 13 + 1 de tolerancia, hoy 14/10:
- S1 debe 3, corte 14/7, paga 2 → corte 14/9, saldo -420, sigue Bloqueado ✔
- S2 debe 4, paga 3 → corte 14/10, sigue Bloqueado; paga 1 más → corte 14/11, Activo ✔
- S3 debe 4, paga 4 de una → corte 14/11, Activo ✔
- S4 debe 1, paga 2 (deuda + ciclo) → corte 14/12 ✔
- S5 al corriente, corte 14/11, paga 1 → corte 14/12 ✔
Cliente #7664 (estado real previo al cobro del 4/oct): el cobro automático sin saldo deja fecha_corte en 05/10 (antes la
pasaba a 05/11) y mueve solo fecha_pago; al pagar 1 mensualidad el corte sube a 05/11 ✔.
Limitaciones: con `Queue::fake()` el cargo de `RectifyBalanceAndCreateTransaction` no refleja el saldo (por eso S4/S5 muestran
saldo +420 y el cron deja saldo 0); en S6 original #17 no cobró porque ya cubrió su contrato (mesesRestantes 0).
En la simulación del cron, fecha_pago salió 14/11 y no 04/11 porque la prueba corre el 14/10 (pago tardío, ancla al día real).

## 2026-10-14 (noche) — Periodo de gracia de recurrentes (código; sin commit, sin datos tocados)

Reglas aprobadas: la gracia solo existe tras cumplir el contrato (pagos reales >= meses contratados), dura 90 días para
todos y durante ella se sigue cobrando; dentro del contrato se cobra sin saldo hasta que la deuda iguale los meses que
faltan; al vencer la gracia → inactivo + address list, SIN activarlo en masa.
- `GraciaRecurrenteCalculator` (puro) + 7 tests; `ClientRepository::addPeriodoGracia` fija 90 días y no renueva una vigente
  (ya no usa `billing_configurations.grace_period`: 90/99/185); `ClientBalanceObserver` y `SuspendService` solo la asignan con
  contrato cumplido; `ClientBillingService`: tope de deuda / gracia en la rama de cobro sin saldo, y la rama de pago aplica también
  a deudores sin gracia (cobrar, avanzar corte por lo saldado, reactivar con saldo >= 0).
- `VerificarPagosRecurrentesCommand::checkRecurrentTopeDuracionContrato` reescrito para las 4 situaciones nuevas.
- Nuevo `clientes:inactivar-gracia-vencida` (NO programado): sin `--apply` solo lista; con `--apply` exige `--ids=` aprobados.
  Hoy lista 757 (755 Bloqueados + 2 Activos, uno es el cliente de pruebas 476). Lista HTML: `~/Público/recurrentes-gracia-vencida.html`.
- Pruebas con rollback (clientes 7664 y 17; cola/correo/HTTP falsos; ambos restaurados idénticos): dentro de contrato cobra sin deuda y se
  detiene con deuda = meses restantes (17); contrato cumplido: inicia gracia 90 d, cobra con gracia vigente, no cobra con gracia vencida;
  el observer no da gracia en contrato y sí ~90 d con contrato cumplido; deudor sin gracia que paga 1 de 2 queda Bloqueado con corte +1 y al
  saldar se reactiva con corte +1 más.
- Pendiente: elegir a quiénes inactivar (bitácora previa + respaldo), reparar datos de corte (61 + 3 originales + clientes con corte de
  meses de cobros no pagados), criterio de fecha_pago, commit/despliegue + `queue:restart`.
