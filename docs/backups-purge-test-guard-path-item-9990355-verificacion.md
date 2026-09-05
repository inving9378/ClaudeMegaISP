# Item #9990355 — Fase 1: reorder + guard de path en `PurgeTestBackups::handle()` (RESUELTO — ya aplicado)

## Pedido del item

Sub-item de seguimiento de #9990353 (que a su vez era sub-item de #9990352), con spec exacto:
en `app/Console/Commands/Active/PurgeTestBackups.php::handle()`, mover
`$baseDir = storage_path('backup_test')` ANTES de `$log = Log::channel('backup')`, e insertar
justo después de calcular `$baseDir` un guard de path duro que aborte (usando
`Log::channel('backup')` directo, ya que `$log` todavía no existe en ese punto, + `return
self::FAILURE`) si `$baseDir` no resuelve dentro de `storage_path()`/no termina en
`/backup_test` — sin tocar el resto de la lógica (`is_dir`, `keep`, `apply`, `glob`).

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
        $this->error("[purge-test] Guard de path duro: baseDir fuera de storage_path()/backup_test — abortando sin tocar nada. baseDir={$baseDir}");
        Log::channel('backup')->error("[purge-test] Guard de path duro abortó la ejecución: baseDir={$baseDir} no resuelve dentro de storage_path()/backup_test");
        return self::FAILURE;
    }

    $log     = Log::channel('backup');
    $keep    = max(0, (int) $this->option('keep'));
    ...
```

El fix ya está en `main` desde el commit `aaa74666` ("Agrega guard de path duro en
backups:purge-test"), integrado vía `926e1823` ("Integra circuito #9990352 ... a main"). Es el
mismo commit que ya cerró el item hermano #9990353 (ver
`docs/backups-purge-test-guard-path-item-9990353-verificacion.md`) — mismo patrón de carrera de
timing entre varios sub-items de seguimiento generados sobre el mismo spec original de #9990352,
sin ver que ya se había resuelto en una sola vuelta. Ya documentado varias veces en `CLAUDE.md`
(#733/#741/#738/#745/#830/#816/#818/#848/#905/#878/#906/#907/#9990003/#9990353).

Reverificado en esta vuelta contra el código real de `main`:

1. `php -l app/Console/Commands/Active/PurgeTestBackups.php` — sin errores de sintaxis.
2. Orden y lógica intactos: `$baseDir` se calcula primero, el guard corre antes de abrir el
   log channel (usa `Log::channel('backup')` directo en la rama de error, como pedía el spec),
   y `$log` se crea justo después de pasar el guard. El resto del método (`is_dir`, `keep`,
   `apply`, `glob`, borrado) no se tocó.
3. `git log` confirma `aaa74666` como único commit que tocó el archivo desde su creación
   (`701fd574`), y que ya está integrado en `main` vía `926e1823`.

## Conclusión

El reorder + guard ya está aplicado y verificado en `main`. **Sin cambio de código** en esta
vuelta — solo la verificación y el cierre documental del item de seguimiento, que quedó
redundante frente al fix ya aplicado bajo #9990352/#9990353.
