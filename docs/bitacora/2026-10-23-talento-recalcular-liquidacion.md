## 2026-10-23 — Talento: recalcular una liquidación ya no cobra dos veces préstamos y fondos

Hallado al revisar lo que ve el Mostrador en Liquidaciones.

### Qué pasaba

«Calcular» la misma semana más de una vez (lo normal mientras está en borrador):

- el préstamo se volvía a cobrar (el saldo bajaba otra vez) y el fondo volvía a sumar;
- el monto a pagar restaba el abono y la aportación del cálculo anterior (asiento en el ledger)
  además de los nuevos.

Probado en una transacción que se deshizo, con un préstamo de 500 (100 por semana) y un fondo de
50 por semana: 1.ª vez a pagar 3,183.33 y préstamo 400; 2.ª vez 3,033.33 y préstamo 300; 3.ª vez
3,033.33 y préstamo 200.

En dev no hay préstamos ni fondos activos, así que no hay datos dañados. En producción no se sabe.

### Qué cambia (rama `fix/talento-recalcular-liquidacion`)

- `LiquidationService::calculate()` corre todo en una transacción y, antes de calcular, deshace
  lo que un cálculo anterior aplicó en esa semana: `LoanService::revertirDeducciones()` regresa el
  abono al saldo del préstamo y `FundService::revertirDeducciones()` quita la aportación del fondo,
  y borran sus asientos. Después se calcula y se aplica una sola vez, como la primera.
- La aportación a un fondo ya gastado no se deshace: su asiento se queda y se descuenta una vez.
- Una liquidación cerrada sigue sin poder recalcularse y no se toca nada.

### Pruebas (transacción que se deshace, base de dev)

16 comprobaciones: tres cálculos seguidos dan el mismo monto y cobran una vez; la semana siguiente
sigue desde el saldo correcto; préstamo que se termina de pagar esa semana; fondo que llega a su
meta; fondo gastado antes de recalcular; penalización capturada a mano; liquidación cerrada. En
main fallan 4. Por el botón (`POST /talento/api/liquidaciones/calcular`) como administrador: en la
rama 3,233.33 / 3,233.33 y préstamo 400 / 400; en main 3,233.33 / 3,133.33 y 400 / 300.
