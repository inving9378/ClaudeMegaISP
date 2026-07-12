# Item #192 — Checklist de diagnóstico solo-lectura (PROD) — 177 pagos ventana 01-jul

## Contexto

El fix del roadmap #191 desconectó `matchPendingInvoice` del flujo por defecto de
`PaymentApplicationService::applyPayment` (`app/Modules/Addons/Payments/Services/PaymentApplicationService.php:71-77`):
ahora **todo** pago se registra `paymentable_type = Client` (dispara
`PaymentObserver` → `PaymentClientJob` → abona balance + transacción + billing),
nunca `paymentable_type = ClientInvoice`.

El bug original: `matchPendingInvoice` buscaba una factura con
`estado LIKE 'Pagar%'` **y** `total == monto exacto` del pago. Si matcheaba, el
pago se adjuntaba a esa `ClientInvoice` en vez de al `Client` → el
`PaymentObserver` no dispara `PaymentClientJob` para ese caso → el pago quedaba
"desregistrado" (sin abonar balance ni dejar transacción).

**Hipótesis a confirmar (item #192):** durante la ventana real de prod del
01-jul, hubo ~177 pagos procesados con la lógica **vieja** (antes del fix), pero
el bug **no se manifestó** porque ninguno de esos 177 montos calzó exactamente
con el `total` de una factura del cliente en estado `Pagar%`. Es decir: el motor
tenía el bug, pero los datos reales no lo dispararon.

Esta verificación requiere leer datos reales de pagos de PROD (dinero) — **no
ejecutable desde DEV** por el candado del Circuito. Este documento es el
checklist de queries **solo-lectura** para que Irving (o quien tenga acceso)
lo corra directamente en PROD.

## Cómo correrlo

Todas las queries son `SELECT` puro, sin escritura. Ajustar `@fecha_inicio` /
`@fecha_fin` a la ventana real de los 177 pagos antes de correr.

```sql
-- Ajustar a la ventana real (ejemplo: todo julio 1)
SET @fecha_inicio = '2026-07-01 00:00:00';
SET @fecha_fin    = '2026-07-01 23:59:59';
```

### Paso 1 — Confirmar el universo de 177 pagos

```sql
SELECT COUNT(*) AS total_pagos
FROM payments
WHERE created_at BETWEEN @fecha_inicio AND @fecha_fin
  AND deleted_at IS NULL;
```

Si el conteo no da 177, ajustar la ventana (puede que "01-jul" se refiera a
`date` en vez de `created_at`, o a un rango de horas específico) antes de
seguir.

### Paso 2 — Confirmar que NINGUNO quedó con `paymentable_type = ClientInvoice`

Esto confirma que el bug (adjuntar a factura, saltarse el ledger) no se
disparó para estos pagos:

```sql
SELECT paymentable_type, COUNT(*) AS n
FROM payments
WHERE created_at BETWEEN @fecha_inicio AND @fecha_fin
  AND deleted_at IS NULL
GROUP BY paymentable_type;
```

Resultado esperado si la hipótesis es correcta: **100% `paymentable_type =
App\\Modules\\Core\\Clientes\\Models\\Client`** (o el FQCN equivalente que use
prod), 0 filas con `ClientInvoice`.

### Paso 3 — Para cada pago, verificar si el monto calzaba una factura `Pagar%` viva

Esta es la comprobación directa de la hipótesis: por cada pago, ¿existía en
ese momento una factura del mismo cliente con `estado LIKE 'Pagar%'` y
`total` == `payments.amount`? Si la respuesta es "no" para los 177, la
hipótesis queda confirmada (el motor viejo no tenía con qué matchear).

```sql
SELECT
  p.id AS payment_id,
  p.paymentable_id AS client_id,
  p.amount,
  p.date,
  p.created_at,
  (
    SELECT COUNT(*)
    FROM client_invoices ci
    WHERE ci.client_id = p.paymentable_id
      AND ci.estado LIKE 'Pagar%'
      AND ci.total = p.amount
  ) AS facturas_pagar_que_calzan_monto
FROM payments p
WHERE p.created_at BETWEEN @fecha_inicio AND @fecha_fin
  AND p.deleted_at IS NULL
  AND p.paymentable_type LIKE '%Client%'
ORDER BY p.id;
```

Revisar la columna `facturas_pagar_que_calzan_monto`:
- **Si todas las filas dan 0** → hipótesis confirmada: ningún pago real de la
  ventana tenía una factura `Pagar%` con monto exacto, por eso el bug viejo
  no se disparó (aunque el motor lo tenía).
- **Si alguna fila da >= 1** → hay al menos un pago que SÍ debió haber
  disparado el bug bajo la lógica vieja. Revisar manualmente ese
  `payment_id`: confirmar en qué `paymentable_type` quedó registrado
  realmente (columna `paymentable_type` del Paso 2) y si su balance se
  abonó (ver `transactions` del cliente en la fecha del pago).

### Paso 4 (opcional, solo si el Paso 3 arroja algún caso >=1) — Auditoría puntual

Para cualquier `payment_id` marcado en el paso anterior, confirmar el estado
real:

```sql
SELECT p.id, p.paymentable_type, p.paymentable_id, p.amount, p.date
FROM payments p
WHERE p.id = <payment_id_a_revisar>;

-- Balance/transacción asociada (ajustar tabla si el ledger vive en otro lado)
SELECT *
FROM transactions
WHERE client_id = <client_id>
  AND created_at BETWEEN @fecha_inicio AND @fecha_fin
ORDER BY id;
```

## Resultado esperado y cierre

- Si Pasos 2 y 3 confirman la hipótesis (0 en `ClientInvoice`, 0 montos que
  calzan factura `Pagar%` viva): el item #192 se cierra como **confirmado
  read-only**, sin acción de código adicional — el fix de #191 ya cubre el
  caso hacia adelante, y no hay pagos históricos que reparar en esta ventana.
- Si algún pago SÍ calzaba (Paso 3 con >=1) pero el sistema real lo procesó
  bien de todos modos (por ejemplo si el fix de #191 ya estaba desplegado
  antes de esos pagos), documentar la fecha de despliegue del fix vs. la
  fecha de los pagos para descartar falso positivo.
- Si algún pago SÍ calzaba y además quedó con `paymentable_type =
  ClientInvoice` (bug real materializado): es un caso de pago desregistrado
  histórico — abrir item aparte para repararlo con `PaymentApplicationService`
  actual (nunca reparar a mano en prod sin pasar por el flujo normal).

No incluir aquí ningún dato real de clientes/montos una vez corrido — este
documento es la plantilla de queries, no el resultado.
