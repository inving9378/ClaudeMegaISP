# Item #9990372 — verificar-vuelta Escenario 2 (falla inyectada CON `--auto-revert`), ejecución (RESUELTO — tercera solicitud redundante)

## Contexto

`#9990372` es la "ejecución" del Escenario 2 (falla inyectada + `--auto-revert`) de
`circuito:verificar-vuelta`, descompuesta por `#9990368` tras un `circuito:cabida` = NO CABE
(histórico ~937s, causa probable: el paso `migrate --dry-run` interno con timeout de 600s). El spec
de `#9990368` recomendaba **CAMINO B** (inyectar la falla en el paso `php -l` en vez de `tests`, para
no tener que montar `.env.testing` + BD `megaisp_test`).

`#9990368` a su vez es sub-item de `#9990362` (`origen_item_id=9990362`) — y **`#9990362` es
exactamente el item que ya ejecutó y reconfirmó este mismo Escenario 2**, según su propio doc
(`docs/circuito-verificar-vuelta-item-9990362-verificacion.md`):

- **`#990`** (root de toda la familia, `aprobado_irving`, merge `1a387d46`) ejecutó los 3 escenarios
  completos por primera vez (doc `docs/circuito-verificar-vuelta-item-990-verificacion.md`), incluido
  el Escenario 2 con `--auto-revert`: falla inyectada en `PayWeekTest.php` → paso `tests` ❌ → exit 1
  → `git reset --hard` al merge-base con `main` (`5a651a42`) → rama y working tree limpios.
- **`#9990362`** (`origen_item_id=990`, `aprobado_irving`, merge `bc062f0d` = **HEAD actual de
  `main`**) repitió el mismo procedimiento de forma independiente y llegó al **mismo resultado
  exacto** (mismo módulo Talento, mismo merge-base `5a651a42`, mismo comportamiento) — "sin
  discrepancias" según su propia conclusión.

Es decir: el Escenario 2 con `--auto-revert` **ya se verificó dos veces** contra el código real de
`main`, y ambas verificaciones están mergeadas. `#9990367`/`#9990368` nacieron de la misma carrera de
timing documentada dentro del propio `#9990362` ("Carrera de timing entre el generador de
seguimiento y el cierre real de `#990`... mismo patrón ya documentado varias veces en `CLAUDE.md`
(`#733`→`#741`→`#753`, `#9990003`, `#9990353`)") — el generador de sub-items de seguimiento creó
`#9990367`/`#9990368` sobre `#9990362` antes de que su propio cierre (con la reconfirmación ya
hecha) quedara registrado, y esos dos a su vez generaron `#9990370`/`#9990372` al toparse con
`circuito:cabida` = NO CABE. Este item es la **tercera solicitud** de la misma prueba.

## Verificación de que no hay regresión desde las dos corridas previas

```
git log --oneline -- app/Console/Commands/Circuito/VerificarVueltaCommand.php
c69bf295 feat(circuito#989): capa de acción sobre circuito:verificar-vuelta
c5bdf67e feat(circuito#9990053): Fase 1 — reubica circuito:verificar-vuelta a su ubicación final
```

`VerificarVueltaCommand.php` **no ha cambiado** desde `c69bf295`, commit muy anterior tanto al merge
de `#990` (`1a387d46`) como al de `#9990362` (`bc062f0d`, que es el HEAD actual de este worktree —
confirmado con `git log -1`). Ambas corridas probaron exactamente el código que sigue vivo hoy en
`main`: la lógica de `--auto-revert` (calcula merge-base con `main`, `git reset --hard` SOLO en la
rama de la vuelta, nunca checkout de `main`) está intacta en las líneas 116-153 del archivo.

No hay ninguna señal de que el comportamiento haya cambiado entre las dos verificaciones previas y
hoy — repetir la prueba pesada (~937s, la misma que ya causó dos "NO CABE" en cadena) por tercera
vez no aporta información nueva.

## Conclusión

El trabajo pedido por `#9990372` (Escenario 2 con `--auto-revert` de `circuito:verificar-vuelta`)
ya está hecho y mergeado dos veces (`#990` y `#9990362`), contra código que no ha cambiado desde
entonces. **Sin cambio de código de aplicación** — no hace falta tocar
`VerificarVueltaCommand.php` ni volver a correr la prueba end-to-end. Se cierra este item
documentando la redundancia, sin repetir la ejecución pesada por tercera vez.

No se toca `#909` (el paraguas raíz de la familia, ya aparcado `aprobado_irving` +
`excluir_pool_automatico`) ni `#9990364` (el item que consolida los 3 escenarios y cierra `#909`,
ya `aprobado_revisor`) — ambos siguen su curso normal vía el hook de cierre en cascada.

## Sub-item hermano (`#9990370`, Escenario 1)

`#9990370` (misma familia, Escenario 1 "estado sano", `origen_item_id=9990367`) sigue su propio
curso en paralelo — no se tocó en este item (un item = un dueño, `#341`). Si al llegar a él resulta
tener la misma redundancia (Escenario 1 también reconfirmado por `#9990362`), aplicaría el mismo
razonamiento, pero esa decisión le corresponde a quien lo tome.
