## 2026-10-01 07:00 — actionBilling: fecha_pago ya no avanza antes de que el cargo real exista

Continuación, en la misma sesión, del fix anterior
([2026-10-01-pagos-recurrentes-billingforce-corte-gratis.md](2026-10-01-pagos-recurrentes-billingforce-corte-gratis.md)).
Este es un segundo bug, en otro lugar del código, relacionado al mismo reporte del cliente
#6722 pero **no** el mismo mecanismo.

### Punto de partida

El dev que investigó el caso #6722 en otra sesión paralela (con acceso a prod) mandó un plan
completo de 10 pasos y preguntó si yo ya lo había ejecutado. Yo ya había mergeado a `main` un fix
distinto (`billingForce`, ver bitácora enlazada arriba). Se le explicó la diferencia y se le
preguntó cómo seguir — eligió: nueva rama `fix/pagos-recurrentes-cargo-y-fecha` partiendo del
`main` actual (que ya incluye mi fix anterior), ejecutar su plan completo de pruebas (4 casos) y
dejar claro que este es un fix **adicional**, no un reemplazo.

### Las 4 reglas probadas primero, sin tocar código (paso 2 del plan)

Con el cliente 17 (cliente de prueba estándar de este repo), en transacción revertida, **cola
forzada a síncrona** (`config(['queue.default' => 'sync'])`), se corrió el camino real
`ClientBillingService::billing()` para 4 escenarios:

1. Debe un ciclo y paga menos de lo que debe (N=0, con periodo de gracia activo).
2. Paga exactamente un ciclo (N=1, sin gracia).
3. Paga dos ciclos (N=2, sin gracia).
4. Cliente al corriente que paga por adelantado, con sobrante que no es múltiplo exacto del
   costo (N=1, balance=500 contra costo=420).

**Los 4 pasaron** con la cola en modo síncrono. Esto no significa que el código estuviera bien —
significa que forzar la cola a síncrona **esconde por diseño** la ventana de carrera: con `sync`,
`dispatch()` corre el job de inmediato, así que nunca hay un instante en que el job siga
pendiente. Para encontrar el bug real hacía falta probar con la cola **real** (asíncrona).

### La carrera real (confirmada con la cola real, sin forzar nada)

`QUEUE_CONNECTION=database` en este entorno (confirmado en `.env`). Se repitió el caso "paga
justo un ciclo" (caso 2) con la configuración real, sin forzar sync y sin correr `queue:work`,
mirando el estado **inmediatamente después** de que `billing()` retorna:

```
ANTES:   fecha_pago=2026-09-30 23:59:59  cargos=28  jobs_en_cola=2
DESPUES: fecha_pago=2026-10-30 23:59:59  cargos=28  jobs_en_cola=4 (RectifyBalanceAndCreateTransaction=1)
*** CONFIRMADO: fecha_pago YA avanzó aunque el cargo real todavía NO se creó
    (sigue en cola, pendiente de que el worker lo procese) ***
```

`fecha_pago` ya se movió un mes completo, y el cargo que supuestamente lo justifica **todavía no
existe** — sigue como una fila sin procesar en la tabla `jobs`. Si el worker tarda, se cae, o el
job falla por cualquier razón, esa fecha queda adelantada para siempre sin que el cliente haya
sido cobrado por ese ciclo.

### Causa raíz

`ClientBillingService::actionBilling()` (el método compartido que usan las dos ramas normales de
`billingServicesByClient()` — "cobro y agrego nueva fecha de pago" con N>0, y la rama RECURRENT
sin balance disparada por el cron — más el bloque N>0 de `billingForce()`):

```php
foreach ($services as $service) {
    foreach ($clientWithServices->$service as $clientService) {
        RectifyBalanceAndCreateTransaction::dispatch($clientService, $cuantasVecesSeLePuedeCobrar, $transaction);
    }
}
// Actualizo fecha de pago nueva
$clientRepository->setFechaPago($client, $newPaymentDate);
```

`::dispatch()` solo **encola** el job — no espera a que corra. `setFechaPago()` se ejecutaba
justo después, en el mismo request, sin ninguna garantía de que el cargo ya existiera.

(De paso: para clientes RECURRENT, `TypeOfBillingController::recurrent()` **nunca** devuelve
`null` — siempre regresa un array con los datos del cobro, en sus dos ramas — así que el guard
`if ($newBalanceAndPrice)` dentro del job nunca bloquea nada para este tipo. El riesgo no era "el
job corre pero no hace nada"; era puramente de **temporización**: el job sí crea el cargo, pero
después de que `fecha_pago` ya avanzó.)

### Fix

```php
RectifyBalanceAndCreateTransaction::dispatchSync($clientService, $cuantasVecesSeLePuedeCobrar, $transaction);
```

`dispatchSync()` es el mecanismo oficial de Laravel para forzar que un job `ShouldQueue` corra
**en el mismo proceso**, sin pasar por la cola. Con esto, `setFechaPago()` solo se alcanza después
de que el cargo de **cada** servicio ya se creó de verdad; si algún servicio falla a medio bucle,
la excepción interrumpe el método ahí mismo y `fecha_pago` nunca se mueve — mejor reintentar la
cobranza completa que dejar fechas avanzadas con cargos a medias.

El job en sí (`RectifyBalanceAndCreateTransaction::handle()`) no hace nada pesado: dos escrituras
a BD (`rectifyBalance` + `addDebitTransactionForPaymentService`), sin llamadas externas — y ya
corre siempre dentro de contextos de background (`PaymentClientJob`, el cron de facturación),
nunca directo en un request HTTP, así que no hay riesgo de bloquear a un usuario esperando una
respuesta.

### Verificación

- Los mismos 4 casos del paso 2 se repitieron después del fix: **idénticos** resultados (el
  camino feliz no se tocó).
- La reproducción de la carrera (cola real, sin `queue:work`) **deja de reproducirse**: el cargo
  se crea en el mismo acto (28→29 filas en `transactions`), y quedan **0** jobs
  `RectifyBalanceAndCreateTransaction` pendientes en la tabla `jobs`.
- Nuevo check `recurrent_fecha_pago_no_avanza_sin_cargo_creado` en `pagos:verificar-recurrentes`
  (mismo patrón que los demás checks del comando: cliente real, transacción revertida, verifica
  que si `fecha_pago` avanzó exista un cargo nuevo Y cero jobs pendientes).
- `php artisan pagos:verificar-recurrentes --dry-run` → **10/10 checks OK**, incluido el nuevo:

```
[OK] recurrent_fecha_pago_no_avanza_sin_cargo_creado: Cliente #7692: fecha_pago avanzó
(2026-10-17 23:59:59 → 2026-11-17 23:59:59) y el cargo de servicio se creó en el mismo acto,
sin dejar ningún job pendiente en la cola.
```

### Fuera de alcance, verificado y descartado a propósito

- `App\Services\BillingService::paidService()` también hace
  `RectifyBalanceAndCreateTransaction::dispatch($this->model)` — pero esta clase **nunca se
  instancia en ningún lugar del repo** (`grep` de `new BillingService(` sin resultados). Código
  huérfano. Tampoco toca `fecha_pago` — ni siquiera aplicaría la misma carrera. No se tocó.
- `app/Console/Commands/Scripts/CreateServiceChargeInClientWithPositiveGracePeriod.php` — script
  one-off manual (la carpeta `Scripts/` es explícitamente de uso manual, no parte del flujo
  automático). Tampoco pasa por `actionBilling()`. Fuera del alcance exacto que pidió el plan
  ("el cambio va en `ClientBillingService::actionBilling` y en las dos ramas que lo usan").
- No se tocó ningún dato de cliente real en ninguna base de datos — todas las pruebas corrieron
  dentro de transacciones revertidas.

### Commit

`b584d74e` en la rama `fix/pagos-recurrentes-cargo-y-fecha` (desde `main`, que ya incluye el
commit `68ca4ab9` del fix de `billingForce`). Archivos:
`app/Modules/Core/Clientes/Services/ClientBillingService.php`,
`app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php`.

### Pendiente (fase "Subida" del plan original, no ejecutada en esta sesión sin confirmación)

El plan original separaba esto en una fase propia — merge a `main`, `queue:restart` (el worker
necesita tomar el código nuevo, importante: `RectifyBalanceAndCreateTransaction` es un job que
corre vía worker hoy y pasa a correr síncrono con este fix), emitir versión nueva y vigilar la
primera noche. Se dejó pendiente de confirmación explícita antes de tocar versión/deploy, dado
que es una acción más visible que un merge de bugfix en dev.
