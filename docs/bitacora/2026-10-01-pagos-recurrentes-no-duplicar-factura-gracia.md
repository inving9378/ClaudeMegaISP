## 2026-10-01 10:00 — billing() ya no duplica la factura de un pago en periodo de gracia

Quinto fix de la cadena de hoy sobre pagos recurrentes (ver los cuatro anteriores:
[billingforce-corte-gratis](2026-10-01-pagos-recurrentes-billingforce-corte-gratis.md),
[cargo-y-fecha](2026-10-01-pagos-recurrentes-cargo-y-fecha.md),
[no-reactivar-pago-parcial](2026-10-01-pagos-recurrentes-no-reactivar-pago-parcial.md),
[no-reactivar-address-list](2026-10-01-pagos-recurrentes-no-reactivar-address-list.md)).

### Origen

David pidió verificar a fondo los 4 fixes anteriores antes de emitir V1.48, y corregir
cualquier error que surgiera. Se construyó una batería de 11 casos (prepago, deuda
exacta/de más/de menos, dos casos límite nuevos — pago a 1 centavo del costo y balance que
queda exactamente en 0 — y CUSTOM) — los 11 pasaron sin ningún error introducido por los 4
fixes. Pero al revisar por qué los casos de deuda cubierta (R3/R4) mostraban **2** jobs de
reconexión de red en vez de 1, se encontró un bug real, **preexistente** (no causado por
ninguno de los 4 fixes de hoy): un pago que cubre una deuda estando en periodo de gracia
genera **2 facturas idénticas** en vez de 1.

### Causa raíz

`ClientBillingService::billing()`:

```php
public function billing($client, $newBalance, $transaction = null)
{
    $this->processPaymentForClientRecurrentWithGracePeriodActive($client, $newBalance, $transaction);
    $this->processPaymentForRestOfClient($client, $newBalance, $transaction);
}
```

Las dos ramas corrían **siempre las dos**, en secuencia, sin ser mutuamente excluyentes.
Para un cliente RECURRENT con periodo de gracia activo que paga lo que debe:

1. `processPaymentForClientRecurrentWithGracePeriodActive()` cobra el ciclo y, dentro de
   `billingForce()`, limpia el periodo de gracia (`removePeriodoGracia()` — esto SIEMPRE
   pasó, desde antes de cualquiera de los 4 fixes de hoy).
2. `processPaymentForRestOfClient()` vuelve a evaluar: su guard es
   `!clientHasGracePeriodActive($client)` — como la gracia YA quedó limpia por el paso 1,
   esta condición ahora es verdadera. Y compara `$newBalance >= $costAllServices` — pero
   `$newBalance` es el **mismo parámetro original** pasado a `billing()` desde el principio
   (el saldo de ANTES del pago), **no** el saldo ya actualizado tras el cobro del paso 1. Como
   ese saldo original también cubría el costo, la condición se cumple de nuevo → vuelve a
   llamar `eliminaLosServiciosDelAddressList()` + `cobrarYActivarCliente()` para el **mismo**
   pago.

`eliminaLosServiciosDelAddressList()` encola `ProcessCreateServiceJob` por servicio →
`DeployService::deployService()` (reconexión real) + `InvoiceService::addInvoice()` →
`ClientInvoiceJob` → crea una fila en `client_invoices` **sin ningún candado anti-duplicado**.
Dos disparos = dos facturas idénticas.

**El saldo del cliente NO se duplicaba** — dentro de `billingServicesByClient()`, el cálculo
de cuántos ciclos se pueden cobrar (`getCuantasVecesSeLePuedenCobrarLosServiciosActivos()`)
SÍ relee el saldo real y fresco de la BD, así que la segunda pasada correctamente detectaba
"ya no hay nada que cobrar" (balance ya en 0) y no volvía a descontar. Solo la factura y la
reconexión se disparaban de más.

### Fix

```php
public function billing($client, $newBalance, $transaction = null)
{
    $procesadoPorGracia = $this->processPaymentForClientRecurrentWithGracePeriodActive($client, $newBalance, $transaction);
    if (!$procesadoPorGracia) {
        $this->processPaymentForRestOfClient($client, $newBalance, $transaction);
    }
}
```

`processPaymentForClientRecurrentWithGracePeriodActive()` ahora devuelve `bool` (antes
`void`): `true` si el pago calificó para esa rama (cliente recurrente, gracia activa,
`$newBalance >= 0`), sin importar si llegó a cobrar un ciclo completo o no. Las dos ramas
quedan mutuamente excluyentes para un mismo evento de pago.

### Por qué esto no rompe el caso de pago parcial (N=0)

Para el caso de pago parcial (gracia activa, balance que no cubre un ciclo), la primera rama
también "aplica" (entra al bloque `$newBalance >= 0`) y devuelve `true` aunque no cobre nada
— así que la segunda rama se salta igual. Esto no cambia nada observable: en ese caso la
segunda rama YA era un no-op de todos modos (su propio guard `$newBalance >= $costAllServices`
ya rechazaba el pago, porque no alcanza a cubrir el costo) — confirmado con los casos R5/R7/R8
de la batería, idénticos antes y después de este 5º fix.

### Verificación

- Batería completa de 11 casos (prepago ×2, deuda exacta/de más/de menos, bordes ×2, CUSTOM
  ×3) → **0 fallas**, resultados idénticos a antes del 5º fix.
- Reproducción directa de la factura duplicada (cola forzada a sync para ver el resultado
  final completo): **antes** → 2 facturas de $420 por un solo pago; **después** → exactamente
  **1** factura.
- `jobs_address_list` en los casos de deuda cubierta (R3/R4): bajó de **2** a **1**, sigue
  reconectando correctamente (no se rompió el camino feliz).
- Nuevo check `recurrent_no_duplica_factura_con_gracia` en `pagos:verificar-recurrentes`
  (cliente real, transacción revertida, cuenta los jobs `ProcessCreateServiceJob` encolados
  contra los servicios activos reales del cliente — deben coincidir 1:1, nunca el doble).

`php artisan pagos:verificar-recurrentes --dry-run` → **12/12 checks OK**:

```
[OK] recurrent_no_duplica_factura_con_gracia: Cliente #7692: un pago que cubre su deuda
encoló exactamente 1 job(s) de reconexión/factura para sus 1 servicio(s) activo(s) —
sin duplicar.
```

### Commit

`3c203b6b` en la rama `fix/pagos-recurrentes-no-duplicar-factura-gracia` (desde `main`, que
ya incluye los 4 fixes anteriores). Archivos:
`app/Modules/Core/Clientes/Services/ClientBillingService.php`,
`app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php`.
