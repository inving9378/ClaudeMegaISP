<?php

namespace App\Console\Commands\Active;

use App\Services\EnvRuntimeScanner;
use Illuminate\Console\Command;

/**
 * ¿Es seguro correr `php artisan config:cache` AHORA MISMO? (item #790)
 *
 * POR QUÉ EXISTE. Con la config cacheada, Laravel se salta `LoadEnvironmentVariables` al bootear
 * (ver `Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables::bootstrap()`: `if
 * ($app->configurationIsCached()) return;`) → el `.env` NO se lee y **toda** llamada a `env()`
 * fuera de `config/*.php` devuelve su default (o null). Este repo tenía credenciales vivas ahí:
 * la convención decía "cierra con `config:cache`" y el código decía "si haces eso me quedo sin
 * llaves". La regla escrita y el código se contradecían justo en el punto del que depende que el
 * circuito pueda llamar a Claude.
 *
 * La salida NO es un consejo: es el contrato del checklist de cierre.
 *
 *     php artisan config:auditar-env && php artisan config:cache
 *
 * Si queda una sola llamada en runtime, el `&&` corta y `config:cache` no corre. Nadie puede
 * "hacer lo correcto" y tumbar el circuito sin enterarse.
 *
 * `env()` SÍ es correcto dentro de `config/*.php` (patrón estándar de Laravel: se evalúa al
 * construir la caché) y en migraciones/seeders que se corren a mano. Por eso no se escanean.
 */
class AuditarEnvRuntimeCommand extends Command
{
    protected $signature = 'config:auditar-env
                            {--lista : sólo la lista, sin la explicación}';

    protected $description = '¿Es seguro `config:cache`? Lista las llamadas a env() en runtime fuera de config/ (0 = seguro). Exit 1 si queda alguna.';

    public function handle(EnvRuntimeScanner $scanner): int
    {
        $hallazgos = $scanner->escanear();

        if (! $hallazgos) {
            $this->info('✔ 0 llamadas a env() en runtime fuera de config/. `php artisan config:cache` es SEGURO.');

            return self::SUCCESS;
        }

        $this->error('✘ ' . count($hallazgos) . ' llamada(s) a env() en runtime fuera de config/. '
            . '`config:cache` las dejaría en su default (o null).');
        $this->line('');

        $porArchivo = [];
        foreach ($hallazgos as $h) {
            $porArchivo[$h['file']][] = $h;
        }
        ksort($porArchivo);
        foreach ($porArchivo as $file => $hits) {
            $this->line("  <fg=yellow>{$file}</>");
            foreach ($hits as $h) {
                $clave = $h['clave'] ?? null;
                $this->line("    :{$h['linea']}  " . ($clave
                    ? $clave . ($h['default'] === null ? '  (sin default → NULL)' : "  (default: {$h['default']})")
                    : '<clave dinámica — no se puede mover mecánicamente, necesita un mapa>'));
            }
        }

        if (! $this->option('lista')) {
            $this->line('');
            $this->line('Cada una se mueve a una clave de `config/*.php` (donde env() sí es correcto) y el');
            $this->line('llamador pasa a `config(...)`. Mientras quede una, el warm-up de cierre es:');
            $this->line('  <fg=green>php artisan config:clear && php artisan route:clear && php artisan queue:restart</>');
        }

        return self::FAILURE;
    }
}
