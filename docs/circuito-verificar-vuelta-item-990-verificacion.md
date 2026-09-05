# Item #990 — verificación end-to-end de `circuito:verificar-vuelta` (detección + capa de acción)

## Contexto

`#990` es el tercer sub-item de `#909` (Torre 24/7 Pieza 5c), junto a `#988` (motor de detección:
`php -l` + boot + tests del módulo + `deploy:dry-run-migrations`) y `#989` (capa de acción: revierte
la rama propia o escala vía `circuito:consultar`). Este item llevaba **28 reverificaciones**
bloqueado: `#989` no tenía `merge_commit` real en `main` (su único commit tocaba una ruta de archivo
inexistente, `app/Modules/Addons/Roadmap/Console/VerificarVueltaCommand.php`, en vez de la real
`app/Console/Commands/Circuito/VerificarVueltaCommand.php`). Al llegar a esta vuelta, **el bloqueo ya
estaba resuelto**: `#989` tiene un commit nuevo (`c69bf295`, ruta correcta) mergeado a `main` vía
`5a651a42`, y `#988` seguía mergeado (`0f8067c7`) desde antes. `VerificarVueltaCommand.php` en `main`
ya trae los 3 flags de acción (`--auto-revert`, `--item`, `--branch`) documentados en su propio
docblock.

## Preparación

- Módulo elegido: **Talento** (`tests/Unit/Talento/PayWeekTest.php`, único archivo, 10 tests, corre
  en **0.037s** — no toca BD: extiende `PHPUnit\Framework\TestCase` puro, no el `TestCase.php` de la
  app que hace `migrate:fresh --seed`). Cumple el requisito del spec de un módulo con tests rápidos.
- El paso `tests` de `circuito:verificar-vuelta` exige `.env.testing` con `DB_DATABASE` distinto al de
  dev (`sandboxDeTestsSeguro()`) o se salta con `skip`. Se creó un `.env.testing` local apuntando a
  `megaisp_test` (BD ya existente, con `GRANT ALL` para `megaisp_user`) para que el paso realmente se
  ejecutara en vez de saltarse — archivo gitignored (`.gitignore:39`), nunca se commitea, solo vive en
  este worktree.

## Escenario 1 — estado sano

```
php artisan circuito:verificar-vuelta Talento --item=990
```
Resultado: **exit 0**, los 4 pasos en ✅ (`php -l`, boot, tests, migrate --dry-run), sin ningún ❌.
Confirmado sobre la rama del propio item (`circuito/item-990-circuitoverificar-vuelta-verificacion`,
código intacto).

## Escenario 2 — falla inyectada + `--auto-revert`

En una rama descartable (`circuito/item-990-verificacion-e1`, creada desde `main`, borrada al
terminar) se modificó una aserción real de `PayWeekTest.php`
(`assertSame('2026-08-08', ...)` → `assertSame('2026-08-09', ...)`, comentario "FALLA INYECTADA A
PROPÓSITO") y se commiteó.

```
php artisan circuito:verificar-vuelta Talento --auto-revert --item=990 --branch=circuito/item-990-verificacion-e1
```

Resultado:
- (a) paso `tests` reportado **❌** (`Failures: 1`).
- (b) **exit 1**.
- (c) `git reset --hard` al merge-base con `main` (`5a651a42...`) — confirmado: `git log --oneline -1`
  en la rama volvió a `5a651a42` (el commit de main, no el de la falla), `git status` limpio, `git diff
  main --stat` vacío. El archivo con la falla injectada desapareció del working tree (revertido a su
  contenido original).

## Escenario 3 — falla inyectada, SIN `--auto-revert`

Misma inyección en otra rama descartable (`circuito/item-990-verificacion-e2`).

```
php artisan circuito:verificar-vuelta Talento --item=990 --branch=circuito/item-990-verificacion-e2
```

Resultado: paso `tests` ❌, y se disparó `circuito:consultar` con **exactamente** las dos opciones
del contrato de `#989` ("Revertir la rama de esta vuelta..." marcada recomendada+reversible,
"Investigar antes de revertir" sin marcar). En este caso concreto Jarvis resolvió **PROCEDE** (exit 0)
porque la política determinista (`JarvisService::evaluar`, paso 3: "¿hay opción recomendada? → esa, y
sigue") auto-aprueba una opción marcada recomendada+reversible que no cae en ninguna frontera dura —
no escaló a la bandeja de Irving (`estado_aprobacion` de `#990` permaneció `en_progreso`, sin pasar a
`requiere_irving`). Sin `--auto-revert`, el comando **no ejecuta ningún reset por sí mismo** (ni con
PROCEDE ni con ESCALADO): solo consulta y devuelve el exit code — la rama de prueba conservó el
commit de la falla hasta que se borró en la limpieza. Esto es consistente con el propio docblock de
la clase ("modo más seguro": no revierte solo).

Nota para quien retome `#989` en el futuro: dado que las dos opciones que este comando siempre pasa
son fijas (una de ellas SIEMPRE recomendada+reversible), en la práctica esta vía nunca escala sola a
Irving salvo que la pregunta/título del item caiga en un término de la frontera dura de
`config('circuito.jarvis.escalamiento')`. No es un bug de contrato (el docblock solo promete "se
invoca circuito:consultar ... y se devuelve el mismo exit code"), pero vale la pena tenerlo en cuenta
si se esperaba ver una escalada real en este flujo. No se tocó el código de `#989` (ya cerrado y
mergeado, fuera de alcance de este item de verificación).

## Limpieza

- Ramas de prueba `circuito/item-990-verificacion-e1` y `circuito/item-990-verificacion-e2` borradas
  (`git branch -D`) tras cada escenario.
- Ningún residuo en `main` (los 2 commits de falla vivieron solo en ramas descartables ya borradas).
- El escenario 3 NO generó una escalada real (Jarvis resolvió PROCEDE, no ESCALADO) — no hizo falta
  limpiar ningún estado de bandeja.
- `.env.testing` es local a este worktree, gitignored, no se commitea.

## Conclusión

Los 3 escenarios del spec quedaron verificados end-to-end contra el código real de `main`
(`#988`+`#989` ya integrados). `#909` (el padre) queda aparcado como paraguas
(`aprobado_irving`+`excluir_pool_automatico`) desde una vuelta previa y se completa solo vía el hook
de cierre en cascada (`RoadmapItem.php:459-491`) en cuanto `#990` cierre — sin necesidad de tocar sus
campos manualmente (`#988` y `#989` ya están `completado`).
