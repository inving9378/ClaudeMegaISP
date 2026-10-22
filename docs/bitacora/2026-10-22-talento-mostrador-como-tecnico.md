## 2026-10-22 — Talento: el Mostrador pasa a ser como un técnico (más órdenes y totales de liquidación)

Pedido: "mostrador ve todos los talentos, y lo que debe ver es su vista del dashboard como los
técnicos, además en la parte de liquidaciones lo que debe ver es lo que debe cobrar cada cual no
hacerle el cálculo de nada". Decisiones confirmadas el mismo día:

- Alcance: como técnico + Liquidaciones (reemplaza "Mostrador = administrador de Talento sin
  crear ni eliminar", del 2026-10-21).
- Liquidaciones: solo ver cuánto cobra cada quien; sin calcular, sin desglose y sin cerrar.
- Sigue creando órdenes de trabajo para los técnicos (se le dio el 28-sep).
- Los permisos de Talento dados directamente a las personas con rol Mostrador se quitan, para que
  queden igual que el rol (se conservan ver y firmar sus propios documentos).

### Qué cambia (rama `fix/talento-mostrador-como-tecnico`)

- Migración `2026_10_22_100000_mostrador_como_tecnico_en_talento`: el rol Mostrador queda con los
  14 permisos de Talento del rol TECNICO más `talento.work_orders.manage`,
  `talento.comisiones.view` y el nuevo `talento.liquidation.totales`. Sin
  `talento.employees.view`, al entrar a Talento cae en su propia ficha, como un técnico. A quien
  tiene el rol se le quitan sus permisos personales de Talento; lo quitado queda en el log de
  Laravel. `down()` no devuelve nada por sí solo.
- `talento.liquidation.totales` (nuevo): abre `/talento/liquidaciones` en modo "solo totales":
  la lista de todos con colaborador, semana, total a pagar y estado; sin Calcular, sin "Ver" y sin
  desglose (el servidor recorta los campos). Lo propio y lo del equipo propio se ve completo. Se
  da también a los roles que ya veían todas las liquidaciones. No se usó
  `talento.liquidation.view` porque también abre finiquitos, la pestaña de liquidaciones en la
  ficha de cualquiera y el desglose desde la app.
- Menú de Talento: enlaces "Órdenes de trabajo" (`talento.work_orders.manage`) y "Liquidaciones"
  (`talento.liquidation.totales`). Hasta ahora no había enlace a esas pantallas; se llegaba desde
  la lista de colaboradores, que el Mostrador ya no ve.

### Permisos personales que se quitan en dev

Tres cuentas reales tienen rol Mostrador (más Vendedor). En dev: una con 38 permisos de Talento
propios (entre ellos `talento.manage`, `talento.liquidation.manage`, `talento.penalties.manage` y
`talento.colaboradores.create`), otra con 32 de solo ver (entre ellos `talento.liquidation.view`
y `talento.penalties.view`) y otra solo con ver y firmar sus documentos. En producción pueden ser
otros: la migración los resuelve por rol y deja en el log quién perdió qué.
