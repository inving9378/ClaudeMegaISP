# Item #9990363 — Escenario 3 de `circuito:verificar-vuelta` (falla inyectada SIN `--auto-revert`, escalada vía `circuito:consultar`)

## Contexto

`#9990363` nació como sub-item de seguimiento de `#990` pidiendo repetir, en un worktree aislado, el
Escenario 3 (falla inyectada, **SIN** `--auto-revert`) de `circuito:verificar-vuelta` y confirmar que
se dispara `circuito:consultar` con las dos opciones esperadas, documentando el exit code real.

Para cuando esta vuelta lo tomó, `#990` **ya había ejecutado y mergeado los 3 escenarios completos**
(commit `1a387d46`, doc `docs/circuito-verificar-vuelta-item-990-verificacion.md`), incluido este
mismo Escenario 3. Mismo patrón de carrera de timing entre el generador de seguimiento y el cierre
real de `#990` ya documentado varias veces en `CLAUDE.md` (`#733`→`#741`→`#753`, `#9990003`,
`#9990353`, `#9990362`).

## Reverificación independiente (wt-3, esta vuelta)

- Módulo elegido: **Talento** (`tests/Unit/Talento/PayWeekTest.php`, extiende
  `PHPUnit\Framework\TestCase` puro, no toca BD) — mismo módulo que usaron `#990` y `#9990362`, para
  consistencia del reporte.
- `.env.testing` local (gitignored) apuntando a `megaisp_test` (BD ya existente con `GRANT ALL` para
  `megaisp_user`), para que el paso `tests` de `circuito:verificar-vuelta` corriera de verdad en vez
  de saltarse (`sandboxDeTestsSeguro()`).
- Rama de prueba descartable **nueva**, creada desde el `HEAD` actual de `main`
  (`prueba/verificar-vuelta-escenario3-wt3`), nunca reusada de otro sub-item.
- Se inyectó la misma clase de falla que usaron `#990`/`#9990362`: una aserción real de
  `PayWeekTest.php` cambiada a un valor incorrecto (`test_nueva_sabado_18_00_cierra_la_semana`:
  `assertSame('2026-08-08', $b['period_end'])` → `assertSame('2026-08-09', ...)`, comentario "FALLA
  INYECTADA A PROPÓSITO"), commiteada en la rama de prueba.
- Comando corrido:
  ```
  php artisan circuito:verificar-vuelta Talento --item=990 --branch=prueba/verificar-vuelta-escenario3-wt3 --json
  ```
  (se reusó `--item=990` solo por consistencia con el reporte de la familia — igual que hicieron
  `#9990362`/`#9990370` — **no** es una escalada real de negocio de `#990`, ver nota de limpieza).

### Resultado

```json
{"modulo":"Talento","pasos":[
  {"paso":"php -l","estado":"ok","detalle":"1 archivo(s) sin errores de sintaxis"},
  {"paso":"boot (artisan --version)","estado":"ok","detalle":"Laravel Framework 10.48.4"},
  {"paso":"tests","estado":"fail","detalle":"suite: tests/Unit/Talento — Tests: 10, Assertions: 34, Failures: 1."},
  {"paso":"migrate --dry-run","estado":"ok","detalle":"Sin migraciones pendientes — nada que validar. OK."}
],"resultado":"fail"}
PROCEDE: PROCEDE con la opción recomendada: Revertir la rama de esta vuelta (git reset al merge-base con main)
Motivo: Opción recomendada por la propia terminal; fuera del conjunto de escalamiento.
```

- **`circuito:consultar` SÍ se disparó**, con **exactamente** las dos opciones del contrato de `#989`:
  1. "Revertir la rama de esta vuelta (git reset al merge-base con main)" → `reversible:true`,
     `recomendada:true`.
  2. "Investigar antes de revertir" → `reversible:false`, `recomendada:false`.
- **Exit code final: 0** (no 1). Jarvis resolvió **PROCEDE** con la opción recomendada+reversible
  (política determinista: una opción recomendada+reversible que no cae en la frontera dura de
  `config('circuito.jarvis.escalamiento')` se auto-aprueba, no escala a la bandeja de Irving) — idéntico
  al resultado que ya documentó `#990` para este mismo escenario.
- **Sin `--auto-revert`, el comando NO ejecutó ningún reset por sí mismo**: confirmado con
  `git log --oneline -1` (el commit de la falla, `2ed0790b`, seguía en `HEAD` de la rama de prueba
  después de correr el comando) y `git status`/`git diff main --stat` (el archivo con la falla seguía
  modificado). Esto es consistente con el propio docblock de la clase ("modo más seguro": solo
  consulta y devuelve el exit code, no revierte solo).

Resultado **idéntico** al ya documentado y mergeado por `#990` para este mismo escenario: mismo
módulo, mismo comportamiento (PROCEDE, exit 0, sin reset automático), sin discrepancias.

## Entrada real generada en el log/estado de `#990` (spec, punto de atención)

Como se reusó `--item=990` (el mismo item real de la familia, no un item de prueba dedicado), la
corrida **sí sobrescribió** los campos `consulta_supervisor`/`consulta_opciones`/`consulta_respuesta`
de `#990` con los datos de esta prueba (mencionando la rama descartable
`prueba/verificar-vuelta-escenario3-wt3`). Para que no quede ambiguo para quien lea después, se
agregó una entrada explícita al `log` de `#990` (evento `nota_prueba_controlada`) aclarando que esos
campos fueron sobrescritos por esta prueba controlada de `#9990363`, **no** por una escalada real de
negocio de `#990` — sin tocar `estado_aprobacion` de `#990` (permanece `aprobado_irving`, como lo
dejó su propio cierre en cascada).

## Limpieza

- Rama de prueba `prueba/verificar-vuelta-escenario3-wt3` borrada (`git branch -D`) tras confirmar el
  resultado; `HEAD` de este worktree vuelto al commit real de `main` (`aa4c7b5c`).
- `PayWeekTest.php` recuperó su contenido original al volver a `main` (la falla solo vivió en la rama
  de prueba ya borrada) — confirmado sin diferencias contra `main`.
- Ningún residuo de código de la app en `main`: el único cambio de esta vuelta es este documento +
  la nota aclaratoria en el log de `#990`.
- `.env.testing` es local a este worktree, gitignored, no se commitea.

## Conclusión

El Escenario 3 de `circuito:verificar-vuelta` (falla inyectada SIN `--auto-revert`) queda
**reconfirmado** de forma independiente: se dispara `circuito:consultar` con las dos opciones
esperadas, y el resultado real (no el "peor caso" anticipado por el spec) es **PROCEDE / exit 0**,
porque la opción recomendada siempre es reversible y no cae en frontera dura — documentado tal cual
pedía el spec ("documentar cuál pasó realmente"). Sin cambio de código de aplicación — no hace falta
tocar `VerificarVueltaCommand.php`. No se toca `#909` (el paraguas de la familia): sigue aparcado y se
completa solo vía el hook de cierre en cascada cuando corresponda.
