## 2026-10-01 08:00 — Un pago parcial que no cubre un ciclo ya no reactiva al cliente

Tercer fix de la misma sesión, sobre el mismo tema (ver
[2026-10-01-pagos-recurrentes-billingforce-corte-gratis.md](2026-10-01-pagos-recurrentes-billingforce-corte-gratis.md)
y
[2026-10-01-pagos-recurrentes-cargo-y-fecha.md](2026-10-01-pagos-recurrentes-cargo-y-fecha.md)).

### Origen

David pidió verificar, de forma amplia, que los dos fixes anteriores (1) no afectaran en nada a
los pagos por adelantado (prepago) y (2) que el cobro de deudas de los clientes recurrentes
siguiera funcionando bien, con sus fechas de corte actualizándose correctamente. Al correr una
batería de 8 casos reales (prepago al corriente, deuda pagada exacta, deuda pagada de más, deuda
pagada de menos, CUSTOM) se confirmó que ambos fixes anteriores funcionan correctamente — pero
se encontró, de paso, un comportamiento PRE-EXISTENTE (no causado por los fixes anteriores) que
David pidió corregir: un cliente Bloqueado que paga **menos** de lo que debe (no cubre ni un
ciclo completo) se **reactivaba igual**, con el servicio de vuelta, sin haber cubierto su deuda.

### Causa raíz

`ClientBillingService::cobrarYActivarCliente()`:

```php
private function cobrarYActivarCliente($client, $forceCobrar = false, $transaction = null)
{
    $this->billingServicesByClient($client, null, $forceCobrar, $transaction);
    $client->activarCliente();
}
```

`activarCliente()` se llamaba **incondicionalmente**, sin importar si `billingServicesByClient()`
de verdad cobró algo. Cuando el pago es parcial (N=0 — no cubre ni un ciclo), ese método no cobra
nada (confirmado por el fix anterior), pero el cliente se reactivaba de todos modos.

### Fix

`billingServicesByClient()` y `billingForce()` ahora devuelven `bool` (¿se cobró al menos un
ciclo completo?); `cobrarYActivarCliente()` solo llama `activarCliente()` cuando ese resultado es
`true`:

```php
private function cobrarYActivarCliente($client, $forceCobrar = false, $transaction = null)
{
    $seCobroAlMenosUnCiclo = $this->billingServicesByClient($client, null, $forceCobrar, $transaction);
    if ($seCobroAlMenosUnCiclo) {
        $client->activarCliente();
    }
}
```

**No se tocó `removePeriodoGracia()`** — sigue limpiando el periodo de gracia aunque el pago sea
parcial, exactamente igual que antes de este fix (y que antes del fix de `billingForce` del
cliente #6722). Se verificó que esto no deja al cliente en un estado muerto: si el cliente vuelve
a quedar con balance negativo (porque el cron de cobro automático intenta cobrarle de nuevo sin
que haya cubierto su deuda), `ClientBalanceObserver::updating()` le asigna un periodo de gracia
**nuevo** automáticamente en cuanto el balance cruza de `>=0` a `<0` — es un mecanismo que ya
existía, autosanador, no hacía falta tocar nada ahí.

### Verificación

Batería de 8 casos (prepago al corriente ×2, deuda pagada exacta, deuda pagada de más, deuda
pagada de menos, sin deuda/sin saldo, CUSTOM ×2), todos con clientes reales de prueba (17 y 19),
en transacciones revertidas:

| Caso | Antes del fix | Después del fix |
|---|---|---|
| Prepago 1 ciclo | Activo→Activo, fechas avanzan, cargo creado | **Idéntico** |
| Prepago 3 ciclos | Activo→Activo, fechas avanzan, cargo creado | **Idéntico** |
| Deuda pagada exacta | Bloqueado→Activo, fechas avanzan, cargo creado | **Idéntico** |
| Deuda pagada de más | Bloqueado→Activo, fechas avanzan, cargo creado | **Idéntico** |
| **Deuda pagada de menos** | **Bloqueado→Activo**, sin fechas, sin cargo | **Bloqueado→Bloqueado**, sin fechas, sin cargo |
| Sin deuda/sin saldo | Activo→Activo, nada se mueve | **Idéntico** |
| CUSTOM 1 ciclo | Activo→Activo, fechas avanzan, cargo creado | **Idéntico** |
| CUSTOM 2 ciclos | Activo→Activo, fechas avanzan, cargo creado | **Idéntico** |

Solo el caso de pago parcial cambió — exactamente lo pedido, sin afectar ningún otro escenario.

Nuevo check `recurrent_pago_parcial_no_reactiva` en `pagos:verificar-recurrentes` (mismo patrón:
cliente real, transacción revertida, verifica el estado antes/después del pago parcial).

`php artisan pagos:verificar-recurrentes --dry-run` → **11/11 checks OK**:

```
[OK] recurrent_pago_parcial_no_reactiva: Cliente #7692: pago parcial (no cubre un ciclo) NO
reactivó al cliente (estado se mantuvo en Bloqueado).
```

### Commit

`6942bcf3` en la rama `fix/pagos-recurrentes-no-reactivar-pago-parcial` (desde `main`, que ya
incluye los dos fixes anteriores). Archivos:
`app/Modules/Core/Clientes/Services/ClientBillingService.php`,
`app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php`.

### Nota de proceso

Este cambio se escribió primero por error directo en `main` del checkout principal
(`/var/www/megaisp`) en vez del worktree de trabajo (`/var/www/megaisp-cc`) — se detectó de
inmediato (antes de cualquier commit), se guardó el diff, se descartó el cambio en `main`
(`git checkout --`, árbol quedó limpio) y se reaplicó correctamente en una rama nueva del
worktree. Nada quedó commiteado fuera de su rama.
