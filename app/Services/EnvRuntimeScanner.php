<?php

namespace App\Services;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Escaneo de llamadas a `env()` en tiempo de ejecución fuera de `config/`, extraído de
 * `AuditarEnvRuntimeCommand` (#790) para que el detector del auditor del circuito (#901) lo
 * consuma sin reimplementar el tokenizador. Fuente única de verdad de "qué cuenta como llamada
 * real a env() en runtime" — ambos consumidores comparten exactamente el mismo criterio.
 */
class EnvRuntimeScanner
{
    /** Árboles que viven en el ciclo request/worker. `config/` y migraciones NO se escanean. */
    public const RAICES = ['app', 'routes', 'bootstrap'];

    /** @return array<int,array{file:string,linea:int,clave:?string,default:?string}> */
    public function escanear(): array
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

        return $hallazgos;
    }

    /**
     * Encuentra las llamadas REALES al helper `env()` con el tokenizador de PHP, no con grep: así
     * no cuenta comentarios, ni `getenv()`, ni un método privado `->env()`, ni el
     * `env(safe-area-inset-bottom)` que es CSS dentro de un blade.
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
        $it  = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && in_array($f->getExtension(), ['php'], true)) {
                $out[] = $f->getPathname();
            }
        }
        sort($out);

        return $out;
    }
}
