<?php

namespace App\Console\Commands\Circuito;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * #988 — motor de detección de regresiones de una vuelta del circuito, por módulo:
 * (1) php -l de los .php tocados vs main, (2) boot de Laravel (`artisan --version`),
 * (3) suite de tests del módulo (si hay sandbox seguro), (4) dry-run de migraciones
 * (`deploy:dry-run-migrations`, reusado tal cual). Cada paso corre y reporta
 * INDEPENDIENTE aunque otro ya haya fallado — el resumen final es FAIL si CUALQUIERA
 * falló de verdad.
 * `--json` emite el mismo resultado como un solo objeto JSON (modulo/pasos/resultado)
 * en vez del texto con iconos, para consumo por otro proceso.
 * `--archivos` (item #9990053, q4): lista explícita de archivos para el Check 1, en vez
 * de derivarlos de `git diff main...HEAD` (útil quien ya sabe qué tocó, ej. otro comando).
 *
 * #989 — capa de acción sobre ese resultado. Si el motor da OK, no se hace nada extra
 * (se pasa el exit 0 tal cual). Si da FAIL:
 *   (a) GUARD DURO: si la rama de la vuelta (actual o --branch) es "main", se ABORTA
 *       sin tocar nada — este comando JAMÁS hace reset/checkout destructivo sobre main.
 *   (b) con --auto-revert: se calcula el merge-base con main y se hace
 *       `git reset --hard` a ese punto SOLO en la rama de la vuelta (nunca checkout de
 *       main), descartando exactamente los commits que esa vuelta agregó.
 *   (c) sin --auto-revert (default, el modo más seguro): en vez de revertir solo, se
 *       invoca `circuito:consultar {--item}` con dos opciones (revertir | investigar
 *       antes) y se devuelve el mismo exit code que ese comando.
 *
 * CONTRATO PARA QUIEN INVOQUE ESTE COMANDO: si devuelve exit 1 — sea por FAIL sin
 * resolver (guard/error) o por escalada de circuito:consultar — el caller NUNCA debe
 * llamar circuito:integrar sobre esa rama.
 */
class VerificarVueltaCommand extends Command
{
    protected $signature = 'circuito:verificar-vuelta
        {modulo : Módulo tocado por la vuelta (ej. Flotas, Talento, Payments, Portal)}
        {--branch= : rama de la vuelta a considerar si falla (default: rama actual, git branch --show-current)}
        {--item= : id del item del roadmap de esa vuelta (requerido para consultar si falla sin --auto-revert)}
        {--auto-revert : si falla, revierte automáticamente la rama al merge-base con main (default apagado = modo más seguro)}
        {--json : Salida en JSON en vez de texto}
        {--archivos=* : Lista explícita de archivos .php a lintear (Check 1), en vez de derivarlos de git diff}';

    protected $description = 'Motor de detección de una vuelta (php -l + boot + tests + dry-run) y su capa de acción: revierte la rama o escala vía circuito:consultar si falla.';

    /** @var array<int, array{paso:string, estado:string, detalle:string}> */
    private array $resultados = [];

    public function handle(): int
    {
        $modulo = trim((string) $this->argument('modulo'));
        $json = (bool) $this->option('json');

        if (! $json) {
            $this->line("Verificando vuelta — módulo: {$modulo}");
            $this->line(str_repeat('-', 70));
        }

        $this->checkPhpLint();
        $this->checkBoot();
        $this->checkTests($modulo);
        $this->checkMigrateDryRun();

        $fallo = collect($this->resultados)->contains(fn (array $r) => $r['estado'] === 'fail');

        if ($json) {
            $this->line(json_encode([
                'modulo' => $modulo,
                'pasos' => $this->resultados,
                'resultado' => $fallo ? 'fail' : 'ok',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->line(str_repeat('-', 70));
            foreach ($this->resultados as $r) {
                $icono = match ($r['estado']) {
                    'ok'   => '✅',
                    'skip' => '⏭️ ',
                    default => '❌',
                };
                $this->line("{$icono} {$r['paso']}" . ($r['detalle'] !== '' ? " — {$r['detalle']}" : ''));
            }
            $this->line(str_repeat('-', 70));
            $this->line($fallo ? '❌ VERIFICACIÓN FALLÓ' : '✅ VERIFICACIÓN OK');
        }

        // OK: el motor no encontró nada — no hay acción que tomar, se pasa el exit 0 tal cual.
        if (! $fallo) {
            return self::SUCCESS;
        }

        // FAIL: #989 — capa de acción (ver contrato completo en el docblock de la clase).
        return $this->accionAlFallar();
    }

    /**
     * #989 — qué hacer cuando el motor de detección dio FAIL: guard duro sobre main,
     * luego auto-revert o consulta a Jarvis según --auto-revert.
     */
    private function accionAlFallar(): int
    {
        $branch = trim((string) $this->option('branch')) ?: $this->ramaActual();

        if ($branch === '') {
            $this->error('No se pudo determinar la rama actual (git branch --show-current vacío) y no se dio --branch. Abortando sin tocar nada.');

            return self::FAILURE;
        }

        if ($branch === 'main') {
            $this->error('🛑 GUARD DURO: la rama de la vuelta (actual o --branch) es "main" — este comando JAMÁS hace reset/checkout destructivo sobre main. Abortando sin tocar nada.');

            return self::FAILURE;
        }

        if ($this->option('auto-revert')) {
            return $this->autoRevertir($branch);
        }

        return $this->consultarAntesDeRevertir($branch);
    }

    /**
     * (b) --auto-revert: descarta en la rama de la vuelta exactamente los commits que
     * agregó, dejándola idéntica a main (nunca toca main ni hace checkout de main).
     */
    private function autoRevertir(string $branch): int
    {
        $mergeBase = $this->git(['merge-base', 'main', $branch]);
        if (! $mergeBase->isSuccessful()) {
            $this->error('No se pudo calcular el merge-base con main: ' . trim($mergeBase->getErrorOutput()));

            return self::FAILURE;
        }
        $base = trim($mergeBase->getOutput());

        if ($this->ramaActual() !== $branch) {
            $checkout = $this->git(['checkout', $branch]);
            if (! $checkout->isSuccessful()) {
                $this->error("No se pudo hacer checkout a la rama «{$branch}»: " . trim($checkout->getErrorOutput()));

                return self::FAILURE;
            }
        }

        $reset = $this->git(['reset', '--hard', $base]);
        if (! $reset->isSuccessful()) {
            $this->error('git reset --hard falló: ' . trim($reset->getErrorOutput()));

            return self::FAILURE;
        }

        $this->line("🔁 --auto-revert: rama «{$branch}» reseteada al merge-base con main ({$base}). Los commits de esta vuelta quedaron descartados.");

        return self::FAILURE;
    }

    /**
     * (c) sin --auto-revert (default): no revierte sola, invoca circuito:consultar con
     * las dos opciones del item y devuelve el mismo exit code que ese comando
     * (0=procede con la opción dada, 1=escalado — ver ConsultarSupervisorCommand).
     */
    private function consultarAntesDeRevertir(string $branch): int
    {
        $itemOpt = trim((string) $this->option('item'));
        if ($itemOpt === '' || ! ctype_digit($itemOpt)) {
            $this->error('Falta --item: sin el id del item no se puede consultar a Jarvis. No se revierte nada (modo más seguro).');

            return self::FAILURE;
        }

        $resumen = "verificar-vuelta detectó (rama «{$branch}»): " . $this->resumenDeFallas();

        $p = new Process([
            'php', 'artisan', 'circuito:consultar', $itemOpt,
            '--pregunta=' . $resumen,
            '--opcion=Revertir la rama de esta vuelta (git reset al merge-base con main)|recomendada|reversible',
            '--opcion=Investigar antes de revertir',
        ], base_path());
        $p->setTimeout(30);
        $p->run();

        $salida = trim($p->getOutput() ?: $p->getErrorOutput());
        if ($salida !== '') {
            $this->line($salida);
        }

        return $p->getExitCode() === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resumenDeFallas(): string
    {
        $fallos = collect($this->resultados)
            ->where('estado', 'fail')
            ->map(fn (array $r) => "{$r['paso']}: {$r['detalle']}")
            ->implode(' | ');

        return $fallos !== '' ? $fallos : 'verificación falló sin detalle disponible';
    }

    private function ramaActual(): string
    {
        $p = $this->git(['branch', '--show-current']);

        return $p->isSuccessful() ? trim($p->getOutput()) : '';
    }

    /**
     * (1) php -l sobre los .php a revisar: si se pasó --archivos, esa lista explícita;
     * si no, los modificados en la rama actual vs main (git diff).
     */
    private function checkPhpLint(): void
    {
        $explicitos = array_values(array_filter(array_map('trim', (array) $this->option('archivos'))));

        if (! empty($explicitos)) {
            $this->lintearArchivos($explicitos, 'lista --archivos');

            return;
        }

        $diff = $this->git(['diff', '--name-only', 'main...HEAD', '--', '*.php']);
        if (! $diff->isSuccessful()) {
            $this->registrar('php -l', 'skip', 'no se pudo diffear contra main (¿sin rama/ref main?)');

            return;
        }

        $archivos = array_values(array_filter(array_map('trim', explode("\n", $diff->getOutput()))));
        $this->lintearArchivos($archivos, 'sin archivos .php modificados vs main');
    }

    /**
     * @param array<int, string> $archivos
     */
    private function lintearArchivos(array $archivos, string $mensajeVacio): void
    {
        if (empty($archivos)) {
            $this->registrar('php -l', 'ok', $mensajeVacio);

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
        $variantes = array_unique([$modulo, ucfirst(strtolower($modulo))]);
        $posibles = [];
        foreach ($variantes as $variante) {
            $posibles[] = "tests/Feature/{$variante}";
            $posibles[] = "tests/Unit/{$variante}";
        }
        $candidatos = array_values(array_unique(array_filter(
            $posibles,
            fn (string $dir) => is_dir(base_path($dir))
        )));

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
     * (4) "php artisan deploy:dry-run-migrations" reusado tal cual. Contrato (item #9990684):
     * exit 1 = migración real falló (FAIL, bloquea) · exit 0 = OK real (corrió contra el
     * sandbox) · exit 2 = OMITIDO (sin sandbox — no valida nada, pero tampoco bloquea). El
     * contrato de este comando NO cambia (0 y 2 siguen sin marcar 'fail', para no bloquear
     * vueltas del circuito): solo se distingue 'skip' de 'ok' en el reporte, igual que ya
     * se hace para "php -l" arriba.
     */
    private function checkMigrateDryRun(): void
    {
        $p = new Process(['php', 'artisan', 'deploy:dry-run-migrations'], base_path());
        $p->setTimeout(600);
        $p->run();

        $ultima    = trim($this->ultimaLinea($p->getOutput() ?: $p->getErrorOutput()));
        $exitCode  = $p->getExitCode();

        if ($exitCode === 0) {
            $this->registrar('migrate --dry-run', 'ok', $ultima);

            return;
        }

        if ($exitCode === 2) {
            $this->registrar('migrate --dry-run', 'skip', $ultima);

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
