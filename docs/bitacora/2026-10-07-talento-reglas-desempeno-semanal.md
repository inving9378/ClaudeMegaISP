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
- `npm run dev` compila limpio; componente confirmado dentro del bundle generado (32 referencias).

## 2026-10-07 — Fusionado a main, migrado, y probado de punta a punta contra datos reales

Merge limpio a `main` (`85dc2670`, solo los 15 archivos de este cambio). Las 2 migraciones
corrieron sin error.

### Verificación — transacción + rollback, usando ACTIVIDAD REAL de un vendedor real

En vez de fabricar datos sintéticos de CRM (frágil: `getSales()` depende de un accessor pesado,
`ClientMainInformation::getServiceAttribute()`, que calcula el costo del servicio contratado vía
`ClientRepository` — no es un campo simple de poner a mano), se ubicó un vendedor real con
actividad real documentada (GUADALUPE, user 9, colaborador 7, rol `Vendedor`) y una semana
calendario real con conteos reales confirmados por los propios métodos de
`CalculateBalanceSellerService` (2026-09-14 a 2026-09-20: 1 prospecto, 5 ventas) — así la prueba
ejercita el camino real de conteo, no un doble ficticio.

8 casos, todos dentro de la misma transacción (rollback al final, nada persistió):

1. Piso de ventas=10 (por encima del real=5) → `penalty_pending`, con `penalty_type_id`/monto
   correctamente resueltos de la regla.
2. Piso=1 (cumplido) + techo=3 (superado por el real=5) → `bonus`, con `bonus_amount_applied`
   correcto.
3. Piso=1 (cumplido) + techo=999 (no alcanzado) → `meets_quota`.
4. Sin ninguna regla cargada → `no_rule`.
5. Usuario con Vendedor+Mostrador, existiendo una regla para "Vendedor" solo Y otra para el combo
   exacto "Mostrador+Vendedor" → gana la del combo exacto (confirmado por id).
6. Mismo usuario, borrada la regla del combo exacto, solo queda la de "Vendedor" solo → cae al
   subconjunto más específico que sí existe (confirmado por id) — la regla de Irving verificada
   tal cual.
7. Llamar `evaluateColaborador()` dos veces para la misma semana → misma fila, no duplica
   (`UNIQUE(colaborador_id, week_start)` funcionando).
8. Confirmar una penalización pendiente → reusa `PenaltyService::apply()` real: penalización queda
   `status=applied`, monto correcto, y la evaluación pasa a `penalty_applied` con `penalty_id`
   enlazado — el mismo camino de dinero que ya usa el flujo humano existente, sin atajos.

Tras el rollback, confirmado por consulta directa: 0 filas en `talento_weekly_rules`/
`talento_weekly_rule_evaluations`, el rol `Mostrador` temporal del caso 5/6 ya no está en el
usuario 9, y 0 penalizaciones reales creadas en el colaborador 7 — nada tocó datos reales.

### Pendiente

- Prueba visual en navegador: crear una regla desde la UI, marcar "tiene más roles", confirmar
  que el rol base no aparece en el multiselect — lo verificado hasta ahora es el motor completo
  por tinker, no un clic real en pantalla.
- Que Irving cree las reglas reales (sus umbrales de piso/techo) y asigne el permiso
  `talento.reglas_desempeno.manage` a quien corresponda — nada de esto trae datos de ejemplo,
  hoy la tabla de reglas está vacía a propósito.
