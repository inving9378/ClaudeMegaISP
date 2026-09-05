# Item #9990353 — Guard de path duro en `backups:purge-test` (RESUELTO — ya aplicado por #9990352)

## Pedido del item

Sub-item de seguimiento de #9990352, con spec exacto: en
`app/Console/Commands/Active/PurgeTestBackups.php::handle()`, mover `$baseDir = storage_path('backup_test')`
ANTES de `$log = Log::channel('backup')`, e insertar justo después de calcular `$baseDir` un
guard de path duro que aborte (`Log::channel('backup')` directo + `return self::FAILURE`) si
`$baseDir` no resuelve dentro de `storage_path()`/termina en `/backup_test` — sin tocar el resto
de la lógica (`is_dir`, `keep`, `apply`, `glob`).

## Verificación

Al llegar a este item, el código **ya tenía exactamente ese cambio aplicado**:

```php
public function handle(): int
{
    $baseDir = storage_path('backup_test');

    // Guard de path duro: ...
    $terminaEnBackupTest = str_ends_with(rtrim($baseDir, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR . 'backup_test');
    $empiezaEnStoragePath = str_starts_with($baseDir, storage_path());
    if (! $terminaEnBackupTest && ! $empiezaEnStoragePath) {
        $this->error(...);
        Log::channel('backup')->error(...);
        return self::FAILURE;
    }

    $log     = Log::channel('backup');
    $keep    = max(0, (int) $this->option('keep'));
    ...
```

El fix ya está en `main` desde el commit `aaa74666` ("Agrega guard de path duro en
backups:purge-test"), integrado vía `926e1823` ("Integra circuito #9990352 ... a main") — hecho
por la sesión que trabajó el propio item padre #9990352. Mismo patrón de carrera de timing entre
el item de seguimiento y la sesión que ya estaba corrigiendo el padre, ya documentado varias
veces en `CLAUDE.md` (#733/#741/#738/#745/#830/#816/#818/#848/#905/#878/#906/#907/#9990003):
el seguimiento se generó a partir del spec original de #9990352 sin ver que ya se había
resuelto en la misma vuelta.

Reverificado en esta vuelta contra el código real de `main`:

1. `php -l app/Console/Commands/Active/PurgeTestBackups.php` — sin errores de sintaxis.
2. `php artisan backups:purge-test --dry-run` con el path real (`storage_path('backup_test')`,
   que en este worktree no existe) — el guard **no bloquea**: pasa de largo y cae directo al
   mensaje normal `"No existe {baseDir} — nada que hacer."`, sin el mensaje de error del guard.
   Confirma que el guard no produce falso-positivo sobre el path legítimo.
3. Orden y lógica intactos: `$baseDir` se calcula primero, el guard corre antes de abrir el
   log channel (usa `Log::channel('backup')` directo en la rama de error, como pedía el spec),
   y `$log` se crea justo después de pasar el guard. El resto del método (`is_dir`, `keep`,
   `apply`, `glob`, borrado) no se tocó.

## Conclusión

El guard ya está aplicado y verificado en `main`. **Sin cambio de código** en esta vuelta — solo
la verificación y el cierre documental del item de seguimiento, que quedó redundante frente al
fix ya aplicado bajo #9990352.
