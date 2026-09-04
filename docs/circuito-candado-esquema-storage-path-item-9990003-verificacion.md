# Item #9990003 — Candado de esquema de #915 usaba `storage_path()` por-worktree (RESUELTO — ya corregido)

## Hallazgo

El item #9990003 (sub-item de seguimiento de #915, creado 2026-09-03 16:27) reportaba que
`GuardedMigrateCommand::conCandadoDeEsquema()` usaba `storage_path('app/circuito')` para el
archivo `migrate-esquema.lock`, y que como `storage_path()` resuelve DENTRO de cada worktree
(`/home/meganet/circuito/wt-N/storage/...`, ruta física distinta por terminal — confirmado con
`readlink -f` en `wt-1..wt-6`), el candado nunca serializaba nada entre worktrees: cada terminal
tomaba su propio lock privado.

## Verificación

Al llegar a este item, el código ya no tenía el bug descrito:

```php
$ruta = config('circuito.candado_migraciones');
```

`config/circuito.php` define:

```php
'candado_migraciones' => env('CIRCUITO_CANDADO_MIGRACIONES', '/var/www/megaisp/storage/app/circuito/migrate-esquema.lock'),
```

El fix ya está en `main` desde el commit `1e83e7cc` ("fix(circuito#915): el candado de esquema
usa ruta compartida, no storage_path()"), integrado vía `1631905d` ("Integra circuito #915").
Ese commit lo hizo **otra sesión trabajando el propio item #915** el mismo día
(2026-09-03 16:29:38), muy poco después de que este item #9990003 se creara como seguimiento
(16:27) — carrera de timing entre el sub-item de seguimiento y la sesión que ya estaba
corrigiendo el item padre. Mismo patrón de gap de bookkeeping ya documentado varias veces en
`CLAUDE.md` (#733/#741/#738/#745/#830/#816/#818/#848/#905/#878/#906/#907), aunque aquí el gap
fue al revés: el trabajo se hizo ANTES de que el seguimiento terminara de escalarse, no después.

Reverificado en esta vuelta contra el código real de `main`:

1. `php -l app/Console/Commands/GuardedMigrateCommand.php` — sin errores de sintaxis.
2. Confirmado que `readlink -f wt-N/storage` sigue devolviendo una ruta física distinta por
   worktree (el bug seguiría vivo si el código usara `storage_path()`), pero el código ya NO lo
   usa — usa la constante absoluta de `config('circuito.candado_migraciones')`, idéntica sin
   importar desde qué worktree se invoque.
3. **Prueba de concurrencia real** entre dos procesos PHP lanzados desde `wt-3` y `wt-1`
   (bootstrapping cada uno su propio `bootstrap/app.php`), ambos resolviendo
   `config('circuito.candado_migraciones')` y haciendo `flock(LOCK_EX)` sobre esa ruta: el
   proceso B (lanzado 0.3s después desde `wt-1`) esperó a que A liberara el lock antes de
   obtenerlo — exclusión mutua real confirmada entre worktrees.
4. El fallback silencioso (si no se puede abrir el archivo, warn + continúa sin bloquear) sigue
   intacto — no se tocó esa rama del código.

## Conclusión

El bug ya está corregido y verificado en `main`. **Sin cambio de código** en esta vuelta — solo
la verificación y el cierre documental del item de seguimiento, que quedó redundante frente al
fix ya aplicado bajo #915.
