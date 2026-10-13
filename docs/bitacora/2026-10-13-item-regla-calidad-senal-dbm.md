## 2026-10-13 17:30 — Motor de reglas extendido con calidad de señal (dBm)

### Contexto

Irving, con un ejemplo concreto: "si un técnico hace 1 instalación en vez de 2, se penaliza...
si la calidad de la fibra da más ruido del que pongo, también se penaliza... si se mantiene
limpia, se da un bono — y esto se define como regla, igual que cantidad." Confirmó: extender el
motor en vez de tocar el sistema de "Bono de salud de red" ya existente (separado, solo premia,
no es configurable por rol).

### El cambio de arquitectura real

Cantidad y calidad son ejes de negocio **independientes** — un técnico puede cumplir cantidad y
fallar calidad, o viceversa. La Fase 2 asumía "una regla activa por combinación de roles" = una
sola regla por colaborador por semana. Eso ya no alcanza: hubo que cambiar la unicidad de
`(colaborador_id, week_start)` a `(colaborador_id, week_start, rule_id)` y que
`TalentoWeeklyRuleEvaluationService::evaluateColaborador()` devuelva una **Collection** (una fila
por cada metric_type con regla activa que le aplique), no una sola fila. `resolveRule()` ahora
filtra también por `metric_type`, y el checkeo de "regla duplicada" al crear/editar quedó acotado
a la misma métrica — un técnico SÍ puede tener a la vez una regla de cantidad y una de calidad.

### Cómo mide la calidad (sin inventar un cálculo nuevo)

Reusa exacto `loss_db` que ya calcula `HealthBonusService` (bono automático por-instalación, Fase
4b, anterior a esta sesión) vía `TalentoHealthBonusLog` — nunca se recalculó la pérdida de señal
en paralelo. "Unidades" de esta regla = instalaciones de la semana con `loss_db` por encima del
umbral **propio de la regla** (`max_loss_db`, columna nueva — deliberadamente independiente del
setting global de salud de red, porque Irving "lo pone" al definir la regla, puede ser un número
distinto). El eje está **invertido** respecto a cantidad (más violaciones = peor), así que el piso/
techo se leen al revés: "violaciones mínimas que ya penalizan" / "violaciones máximas toleradas
para el bono". Sin ninguna instalación medible esa semana → `sin_datos` (outcome nuevo), nunca
bono ni penalización — evita premiar a quien simplemente no trabajó.

### Dónde se ve

El selector de métrica en `/talento/reglas-desempeno` gana "Calidad de señal" + su propio campo de
umbral en dB. Los 3 lugares que muestran "Mi progreso" (Portal técnico/vendedor/mostrador + la
ficha nativa del admin) ahora iteran una **lista** de reglas en vez de asumir una sola — un
técnico con ambos ejes activos ve dos tarjetas a la vez, una por cada uno.

### Verificación en vivo

Regla de calidad creada **a través del formulario real** (Playwright) para TECNICO_PLANTA,
coexistiendo sin conflicto con la regla de cantidad ya existente para el mismo rol. 3 técnicos de
prueba con `TalentoHealthBonusLog` sintético cubrieron los 3 desenlaces (2 instalaciones fuera de
rango → penalización pendiente; 3 limpias → bono; sin ningún dato esa semana → sin_datos) — los
tres visibles simultáneamente junto a la tarjeta de cantidad, en el Portal y en la ficha.
Confirmar una penalización de calidad generó un `TalentoPenalty` + `TalentoLedgerEntry` real que
**sí** redujo la liquidación real de esa semana (mismo fix de período de la vuelta anterior,
confirmado también funcionando aquí). Un hallazgo menor corregido en el camino: la primera versión
creaba una fila "sin regla" por cada metric_type que NO aplicaba al colaborador (ej. "ventas" para
un técnico puro) — inflaba la tabla sin ningún dato útil; corregido para solo evaluar los ejes que
de verdad le corresponden.

### Qué NO se dejó vivo, a propósito

La regla de calidad de prueba (umbral 1.0 dB, bono $250, penalización $200) se **borró** al
cierre — el umbral real en dB y los montos son una decisión de negocio de Irving, no algo para
dejar corriendo con un número inventado por mí. Crear la(s) regla(s) real(es) de calidad queda
pendiente, desde la misma pantalla `/talento/reglas-desempeno` → "Nueva regla" → "Calidad de
señal", cuando él defina los números.

### Estado

Motor de reglas semanales completo: cantidad (Fase 2) + calidad de señal (esta vuelta), ambos ejes
verificados de punta a punta incluyendo el pago real. Pendiente de Irving: crear la(s) regla(s)
reales de calidad con sus números definitivos.
