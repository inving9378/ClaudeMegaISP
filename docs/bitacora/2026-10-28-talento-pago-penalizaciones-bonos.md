## 2026-10-28 — Talento: penalizaciones, bonos y reglas de desempeño en el pago

Antes de pasar a la app se probó lo que faltaba de la revisión del 27-oct: que las penalizaciones,
los bonos y las reglas de desempeño lleguen al pago. Semana del 17 al 24 de octubre, cuentas demo
(técnico bajo, medio y alto, Mostrador, vendedor), dentro de una transacción deshecha: la base quedó
con los mismos conteos antes y después (libro, penalizaciones, evaluaciones, liquidaciones, asistencia).

| Prueba | Resultado |
|---|---|
| Penalización a mano «Sin casco» ($150) | el pago baja $150 |
| Apelación aceptada por otra persona | el descuento se regresa (crédito `penalty_reversal`) |
| Reglas de la semana: técnico bajo (126 de 216 puntos) | penalización pendiente de $200; confirmada, baja $200 |
| Reglas: Mostrador y vendedor sin cobros ni ventas | pendientes de $150 y $200; confirmadas, bajan exacto |
| Reglas: técnicos medio y alto (228 y 282) | cumplen, sin cambio |
| Bono por regla (umbral alcanzado) | +$300 automático |
| Lista del Mostrador (Liquidaciones) vs «Calcular» | mismos montos antes y después |

Cómo trabajan las reglas: `talento:evaluar-reglas-semanales` corre los domingos a las 4:00 con la
semana cerrada; la penalización queda `penalty_pending` hasta que el supervisor o administración la
confirma y el bono entra solo al libro. En dev no corre (no hay cron de `schedule:run`).

### Lo que no cuadraba y se corrigió (rama `fix/talento-mi-dinero-pago-real`)

El técnico no veía lo que se le iba a pagar:
- **«Mi dinero › Desglose» → Pago proyectado** (y el avance del administrador y la sección de dinero
  de la app, que leen `LiquidationService::breakdown()`) era sueldo base + sobreproducción: no restaba
  faltas, penalizaciones, préstamo ni fondo, ni sumaba bonos. Con una penalización de $150 el técnico
  veía $2,000 y el Mostrador $1,850.
- **«Mi semana» de la app** sumaba los movimientos del libro al sueldo completo: no descontaba faltas,
  ni el préstamo ni el fondo hasta calcular la semana.
- **«Mi dinero › Cuenta»**, sin calcular la semana, solo trae bonos y descuentos y lo presentaba como
  «Neto de la semana»: con una penalización salía «-$150» en rojo, sin sueldo.

Ahora:
- `LiquidationService::pagoDeLaSemana()` da lo que pagaría «Calcular» (vía `simular()`; si la semana
  está cerrada, lo cerrado) con sueldo base con faltas, sobreproducción, bonos, descuentos y
  préstamo/fondo. `breakdown()` lo trae en `pago` y `projected_pay` = ese monto, así que web, app y
  avance muestran lo mismo que el Mostrador.
- «Mi semana» de la app: `proyectado`, `sueldo_base` y `sobreproduccion` salen de ahí; `otros` es la
  diferencia (bonos menos penalizaciones, préstamo y fondo). La app no necesita actualización.
- «Mi dinero» web: el desglose dice de qué se compone el monto (faltas, bonos, penalizaciones,
  préstamo y fondo, solo lo que aplica) y «Pago de la semana» si ya se cerró. La cuenta, sin calcular,
  dice «Bonos y descuentos de la semana» y que el sueldo entra al calcularla (`calculada` en
  `dinero/cuenta` y en `equipo/compensacion` de la app). «Penalización revertida» ya tiene nombre.

Probado con el código de la rama, en transacción deshecha: desglose = «Calcular» para las 5 cuentas en
la semana en curso y la pasada (incluida una falta: $1,714.29 en vez de $2,000); «Mi semana» de la app
$1,850 = $2,000 − $150; cuenta sin calcular `calculada=false`, ya calculada neto = desglose = $1,850;
semana cerrada devuelve lo cerrado; supervisor en la app y avance del administrador iguales. En
navegador como técnico (vista previa, con las respuestas cambiadas en el navegador para simular la
penalización): textos y montos bien, sin errores.

Pendiente para la app: la sección de dinero (`SeccionDetalleScreen`, «Neto de la semana») debe usar
`calculada` igual que la web.
