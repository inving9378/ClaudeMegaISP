<?php

namespace App\Modules\Addons\Manual\Listeners;

use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Dispara `manual:regenerate` cuando una corrida de migraciones aplica
 * al menos una migración nueva. Laravel emite:
 *   - MigrationEnded   (una por cada migración aplicada exitosamente)
 *   - MigrationsEnded  (una al final de toda la corrida)
 *
 * Usamos un flag estático para diferenciar "migrate sin pendientes"
 * (0 emisiones de MigrationEnded) de "migrate con cambios reales".
 */
class RegenerateManualAfterMigrate
{
    private static bool $hadChanges = false;
    private static int  $count      = 0;

    public function handleSingle(MigrationEnded $event): void
    {
        self::$hadChanges = true;
        self::$count++;
    }

    public function handleEnded(MigrationsEnded $event): void
    {
        if (!self::$hadChanges) {
            return;
        }

        $count = self::$count;
        self::$hadChanges = false;
        self::$count      = 0;

        Log::info("[Manual] {$count} migraciones nuevas detectadas — disparando manual:regenerate");

        try {
            Artisan::call('manual:regenerate');
            $output = trim(Artisan::output());
            Log::info('[Manual] Regeneración post-migrate completada', [
                'migrations_applied' => $count,
                'output'             => $output,
            ]);
        } catch (\Throwable $e) {
            Log::error('[Manual] Falló regeneración post-migrate: ' . $e->getMessage());
        }
    }
}
