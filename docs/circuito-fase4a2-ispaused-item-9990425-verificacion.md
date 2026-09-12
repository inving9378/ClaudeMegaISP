# Item #9990425 — FASE 4a-2: autolimpieza en isPaused() + candado de regresión (3 casos)

**Veredicto: RESUELTO — el trabajo ya estaba hecho y mergeado en `main` antes de reclamarse el item.**

## Contexto

#9990425 es sub-item de seguimiento de #9990417 (FASE 4a: que un freno del centinela con
`expira_en` vencido se autolimpie solo). El propio item exige, como precondición, que
`FrenoCircuito::expirado()` (FASE 4a-1) ya exista, y pide dos cosas:

1. Cambiar `RoadmapCircuitoService::isPaused()` para que, si `FrenoCircuito::activo()` es
   verdadero, consulte `FrenoCircuito::expirado()` y, si venció, lo quite (`FrenoCircuito::quitar()`)
   en vez de devolver `true` directo.
2. Extender `tests/Unit/Modules/Addons/Roadmap/FrenoFueraDeLaBaseTest.php` con 3 casos de
   comportamiento (freno sin `expira_en` nunca se autolimpia / con `expira_en` pasado se
   autolimpia / con `expira_en` futuro sigue activo), bindeando `config` a mano en el contenedor
   de Illuminate para no romper el carácter "TestCase puro" del archivo.

Criterio de verificación del propio item: `php -l` de ambos archivos + que
`php artisan test --filter=FrenoFueraDeLaBaseTest` pase con los 4 tests viejos + los 3 nuevos.

## Verificación contra el código real

`git diff main` sobre `RoadmapCircuitoService.php` y `FrenoCircuito.php` en este worktree: **sin
diferencias** — el worktree ya nace sincronizado con `main` y el código pedido ya está ahí.

- `RoadmapCircuitoService::isPaused()` (líneas 150-171) ya tiene exactamente el cambio pedido:
  dentro del `if (FrenoCircuito::activo())`, si `FrenoCircuito::expirado()` es `true` llama a
  `FrenoCircuito::quitar()`; si no, `return true`. Todo dentro del mismo `try/catch` fail-closed
  existente, sin tocar el `catch`. Commit `3b443dc5` ("FASE 4a (#9990417): isPaused() autolimpia
  el freno con expira_en vencido", autor Irving MegaISP, 2026-09-06 17:04:56 — dos minutos
  después de que el revisor aprobara este mismo sub-item).
- `FrenoCircuito::expirado()` (FASE 4a-1, precondición del item) ya existe en
  `app/Modules/Addons/Roadmap/Support/FrenoCircuito.php:130-142`.
- `FrenoFueraDeLaBaseTest.php` ya tiene los 3 casos pedidos, con los nombres y el mecanismo
  exacto que describe el spec (bindear `config` a mano vía `Illuminate\Container\Container` +
  `Illuminate\Config\Repository`, sin bootear Laravel completo):
  - `test_freno_sin_expira_en_nunca_se_autolimpia` (caso a)
  - `test_freno_con_expira_en_pasado_se_autolimpia` (caso b)
  - `test_freno_con_expira_en_futuro_sigue_activo` (caso c)

  Commit `ad65a898` ("FASE 4a (#9990417): 3 casos de test para expira_en del centinela"), mismo
  autor, mismo día.

## Corrida real

```
php -l app/Modules/Addons/Roadmap/Services/RoadmapCircuitoService.php   # sin errores
php -l app/Modules/Addons/Roadmap/Support/FrenoCircuito.php             # sin errores
php -l tests/Unit/Modules/Addons/Roadmap/FrenoFueraDeLaBaseTest.php     # sin errores

php artisan test --filter=FrenoFueraDeLaBaseTest
  ✓ is paused falla hacia frenado
  ✓ la ruta del centinela es absoluta
  ✓ vuelta sh consulta el centinela dentro del lazo
  ✓ reanudar exige salud y pausar no
  ✓ freno sin expira en nunca se autolimpia
  ✓ freno con expira en pasado se autolimpia
  ✓ freno con expira en futuro sigue activo
  Tests: 7 passed (26 assertions)
```

Exactamente "4 tests viejos + 3 nuevos", el criterio de aceptación literal del item.

## Sobre el "candado por regex" mencionado en el spec

El spec sugiere, como vía SUFICIENTE (no como cuarto test obligatorio aparte de los 3 casos de
comportamiento), un candado por regex que confirme el orden `activo() → expirado() → quitar()`
antes del fallback a `settings` dentro de `isPaused()`. Los 3 tests de comportamiento ya ejercitan
el código real de `FrenoCircuito` (no solo su forma) y el propio criterio de cierre del item
("4 viejos + 3 nuevos deben pasar") no pide un cuarto test adicional — se cumple tal cual está.
No se agregó ningún test extra para no introducir cobertura no pedida por el criterio de
aceptación (regla de minimalismo).

## Por qué llegó "ya resuelto"

Mismo patrón de carrera de timing ya documentado varias veces en este repo (#9990003, #9990353,
#9990658, entre otros): una sesión previa hizo FASE 4a-1 y FASE 4a-2 completas en un solo tramo de
trabajo, commiteando directo bajo la referencia del item PADRE (#9990417) en vez de bajo el
sub-item específico (#9990425) que el propio circuito generó como seguimiento — el sub-item nació
y fue aprobado por el revisor prácticamente al mismo tiempo en que el trabajo ya se estaba
haciendo/terminando en la sesión de Irving.

## Conclusión

Sin cambio de código en esta vuelta — el trabajo pedido por #9990425 ya existía en `main`,
verificado línea por línea contra el spec y con la corrida real de los 7 tests pasando.
