## 2026-10-10 — Comisión real + regla de desempeño (comisiones/penalizaciones) en "Mi progreso"

### Contexto

Pregunta directa: "¿seguro que hiciste todo lo que Irving pidió, incluida la visualización de
las métricas que se definan para ese trabajador en las reglas tanto de comisiones como de
penalizaciones?". Se encontró el mockup original (`~/Imágenes/Panel de Dashboards y Roles
Meganet.png`) y se comparó contra lo construido: el Vendedor del mockup muestra "Comisión
estimada"/"Mis comisiones" — eso no se había construido.

### Investigación antes de construir

Las 3 fuentes de datos posibles estaban vacías en dev:
- `commissions`: 0 filas (ya documentado, item #9990471).
- `talento_compensation_rules`: 1 fila en todo el sistema.
- `talento_weekly_rules`/`talento_weekly_rule_evaluations` (reglas de desempeño semanal,
  comisiones+penalizaciones combinadas, construidas hace 2 días): **0 filas cada una** — nadie
  ha dado de alta ninguna regla todavía.

Irving: "constrúyelo igual, vacío por ahora".

### Qué se construyó

- `DashboardService::reglaDesempeno($colaboradorId)` — última
  `TalentoWeeklyRuleEvaluation` real ya corrida para ese colaborador (nunca calcula una
  hipotética); sin evaluación → `tiene_evaluacion:false`, honesto.
- Vendedor (`TalentoVentasController::misVentas()`): `comision_total` =
  `Commission::where('seller_id', $seller->id)->sum('account_balance')` — el MISMO campo que ya
  usa el admin de Vendedores al registrar pagos de comisión (`PaymentSellerController.php:246`).
  `commissions.seller_id` guarda `sellers.id` (confirmado contra el único INSERT real del modelo,
  `CalculateClientCommission::handle()`, aunque esté comentado/inerte) — NO `users.id`, que es
  el error fácil de cometer aquí. + `regla_desempeno`.
- Técnico (`TalentoColaboradorController::progreso()` + `TalentoFichaCalidad.vue`): tarjeta
  "Regla de desempeño semanal" nueva, mismo shape de datos.
- Portal (`app.js`, tab `mis_ventas`): tarjeta "Comisión acumulada" + "Regla de desempeño
  semanal", mismo criterio.

### Bug real encontrado en el camino (del mismo tipo que el de Mostrador de ayer)

Probando con un vendedor de prueba, "Mi progreso" seguía mostrando "Sin datos de ventas
disponibles." — el rol Vendedor **no tenía `talento.ventas.view`**, pese a que la migración de
hace 2 días (`2026_10_08_133812_grant_talento_ventas_view_to_vendedor_role`) aparece **"Ran"**
en `migrate:status`. `role_has_permissions` confirmó que solo `super-administrator`/
`DESARROLLADOR` lo tenían — Vendedor nunca recibió la fila. Causa exacta sin confirmar (posible
caché de permisos stale en el momento de aquella migración, que haría que su propio guard
`! $role->hasPermissionTo($permission)` se saltara el `givePermissionTo()` real). Se corrigió con
`2026_10_10_000000_regrant_talento_ventas_view_to_vendedor_fix.php` — limpia caché antes/después
y YA NO usa `hasPermissionTo()` como guard previo (ese guard pudo ser justo lo que calló el fallo
original); `givePermissionTo()` de Spatie ya es idempotente por sí solo.

### Verificación

- Backend (tinker, transacción+rollback): `reglaDesempeno()` con una regla+evaluación sintéticas
  (outcome `bonus`) devuelve el shape correcto; `Commission::sum('account_balance')` con una fila
  sintética confirma $777.50 exacto.
- Navegador real, técnico de prueba (sin reglas, caso real de hoy): tarjeta "Regla de desempeño
  semanal" visible con "Sin reglas definidas todavía."
- Navegador real, vendedor de prueba (sin reglas ni comisiones, caso real de hoy) — **antes** del
  fix de permiso: pantalla completa en blanco ("Sin datos de ventas disponibles."); **después**:
  "$ 0.00 Comisión acumulada" + "Sin reglas definidas todavía." + el resto del tab funcionando
  normal.
- Datos de prueba (2 usuarios, 2 colaboradores, 1 seller) borrados y confirmados en cero.

### Estado

Mergeado a `main` + migración de permiso corrida. Con esto, las métricas de comisiones y
penalizaciones del mockup original quedan representadas (hoy vacías, honestas, listas para
poblarse solas en cuanto existan reglas/comisiones reales).

### Fuera de alcance (deuda señalada, no resuelta aquí)

- La tabla `commissions` sigue vacía/rota (item #9990471) — arreglar el motor real de comisiones
  es una decisión de dinero aparte, no se tocó.
- Causa raíz exacta de por qué la migración de hace 2 días quedó "Ran" sin aplicar su efecto
  real — no se investigó a fondo (posible patrón recurrente si vuelve a pasar en otra migración
  de permisos; ya van dos casos en dos días: éste y el de Mostrador/captura-pago, aunque las
  mecánicas de fondo fueron distintas — rama sin mergear vs. permiso sin otorgar pese a "Ran").
