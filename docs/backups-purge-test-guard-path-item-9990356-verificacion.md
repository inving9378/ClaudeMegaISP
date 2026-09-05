# Item #9990356 — Fase 2: verificación funcional del guard de path en `backups:purge-test`

## Contexto

Sub-item de seguimiento de #9990353 (mismo linaje que #9990351 → #9990352 → #9990353 →
#9990354/#9990355/#9990356), todos apuntando al mismo cambio: reordenar
`PurgeTestBackups::handle()` para calcular `$baseDir` **antes** de abrir el log channel, e
insertar un guard de path duro que aborte si `$baseDir` no resuelve dentro de
`storage_path()`/termina en `/backup_test`.

El spec de esta Fase 2 pedía tomar la rama de la Fase 1 (#9990355). Al momento de ejecutar
este item, #9990355 seguía `en_progreso` (reclamado por otra terminal) — pero el código real
en `main` **ya tiene exactamente ese cambio aplicado**, heredado de los merges anteriores de
esta misma familia (`aaa74666` vía #9990352 → `926e1823`, y el re-chequeo de #9990353 →
`7db82dd8`). Mismo patrón de carrera de timing ya documentado varias veces en `CLAUDE.md`
(#733/#741/#753/#9990003/#9990353, entre otros): el sub-item de verificación se generó antes
de que la vuelta que lo genera viera que el trabajo ya estaba resuelto.

## Verificación realizada

1. **Lectura del archivo real** (`app/Console/Commands/Active/PurgeTestBackups.php`,
   `handle()`): `$baseDir = storage_path('backup_test');` se calcula primero; justo después
   viene el guard de path duro (`str_ends_with(.../backup_test)` + `str_starts_with(storage_path())`)
   que aborta con `self::FAILURE` sin tocar nada más si falla; recién después de pasar el
   guard se crea `$log = Log::channel('backup')`. Orden y guard exactamente como pide el spec.

2. **`php -l`**:
   ```
   $ php -l app/Console/Commands/Active/PurgeTestBackups.php
   No syntax errors detected in app/Console/Commands/Active/PurgeTestBackups.php
   ```

3. **Dry-run funcional con el path real** (`storage_path('backup_test')`, que no existe en
   este worktree):
   ```
   $ php artisan backups:purge-test --dry-run
   [purge-test] No existe /home/meganet/circuito/wt-3/storage/backup_test — nada que hacer.
   $ echo $?
   0
   ```
   El guard **no bloquea** el flujo normal: como `$baseDir` sí resuelve dentro de
   `storage_path()`/termina en `/backup_test`, pasa el guard sin error y cae directo al mensaje
   de "no existe, nada que hacer" — mismo comportamiento que tenía el comando **antes** de que
   existiera el guard (confirmado también en la verificación de #9990353).

## Conclusión

El guard de path duro está aplicado, ordenado correctamente y verificado funcionalmente: no
interfiere con el flujo normal del comando. No se requirió ningún cambio de código en este
item — es documentación de la verificación de un fix que ya estaba en `main`.

El item padre (#9990353) queda como paraguas (`aprobado_irving` + `excluir_pool_automatico`)
hasta que su otro sub-item abierto, #9990355 (Fase 1, en curso en otra terminal), también
cierre — el hook de cierre en cascada (`RoadmapItem.php:459-491`) lo completará solo en ese
momento, sin necesitar intervención manual.
