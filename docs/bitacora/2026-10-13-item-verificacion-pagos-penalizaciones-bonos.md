## 2026-10-13 15:50 — Verificación a fondo de pagos/penalizaciones/bonos por rol (pedido directo tras cuestionar la Fase 3)

### Contexto

Pregunta directa: "¿comprobaste paso a paso toda la parte de pagos, penalizaciones, bonos y
demás, funcione correctamente para cada rol, aplicando las reglas que se definan, lo mismo por
un mostrador que el admin?" — honesto: **no del todo**. La Fase 3 (verificación de cálculo)
probó que el motor calcula bien el OUTCOME (bono/cumple/penalización) y que se VE bien en
pantalla — pero nunca until ahora había probado en vivo el botón real de "Confirmar
penalización", ni investigado si un "bono" realmente paga algo en algún lado. Esta vuelta cierra
esa brecha.

### 1) Pagos — verificado, sin defecto

`ManualPaymentController::store()` (la pantalla de Mostrador, `/finanzas/captura-pago`) no tiene
NINGUNA rama de código por rol — el único campo que depende de quién lo usa es `add_by =
auth()->id()` (atribución). Todo lo demás (comprobante, `PaymentApplicationService::
applyPayment()`, metadata del pago reportado) es el MISMO código sin importar si lo corre
Mostrador o un admin. Por construcción, no puede comportarse distinto por rol — confirmado
leyendo el código, no hacía falta repetir la prueba en vivo que ya se hizo en sesiones previas.

### 2) Penalizaciones — 3 defectos reales encontrados y corregidos

Construí datos de prueba reales (un supervisor con 2 subordinados técnicos, 2 evaluaciones
`penalty_pending` reales) y until ahora probé por primera vez el flujo completo de
"Confirmar" en vivo. Encontré:

1. **El supervisor no podía ni ver ni confirmar la penalización de su propio subordinado** —
   `/talento/api/reglas-desempeno/evaluaciones*` no estaba registrado en NINGÚN bloque de
   `config/route_permission.php`, así que el middleware denegaba 403 a cualquier no-admin ANTES
   de que el controller pudiera aplicar su propia excepción "o eres su supervisor directo". El
   código de la excepción ya existía (escrito en la Fase 2), pero era inalcanzable para
   cualquiera que no fuera super-administrator/DESARROLLADOR.
2. **El supervisor no tenía cómo enterarse de qué confirmar** — `evaluationsIndex()` exigía
   `talento.reglas_desempeno.view` (admin-only) sin ninguna excepción de equipo.
3. **Un admin sin ficha propia de colaborador no podía confirmar nada** — mismo patrón que el
   bug de "Mi equipo" de la Fase 1 (un admin/DESARROLLADOR real, sin perfil de colaborador,
   topaba con un 422 "No tienes perfil de colaborador").

**Corregido los 3** (commit `5fe10c2c`): ruta registrada bajo `talento.view` (el controller sigue
haciendo la distinción fina real); `evaluationsIndex()` ahora escala a "ve todo" con permiso de
staff y si no, a "uno mismo + mi equipo directo" (mismo patrón que `TalentoWorkOrderController`);
`confirmarPenalizacion()` cae a MEGAISP (el bot de sistema, mismo usado para pagos automáticos)
cuando quien confirma no tiene ficha propia.

**Verificado en vivo, de punta a punta:** el supervisor vio exactamente 1 evaluación pendiente
(la de su subordinado real, ninguna otra), la confirmó → se creó un `TalentoPenalty` real +
un `TalentoLedgerEntry` débito real de $200; intentó confirmar la de alguien que NO supervisa →
siguió bloqueado (403, aislamiento intacto); el admin sin ficha propia confirmó la otra →
atribuida a MEGAISP, mismo resultado real (penalty + ledger debit). Datos de prueba borrados al
cierre — se conserva la ficha de MEGAISP recién creada (`talento_colaboradores` id=177), que
queda como fixture permanente del sistema, igual que el propio usuario MEGAISP.

### 3) Bonos — hallazgo sin corregir, pendiente de decisión

**El "bono" es puramente informativo — no se paga en ningún lado.** `bonus_amount_applied` se
escribe en la evaluación cuando el outcome es `bonus`, y se muestra en pantalla ("Bono ganado
$X") — pero a diferencia de las penalizaciones (que tienen un botón "Confirmar" real que crea un
`TalentoPenalty` + un débito en `TalentoLedgerEntry`), **no existe ningún equivalente para
bonos**: ni endpoint de confirmación, ni creación de un crédito en el ledger, nada. Un colaborador
puede ver "¡Ganaste $300 de bono!" en su propio dashboard sin que ese dinero llegue nunca a
ningún lado real.

Esto no se corrigió en esta vuelta porque es una **decisión de diseño, no un bug mecánico**:
- ¿El bono se paga automático (sin confirmación humana, como sugiere que sea una recompensa y no
  un castigo) o requiere el mismo paso de "Confirmar" que una penalización?
- ¿Quién confirma/autoriza un bono — el mismo supervisor, o alguien de nómina/finanzas?
- ¿Se acredita como `TalentoLedgerEntry` tipo `credit`/concepto `bonus` (mismo patrón que ya
  existe para `penalty_reversal`), o pasa por otro mecanismo (fondo, préstamo)?

Queda documentado como pendiente explícito — necesita una decisión de Irving antes de construirse,
no algo para decidir solo.

### Estado

Pagos: verificado, sin defecto. Penalizaciones: 3 defectos reales encontrados y corregidos,
verificados en vivo. Bonos: hallazgo real (nunca se pagan), pendiente de decisión de diseño antes
de construir el mecanismo de pago.
