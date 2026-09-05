# Item #9990362 — Escenario 1 + Escenario 2 de `circuito:verificar-vuelta` (RESUELTO — reconfirma trabajo ya mergeado en #990)

## Contexto

`#9990362` nació como "sub-item de seguimiento de #990" (16:12) pidiendo repetir, en un worktree
aislado, exactamente el Escenario 1 (estado sano) y el Escenario 2 (falla inyectada + `--auto-revert`)
de `circuito:verificar-vuelta`. Para cuando esta vuelta lo tomó, **`#990` ya había ejecutado y
mergeado los 3 escenarios completos** (incluido el 3, sin `--auto-revert`) — commit `1a387d46`, doc
`docs/circuito-verificar-vuelta-item-990-verificacion.md`. Carrera de timing entre el generador de
seguimiento y el cierre real de `#990`, mismo patrón ya documentado varias veces en `CLAUDE.md`
(`#733`→`#741`→`#753`, `#9990003`, `#9990353`).

## Reverificación independiente (wt-4, esta vuelta)

Sin haber leído aún el doc de `#990`, se repitió el mismo procedimiento desde cero:

- Módulo elegido: **Talento** (`tests/Unit/Talento/PayWeekTest.php`, extiende
  `PHPUnit\Framework\TestCase` puro — no toca BD, corre en ~0.02s). Mismo módulo que usó `#990`.
- Se creó `.env.testing` local (gitignored) apuntando a `megaisp_test` para que el paso `tests` de
  `circuito:verificar-vuelta` no se saltara (`sandboxDeTestsSeguro()` exige `DB_DATABASE` distinto
  al de dev).
- **Escenario 1** (`php artisan circuito:verificar-vuelta Talento`, rama de prueba recién creada
  desde `main`, código intacto): **exit 0**, los 4 pasos (`php -l`, boot, tests, migrate --dry-run)
  en ✅, ninguno en ❌.
- **Escenario 2** (aserción de `PayWeekTest.php` modificada a propósito, commiteada, luego
  `php artisan circuito:verificar-vuelta Talento --auto-revert --item=990 --branch=<rama-de-prueba>`):
  - (a) paso `tests` reportado **❌** (`Failures: 1`).
  - (b) **exit 1**.
  - (c) `git reset --hard` al merge-base con `main` — confirmado con `git rev-parse HEAD` (volvió a
    `5a651a42`, el mismo commit de main que usó `#990` en su propio Escenario 2), `git status`
    limpio y `git diff main --stat` sin cambios de código de la app. El archivo con la falla
    inyectada volvió a su contenido original.

Resultado: **idéntico** al ya documentado y mergeado por `#990` (mismo módulo, mismo comportamiento,
mismo merge-base). Sin discrepancias.

## Limpieza

- Rama de prueba borrada (`git branch -D`) tras el Escenario 2.
- `.env.testing` es local a este worktree, gitignored, no se commitea.
- Ningún residuo en `main`: el único cambio de esta vuelta es este documento de cierre.

## Conclusión

El trabajo pedido por `#9990362` ya estaba hecho y mergeado por `#990` antes de que esta vuelta lo
tomara. Esta vuelta lo reconfirmó de forma independiente sin encontrar ninguna diferencia. **Sin
cambio de código de aplicación** — no hace falta tocar `VerificarVueltaCommand.php` ni nada más.
No se toca `#909` (el paraguas de la familia): sigue aparcado según lo documentado en el propio doc
de `#990`, se completará solo vía el hook de cierre en cascada cuando corresponda.
