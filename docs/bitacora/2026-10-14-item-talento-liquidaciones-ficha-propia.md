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
