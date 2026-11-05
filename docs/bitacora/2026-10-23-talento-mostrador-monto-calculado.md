## 2026-10-23 — Talento: el Mostrador ve en Liquidaciones el monto a cobrar de cada quien, ya calculado

Pedido: «verifica que el mostrador cuando vea los talentos en la pestaña de liquidaciones solo
pueda ver el monto a cobrar, por lo que ahí debería tener el cálculo hecho para poder mostrarlo».
Decisiones: calculado al ver (sin guardar nada), por omisión la semana que toca pagar.

### Qué había

Lo que veía el Mostrador ya estaba acotado (nombre, semana, «A pagar» y estado; sin Calcular,
sin desglose ni finiquito: 403). Pero solo veía las liquidaciones que un administrador ya había
calculado: en dev, 5 de 13 colaboradores activos y nada de la semana en curso.

### Qué cambia (rama `feat/talento-mostrador-monto-calculado`, sobre `fix/talento-recalcular-liquidacion`)

- `LiquidationService::simular()`: corre el mismo «Calcular» dentro de una transacción que se
  deshace. El monto es exactamente el del botón y no se guarda nada (ni préstamos ni fondos).
- `TalentoLiquidacionController::data()` en modo solo totales: de una semana (por omisión la que
  toca pagar; selector con la semana en curso y las 8 anteriores, ninguna antes del arranque de
  liquidaciones), cada colaborador activo y cualquiera con liquidación esa semana. Liquidación
  cerrada → su monto («Cerrada»); si no, el monto calculado («Por cerrar» o «En curso»).
- Nota «sin regla de pago» bajo el monto de quien no tiene regla asignada (su monto sale en cero
  sin que eso quiera decir que no se le debe nada). En dev son 7 colaboradores reales.
- Pantalla: para el Mostrador solo dos columnas, colaborador y «A pagar» («cerrada» o «sin regla
  de pago» debajo del monto cuando aplica), con el selector de semana y el aviso «Monto calculado
  al momento…»; sin buscador, período ni estado («no que ella pueda buscar uno a uno, esa info
  debe estar explícita ahí, nada más»). El administrador y el colaborador en su ficha ven lo mismo
  que antes.

### Pruebas (base de dev)

- Como Mostrador, sin transacción de por medio: la consulta no cambia nada (`CHECKSUM TABLE` de
  liquidaciones, asientos, préstamos, fondos y bitácora idéntico antes y después); ~300 ms.
- Aparecen los 13 activos; los 13 montos coinciden con «Calcular»; semana en curso → «En curso»;
  semana inválida → la que toca pagar; liquidación cerrada → su monto; búsqueda por nombre.
- Con un préstamo activo: ver dos veces descuenta el abono una vez y no toca el saldo; tras un
  «Calcular» del administrador el Mostrador ve el mismo monto (sin descuento doble).
- Administrador (lista con desglose y Calcular) y técnico en su ficha: sin cambios. El Mostrador
  sigue sin poder abrir un desglose (403).
- En el navegador como Mostrador: 13 filas, sin Calcular ni Ver, selector de semana y estados.
