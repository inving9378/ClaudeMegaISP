## 2026-10-01 12:00 — Reactivar según el balance tras el pago, no si se cobró un ciclo nuevo

### El reporte

Otra terminal corrió una batería de 6 casos en 2 clientes de dev (#17, ciclo $420; #7692, ciclo
$349), con transacciones revertidas y cola síncrona, sobre `main` ya en V1.49. Encontró un error
nuevo, efecto secundario directo del fix 3 de hoy ("pago parcial no reactiva"):

| Caso | Resultado (antes de este fix) | ¿Correcto? |
|---|---|---|
| 1. Debe un ciclo y abona la mitad | Sigue Bloqueado, sin cargo, fechas intactas | Sí |
| **2. Debe un ciclo y paga exacto (saldo 0)** | **Sigue Bloqueado, y pierde el periodo de gracia** | **No** |
| **3. Paga la deuda y le sobran $19 (caso #6722)** | **Sigue Bloqueado, y pierde el periodo de gracia** | **No** |
| 4. Debe un ciclo y paga dos | Pasa a Activo, un cargo nuevo, fecha_pago avanza | Sí |
| 5. Al corriente, paga un ciclo adelantado | Un cargo, fecha_pago +1 mes | Sí |
| 6. Al corriente, paga dos ciclos | Un cargo, fecha_pago +2 meses | Sí |

Diagnóstico certero de la otra terminal: *"El fix 3 solo reactiva cuando se cobra al menos un
ciclo, pero un pago que solo salda la deuda no cobra ciclo, porque el cargo ya lo había hecho el
cron. Al quedar sin gracia, nada lo vuelve a reactivar solo."* — y propuso el criterio correcto:
*"reactivar cuando el saldo queda en cero o positivo después del pago, haya o no ciclo cobrado.
Mantener el bloqueo solo si el saldo sigue negativo."*

### Causa raíz (re-trazada contra el caso real del #6722)

El cliente #6722 llegó a este pago con balance **-431** (el cron ya le había cargado el ciclo de
$420 adeudado en una corrida anterior, dejándolo negativo y asignándole periodo de gracia vía
`ClientBalanceObserver`). Pagó $450, balance quedó en **+19**.

`ClientBillingService::cobrarYActivarCliente()` (versión de la primera vuelta del fix de hoy):

```php
$seCobroAlMenosUnCiclo = $this->billingServicesByClient($client, null, $forceCobrar, $transaction);
if ($seCobroAlMenosUnCiclo) {
    $client->activarCliente();
}
```

Con balance +19 (menor al costo de $420), `getCuantasVecesSeLePuedenCobrarLosServiciosActivos()`
devuelve `null` → `billingForce()` recibe N=0 → no cobra nada NUEVO (el ciclo YA estaba cargado
de antes) → `$seCobroAlMenosUnCiclo = false` → **no se reactiva**. El criterio confundía "¿hubo un
cargo nuevo en ESTA llamada?" con "¿el cliente sigue debiendo?" — son preguntas distintas. Un
cliente puede saldar su deuda completa sin que esta llamada específica genere un cargo nuevo
(porque el cargo que generó la deuda ya ocurrió antes).

### Fix

El criterio de reactivación pasa a ser el **balance tras el pago**, no si hubo cargo nuevo:

```php
$client->load('balance');
$sinDeuda = $client->balance->amount >= 0;

if ($sinDeuda) {
    $client->activarCliente();
}

return $sinDeuda;
```

- Saldo ≥ 0 (deuda saldada, con o sin sobrante, con o sin ciclo nuevo cobrado) → reactiva.
- Saldo sigue negativo (pago parcial real, deuda sin cubrir) → se mantiene Bloqueado.

Consistente con que el sistema es prepago: un balance negativo **es** la deuda; balance ≥ 0
significa que no hay deuda pendiente. El mismo booleano sigue gateando
`eliminaLosServiciosDelAddressList()` (fix 4 de hoy) — esto además **restaura** el comportamiento
que existía antes de cualquiera de los fixes de hoy (esa reconexión corría siempre que
`$newBalance >= 0`, sin gate alguno). No reabre el riesgo de factura duplicada: el fix 5 (ramas de
`billing()` mutuamente excluyentes) sigue intacto, así que la reconexión solo se dispara una vez
por pago, sin importar cuál de los dos criterios de activación se use.

### Verificación

Se reprodujeron los **6 casos exactos** reportados, en **ambos** clientes de prueba (costos $420 y
$349), más 2 bordes (balance=-1 "apenas sigue en deuda", balance=0 "borde exacto"):

| Caso | Resultado tras este fix |
|---|---|
| 1. Debe un ciclo y abona la mitad (balance queda **negativo**) | Bloqueado, sin cargo, fechas intactas — **igual que antes** |
| 2. Debe un ciclo y paga exacto (balance=0) | **Activo**, sin cargo nuevo, reconecta — **corregido** |
| 3. Paga la deuda y le sobran $19 (balance=+19) | **Activo**, sin cargo nuevo, reconecta — **corregido** |
| 4. Debe un ciclo y paga dos (balance final=0 tras cobrar 1 nuevo) | Activo, 1 cargo nuevo, fecha_pago avanza — **igual que antes** |
| 5. Al corriente, paga 1 ciclo adelantado | Activo, 1 cargo, fecha_pago +1 mes — **igual que antes** |
| 6. Al corriente, paga 2 ciclos | Activo, 1 cargo, fecha_pago +2 meses — **igual que antes** |

**0 fallas**, resultados idénticos en los dos clientes de prueba. En ningún caso se duplicó la
factura/reconexión (verificado: `jobs_address_list` sube exactamente 1 cuando debe reconectar).

### Checks automatizados corregidos/agregados

- `recurrent_pago_parcial_no_reactiva` (del fix 3 original) usaba `$newBalance = 1` (POSITIVO)
  para simular "pago parcial" — correcto bajo el criterio viejo, pero con el criterio nuevo ese
  balance (≥0) **debería** reactivar, así que el check habría empezado a dar **falso positivo**
  esta misma noche. Corregido para usar un balance genuinamente **negativo**
  (`-($costAllServices / 2)`, "abona la mitad, sigue en deuda").
- Nuevo check `recurrent_deuda_saldada_si_reactiva`: reproduce el caso #6722 (balance llega
  exactamente a 0, sin cobrar ciclo nuevo) y verifica que el cliente **sí** se reactive.

`php artisan pagos:verificar-recurrentes --dry-run` → **13/13 checks OK**:

```
[OK] recurrent_pago_parcial_no_reactiva: Cliente #7692: pago parcial que deja el balance aún
negativo (sigue en deuda) NO reactivó al cliente (estado se mantuvo en Bloqueado).
[OK] recurrent_deuda_saldada_si_reactiva: Cliente #7692: pagó exactamente su deuda (balance
quedó en 0, sin cobrar un ciclo nuevo) y SÍ se reactivó (Bloqueado → Activo).
```

### Nota sobre la batería de pruebas propia de sesiones anteriores

Los casos límite R5/R7/R8 de mi propia batería de verificación de hoy (balance=150, 419, 0, con
gracia activa) tenían la expectativa **incorrecta** de "no debe reactivar" — estaban escritos bajo
el criterio viejo (roto). Son exactamente los mismos 3 casos que esta terminal encontró rotos. No
representan una regresión nueva: son evidencia de que el fix corrigió justo lo que debía.

### Commit

`1a31cddc` en la rama `fix/pagos-recurrentes-reactivar-por-saldo` (desde `main`, V1.49 ya
publicada). Archivos: `app/Modules/Core/Clientes/Services/ClientBillingService.php`,
`app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php`.

### Pendiente (igual que antes, no tocado en esta sesión)

El cliente #6722 real en producción y los 52 casos del CSV — corrección de datos reales, fuera de
esta sesión de dev.
