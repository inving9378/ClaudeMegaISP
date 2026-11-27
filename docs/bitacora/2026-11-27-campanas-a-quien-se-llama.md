## 2026-11-27 — Campañas: «A quién se le llama» por estado, zona y caja, con la lista revisada

### Pedido

Al crear una campaña de aviso no aparecían todas las zonas ni las cajas; en la zona 54 salía una sola
caja y ningún cliente. Irving pidió que «A quién se le llama» deje de ser Cobranza / Aviso / Anuncio /
Corte: elegir Activos, Bloqueados o Inactivos, y opcionalmente zona y caja; al dar «Siguiente», la lista
de clientes con una casilla para quitarle la llamada a quien no corresponda. Diseño con impeccable.

### Causa

El selector leía las tablas viejas `districts` / `zones` / `box_zones`. En dev tienen una fila de prueba
de 2024: zona «54», caja «7», un cliente. La caja real del cliente es su nomenclatura `D1Z54C7:3`
(distrito · zona · caja : salida) en `nomenclatures`.

### Decisiones de Irving

1. Cobranza se vuelve una casilla opcional «Solo los que deben» (factura vencida hace N días o más): con
   ella el mensaje puede decir el saldo y al que pague ya no se le llama.
2. Aviso / Anuncio / Corte se quedan como «Motivo» (etiqueta); Corte sigue pidiendo su permiso.

### Qué cambió

- `AudienciaCampana` (servicio nuevo): candidatos Activo/Bloqueado/Inactivo con su nomenclatura (la fila
  que ocupa en `nomenclatures` o, si no, la de su ficha `box_nomenclator`), teléfono y adeudo. Cuenta por
  estado, por zona y por caja con la misma lista a la que se llama.
- `GET /cobranza/campanas/audiencia`: conteos y, con `lista=1`, los clientes para revisar.
- `POST /campanas/{id}/activar`: recibe los filtros y los clientes marcados; solo se llama a los marcados
  que siguen entrando. El que entró después de revisar no se llama sin verse.
- Migración `2026_11_27_120000`: `solo_deudores` (las de cobranza quedan con él) y `audiencia` (filtros y
  a quién se quitó a mano).
- El Blaster decide saldo y «ya pagó» con `solo_deudores` (antes, con el tipo cobranza).
- Se quitan `activar-por-zona`, `alcance` y los catálogos distrito/zona/caja de las tablas viejas.
- Pantalla: alta con Motivo junto al nombre y «A quién se le llama» a la derecha (estados con cuántos hay,
  zona y caja con cuántos clientes, «Solo los que deben», conteo en vivo y «Siguiente: revisar N
  clientes»); revisión con buscador, «Todos / Se les llama / Quitados», casilla de todos, filtros que se
  cambian ahí mismo, y a la derecha a cuántos se llama y la ficha.

### Datos de dev (para dimensionar)

1. Activos y Bloqueados: 3,263 con teléfono; con «solo los que deben», 2,173.
2. Zona 54 (D1): 72 Activos y Bloqueados en 13 cajas.
3. 103 zonas con clientes; los números 20–24, 27 y 28 existen en los dos distritos (se nombran con D1/D2).
4. 1,144 Activos y Bloqueados no tienen caja en la nomenclatura nueva (solo la vieja, texto libre): sin
   filtrar por zona entran; al filtrar por zona no, y la pantalla lo avisa.

### Pruebas

1. `tests/Unit/VoIP/AudienciaCampanasTest.php` (7 nuevas) y las de Voz, Blaster e Historial: verdes.
2. Punta a punta en la base de dev dentro de una transacción deshecha y con `Bus::fake` (nadie recibió una
   llamada): alta, conteo, lista, activar con 4 marcados + 1 de otra zona (entraron los 4), cobranza con
   saldo, «sin mensaje fijo» y «nadie entra» rechazados con 422 dejando la campaña en borrador.
3. Pantalla revisada en una página de prueba con datos inventados: claro y oscuro, 1440, 1024 y 390 px.

## 2026-11-27 (tarde) — Revisión completa: código (revisor independiente) y diseño (impeccable)

Irving pidió revisar todo otra vez. Un revisor independiente leyó el código sin conocer las conclusiones
de quien lo escribió; en paralelo, pasada de pulido de impeccable sobre la pantalla.

### Errores del código, corregidos

1. **Facturas borradas contaban como deuda.** `invoices` usa SoftDeletes y las consultas iban por
   `DB::table` sin `deleted_at`. En dev, con «Solo los que deben»: 1,501 Activos «deudores» → 392 reales.
   A los demás se les habría dicho un saldo que no existe. Ahora un solo criterio
   (`CobranzaCampanaService::facturasVencidas`) para la lista, la carga y el «ya pagó». También afectaba al
   «ya pagó» de antes de este cambio.
2. **Sin «Solo los que deben», la plantilla con {nombre}/{saldo} se leía tal cual a todos.** Se rechaza en
   el alta y al activar; la pantalla lo avisa y no deja seguir.
3. **Duplicar le quitaba «Solo los que deben» a la copia** (watcher del motivo). Ahora va en el cambio
   del select.
4. **Caja de la ficha:** solo cuenta si nadie más ocupa ese puerto; si la escribió Nomenclatura ONU se lee
   como texto, no como id. Sin clientes ni fichas borrados.
5. **Dos activaciones a la vez duplicaban llamadas:** la campaña se bloquea (`lockForUpdate`) al cargar.
6. Más chicos: si la recarga de la lista falla no se deja llamar; Enter no salta el «Siguiente»
   deshabilitado; «quitados» guarda solo los quitados a mano y la lista de campañas ya no manda esos ids;
   estados escritos distinto no tumban la lista; zonas WiFi (D1W10); mensajes de validación en español.

### Pulido de diseño

1. Quitado: fila en gris con «No se le llama» (antes opacidad, contraste bajo).
2. Estados de carga, vacío (sin estados, sin resultados, sin quitados) y error con «Reintentar».
3. Corte sin permiso: se avisa junto al motivo. Detalle y Excel: zona con distrito y cuántos se quitaron.
4. Celular: «Todos» visible junto a la casilla de marcar o quitar a todos. Teclado: orden lógico y foco
   visible en cada control.

### Pruebas

1. Unitarias: AudienciaCampanas (8), VozCampanas (8), BlasterCampanas (44), HistorialCampanas (6): verdes.
2. Punta a punta en dev con el código corregido, en transacción deshecha y `Bus::fake`: deudores sin
   borradas, 422 con variables y sin estados, quitados guardados solo los de a mano, segunda activación
   rechazada, `quitados_n` en la lista, monto de la llamada = adeudo sin borradas.
3. Pantalla: claro, oscuro, 1440, 1024 y 390 px; carga, error, vacío, nombres largos, sin permiso.
