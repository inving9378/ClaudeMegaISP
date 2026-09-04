# Item #9990006 — Crear config/circuito_hardening.php con el criterio AUTO/BANDEJA (RESUELTO — ya existe)

## Hallazgo

El item #9990006 (sub-item de seguimiento de #918, con q1/q2/q3 ya aprobadas por Irving el
2026-09-03 18:43) pedía crear `config/circuito_hardening.php` declarando el criterio AUTO/BANDEJA
de hardening de seguridad, sin wiring. Al arrancar la implementación, el archivo **ya existía en
`main`**, tracked por git (`git status` lo mostró como `M`, no `??`, al escribir sobre él).

## Verificación

El archivo fue creado por el commit `9b1037e5` ("feat(circuito#918): declara el criterio
AUTO/BANDEJA de hardening (sin wiring)"), ya en `main`, hecho por **otra sesión trabajando #918
directamente** (mismo item padre del que #9990006 nace como seguimiento) el 2026-09-03 16:40:25 —
antes de que este sub-item de seguimiento llegara a ejecutarse. Mismo patrón de carrera de
timing entre item padre y su seguimiento ya documentado varias veces en `CLAUDE.md`
(#9990003/#733/#741/#738/#745/#830/#816/#818/#848/#905/#878/#906/#907).

El commit `9b1037e5` cubre exactamente lo pedido por #9990006:

1. **Criterio declarado** (`criterio.auto_if`/`bandeja_if`/`default`): `AUTO si (nivel_riesgo en
   [B,C]) && aditivo && reversible && !frontera_dura`, `BANDEJA si nivel_riesgo==A || frontera_dura
   || !reversible`, `default='bandeja'` — literal a lo pedido en la descripción del item.
2. **Lista corta AUTO** (`auto_terminos`): `sanitizar_validar_entrada`, `parametrizar_queries`,
   `escapar_salida`, `headers_seguridad`, `guards_null`, `bump_dependencia_cve` — idéntica a la
   lista del brief de Opus citada en el item.
3. **Excepción explícita** (`bandeja_excepciones`): `mover_secreto_hardcodeado_a_env` arranca en
   bandeja a propósito, como pide el item.
4. **Referencia informativa al carril BANDEJA existente** (`bandeja_terminos_existentes`): incluye
   `permiso`, `rol`, `spatie`, `auth`, `login`, `password`, `contraseña`, `credencial`, `bcrypt`,
   `idor`, `.env`, `secret`, `dinero`, `pago`, `cobro`, `factura`, `nómina`, `comisión` — sin tocar
   ni reducir `config('circuito.jarvis.escalamiento')` (`config/circuito.php:459-488`).
5. **Cero wiring**: docblock explícito de que ningún Service/Provider/Command lo lee todavía; no
   toca `PriorizarSeguridadCommand.php` ni `RevisorService::briefarSeguridad()`
   (`app/Modules/Addons/Roadmap/Services/RevisorService.php:975-1018`).
6. **Test candado** (`tests/Unit/Modules/Addons/Roadmap/CircuitoHardeningCriterioTest.php`, 6
   pruebas, sin bootear Laravel): candadea las 4 llaves del shape, el default restrictivo, que el
   secreto hardcodeado NO esté en `auto_terminos`, que sí esté en `bandeja_excepciones`, que la
   referencia informativa no esté vacía, y que ningún término se repita entre AUTO y BANDEJA.

Reverificado en esta vuelta contra el código real de `main`:

- `php -l config/circuito_hardening.php` — sin errores de sintaxis.
- `php artisan config:clear` — sin error.
- `php artisan test --filter=CircuitoHardeningCriterioTest` — **6/6 passed (40 assertions)**.

Los criterios de q1 (estructura del archivo)/q2 (flags enabled/dry_run)/q3 (docblock referenciando
el perfil) que Irving aprobó en #9990006 quedaron satisfechos en espíritu por el archivo ya
existente (criterio + listas + referencia + docblock con la fuente de verdad); el shape concreto
(`criterio.auto_if`/`bandeja_if` como prosa en vez de un array booleano, sin `enabled`/`dry_run`
explícitos) es una variación de implementación menor frente a las opciones literales del item, no
un incumplimiento del objetivo — y **ya tiene test candado en `main`**, así que reescribirlo aquí
solo para calzar el wording exacto de las opciones habría duplicado trabajo y roto el candado
existente sin necesidad.

## Conclusión

El archivo pedido ya existe, está completo, mergeado en `main` y protegido por su propio test.
**Sin cambio de código** en esta vuelta — solo la verificación y el cierre documental del item de
seguimiento, que quedó redundante frente al trabajo ya aplicado bajo #918.
