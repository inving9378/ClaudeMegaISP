## 2026-10-28 — Talento: comprobante de pago para imprimir

Pedido de Irving (video revisado el 28-oct): que el Mostrador solo vea cuánto se le paga a cada
persona y le imprima su «ticket de pago». La lista ya existía (Talento › Liquidaciones, monto «A
pagar»); faltaba el comprobante.

Qué se hizo (rama `feat/talento-comprobante-pago`):
- Botón **Comprobante** en cada fila de la lista de pagos (Mostrador) y en la tabla de liquidaciones
  (administración). No sale en quien no tiene sueldo dado de alta («sin regla de pago»).
- Abre una ventana con la vista previa y los botones **Imprimir** y **Descargar**. Es un ticket de
  80 mm, igual que el ticket de caja, para la misma impresora.
- El comprobante lleva: folio (`PAGO-<ficha>-<fin de semana>`), semana, nombre y puesto, unidades,
  faltas, sueldo base, sobreproducción, bonos, penalizaciones y descuentos, préstamo y fondo (solo lo
  que aplica), el total con letra, si la semana está cerrada / en curso / por cerrar, la leyenda
  «Recibí la cantidad de…», la línea de firma, quién lo entregó y cuándo se imprimió.
- El monto es el mismo de la lista: lo cerrado, o lo que pagaría «Calcular» sin guardar nada
  (`LiquidationService::pagoDeLaSemana()`, que ahora también da las unidades).
- `GET /talento/liquidaciones/comprobante?colaborador_id=&semana=` con `talento.liquidation.totales`
  o `talento.liquidation.view`; solo semanas de pago válidas (empiezan en sábado, no futuras), solo
  personas de la lista (activas o con liquidación esa semana) y con sueldo dado de alta (si no, 422).

Probado, en transacción deshecha (conteos de libro, asistencia, penalizaciones y liquidaciones iguales
antes y después):
- Técnico de prueba con una falta, un bono de $300 y una penalización de $150: comprobante y lista
  dan lo mismo, $1,864.29 («MIL OCHOCIENTOS SESENTA Y CUATRO PESOS 29/100 M.N.»).
- Técnico sin permiso → 403; persona sin sueldo → 422; semana que no empieza en sábado o futura → 404.
- En el navegador (vista previa de la rama) como Mostrador y como administrador: el botón sale en las
  7 personas con sueldo y no en las que no lo tienen; el PDF llega (200, application/pdf); sin errores.

Pendiente aparte: en dev las 10 personas reales no tienen sueldo dado de alta, así que su «A pagar»
sale en $0.00 y no tienen botón hasta que se capture su regla de pago (dato de Irving).
