# Item #164 — Fase 2 Medussa: cuota de software por transacción conciliada

**Estado: diseño únicamente. Sin cálculo, sin cobro, sin cambios al flag.**
`config/pagos.php:45` (`medussa_fees_enabled`) sigue en `false` por default, tal como estaba.

## Por qué este item no se implementó

El propio texto del item se contradice a sí mismo a propósito: pide "cobro de comisión a
sub-ISPs por transacción conciliada" pero termina con **"NO implementar sin autorización"**
(pendiente validación legal — ¿agregador o SaaS fee? autorización CNBV). Ya pasó por el ciclo
completo de escalación antes de llegar a este worktree:

- wt-4 (2026-08-26) detectó la contradicción y consultó a Thomas.
- Thomas escaló a Irving (categoría dinero) — fuera de lo que un supervisor on-box puede autorizar.
- El "des-trabe" (Opus) devolvió un brief con 5 opciones de modelo de cobro (A–E) y recomendó
  explícitamente: **"No implementar nada aún. Decidir primero el modelo de cobro… mientras
  tanto, no mergear código preparatorio que pueda convertirse en lógica de cargo activa."**
- Irving aprobó el item (`aprobado_irving`) después de ver ese brief, sin fijar un modelo
  concreto ni indicar que la validación CNBV ya se resolvió.

Dado que sigue siendo una frontera dura de dinero sin decisión de modelo ni autorización legal
explícita, esta vuelta ejecuta la única parte segura y reversible: dejar el contexto investigado
y documentado para que la próxima decisión (de Irving, con el modelo de cobro ya elegido) no
tenga que repetir esta arqueología.

## Contexto técnico encontrado

- **Módulo real:** `app/Modules/Addons/PortalPago/` ("Portal de Pago Meganet", module id 220).
  Cobro por transferencia SPEI con conciliación automática vía CEP de Banxico. Su propia
  descripción en `module.json` dice **"Cero comisión a terceros"** — el modelo de negocio actual
  es explícitamente sin fee.
- **"Medussa"** = nombre en clave de la plataforma MegaISP base (`CONTEXTO-MEGAISP.md:184`:
  *"Portal SPEI nativo… Seam Medussa gated pendiente de revisión legal"*). "Fase 2 Medussa" se
  refiere a vender este Portal de Pago como producto SaaS a **otros ISPs** (sub-ISPs), cobrándoles
  por cada transacción que el sistema concilia por ellos.
- **No existe hoy ningún modelo de tenant/sub-ISP real** para facturarles. La única pista es la
  columna `instance_id` (nullable) en `portal_pago_accounts`
  (`database/migrations/2026_06_25_120000_create_portal_pago_accounts_table.php:29`, comentario
  "Multi-tenant: aislable por instancia. NULL = cuenta global Meganet") — es un placeholder sin
  tabla `instances` detrás, sin uso real. Antes de cobrar por transacción a un sub-ISP hace falta
  primero decidir CÓMO se modela un sub-ISP en este sistema (fila con `tenant_id`, o instancia
  Laravel completa — el mismo dilema que ya se resolvió para MultiOLT en
  `docs/MULTIOLT_SAAS_DISENO.md`, pero **ese documento no cubre Portal de Pago ni fees**).
- **Punto de enganche natural para un futuro cálculo:** `PortalPagoPaymentReport`
  (tabla `portal_pago_payment_reports`), estado `validado` — ahí es donde una transacción queda
  "conciliada". Confirmado por grep: **ningún archivo del repo consume `medussa_fees_enabled`
  hoy** — es un interruptor sin ninguna lógica asociada, como dice la descripción del item.

## Las 5 opciones de modelo de cobro (heredadas del des-trabe de Irving, sin decidir)

- **A. Cuota fija por transacción conciliada** — predecible, pero castiga tickets chicos.
- **B. Cuota porcentual sobre monto conciliado** — más justa para tickets chicos, pero variable
  y necesita topes/mínimos.
- **C. Híbrido (fijo + % con tope)** — equilibrado, más lógica y casos borde.
- **D. Cuota mensual plana por tenant** (no por transacción) — simplísimo, cambia el modelo de
  negocio propuesto en el título del item.
- **E. Posponer Fase 2** hasta cerrar Fase 1 y tener datos reales de volumen por sub-ISP.

## Qué falta antes de poder implementar el cálculo real

1. **Autorización legal/CNBV explícita** (agregador vs. SaaS fee) — el bloqueo que el propio item
   señala y que sigue sin resolverse.
2. **Decisión de Irving del modelo de cobro** (A/B/C/D/E arriba), con datos de volumen esperado.
3. **Modelo de tenant/sub-ISP** para Portal de Pago — hoy no existe; decidir si se reusa el
   placeholder `instance_id` o se diseña desde cero (ver precedente en `MULTIOLT_SAAS_DISENO.md`).
4. Recién con (1)+(2)+(3) resueltos tiene sentido escribir el cálculo detrás del flag
   `medussa_fees_enabled` (que puede seguir apagado por default hasta ese momento).

El sub-item de implementación real queda registrado por separado y bloqueado explícitamente
hasta que (1) y (2) tengan respuesta de Irving.
