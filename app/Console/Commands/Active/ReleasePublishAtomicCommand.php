<?php

namespace App\Console\Commands\Active;

use App\Models\Release;
use App\Services\Deploy\ReleasePreflightService;
use App\Services\Deploy\ReleasePublisherService;
use Illuminate\Console\Command;

/**
 * Entrada manual del ReleasePublisher atómico (item roadmap #9990674, F3 de la épica
 * #9990668) — para ensayar el motor nuevo (ej. con una versión de prueba prefijo `TEST-`)
 * SIN depender de que el feature flag `releases.emision_atomica` esté encendido en el
 * pipeline web. Este comando NO se ejecuta solo: alguien tiene que invocarlo a propósito.
 *
 * --dry-run es puramente informativo: corre el pre-vuelo (F2, solo lectura) y describe qué
 * pasos ejecutaría, pero NO hace push, NO crea tag, NO llama a la API de GitHub. Sin
 * --dry-run, el comando SÍ empuja a origin y publica un GitHub Release real — exactamente
 * lo que pide la "ACEPTACIÓN" del item (rehearsal con prefijo TEST- que se borra), pero es
 * una acción real contra el repositorio remoto compartido y debe correrla una persona que
 * entienda esa consecuencia, no un proceso desatendido.
 */
class ReleasePublishAtomicCommand extends Command
{
    protected $signature = 'releases:publish-atomic
                            {version : Versión a publicar tal como está en la tabla releases (ej. TEST-ensayo-1)}
                            {--dry-run : Solo corre el pre-vuelo y describe los pasos, sin tocar git ni GitHub}';

    protected $description = 'Publica una versión con el motor atómico (push + tag + GitHub Release + fila releases, con compensación) — item #9990674';

    public function handle(ReleasePreflightService $preflight, ReleasePublisherService $publisher): int
    {
        $version = trim($this->argument('version'));

        $release = Release::where('version', $version)->first();
        if (!$release) {
            $this->error("ABORTADO — no existe la versión «{$version}» en la tabla releases.");
            $this->line('  El ReleasePublisher opera sobre una fila ya creada (POST /releases/store la crea primero).');
            return self::FAILURE;
        }

        if (config('deployment.publisher', false) !== true) {
            $this->error('ABORTADO — esta instancia no es la publicadora (deployment.publisher=false).');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $report = $preflight->evaluate($version);
            $filas  = array_map(fn (array $c) => [$c['key'], strtoupper($c['status']), $c['detail']], $report['checks']);
            $this->table(['Check', 'Estado', 'Detalle'], $filas);
            $this->line('Veredicto del pre-vuelo: ' . strtoupper($report['veredicto']));

            if ($report['veredicto'] === 'rojo') {
                $this->error('DRY-RUN: con este veredicto el acto atómico ABORTARÍA en el paso 0, sin tocar nada.');
                return self::FAILURE;
            }

            $this->newLine();
            $this->info('DRY-RUN: el acto atómico procedería con:');
            $this->line('  1. git push origin main');
            $this->line("  2. git tag -a {$version} -m \"Release {$version}\" + git push origin refs/tags/{$version}");
            $this->line('  3. POST /repos/{repo}/releases (GitHub Release)');
            $this->line('  4. UPDATE releases SET github_release_id, published_at, estado_publicacion=publicada');
            $this->warn('DRY-RUN: no se ejecutó ningún paso real.');
            return self::SUCCESS;
        }

        $this->warn("Publicando «{$version}» con el motor atómico — esto SÍ empuja a origin y publica en GitHub.");
        $resultado = $publisher->publish($release);

        if ($resultado['ok']) {
            $this->info("OK — {$resultado['html_url']} (github_release_id={$resultado['github_release_id']}).");
            return self::SUCCESS;
        }

        $this->error("FALLÓ en «{$resultado['failed_at']}» (compensado, sin dejar nada a medias): {$resultado['message']}");
        return self::FAILURE;
    }
}
