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
                            {--dry-run : Muestra qué se publicaría (incluidas las notas) sin llamar a la API de GitHub}
                            {--force : Publica aunque el código del tag no esté en origin/main (NO recomendado)}';

    protected $description = 'Publica en GitHub el Release de una versión ya tageada (solo en la instancia publicadora)';

    /**
     * ¿El commit al que apunta el tag es alcanzable desde origin/main?
     *
     * No basta con que el tag exista en el remoto: si su commit cuelga fuera de la rama, un
     * clon o un repo consumidor configurado sin tags no puede llegar a él. Se refresca
     * `origin/main` antes de comparar para no decidir con una referencia local vieja.
     */
    private function codigoPublicadoEnRemoto(string $version): bool
    {
        // --force es obligatorio: hay tags antiguos cuyo objeto local difiere del remoto y sin
        // él git RECHAZA el fetch entero con exit 1 ("sobrescribiría tag existente").
        exec('git fetch origin main --tags --force 2>&1', $o, $c);
        if ($c !== 0) {
            $this->warn('No se pudo refrescar origin (git fetch falló); se evalúa con la referencia local, '
                . 'que solo puede estar atrasada → el guard falla-seguro (aborta de más, nunca de menos).');
        }

        exec('git rev-list -n1 ' . escapeshellarg($version) . ' 2>/dev/null', $salida, $codigo);
        $commit = trim($salida[0] ?? '');
        if ($codigo !== 0 || $commit === '') {
            return false;
        }

        exec('git merge-base --is-ancestor ' . escapeshellarg($commit) . ' origin/main 2>/dev/null', $x, $esAncestro);

        return $esAncestro === 0;
    }

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

        // ── Guard de alcanzabilidad (item #530) ───────────────────────────────────────────
        // Publicar el Release ANUNCIA la versión a producción. Si el commit del tag no es
        // alcanzable desde origin/main, prod la ve, la intenta aplicar y su
        // `git fetch origin && git checkout tags/{v}` falla con exit 1 → rollback.
        // Esto fue exactamente lo que pasó con V1.26-V1.29.
        if (!$this->codigoPublicadoEnRemoto($version)) {
            if (!$this->option('force')) {
                $this->error("ABORTADO — el código de «{$version}» no está en origin/main.");
                $this->line('  Publicar el Release ofrecería a producción una actualización que NO va a poder aplicar');
                $this->line('  (su git checkout del tag fallaría con exit 1 y haría rollback).');
                $this->line('  Solución: `git push origin main` desde el publicador y reintentar.');
                $this->line('  Para publicar de todas formas: --force');
                return self::FAILURE;
            }
            $this->warn('AVISO: el código no está en origin/main, se publica igual por --force.');
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

        // Corre por CLI (sin sesión), pero `deployment_logs.triggered_by` es NOT NULL: la
        // publicación se atribuye al usuario de sistema MEGAISP, resuelto por email
        // (los ids NO coinciden entre dev y prod).
        $sistema = \App\Models\User::systemBot();
        if (!$sistema) {
            $this->error('ABORTADO — no existe el usuario de sistema MEGAISP para atribuir la publicación.');
            return self::FAILURE;
        }

        // Queda registro en deployment_logs igual que cualquier otra operación de release,
        // para que la publicación sea auditable desde la pantalla de Historial.
        $log = DeploymentLog::create([
            'release_id'   => $release->id,
            'triggered_by' => $sistema->id,
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
