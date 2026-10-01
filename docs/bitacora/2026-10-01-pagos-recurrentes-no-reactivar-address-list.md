## 2026-10-01 09:00 — Pago parcial ya no reconecta el servicio de red del cliente

Cuarto fix de la cadena de hoy sobre pagos recurrentes (ver los tres anteriores:
[billingforce-corte-gratis](2026-10-01-pagos-recurrentes-billingforce-corte-gratis.md),
[cargo-y-fecha](2026-10-01-pagos-recurrentes-cargo-y-fecha.md),
[no-reactivar-pago-parcial](2026-10-01-pagos-recurrentes-no-reactivar-pago-parcial.md)).

Instrucción específica de otra terminal/sesión (mismo hilo de trabajo), con el diagnóstico ya
hecho: el fix anterior (no reactivar con pago parcial) dejaba el campo `estado` del cliente en
`Bloqueado`, pero **la conectividad de red real seguía reconectándose de todos modos** — campo y
realidad quedaban desincronizados.

### Causa raíz

`ClientBillingService::processPaymentForClientRecurrentWithGracePeriodActive()`:

```php
if ($newBalance >= 0) {
    $this->eliminaLosServiciosDelAddressList($client);
    $this->cobrarYActivarCliente($client, true, $transaction);
}
```

`eliminaLosServiciosDelAddressList()` (nombre heredado, confuso — no borra nada de una lista de
direcciones en el sentido literal) encola `ProcessCreateServiceJob` por cada servicio del
cliente, cuyo `handle()` llama `DeployService::deployService()` — la reconexión real a nivel
red/MikroTik — y `InvoiceService::addInvoice()`. Esta llamada corría en cuanto `$newBalance >= 0`,
**antes** de saber si el pago alcanzaba a cubrir algo. Con pago parcial (N=0), el fix anterior ya
dejaba `estado=Bloqueado`, pero esta línea igual reconectaba el servicio.

### Fix

```php
$seCobroAlMenosUnCiclo = $this->cobrarYActivarCliente($client, true, $transaction);
if ($seCobroAlMenosUnCiclo) {
    $this->eliminaLosServiciosDelAddressList($client);
}
```

`cobrarYActivarCliente()` ahora devuelve `bool` (antes era `void`) — el mismo booleano que ya
gatea `activarCliente()` también gatea la reconexión de red, así que ambos quedan sincronizados
por construcción (una sola fuente de verdad).

### Verificación

Reproducción real con el cliente 17 (transacción revertida, cola real `database` sin forzar
sync), inspeccionando directamente la tabla `jobs` para contar `ProcessCreateServiceJob`
encolados:

| Caso | Antes | Después |
|---|---|---|
| Deuda pagada exacta (gracia activa) | Reconecta | **Idéntico** — reconecta |
| Deuda pagada de más (gracia activa) | Reconecta | **Idéntico** — reconecta |
| **Deuda pagada de menos (pago parcial)** | **Reconecta igual** | **Ya NO reconecta** |
| Prepago normal 1 ciclo (sin gracia) | Reconecta | **Idéntico** |
| Prepago normal 2 ciclos (sin gracia) | Reconecta | **Idéntico** |

`php artisan pagos:verificar-recurrentes --dry-run` → **11/11 checks OK**, incluido
`recurrent_pago_parcial_no_reactiva` (que ya existía del fix anterior y sigue pasando).

### Hallazgo aparte, NO corregido en este fix (fuera de alcance)

Al inspeccionar la tabla `jobs` se observó que los casos de deuda pagada (exacta o de más, con
periodo de gracia activo) encolan `ProcessCreateServiceJob` **dos veces** por servicio, no una.
Causa: `billing()` llama secuencialmente a `processPaymentForClientRecurrentWithGracePeriodActive()`
(que limpia el periodo de gracia vía `removePeriodoGracia()` dentro de `billingForce()`) y
después a `processPaymentForRestOfClient()` — como el periodo de gracia YA quedó limpio por la
primera llamada, el guard `!clientHasGracePeriodActive()` de la segunda también pasa, y con el
mismo `$newBalance` (que ya cubre el costo) ambas ramas terminan llamando
`eliminaLosServiciosDelAddressList()` + `cobrarYActivarCliente()` para el mismo pago. Esto es
**preexistente** — no lo causó ningún fix de hoy, ya pasaba antes de todos ellos — y
probablemente inofensivo en la práctica (el redeploy de red es presumiblemente idempotente), pero
queda anotado como posible deuda técnica a revisar aparte si alguna vez se nota un efecto real
(doble factura, doble jale de recursos del router, etc.).

### Nota sobre `recurrent_tope_duracion_contrato`

La otra terminal reportó que este chequeo falló en su sesión, aclarando que no tiene relación con
estos fixes. Se re-corrió aquí (mismo `pagos:verificar-recurrentes --dry-run`, mismos clientes
167/17) y **pasó limpio**. Al ser una BD de dev compartida entre varias terminales trabajando en
paralelo, lo más probable es que haya sido una falla transitoria por datos tocados al mismo
tiempo por otra sesión — no se investigó más a fondo, tal como se indicó explícitamente que no
debía bloquear este cierre.

### Commit

`08eac18c` en la rama `fix/pagos-recurrentes-no-reactivar-address-list` (desde `main`, que ya
incluye los tres fixes anteriores). Archivo:
`app/Modules/Core/Clientes/Services/ClientBillingService.php`.
