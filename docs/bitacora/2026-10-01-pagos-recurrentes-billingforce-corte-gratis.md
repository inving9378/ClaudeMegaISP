## 2026-10-01 06:00 — Fix billingForce: pago parcial de un recurrente regalaba fecha_corte (cliente #6722)

**Reporte original de David** (prod, otra sesión paralela): el cliente #6722, recurrente, no
pagó el ciclo agosto→septiembre (quedó con deuda) y pagó en septiembre. El sistema, en vez de
aplicar ese pago a la deuda existente (cobrar lo que debía, y si sobraba, abonarlo), lo trató
como si fuera el prepago normal del SIGUIENTE ciclo y avanzó la fecha de corte del cliente —
exactamente como si hubiera pagado completo, cuando el pago fue parcial.

**Instrucción explícita de David** (la que define el alcance de esta sesión): *"alla voy a
arreglar los clientes recurrentes que tengan ese problema, aca hy que arreglar el error para
poder subierlo"* — es decir: él corrige a mano, en producción, a los clientes ya afectados por
el bug (incluido el #6722); **esta sesión (dev) solo corrige el código**, sin tocar ningún dato
de cliente real.

### Causa raíz

`ClientBillingService::billingForce()` llamaba, sin condición:

```php
$clientRepository->removePeriodoGracia($client, true, $cuantasVecesSeLePuedeCobrar + 1);
```

El tercer argumento de `removePeriodoGracia()` **adelanta `fecha_corte`**. El problema: esta
llamada se ejecutaba SIEMPRE, incluso cuando `$cuantasVecesSeLePuedeCobrar = 0` — es decir,
cuando el pago recibido NO alcanza para cubrir ni un ciclo completo (el caso exacto de un pago
parcial sobre una deuda vieja). Con N=0 no debería tocarse `fecha_corte` en absoluto — solo
debe quitarse el periodo de gracia (el cliente deja de estar "en gracia" porque pagó algo), sin
regalarle el avance de fecha que correspondería a un ciclo completo pagado.

Más abajo en el mismo método, el bloque `if ($cuantasVecesSeLePuedeCobrar > 0) { ... }` sí hace
el avance real y correcto de `fecha_corte` (vía `BillingExpirationService`) cuando el pago sí
cubre uno o más ciclos completos — ese camino estaba bien y no se tocó.

### Teoría descartada durante la investigación

Se consideró también la posibilidad de un "doble avance" de `fecha_corte` para el caso N>0 (dos
escrituras seguidas a la misma fecha). Se descartó con una reproducción directa sobre el cliente
17 (transacción con rollback, mismo cliente que se usa desde hace tiempo para pruebas de pagos
en este repo): `BillingExpirationService::getFechaCorteForBillingPrepaidRecurrent()`, en su rama
`$fechaCorteAnterior`, deriva `fecha_corte` **solo** de `fecha_pago + billing_expiration`,
pisando cualquier valor escrito antes — la segunda escritura no acumula ni corrompe nada. Repetir
la misma prueba después del fix dio resultado idéntico al de antes del fix para N=1, confirmando
que ese camino nunca estuvo roto. El bug real era únicamente el caso N=0.

### Fix

```php
// Antes:
$clientRepository->removePeriodoGracia($client, true, $cuantasVecesSeLePuedeCobrar + 1);

// Después:
$clientRepository->removePeriodoGracia($client);
```

Sin el adelanto de fecha cuando `$cuantasVecesSeLePuedeCobrar` es 0. El resto del método
(incluido el bloque N>0) queda igual.

### Guardia de regresión agregada

Nuevo check `recurrent_pago_parcial_no_regala_corte` en
`php artisan pagos:verificar-recurrentes` (mismo patrón que los 8 checks ya existentes en ese
comando: toma un cliente recurrente real, simula suspensión + periodo de gracia + un pago que NO
cubre un ciclo completo, corre el camino REAL de `ClientBillingService::billing()` dentro de una
transacción con rollback, y verifica que `fecha_corte` no se haya movido).

**Verificado:** `php artisan pagos:verificar-recurrentes --dry-run` → **9/9 checks OK**, incluido
el nuevo:

```
[OK] recurrent_pago_parcial_no_regala_corte: Cliente #7692: pago parcial (no cubre un ciclo)
dejó fecha_corte intacta (2026-10-18 23:59:59), estado Activo → Activo.
```

### Qué queda fuera de esta sesión (a propósito)

1. **El caso real del cliente #6722** (su fecha de corte y su deuda en PRODUCCIÓN) — David lo
   corrige a mano en su propia sesión con acceso a prod. Esta sesión no tocó ningún dato de
   cliente real, en ninguna base de datos.
2. **Pregunta de política NO resuelta, solo señalada**: `cobrarYActivarCliente()` sigue llamando
   `activarCliente()` sin condición, incluso cuando el pago fue parcial (N=0) y no cubrió un
   ciclo completo. Es decir, hoy el cliente se reactiva aunque solo haya abonado parte de su
   deuda. No es el mismo bug que el de la fecha de corte (ese sí era un error claro); esto es una
   decisión de negocio (¿debe reactivarse con abono parcial, o solo cuando cubre lo que debe?)
   que no se tocó — se deja anotada para que David/Irving la decidan si hace falta.

### Commit

`23cf6beb` en la rama `fix/pagos-recurrentes-billingforce-corte-gratis` (desde `main`, tip
`7c974181`). Archivos: `app/Modules/Core/Clientes/Services/ClientBillingService.php`,
`app/Console/Commands/Active/VerificarPagosRecurrentesCommand.php`.
