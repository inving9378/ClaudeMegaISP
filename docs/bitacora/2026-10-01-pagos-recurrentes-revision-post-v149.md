## 2026-10-01 13:00 — Revisión a fondo post-V1.49 (a petición de David: "revisa")

Tras cerrar el fix de "reactivar por saldo" ([reactivar-por-saldo](2026-10-01-pagos-recurrentes-reactivar-por-saldo.md))
y mergearlo a `main`, David pidió una revisión adicional antes de emitir V1.50. Se hizo una
auditoría dirigida a lo que todavía no se había probado explícitamente.

### 1. fecha_corte no se regala en los casos de deuda saldada (confirmado)

Los casos 2/3 del reporte anterior (deuda saldada exacta, deuda saldada con sobrante — el caso
#6722) reactivan al cliente, pero **no deben** avanzar `fecha_corte` (eso sería volver a regalar
plazo, el bug original del primer fix de hoy). Verificado explícitamente:

```
2. Debe exacto (saldo 0)   -> estado: Activo, fecha_corte NO avanzó, 1 factura
3. Paga de más (+19, #6722) -> estado: Activo, fecha_corte NO avanzó, 1 factura
4. Debe uno y paga dos      -> estado: Activo, fecha_corte SÍ avanzó (cobró 1 ciclo real), 1 factura
```

Los tres criterios (fecha_corte, factura única, reactivación) quedan consistentes entre sí: el
cliente se pone al día (reactiva) sin que eso implique haberle regalado un ciclo nuevo de
servicio, salvo que de verdad lo haya pagado.

### 2. No se reabrió el riesgo de factura duplicada (confirmado)

El fix de "reactivar por saldo" reactiva en más casos que antes (correctamente), lo que dispara
`eliminaLosServiciosDelAddressList()` en más casos — vale la pena confirmar que el fix 5 (ramas de
`billing()` mutuamente excluyentes) sigue cerrando la puerta a la duplicación. Confirmado: en los
3 casos de arriba, exactamente **1** factura por pago (nunca 2).

### 3. Cliente con múltiples servicios activos (nuevo, no probado antes)

Todas las pruebas de hoy usaban clientes con 1 solo servicio activo. Se buscó uno con más y se
encontró el cliente **#1667 (4 servicios activos)**. Al saldar su deuda exacta (balance=0, sin
ciclo nuevo cobrado):

```
estado: Bloqueado -> Activo
facturas nuevas: 4 (una por servicio, exactamente — sin duplicar)
```

Correcto: 1 factura por servicio es el comportamiento esperado (cada servicio es una línea de
facturación independiente), no una duplicación — lo que habría sido un problema es ver 8.

### 4. Limpieza de código (sin cambio de comportamiento)

Se encontró que `processPaymentForClientRecurrentWithGracePeriodActive()` seguía con una variable
local (`$seCobroAlMenosUnCiclo`) y un comentario describiendo el criterio VIEJO de reactivación
("¿se cobró un ciclo?"), aunque el código ya usaba el criterio corregido (`cobrarYActivarCliente()`
ahora devuelve "¿quedó sin deuda?"). No era un bug — el valor seguía siendo correcto — pero el
nombre/comentario podían confundir a quien lea el archivo después, reproduciendo el mismo error
de razonamiento que llevó al bug original. Renombrada a `$quedoSinDeuda`, comentario actualizado.
Commit `87674895`.

### Verificación final

`php artisan pagos:verificar-recurrentes --dry-run` → **3 corridas consecutivas en verde**, 13/13
checks cada vez, en `main` (sin el cambio cosmético del punto 4, que vive en esta rama hasta
mergear — no afecta comportamiento, no necesitaba re-verificación del suite).

### Conclusión

No se encontró ningún bug nuevo en esta revisión — solo confirmaciones positivas de que el fix de
"reactivar por saldo" es consistente con los otros 4 fixes del día (fecha_corte, factura única,
multi-servicio), más una limpieza de claridad de código sin impacto funcional. Lista para
V1.50.

### Commit

`87674895` en la rama `chore/pagos-recurrentes-aclarar-variable-reactivacion` (desde `main`, que
ya incluye el fix de reactivar-por-saldo). Archivo:
`app/Modules/Core/Clientes/Services/ClientBillingService.php`.
