<?php

namespace App\Console\Commands\Active;

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

    /** Se escanean los árboles que viven en el ciclo request/worker. `config/` y migraciones NO. */
    private const RAICES = ['app', 'routes', 'bootstrap'];

    public function handle(): int
    {
        $hallazgos = [];

        foreach (self::RAICES as $raiz) {
            $dir = base_path($raiz);
            if (! is_dir($dir)) {
                continue;
            }
            foreach ($this->archivos($dir) as $file) {
                foreach ($this->llamadasEnv(file_get_contents($file), str_ends_with($file, '.blade.php')) as $hit) {
                    $hallazgos[] = ['file' => str_replace(base_path() . '/', '', $file)] + $hit;
                }
            }
        }

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

    /**
     * Encuentra las llamadas REALES al helper `env()` con el tokenizador de PHP, no con grep: así
     * no cuenta comentarios, ni `getenv()`, ni el método privado `$this->env()` de MysqldumpEngine,
     * ni el `env(safe-area-inset-bottom)` que es CSS dentro de un blade.
     *
     * @return array<int,array{linea:int,clave:?string,default:?string}>
     */
    private function llamadasEnv(string $src, bool $esBlade): array
    {
        if ($esBlade) {
            // El tokenizador ve `{{ ... }}` como HTML plano. Se traduce a PHP para poder mirar dentro.
            $src = preg_replace('/\{\{(.+?)\}\}/s', '<?= $1 ?>', $src);
            $src = preg_replace('/\{!!(.+?)!!\}/s', '<?= $1 ?>', $src);
        }

        $tokens = @token_get_all($src);
        $hits   = [];
        $sig    = [];   // índices de tokens significativos (sin espacios ni comentarios)

        foreach ($tokens as $i => $t) {
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $sig[] = $i;
        }

        foreach ($sig as $k => $i) {
            $t = $tokens[$i];
            if (! is_array($t) || $t[0] !== T_STRING || strtolower($t[1]) !== 'env') {
                continue;
            }
            // Descarta `->env(`, `::env(`, `function env(`, `new env(`.
            $prev = $k > 0 ? $tokens[$sig[$k - 1]] : null;
            if (is_array($prev) && in_array($prev[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR,
                T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                continue;
            }
            // Tiene que ser una llamada: el siguiente significativo es `(`.
            $next = $tokens[$sig[$k + 1] ?? $i] ?? null;
            if ($next !== '(') {
                continue;
            }

            $clave = $tokens[$sig[$k + 2] ?? $i] ?? null;
            $clave = (is_array($clave) && $clave[0] === T_CONSTANT_ENCAPSED_STRING)
                ? trim($clave[1], "'\"")
                : null;

            $default = null;
            if ($clave !== null && ($tokens[$sig[$k + 3] ?? $i] ?? null) === ',') {
                $d = $tokens[$sig[$k + 4] ?? $i] ?? null;
                $default = is_array($d) ? trim($d[1], "'\"") : (is_string($d) ? $d : null);
            }

            $hits[] = ['linea' => $t[2], 'clave' => $clave, 'default' => $default];
        }

        return $hits;
    }

    /** @return string[] */
    private function archivos(string $dir): array
    {
        $out = [];
        $it  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && in_array($f->getExtension(), ['php'], true)) {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }
}
