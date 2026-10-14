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

## 2026-10-14 15:30 — demo_vendedor: regla + recálculo, y un hallazgo nuevo (sin UI para verlo)

Mismo patrón que con el técnico: `demo_vendedor` (colaborador #185) tampoco
tenía ninguna regla de Compensación asignada ni liquidación calculada.

- Creada una regla nueva **de demo** ("Vendedores — sueldo base semanal
  (demo)", id=6, `target_type=seller`, `base_salary=$1500.00`,
  `weekly_quota_units=0`) — no existía ninguna regla para `seller` que
  reusar (la única regla real del sistema es la de Técnicos). `cuota=0` a
  propósito: `countBillableUnits()` solo cuenta OTs/tasks de campo/puntos de
  proyecto — nada de eso aplica a un vendedor, así que un piso/techo de
  "unidades" no tendría sentido real para este rol; su desempeño real se
  mide aparte, por la regla semanal de ventas/prospectos (ya evaluada esta
  semana con el motor de reglas, `outcome=bonus`, +$400).
- Asignada (`assigned_at=2026-09-20`) y recalculada la semana 2026-10-03→
  2026-10-10 con `LiquidationService::calculate()`: **Base $1,500.00 ·
  Sobreproducción $0.00 · Otros créditos/débitos $0.00 · Gross $1,500.00**
  (liquidación nueva, id=9, estado borrador).

### Hallazgo — Vendedor no tiene NINGUNA forma de ver esto (ni admin ni Portal)

A diferencia del técnico, el rol **Vendedor no tiene el permiso
`talento.view`** (solo `talento.ventas.view` + documentos propios) →
`/talento/colaborador/185` (la ficha admin, de donde sale la pestaña
Liquidaciones que se arregló hoy) le redirige al dashboard — no puede
entrar ni para ver lo suyo. Y el Portal (`/talento/portal` → "Mi dinero")
**solo muestra la semana EN CURSO** (`dinero/desglose` devuelve
`period_start=2026-10-10` — la semana que sigue, no la que se acaba de
calcular) — no hay ninguna pantalla de "liquidaciones pasadas" para
colaboradores sin `talento.view`. Confirmado que `base_salary=1500` ya se
refleja correctamente en el desglose de la semana en curso (la regla sí
quedó bien asignada), pero la liquidación id=9 en sí (la semana pasada, ya
calculada) es hoy invisible para `demo_vendedor` por cualquier camino de UI
— solo se verificó por BD directamente.

No se tocó ningún permiso para "arreglar" esto (es un cambio de alcance,
no lo que se pidió) — queda anotado para que Irving decida si quiere que
Vendedor también vea su propia ficha/Liquidaciones (como ya ve el técnico)
o si el Portal debería tener una pestaña de historial.

## 2026-10-14 16:00 — Vendedor con acceso igual que técnico + los bonos ya pagan de verdad + bug de duplicado encontrado y corregido

Irving, siguiendo la prueba con `demo_vendedor`, pidió dos cosas:

### 1. Vendedor ve su propia ficha (igual que técnico)

El rol **Vendedor no tenía `talento.view`** → `/talento/colaborador/{su-propio-id}`
le redirigía al dashboard, sin poder ver ni su propia pestaña Liquidaciones
(hallazgo de la vuelta anterior). Migración aditiva
`2026_10_14_160000_grant_talento_view_to_vendedor.php`, mismo patrón EXACTO
que la hermana `grant_talento_admin_lite_to_mostrador`: **solo**
`talento.view` (gate amplio de middleware) — el controller sigue exigiendo
uno-mismo/supervisor/staff para todo lo demás, no destapa ver a otros
colaboradores ni gestionar nada. Verificado en vivo: `demo_vendedor` ya
entra a su ficha, ve "Liquidaciones" con su propia fila, sin Calcular/Cerrar
ni buscador (mismo fix de la vuelta anterior, reusado automáticamente).

### 2. Los bonos de regla semanal ahora pagan de verdad

Decisión explícita de Irving: "los bonos no son informativos, suman a lo
que gana en la semana" — corrige el supuesto con el que se había
construido el motor de reglas (documentado como deuda pendiente en
sesiones anteriores). `TalentoWeeklyRuleEvaluationService::
aplicarBonoAlLedger()` (nuevo, privado) escribe un **crédito real** en el
ledger (`concept=bonus`) en el instante mismo en que se crea una evaluación
con `outcome=bonus` — en los DOS ejes (cantidad y calidad_senal). Mismo
patrón ya usado por `ProjectBonusService::award()` para el bono de
proyecto; a diferencia de una penalización (que exige confirmación humana
de un supervisor), el bono se AUTO-aplica, igual que ya hace
`HealthBonusService` con su bono de calidad por OT. Idempotente por
`reference_type`/`reference_id` (un reintento del comando semanal no
duplica el crédito). Atribuido a MEGAISP (`User::systemBot()`).

**Backfill de las evaluaciones de bono que ya existían antes del fix**
(no se recrean automáticamente solas, porque el guard de "ya evaluado" de
`evaluateUnaRegla()` corta antes de llegar a la rama de bono en una
evaluación repetida): se aplicó manualmente para los 3 casos de **demo**
(`demo_tecnico_alto` ×2 — cantidad $300 + calidad $250 —, `demo_vendedor`
×1 — $400) y se recalcularon sus semanas:
- `demo_tecnico_alto` (col. 183): sin regla de Compensación asignada (base
  $0) → Gross **$550.00** (puro crédito de bonos).
- `demo_vendedor` (col. 185): base $1,500 + bono $400 → Gross **$1,900.00**.

⚠️ **Hallazgo NO aplicado a propósito — DIANA (colaborador real #6, no
demo):** existe una evaluación real `outcome=bonus` de $250
(regla "Mostrador + Vendedor — cuota semanal", semana 2026-10-03→10-10),
generada por el cron real de evaluación semanal — nunca se le aplicó el
crédito (el motor es nuevo). Es un colaborador REAL, así que **no se tocó
sin que Irving lo decida primero**, aunque sea en dev. Queda pendiente:
confirmar con Irving si se aplica también retroactivamente.

### 3. Bug encontrado al verificar visualmente: "Sueldo base" duplicado en el ledger

Al abrir el detalle de `demo_vendedor` tras el recálculo, "Sueldo base"
aparecía **dos veces**. Causa real en `LiquidationService::calculate()`:
el borrado previo a reescribir el ledger de cada recálculo filtraba
`->whereNull('reference_type')`, pero las filas que el MISMO bloque escribe
llevan `reference_type=TalentoLiquidation::class` (nunca null) — el borrado
nunca encontraba sus propias filas anteriores, y cada recálculo de una
liquidación YA existente sumaba una fila nueva sin borrar la vieja. No
afectaba el monto (`gross_pay` se recalcula fresco cada vez, nunca sumando
el ledger existente), pero ensuciaba permanentemente el detalle que sí ve
el colaborador.

**Fix:** se quita el `whereNull('reference_type')` del borrado — el match
`concept` (salary_base/overproduction, exclusivos de este método) +
colaborador + período ya es preciso de sobra. Verificado: recalculado 2
veces seguidas tras el fix, sigue en exactamente 1 fila cada vez.

**Limpieza de los duplicados ya existentes** (barrido de todo el sistema,
no solo demo): se encontraron 2 grupos duplicados reales —
`demo_vendedor` (creado hoy mismo, limpiado) y **BRANDON** (colaborador
real #21, duplicado desde el **2026-09-17**, casi un mes — mismo monto
$2,000, mismo `reference_id`, literalmente la misma fila dos veces). Se
borró la copia más vieja en ambos casos (dedupe mecánico, sin tocar ningún
monto ni liquidación — las dos filas eran idénticas). Barrido final:
**0 grupos duplicados en todo el sistema.**

Rama `feature/talento-bonos-ledger-y-vendedor-acceso`, commits `a3f134b0`
(Vendedor + bonos al ledger) + `1a6ee088` (fix de duplicado), mergeados a
`main`.
