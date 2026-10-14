## 2026-10-14 14:40 — Liquidaciones de la ficha propia: 403 silencioso + picker admin de sobra

Irving probando como `demo_tecnico_bajo` reporta dos síntomas en la pestaña
"Liquidaciones" de su propia ficha:
1. El botón "Calcular" pide elegir un colaborador, cuando debería ver/operar
   únicamente su propia cuota.
2. No aparece ninguna liquidación pese a que la semana pasada se le insertaron
   datos demo (dos penalizaciones confirmadas).

### Investigación

Reproducido en vivo (Playwright, cuenta `demo_tecnico_bajo` con password fijada
para la prueba). Causa raíz en dos capas independientes:

1. **Ruta nunca registrada.** `GET/POST /talento/api/liquidaciones`,
   `/talento/api/liquidaciones/calcular`, `GET /talento/api/liquidaciones/{id}`
   y `POST /talento/api/liquidaciones/{id}/cerrar` no aparecían en NINGÚN
   bloque de `config/route_permission.php`. `CheckRoutePermission` deniega por
   defecto cualquier ruta sin match → 403 para todos salvo el bypass
   admin/DESARROLLADOR. El componente `TalentoLiquidaciones.vue` SÍ mandaba
   `colaborador_id=181` correctamente (prop ya conectada desde antes), pero el
   request nunca llegaba al controller.
2. **`TalentoLiquidacionController::data()`/`show()`** exigían
   `talento.liquidation.view` a secas, sin la excepción "es mi propia ficha"
   que SÍ existe en el resto de la ficha (`$tieneAccesoAmplio` en
   `TalentoColaboradorController::ficha()`). Y el botón "Calcular" del
   componente abre SIEMPRE el modal con buscador de colaborador —  diseñado
   para la pantalla admin standalone `/talento/liquidaciones`, sin distinguir
   si está embebido en la ficha de alguien.

Verificado que la fila de datos SÍ existía (`talento_liquidations` id=8,
colaborador 181, período 2026-10-03→2026-10-10, `gross_pay=-400.00`,
ledger con las 2 penalizaciones de $200 confirmadas antes en esta misma
sesión) — el "no hay compensaciones" no era falta de datos, era el 403
silencioso impidiendo que el frontend los leyera.

### Fix

- `config/route_permission.php`: las 2 rutas de LECTURA
  (`/talento/api/liquidaciones`, `/talento/api/liquidaciones/{id}`)
  registradas bajo `talento.view` (lo tiene casi cualquier rol de Talento,
  mismo criterio que `/talento/api/colaboradores/{id}/progreso`) — el
  scoping real vive en el controller. Las 2 de ESCRITURA
  (`/calcular`, `/{id}/cerrar`) registradas bajo `talento.liquidation.manage`,
  **sin** excepción de autoservicio (nadie se auto-calcula/auto-cierra su
  propio pago — mismo criterio que `ordenes_manage`/`compensacion_manage`).
- `TalentoLiquidacionController::data()`/`show()`: cuando el caller no tiene
  `talento.liquidation.view`, se acota a self + subordinados directos (mismo
  patrón ya usado hoy mismo en
  `TalentoReglaDesempenoController::evaluationsIndex()`).
- `TalentoColaboradorController::ficha()`: nuevo flag
  `permisos.liquidaciones_manage` (`talento.liquidation.manage` o ser su
  supervisor directo), análogo a los ~10 flags `_manage` ya existentes.
- `TalentoLiquidaciones.vue`: nuevo prop `puedeGestionar` (default `false`);
  oculta "Calcular" y "Cerrar" cuando está embebido en una ficha propia sin
  ese permiso — mismo patrón ya usado por `TalentoCompensacion.vue`
  (`v-if="!colaboradorId || puedeGestionar"`).
- `TalentoColaboradorFicha.vue`: pasa `:puede-gestionar="permisos.liquidaciones_manage"`.

### Verificación en vivo

- `demo_tecnico_bajo` → pestaña Liquidaciones: sin botón "Calcular", ve
  directo su propia fila (período 3 oct–10 oct, Gross -$400.00, Borrador),
  botón "Ver" abre el detalle con el ledger de las 2 penalizaciones; sin
  botón "Cerrar".
- Cuenta de prueba desechable con `talento.liquidation.manage` (creada y
  borrada solo para esta verificación): el buscador de colaboradores del
  modal "Calcular" SÍ sugiere en vivo al escribir — probado con "demo" →
  aparecen las 5 cuentas demo existentes. Sin cambio de comportamiento ahí
  (ya funcionaba; se verificó porque Irving preguntó explícitamente por los
  roles que sí gestionan, ej. Mostrador/admin).

Rama `fix/talento-liquidaciones-propia-ficha`, commit `7fea90fa`.

## 2026-10-14 15:10 — Segunda vuelta: buscador seguía ahí + regla de Compensación de prueba

### Hallazgo 2 (reportado por Irving probando en vivo)

Tras el fix anterior, `demo_tecnico_bajo` ya no veía el botón "Calcular", pero
el cuadro "Buscar colaborador…" de la fila de **filtros** (independiente del
modal "Calcular", arriba de la tabla) seguía visible — "está por gusto si no
puede buscar a nadie". Correcto: ese input es una pieza aparte del botón que
ya se ocultó.

**Fix:** mismo guard (`!colaboradorId || puedeGestionar`) aplicado también a
ese input. De paso se encontró que `TalentoLiquidacionController::data()`
nunca leía ese parámetro — ni siquiera en la pantalla admin (dead param desde
siempre) — se conectó con el mismo patrón `whereHas` que ya usa
`TalentoColaboradorController`. Rama `fix/talento-liquidaciones-buscador-propio`,
commit `24ed7a28`, mergeada a `main`.

Verificado en vivo: ya no aparece ningún buscador en la pestaña propia; solo
los filtros de estado/fecha (que sí hacen algo sobre su propio historial).

### Compensación — regla asignada + recálculo de la semana (dato demo, sin código)

Irving pidió, tras preguntarle qué es la pestaña Compensación, asignarle una
regla real a `demo_tecnico_bajo` (colaborador #181, sin regla hasta ahora —
por eso su liquidación mostraba Base $0.00) y recalcular su semana.

- Reusada la ÚNICA regla activa que ya existía en el sistema (sin duplicar):
  **"Técnicos — cuota diaria (4 instalaciones o 12 garantías)"** (id=5,
  `target_type=technician`, `base_salary=$2000.00`, `weekly_quota_units=36`
  — el mismo 36 que ya usaba la regla semanal de penalización de este mismo
  técnico, "Incumplimiento de cuota semanal — Técnico (36 completados/semana)").
- Asignada vía `TalentoCompensationRuleHistory` (mismo flujo inmutable que usa
  `TalentoCompensacionController::assignRule()`), `assigned_at=2026-09-20`
  (antes del período a recalcular, para que `calculate()` la tome como vigente
  esa semana).
- Recalculada la semana 2026-10-03→2026-10-10 vía
  `LiquidationService::calculate()` (el mismo servicio real que usa el botón
  "Calcular" — sobrescribe el borrador existente id=8, no duplica).

**Resultado verificado en vivo** (modal de detalle, cliente):
Sueldo base $2000.00 · Sobreproducción $0.00 (0 unidades, por debajo de la
cuota de 36) · Ledger: 2 penalizaciones de $200.00 (las mismas confirmadas
antes) · **Gross final $1,600.00** ($2000 − $200 − $200). Cuenta exacta,
pipeline completo (regla de compensación → cálculo real → ledger →
liquidación) verificado de punta a punta con datos demo.

Sin cambio de código en esta parte — solo datos de prueba vía los mismos
servicios reales que usa la UI.
