<?php

namespace App\Console\Commands\Active;

use App\Models\DeploymentLog;
use App\Models\Release;
use App\Services\Deploy\DeploymentService;
use App\Services\Updates\GitHubUpdateService;
use Illuminate\Console\Command;

/**
 * Publica en GitHub el Release de una versión YA tageada.
 *
 * Por qué existe (item #529): el checker de actualizaciones de las instancias de producción
 * (GitHubUpdateService → GET /repos/{repo}/releases/latest) solo ve objetos "Release", nunca
 * tags sueltos. En el publicador (dev, APP_ENV=local) el paso `github_release` del pipeline
 * se omite por `skip_if_not_production` (política del item #245, que NO se reabre), así que
 * las versiones quedaban tageadas pero sin Release → producción reportaba "Estás al día".
 *
 * Este comando cierra ese hueco de forma EXPLÍCITA: publicar es una acción deliberada del
 * operador, no un efecto secundario de crear una versión.
 */
class PublishGithubReleaseCommand extends Command
{
    protected $signature = 'releases:publish-github
                            {version : Versión a publicar, tal como está en la tabla releases (ej. V1.29-05.08.2026)}
                            {--dry-run : Muestra qué se publicaría (incluidas las notas) sin llamar a la API de GitHub}';

    protected $description = 'Publica en GitHub el Release de una versión ya tageada (solo en la instancia publicadora)';

    public function handle(DeploymentService $deploy, GitHubUpdateService $updates): int
    {
        $version = trim($this->argument('version'));

        // ── Guard duro: solo la instancia publicadora puede publicar ───────────────────────
        // Producción queda en false por default: aunque alguien corra el comando allá, aborta.
        if (config('deployment.publisher', false) !== true) {
            $this->error('ABORTADO — esta instancia no es la publicadora de releases.');
            $this->line('  Solo el publicador (dev) puede crear GitHub Releases.');
            $this->line('  Si esta ES la instancia publicadora: DEPLOY_IS_PUBLISHER=true en su .env.');
            return self::FAILURE;
        }

        if (empty(config('deployment.github.token')) || empty(config('deployment.github.repo'))) {
            $this->error('ABORTADO — GITHUB_TOKEN o GITHUB_REPO no configurados.');
            return self::FAILURE;
        }

        $release = Release::where('version', $version)->first();
        if (!$release) {
            $this->error("ABORTADO — no existe la versión «{$version}» en la tabla releases.");
            return self::FAILURE;
        }

        // El Release de GitHub se ancla al tag: sin tag no hay nada que publicar.
        exec('git tag --list ' . escapeshellarg($version), $salida, $codigo);
        if ($codigo !== 0 || empty($salida)) {
            $this->error("ABORTADO — el tag «{$version}» no existe en este repositorio local.");
            $this->line('  Publicar un Release exige que el tag ya esté creado y empujado a origin.');
            return self::FAILURE;
        }

        $body = $deploy->buildReleaseBody($release, $version);

        $this->info("Versión : {$version}");
        $this->info('Título  : ' . ($release->title ?: '(sin título — se usará el tag)'));
        $this->info('Notas   : ' . strlen($body) . ' caracteres');

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->line('───────── NOTAS QUE SE PUBLICARÍAN ─────────');
            $this->line($body);
            $this->line('────────────────────────────────────────────');
            $this->warn('DRY-RUN: no se llamó a la API de GitHub.');
            return self::SUCCESS;
        }

        // Queda registro en deployment_logs igual que cualquier otra operación de release,
        // para que la publicación sea auditable desde la pantalla de Historial.
        $log = DeploymentLog::create([
            'release_id'   => $release->id,
            'triggered_by' => null,
            'status'       => 'running',
            'steps'        => [],
            'payload'      => [
                'version' => $version,
                'title'   => $release->title ?? $version,
                'source'  => 'releases:publish-github',
            ],
        ]);

        [$exitCode, $output, $durationMs] = $deploy->publishGithubRelease($version, $log);

        // executeGithubRelease nunca marca fallo con exit code (es no-crítico en el pipeline):
        // el resultado real viene en el texto, así que se clasifica por ahí.
        $ok = $exitCode === 0 && (str_contains($output, 'creado') || str_contains($output, 'actualizado'));

        $log->update([
            'status' => $ok ? 'success' : 'failed',
            'steps'  => [[
                'key'         => 'github_release',
                'name'        => "Publicar GitHub Release ({$version})",
                'status'      => $ok ? 'success' : 'failed',
                'output'      => $output,
                'duration_ms' => $durationMs,
            ]],
            'error_message' => $ok ? null : $output,
            'finished_at'   => now(),
        ]);

        if (!$ok) {
            $this->error("FALLÓ ({$durationMs} ms): {$output}");
            return self::FAILURE;
        }

        // El resultado del checker se cachea: sin esto, una instancia que acaba de consultar
        // seguiría viendo el estado viejo hasta que expire el TTL.
        $updates->clearCache();

        $this->info("OK ({$durationMs} ms): {$output}");
        $this->line("Registrado en deployment_logs #{$log->id}.");

        return self::SUCCESS;
    }
}
