<?php

namespace App\Modules\Addons\IA\Services\Contexto;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class BaseDatosContextoService
{
    public function obtenerContexto(): array
    {
        return [
            'driver' => config('database.default'),
            'tablas' => $this->listarTablas(),
            'migraciones_pendientes' => $this->migracionesPendientes(),
            'seeders' => $this->listarSeeders(),
        ];
    }

    public function listarTablas(): array
    {
        try {
            $driver = config('database.default');
            $dbName = config("database.connections.$driver.database");

            return match (DB::getDriverName()) {
                'mysql' => collect(DB::select('SHOW TABLES'))->map(fn ($r) => array_values((array) $r)[0])->all(),
                'pgsql' => collect(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'"))
                    ->pluck('tablename')->all(),
                'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type='table'"))
                    ->pluck('name')->all(),
                default => [],
            };
        } catch (\Throwable) {
            return [];
        }
    }

    public function migracionesPendientes(): array
    {
        try {
            Artisan::call('migrate:status');
            $output = Artisan::output();
            $lineas = array_filter(array_map('trim', explode("\n", $output)));
            $pendientes = [];
            foreach ($lineas as $l) {
                // formato: "| Ran? | Migration | Batch |"  -> "No" indica pendiente
                if (preg_match('/\|\s*No\s*\|\s*([^\|]+)\s*\|/i', $l, $m)) {
                    $pendientes[] = trim($m[1]);
                }
                if (preg_match('/^\s*Pending\s+(.+)$/i', $l, $m)) {
                    $pendientes[] = trim($m[1]);
                }
            }
            return $pendientes;
        } catch (\Throwable) {
            return [];
        }
    }

    public function listarSeeders(): array
    {
        $dir = database_path('seeders');
        if (!is_dir($dir)) return [];
        $archivos = [];
        foreach (scandir($dir) as $f) {
            if (str_ends_with($f, '.php')) {
                $archivos[] = $f;
            }
        }
        sort($archivos);
        return $archivos;
    }
}
