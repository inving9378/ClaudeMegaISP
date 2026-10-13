## 2026-10-13 10:15 — Reglas de desempeño semanal, Fase 2/5 (diseño + motor + 8 reglas reales)

### Hallazgo de arranque — el motor solo podía medir Vendedor

Antes de crear ninguna regla se investigó el motor real
(`TalentoWeeklyRuleEvaluationService::evaluateColaborador()`). Aunque la tabla
`talento_weekly_rules` tiene un campo `roles` genérico (JSON, cualquier combinación), el servicio
llenaba `prospects`/`sales` **siempre** desde `CalculateBalanceSellerService::getProspects()/
getSales()` — consultas 100% de CRM/ventas (`owner_id`, `seller_id`). Un técnico o un mostrador
evaluados ahí **siempre darían 0/0**, sin importar su rol o su trabajo real. El motor, tal como
existía, solo podía expresar cuotas de vendedor — no era el motor genérico "para cada rol" que el
pedido original necesitaba.

### Cambio de código (rama `talento-reglas-desempeno-fase2`, mergeada a `main`)

- **Migración aditiva** `2026_10_13_090000_add_metric_type_to_talento_weekly_rules.php`: columna
  `metric_type` enum('ventas','tecnico','mostrador') default 'ventas' (retrocompatible — 0 filas
  existían, nada que migrar). Se **reusan** `min_sales`/`bonus_sales_threshold`/`bonus_amount`/
  `penalty_type_id` como el piso/techo/bono de "unidades" cuando `metric_type != 'ventas'` — se
  evitó duplicar columnas para el mismo concepto.
- **`TalentoWeeklyRuleEvaluationService::medirUnidades()`** (nuevo, privado): por `metric_type`,
  calcula:
  - `tecnico` → `TalentoWorkOrder` del colaborador, `status IN (completed,validated)`, por
    `created_at` en la semana — **el mismo criterio exacto** que ya usa
    `DashboardService::calidadTecnico()` para "completados" en "Mi progreso". A propósito: para
    que la regla NUNCA mida un número distinto al que el colaborador ya ve en su propio
    dashboard.
  - `mostrador` → `Payment::where('add_by', $userId)` por `created_at` en la semana — mismo
    criterio que `DashboardService::progresoMostrador()`.
  - `ventas` (default) → sin cambio, el comportamiento de siempre.
- **UI** (`TalentoReglasDesempeno.vue`): selector "Qué mide esta regla" (Ventas/Técnico/Mostrador)
  en el modal de alta/edición; los campos de piso/techo se relabelan dinámicamente ("ventas" vs
  "completados" vs "pagos capturados"); la lista y la pestaña "Pendientes de confirmar" muestran
  un badge de métrica y las unidades con su nombre correcto.
- **Validación del controller**: `metric_type` ahora `required|in:ventas,tecnico,mostrador`.

### 8 reglas reales creadas (combinaciones EXACTAS de rol, sin ambigüedad)

Se auditó primero qué combinaciones de rol existen de verdad entre colaboradores activos (excluyendo
roles de sistema): `TECNICO+Vendedor` (2), `Mostrador+Vendedor` (2), `Vendedor` (5),
`Administrador+Vendedor` (1), `Mostrador+TECNICO+Vendedor` (1) — y se creó una regla por cada
combinación real, más las combinaciones "limpias" de un solo rol (hoy sin colaboradores activos,
pero son la forma esperada de una alta nueva). Esto evita el empate de `resolveRule()` (dos reglas
de subconjunto del mismo tamaño competirían por "id mayor", arbitrario) para cualquier combo real —
verificado con la reflexión del método privado contra los 8 combos reales + `client` (sin regla,
correcto) + `Administrador+Vendedor` (cae a la regla `Vendedor` por subconjunto único, sin empate).

El criterio de prioridad para combos (técnico gana sobre vendedor/mostrador; mostrador gana sobre
vendedor) replica EXACTO el ya decidido en `Actor::trackPrincipal()` (27-ago/08-oct) — "un técnico
que también carga Vendedor sigue siendo técnico para efecto de qué pantalla/métricas le
corresponden".

| Regla | Combinación | Métrica | Piso | Techo (bono) | Bono | Penalización |
|---|---|---|---|---|---|---|
| Técnico (×5: planta, instalador, TECNICO solo, +Vendedor, Mostrador+TECNICO+Vendedor) | ver arriba | Completados/semana | 36 | 45 | $300 | $200 |
| Mostrador (×2: solo, +Vendedor) | ver arriba | Pagos capturados/semana | 15 | 25 | $250 | $150 |
| Vendedor | Vendedor | Prospectos+ventas/semana | 8 prospectos / 2 ventas | 15 / 4 | $400 | $200 |

⚠️ **El piso de Técnico (36/semana) es el ÚNICO número con respaldo real**: coincide exacto con
`talento_compensation_rules` (id=5, "Técnicos — cuota diaria", `weekly_quota_units=36`, ya vigente
desde el 17-sep). **Los demás números (Vendedor, Mostrador, todos los techos de bono y TODOS los
montos en pesos) son valores de arranque/borrador** — sin dato real de producción que los respalde
(no hay actividad de pagos/ventas suficiente en dev para calibrarlos). Quedan editables desde
`/talento/reglas-desempeno` → "Editar" en cualquier momento; **ninguna penalización se cobra sola**
— el motor solo dejar `penalty_pending`, un supervisor humano tiene que darle "Confirmar" para que
se descuente dinero real (candado ya existente, intacto).

### Verificación

- `php -l` limpio en los 4 archivos PHP tocados; `npm run dev` compiló sin errores nuevos.
- Las 8 reglas se crearon **a través del formulario real** (una, Mostrador, clic a clic con
  Playwright — confirmó que el selector de métrica + relabel dinámico funcionan en el navegador) y
  **vía la API real ya autenticada** (las otras 7 — mismo endpoint/validación, sin atajos).
- `resolveRule()` (invocado por reflexión, sin reimplementar su lógica aparte) resuelve
  exactamente la regla esperada para los 8 combos reales, sin empates.
- `php artisan talento:evaluar-reglas-semanales` corrió limpio contra los 13 colaboradores
  activos reales de dev (semana 2026-10-03→10-10): confirmó que el motor SÍ mide números
  distintos por rol — Diana (Mostrador) devolvió 292 pagos capturados reales → `bonus`
  correctamente disparado (292 ≥ piso 15 y ≥ techo 25); los técnicos devolvieron 0 completados
  (no hay OTs completadas en esa ventana en dev) → `penalty_pending`. **Las 13 filas de esa
  corrida de prueba se borraron después** (eran una prueba contra colaboradores reales con
  umbrales todavía borrador — dejarlas habría puesto "penalizaciones sugeridas" de verdad en la
  cola de un supervisor sin que Irving hubiera revisado los números todavía). Las 8 reglas
  SÍ quedan vivas — la próxima corrida real es el cron de los domingos 04:00, ya programado.
- Cuenta de prueba `prueba_fase2_admin` creada y borrada al cierre (confirmado en 0).

### Estado

Fase 2 cerrada. El motor ya puede medir y evaluar correctamente a técnico, vendedor y mostrador por
separado, con 8 reglas reales activas cubriendo cada combinación real de rol en dev. Pendiente de
que Irving revise/ajuste los montos en pesos y los techos de bono de Vendedor/Mostrador (el piso de
Técnico ya tiene respaldo real). Sigue la Fase 3: verificar el cálculo end-to-end entrando como cada
rol y confirmando que "Mi progreso" muestra la evaluación real (requiere datos sintéticos de OTs
completadas/pagos capturados/ventas para poder ver los 3 desenlaces —bono/cumple/penalización— no
solo "sin reglas definidas").
