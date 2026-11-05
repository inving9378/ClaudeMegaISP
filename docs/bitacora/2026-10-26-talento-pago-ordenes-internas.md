## 2026-10-26 — Talento: las órdenes internas cuentan para el pago igual que las de campo

**Decisión del usuario** (pendiente que quedó en `2026-10-24-talento-ordenes-internas-visibles.md`):
si un tipo de trabajo interno tiene puntos, cuenta para el pago «si es cobrable», igual que los de
campo. Explicación: «los 2, tanto técnicos internos como externos, cobran de la misma forma; la
diferencia es que unos trabajan dentro de la casa montando los routers y haciendo las
configuraciones y los otros en la calle haciendo las conexiones de fibra, montando las cajas,
empalmando la fibra, etc.».

**Antes:** `LiquidationService::countBillableUnits()` (punto único del conteo: liquidación,
desglose de «Mi dinero», app móvil y regla semanal) solo sumaba las órdenes en `tasks` con
`tipo = 'campo'`. Una orden de un tipo interno con puntos y cobrable no contaba para nada.

**Ahora:** cuenta toda orden de Talento (`talento_type_id` no nulo), de campo o interna, con las
mismas condiciones que ya había: puntos > 0, cobrable, terminada, validada dentro de la semana y
asignada a la persona. Es el mismo criterio con el que el 24-oct se volvieron visibles en Talento.
Los puntos y lo cobrable se copian del tipo al crear la orden, así que quien configura el tipo
decide.

**Hoy no cambia ningún monto:** en dev el único tipo interno es Reubicación, con 0 puntos y no
cobrable. Reubicación se dejó como está: pide evidencias de calle (fachada, NAP, dBm, firma), pero
con este cambio su clasificación ya no influye en el pago.

**Prueba** (código de la rama contra la base de dev, todo dentro de una transacción que se deshizo;
colaborador de prueba con regla de 2,000 de base y cuota de 216):
- Interna con 5 puntos y cobrable: suma 5 unidades y la liquidación de la semana las cuenta (0 → 5).
- No suman: interna no cobrable, interna con 0 puntos, tarea normal de Agenda, interna sin validar,
  sin terminar, o validada en otra semana. Las de campo suman igual que antes.
- Pasando la cuota con trabajo interno (244 unidades), se paga sobreproducción: 2,000.00 → 2,259.26.
- En main la interna cobrable no sumaba (0 → 0); lo demás, igual.
- No quedó nada guardado.

Textos que decían «tasks de campo» actualizados: comentarios de `LiquidationService` y
`TalentoMobileApiController`, `docs/modulos/talento.md` y `CLAUDE.md`.
