## 2026-10-07 — Reglas de desempeño semanal por combinación de roles (Talento)

Pedido de Irving, tras preguntar si existían reglas de comisiones/penalizaciones estilo "80
prospectos y 2 ventas mínimo a la semana → penaliza si entrega menos, comisiona si entrega más"
(investigado antes: no existía nada así — ni en el catálogo de Penalizaciones, que es
disciplinario, ni en Comisiones, que no tiene concepto de meta semanal; lo más cercano era un
armazón sin cablear del item #121).

Requisito clave de Irving, detallado después: las reglas no son por un solo rol — son por
**combinación EXACTA de roles**. "Mostrador" solo tiene sus reglas; "Mostrador + Vendedor" tiene
reglas propias, distintas tanto de "Mostrador" solo como de "Vendedor" solo. UI: eliges un rol
base, un checkbox "tiene más roles" despliega un multiselect con todos los demás roles **excepto**
el base ya elegido.

### Investigación previa (3 forks en paralelo) — nada se inventó

- **Conteo de prospectos/ventas**: se reusan tal cual los métodos YA PRODUCTIVOS
  `CalculateBalanceSellerService::getProspects()/getSales()` — el mismo motor que ya usa
  Comisiones de Vendedores. Confirmado por tinker que `owner_id`/`seller_id` en esas queries
  guardan `users.id`, no `sellers.id` (pese a que la relación del modelo CRM sugiere lo
  contrario) — verificado contra datos reales antes de confiar en el método.
- **Ventana semanal**: `PayWeek::lastCompleted()` — primera vez que se reusa fuera del motor de
  compensación de técnicos.
- **Penalización**: reusa el catálogo de Penalizaciones ya existente (decisión de Irving) — la
  regla nueva solo referencia un `penalty_type_id` ya existente.
- **Multiselect de roles**: se replicó EXACTO el patrón `FormRule.vue:61-93` (`q-select multiple
  use-chips emit-value map-options` + slot `#option` con `q-checkbox`) — el único multiselect real
  que ya existía en todo el proyecto para "elegir varios de una lista".
- **Decisión de arquitectura explícita**: NO se tocó `commissions_rules`/`CommissionRule` (motor
  grande, ya en producción real pagando comisiones, asignado por vendedor individual vía pivot) —
  se construyó una tabla/servicio nuevos y separados, acotados a este caso.

### ⚠️ Ajuste real durante la construcción — el robot nunca aplica dinero solo

Al revisar `PenaltyService::apply()` para la pieza de "no cumplió el piso → penaliza", se encontró
que aplicar una penalización escribe de inmediato un cargo contable REAL e INMUTABLE
(`TalentoLedgerEntry` DEBIT), y el sistema ya exige a propósito que la aplique un supervisor
humano (`applied_by`, con guard que impide auto-penalizarse). El plan original decía "el robot
crea la penalización real" — se pausó la implementación, se le explicó el hallazgo a Irving, y
decidió: **el robot solo la deja `penalty_pending`** (tipo/monto ya resueltos por la regla); un
supervisor humano la confirma con un clic desde la UI nueva, que por dentro llama al MISMO
`PenaltyService::apply()` ya existente — mismo guard de autorización, mismo ledger, cero caminos
de dinero paralelos.

### Qué se construyó

- `talento_weekly_rules` (regla: combinación de roles normalizada en JSON, piso de
  prospectos/ventas, `penalty_type_id`, techo de bono, monto del bono, activa) y
  `talento_weekly_rule_evaluations` (resultado real por colaborador/semana, idempotente vía
  `UNIQUE(colaborador_id, week_start)`, con `outcome` ∈ `penalty_pending|penalty_applied|bonus|
  meets_quota|no_rule`).
- `TalentoWeeklyRuleEvaluationService::evaluateColaborador()` — resuelve la regla aplicable
  (match exacto de roles gana siempre; si no hay exacto, cae al subconjunto de roles más
  específico que sí tenga regla — decisión de Irving), cuenta prospectos/ventas de la semana,
  decide `penalty_pending`/`bonus`/`meets_quota`/`no_rule`.
- Comando `talento:evaluar-reglas-semanales`, programado domingo 04:00 (después del corte de
  PayWeek del sábado 18:00), `withoutOverlapping()->onOneServer()`.
- `TalentoReglaDesempenoController`: CRUD de reglas (bloquea 2 reglas activas para la misma
  combinación exacta — evita ambigüedad silenciosa en el resolver), lista de evaluaciones, y
  `confirmarPenalizacion()` (el único punto que de verdad mueve dinero, reusando el servicio
  existente).
- `TalentoReglasDesempeno.vue` — tab "Reglas" (tabla + modal crear/editar) y tab "Pendientes de
  confirmar" (con alerta explícita de que confirmar descuenta dinero real). Entrada en el sidebar
  vía `module.json` (declarativo, sin tocar Blade).
- Permisos `talento.reglas_desempeno.view`/`.manage`, sin grant a ningún rol de negocio (política
  nueva, no un espejo de algo ya verificado) — Irving asigna desde el editor de roles.

### Verificado

- `php -l` en todos los archivos nuevos.
- `php artisan migrate --pretend` confirma el SQL correcto de las 2 migraciones (tablas +
  permisos).
- `npm run dev` compila limpio; componente confirmado dentro del bundle generado (32 referencias).
- **Pendiente**: la migración NO se ha corrido de verdad (guardrail de migraciones — rama sin
  ruta a main todavía) — la verificación por transacción+rollback contra datos reales (regla
  sintética para `['Mostrador','Vendedor']`, confirmar resolución de regla, conteos reales de
  CRM/ventas, los 3 outcomes) queda para después de fusionar, igual que se hizo con el ítem de
  ubicación manual de Talento el 6 de octubre.

### Pendiente

- Fusionar a `main` + correr las 2 migraciones + la batería de pruebas transaccionales descrita
  en el plan.
- Prueba visual en navegador: crear una regla, marcar "tiene más roles", confirmar que el rol
  base no aparece en el multiselect.
- Que Irving cree las reglas reales (sus umbrales de piso/techo) y asigne el permiso
  `talento.reglas_desempeno.manage` a quien corresponda — nada de esto trae datos de ejemplo.
