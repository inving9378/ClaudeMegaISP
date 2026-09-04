# Item #9990282 — Fase 3a de #9990246 (RESUELTO — ya implementado por el propio #9990246)

## Qué pedía el item

Crear `app/Modules/Addons/Roadmap/Support/AblandamientoFrontera.php` (clase pura, sin Laravel),
con:
- `const VEREDICTO = 'ablandamiento_paso'`
- `static function esAblandamientoQueDejaPasar(?string $categoria, ?string $categoriaDetectada, bool $ablandada): bool`
- Un test candado puro (`tests/Unit/Modules/Addons/Roadmap/AblandamientoFronteraTest.php`) cubriendo
  4 casos (true cuando ablandó y categoría salió null; false en `avisar`; false sin categoría
  detectada; false cuando `categoria` no es null).
- Explícitamente pedía **NO** tocar `TorreAutomationPolicy.php` todavía (eso sería Fase 3b,
  dependiente de que esta clase ya exista en `main`).

## Qué se encontró en `main`

El propio item padre, **#9990246** ("Fase 3 de #9990210 — registrar en Torre"), ya resolvió
las dos fases de un tirón en el commit `35526028` (mergeado a `main` vía `7c2e7be3`):

- `Support/AblandamientoFrontera.php` — existe, con `const VEREDICTO = 'ablandamiento_paso'`
  (idéntico) y un método `static function evento(array $det): ?array` que es la MISMA decisión
  pura que pedía `esAblandamientoQueDejaPasar()` (bool), solo que devuelve el array
  `{categoria, termino, veredicto}` en vez de un booleano — forma más útil para el consumidor real
  (`TorreAutomationPolicy::registrarAblandamientoPaso()`, que necesita esos datos para el `INSERT`),
  no solo un booleano que luego habría que volver a desarmar.
- `tests/Unit/Modules/Addons/Roadmap/AblandamientoFronteraTest.php` — existe, con 6 tests que
  cubren exactamente los mismos casos que pedía #9990282 (más un caso extra de blindaje
  defensivo). Extiende `PHPUnit\Framework\TestCase` puro (no `Tests\TestCase` de Laravel), mismo
  patrón que `MencionFronteraDuraTest`.
- `TorreAutomationPolicy.php` — el wiring (Fase 3b) también se hizo en el mismo commit:
  `estadoInicial()` calcula `fronteraDuraDeItemDetalle()` una sola vez y, si
  `AblandamientoFrontera::evento($det)` no es null, llama a
  `registrarAblandamientoPaso()` (INSERT + dedup por ventana de 6h, mismo patrón que
  `registrarFronteraDuraVivo()`).

## Verificado en esta vuelta (wt-2)

```
php -l app/Modules/Addons/Roadmap/Support/AblandamientoFrontera.php
php -l tests/Unit/Modules/Addons/Roadmap/AblandamientoFronteraTest.php
vendor/bin/phpunit tests/Unit/Modules/Addons/Roadmap/AblandamientoFronteraTest.php
```

Resultado: sin errores de sintaxis, `OK (6 tests, 8 assertions)` — sin bootear Laravel ni tocar BD,
tal como exigía el criterio de verificación del propio item.

## Por qué pasó esto

Carrera de descomposición: cuando #9990246 se creó como "Fase 3 de #9990210", su propio spec pedía
partirse en 3a (decisión pura) y 3b (wiring) para caber en una vuelta. Pero la vuelta que lo tomó
resolvió ambas fases en un solo commit (spec chico, cabía completo). El sub-item #9990282 (3a) ya
había nacido como seguimiento antes de que esa vuelta terminara — o nació de una lectura del spec
que no se enteró de que el padre ya se había resuelto entero. Mismo patrón de carrera que
`#733/#741/#753` (generador de seguimientos vs. cierre del padre), documentado en `CLAUDE.md`.

## Conclusión

Nada que implementar: el código, la clase, la constante y el test candado ya existen en `main` y
pasan. **Sin cambio de código de negocio** en esta vuelta — solo esta verificación.
