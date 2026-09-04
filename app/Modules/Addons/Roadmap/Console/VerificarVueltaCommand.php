<?php

namespace App\Modules\Addons\Roadmap\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * #988 — motor de detección de regresiones de una vuelta del circuito, por módulo:
 * (1) php -l de los .php tocados vs main, (2) boot de Laravel (`artisan --version`),
 * (3) suite de tests del módulo (si hay sandbox seguro), (4) dry-run de migraciones
 * (`deploy:dry-run-migrations`, reusado tal cual). Cada paso corre y reporta
 * INDEPENDIENTE aunque otro ya haya fallado — el resumen final es FAIL si CUALQUIERA
 * falló de verdad. ESTA FASE NO REVIERTE NI ESCALA, solo detecta y reporta.
 */
class VerificarVueltaCommand extends Command
{
    protected $signature = 'circuito:verificar-vuelta {modulo : Módulo tocado por la vuelta (ej. Flotas, Talento, Payments, Portal)}';

    protected $description = 'Motor de detección de una vuelta: php -l + boot + tests del módulo + dry-run de migraciones.';

    /** @var array<int, array{paso:string, estado:string, detalle:string}> */
    private array $resultados = [];

    public function handle(): int
    {
        $modulo = trim((string) $this->argument('modulo'));

        $this->line("Verificando vuelta — módulo: {$modulo}");
        $this->line(str_repeat('-', 70));

        $this->checkPhpLint();
        $this->checkBoot();
        $this->checkTests($modulo);
        $this->checkMigrateDryRun();

        $this->line(str_repeat('-', 70));
        $fallo = false;
        foreach ($this->resultados as $r) {
            $icono = match ($r['estado']) {
                'ok'   => '✅',
                'skip' => '⚠️ ',
                default => '❌',
            };
            $this->line("{$icono} {$r['paso']}" . ($r['detalle'] !== '' ? " — {$r['detalle']}" : ''));
            if ($r['estado'] === 'fail') {
                $fallo = true;
            }
        }
        $this->line(str_repeat('-', 70));
        $this->line($fallo ? '❌ VERIFICACIÓN FALLÓ' : '✅ VERIFICACIÓN OK');

        return $fallo ? self::FAILURE : self::SUCCESS;
    }

    /**
     * (1) php -l sobre los .php modificados en la rama actual vs main.
     */
    private function checkPhpLint(): void
    {
        $diff = $this->git(['diff', '--name-only', 'main...HEAD', '--', '*.php']);
        if (! $diff->isSuccessful()) {
            $this->registrar('php -l', 'skip', 'no se pudo diffear contra main (¿sin rama/ref main?)');

            return;
        }

        $archivos = array_values(array_filter(array_map('trim', explode("\n", $diff->getOutput()))));
        if (empty($archivos)) {
            $this->registrar('php -l', 'ok', 'sin archivos .php modificados vs main');

            return;
        }

        $fallos = [];
        $revisados = 0;
        foreach ($archivos as $archivo) {
            $ruta = base_path($archivo);
            if (! is_file($ruta)) {
                continue; // borrado en esta rama, nada que lintear
            }
            $revisados++;
            $p = new Process(['php', '-l', $ruta]);
            $p->run();
            if (! $p->isSuccessful()) {
                $fallos[] = $archivo . ': ' . trim($p->getOutput() ?: $p->getErrorOutput());
            }
        }

        if (empty($fallos)) {
            $this->registrar('php -l', 'ok', "{$revisados} archivo(s) sin errores de sintaxis");

            return;
        }

        $this->registrar('php -l', 'fail', count($fallos) . ' archivo(s) con error: ' . implode(' | ', $fallos));
    }

    /**
     * (2) "php artisan --version" via Symfony\Process con timeout corto, confirma que
     * la app bootea sin excepción.
     */
    private function checkBoot(): void
    {
        $p = new Process(['php', 'artisan', '--version'], base_path());
        $p->setTimeout(15);
        $p->run();

        if ($p->isSuccessful()) {
            $this->registrar('boot (artisan --version)', 'ok', trim($p->getOutput()));

            return;
        }

        $this->registrar('boot (artisan --version)', 'fail', trim($p->getErrorOutput() ?: $p->getOutput()));
    }

    /**
     * (3) Suite de tests del módulo: tests/Feature/{Modulo} y/o tests/Unit/{Modulo} si
     * existen; si NO existe ninguna, fallback a tests/Unit completo con aviso. Nunca
     * corre migrate:fresh --seed (TestCase.php) contra la BD compartida de dev: sin
     * .env.testing con DB_DATABASE distinta, este paso se SALTA (mismo principio que
     * deploy:dry-run-migrations — sin sandbox seguro, no se arriesga la BD viva).
     */
    private function checkTests(string $modulo): void
    {
        $candidatos = array_values(array_filter([
            "tests/Feature/{$modulo}",
            "tests/Unit/{$modulo}",
        ], fn (string $dir) => is_dir(base_path($dir))));

        $usaFallback = empty($candidatos);
        if ($usaFallback) {
            $candidatos = ['tests/Unit'];
        }

        if (! $this->sandboxDeTestsSeguro()) {
            $this->registrar(
                'tests',
                'skip',
                'sin .env.testing con DB_DATABASE distinta a dev — TestCase.php corre migrate:fresh --seed, '
                . 'no se arriesga la BD compartida'
            );

            return;
        }

        $etiqueta = $usaFallback
            ? "sin suite específica para «{$modulo}», fallback a tests/Unit"
            : 'suite: ' . implode(', ', $candidatos);

        $fallos = [];
        foreach ($candidatos as $dir) {
            $p = new Process(['vendor/bin/phpunit', $dir], base_path());
            $p->setTimeout(600);
            $p->run();
            if (! $p->isSuccessful()) {
                $fallos[] = $dir . ': ' . trim($this->ultimaLinea($p->getOutput() ?: $p->getErrorOutput()));
            }
        }

        if (empty($fallos)) {
            $this->registrar('tests', 'ok', $etiqueta . ' — pasaron');

            return;
        }

        $this->registrar('tests', 'fail', $etiqueta . ' — ' . implode(' | ', $fallos));
    }

    /**
     * ¿Hay sandbox seguro para correr tests sin tocar la BD de dev? Requiere .env.testing
     * con DB_DATABASE distinto al de la conexión por defecto.
     */
    private function sandboxDeTestsSeguro(): bool
    {
        $envTesting = base_path('.env.testing');
        if (! is_file($envTesting)) {
            return false;
        }

        $contenido = (string) file_get_contents($envTesting);
        if (! preg_match('/^DB_DATABASE=(.+)$/m', $contenido, $m)) {
            return false;
        }

        $dbTesting = trim($m[1]);
        $dbDev = (string) config('database.connections.' . config('database.default') . '.database');

        return $dbTesting !== '' && $dbTesting !== $dbDev;
    }

    /**
     * (4) "php artisan deploy:dry-run-migrations" reusado tal cual. Contrato: exit 1 =
     * migración real falló (FAIL); exit 0 = OK o "sin sandbox, se omite" (no bloquea).
     */
    private function checkMigrateDryRun(): void
    {
        $p = new Process(['php', 'artisan', 'deploy:dry-run-migrations'], base_path());
        $p->setTimeout(600);
        $p->run();

        $ultima = trim($this->ultimaLinea($p->getOutput() ?: $p->getErrorOutput()));

        if ($p->getExitCode() === 0) {
            $this->registrar('migrate --dry-run', 'ok', $ultima);

            return;
        }

        $this->registrar('migrate --dry-run', 'fail', $ultima);
    }

    private function registrar(string $paso, string $estado, string $detalle): void
    {
        $this->resultados[] = ['paso' => $paso, 'estado' => $estado, 'detalle' => $detalle];
    }

    private function ultimaLinea(string $texto): string
    {
        $lineas = array_values(array_filter(array_map('trim', explode("\n", $texto))));

        return end($lineas) ?: '';
    }

    private function git(array $args): Process
    {
        $p = new Process(array_merge(['git'], $args), base_path());
        $p->run();

        return $p;
    }
}
