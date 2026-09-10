<?php

namespace App\Services\Deploy;

use App\Services\ReleaseChangelogService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Preflight de release READ-ONLY (item roadmap #9990681, F2b de la épica #9990668). Corre 9
 * chequeos heurísticos antes de cortar una versión; NINGUNO cambia estado (no toca git, no
 * despliega, no llama a nada mutante). Cada chequeo va envuelto en try/catch propio: un
 * chequeo roto nunca tumba el resto, degrada a 'warn' con el mensaje del error.
 *
 * F2c (item futuro) decidirá si esto se engancha al pipeline de deploy; este servicio, por sí
 * solo, no lo hace.
 */
class ReleasePreflightService
{
    public function __construct(private ReleaseChangelogService $changelog)
    {
    }

    /**
     * @return array{version:string,checks:array<int,array{key:string,label:string,status:string,detail:string}>,veredicto:string}
     */
    public function evaluate(string $version, ?string $branch = null): array
    {
        $ref  = $branch ?: 'HEAD';
        $env  = $this->gitEnv();
        $base = base_path();

        // Cobertura del changelog: la comparten los checks 4 (rango de migraciones), 8 y 9
        // (mismo $prevTag) — se calcula UNA vez aquí, no en cada check.
        $cobertura      = null;
        $coberturaError = null;
        try {
            $cobertura = $this->changelog->coverage($version, $branch);
        } catch (Throwable $e) {
            $coberturaError = $e->getMessage();
        }
        $prevTag = $cobertura['desde_tag'] ?? null;

        $checks = [
            $this->safeCheck('arbol_limpio', 'Árbol de trabajo limpio', fn () => $this->checkArbolLimpio($env, $base)),
            $this->safeCheck('sin_secretos_en_diff', 'Sin secretos en el diff pendiente', fn () => $this->checkSinSecretosEnDiff($env, $base)),
            $this->safeCheck('head_alcanzable_origin', "{$ref} es descendiente de origin/main", fn () => $this->checkHeadAlcanzableOrigin($env, $base, $ref)),
            $this->safeCheck('migraciones_aditivas', 'Migraciones nuevas del rango son aditivas', fn () => $this->checkMigracionesAditivas($env, $base, $ref, $prevTag)),
            $this->safeCheck('app_debug_esperado', 'APP_DEBUG esperado para el entorno', fn () => $this->checkAppDebugEsperado()),
            $this->safeCheck('killswitches_dinero_false', 'Kill switches de dinero en false', fn () => $this->checkKillswitchesDineroFalse()),
            $this->safeCheck('token_github_valido', 'Token de GitHub válido', fn () => $this->checkTokenGithubValido()),
            $this->safeCheck('changelog_no_truncado', 'El changelog no se truncaría', fn () => $this->checkChangelogNoTruncado($cobertura, $coberturaError)),
            $this->safeCheck('version_previa_publicada', 'La versión previa quedó publicada', fn () => $this->checkVersionPreviaPublicada($prevTag, $coberturaError)),
        ];

        return [
            'version'   => $version,
            'checks'    => $checks,
            'veredicto' => $this->veredictoDe($checks),
        ];
    }

    private function veredictoDe(array $checks): string
    {
        $hayFail = false;
        $hayWarn = false;
        foreach ($checks as $check) {
            if ($check['status'] === 'fail') {
                $hayFail = true;
            } elseif ($check['status'] === 'warn') {
                $hayWarn = true;
            }
        }
        if ($hayFail) {
            return 'rojo';
        }
        return $hayWarn ? 'amarillo' : 'verde';
    }

    /**
     * Envuelve un chequeo en try/catch: si lanza cualquier excepción, degrada a 'warn' con el
     * mensaje del error en vez de tumbar el resto del preflight.
     */
    private function safeCheck(string $key, string $label, callable $fn): array
    {
        try {
            $result = $fn();
        } catch (Throwable $e) {
            Log::warning("ReleasePreflightService: el chequeo '{$key}' lanzó una excepción", [
                'error' => $e->getMessage(),
            ]);
            $result = ['status' => 'warn', 'detail' => 'Error inesperado al evaluar este chequeo: ' . $e->getMessage()];
        }

        return array_merge(['key' => $key, 'label' => $label], $result);
    }

    // ------------------------------------------------------------------
    // Los 9 chequeos
    // ------------------------------------------------------------------

    private function checkArbolLimpio(array $env, string $base): array
    {
        $porcelain = trim($this->runGit('git status --porcelain', $env, $base)->getOutput());
        if ($porcelain === '') {
            return ['status' => 'ok', 'detail' => 'Árbol de trabajo limpio.'];
        }

        $lines = array_slice(explode("\n", $porcelain), 0, 10);
        return ['status' => 'fail', 'detail' => "Hay cambios sin commitear:\n" . implode("\n", $lines)];
    }

    /**
     * Mismo denylist que DeploymentService::executeSecretCheck() (app/Services/Deploy/DeploymentService.php
     * ~413-425), copiado a propósito (item #9990681 pide no extraerlo a compartido en este item).
     */
    private function checkSinSecretosEnDiff(array $env, string $base): array
    {
        $porcelain = trim($this->runGit('git status --porcelain', $env, $base)->getOutput());
        if ($porcelain === '') {
            return ['status' => 'ok', 'detail' => 'Sin cambios pendientes que revisar.'];
        }

        $patterns = [
            '/\.env(\b|\.)/',
            '/\.pem$/i',
            '/\.key$/i',
            '/credential/i',
            '/secret.*\.(json|yaml|yml|txt)$/i',
        ];
        $templateWhitelist = '/\.(example|dist|sample)$/i';

        $flagged = [];
        foreach (explode("\n", $porcelain) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $file = ltrim(substr($line, 2));
            if (preg_match($templateWhitelist, $file)) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $file)) {
                    $flagged[] = trim($line);
                    break;
                }
            }
        }

        if ($flagged) {
            return ['status' => 'fail', 'detail' => "Archivos sensibles pendientes en el árbol de trabajo:\n" . implode("\n", $flagged)];
        }
        return ['status' => 'ok', 'detail' => 'Sin archivos sensibles en el árbol de trabajo.'];
    }

    private function checkHeadAlcanzableOrigin(array $env, string $base, string $ref): array
    {
        $process = $this->runGit('git merge-base --is-ancestor origin/main ' . escapeshellarg($ref), $env, $base);
        $exit    = $process->getExitCode();

        if ($exit === 0) {
            return ['status' => 'ok', 'detail' => "{$ref} es descendiente de origin/main (sin divergencia)."];
        }
        if ($exit === 1) {
            return ['status' => 'fail', 'detail' => "{$ref} NO es descendiente de origin/main (historia divergente o rebase)."];
        }
        return ['status' => 'warn', 'detail' => "No se pudo verificar el ancestro (exit {$exit}): " . trim($process->getErrorOutput())];
    }

    private function checkMigracionesAditivas(array $env, string $base, string $ref, ?string $prevTag): array
    {
        if ($prevTag === null) {
            return ['status' => 'warn', 'detail' => 'Sin tag previo, no se pudo acotar el rango de migraciones.'];
        }

        $range     = escapeshellarg("{$prevTag}..{$ref}");
        $listado   = trim($this->runGit("git diff --name-only --diff-filter=A {$range} -- database/migrations", $env, $base)->getOutput());
        if ($listado === '') {
            return ['status' => 'ok', 'detail' => 'Sin migraciones nuevas en el rango.'];
        }

        $archivos = array_values(array_filter(array_map('trim', explode("\n", $listado))));

        $destructivos = [
            '/Schema::drop\(/',
            '/->dropColumn\(/',
            '/->dropIfExists\(/',
            '/DB::statement\([\'"]DROP/i',
            '/->truncate\(/',
        ];

        $hallazgos = [];
        foreach ($archivos as $archivo) {
            $contenido = $this->runGit('git show ' . escapeshellarg("{$ref}:{$archivo}"), $env, $base)->getOutput();
            if ($contenido === '') {
                continue;
            }

            $upStart = strpos($contenido, 'function up()');
            if ($upStart === false) {
                continue;
            }
            $downStart = strpos($contenido, 'function down()', $upStart);
            $upBody    = $downStart !== false
                ? substr($contenido, $upStart, $downStart - $upStart)
                : substr($contenido, $upStart);

            foreach ($destructivos as $patron) {
                if (preg_match($patron, $upBody)) {
                    $hallazgos[] = "{$archivo} ({$patron})";
                    break;
                }
            }
        }

        if ($hallazgos) {
            return ['status' => 'warn', 'detail' => "Posibles operaciones destructivas en up() (heurístico, revisar):\n" . implode("\n", $hallazgos)];
        }
        return ['status' => 'ok', 'detail' => count($archivos) . ' migración(es) nueva(s) en el rango, ninguna con patrón destructivo detectado.'];
    }

    private function checkAppDebugEsperado(): array
    {
        if (!app()->environment('production')) {
            return ['status' => 'ok', 'detail' => 'Fuera de producción (env=' . app()->environment() . '); APP_DEBUG=true es lo esperado.'];
        }
        if (config('app.debug') === false) {
            return ['status' => 'ok', 'detail' => 'APP_DEBUG=false en producción.'];
        }
        return ['status' => 'fail', 'detail' => 'APP_DEBUG está activo en producción (fuga de stack traces/queries/env).'];
    }

    private function checkKillswitchesDineroFalse(): array
    {
        $switches = [
            'domiciliacion.cobro_live_enabled' => config('domiciliacion.cobro_live_enabled'),
            'pagos.recurrentes_cron_enabled'   => config('pagos.recurrentes_cron_enabled'),
            'payments.auto_apply_enabled'      => config('payments.auto_apply_enabled'),
        ];

        $activos = array_keys(array_filter($switches));
        if ($activos) {
            return ['status' => 'fail', 'detail' => 'Kill switches de dinero en true: ' . implode(', ', $activos)];
        }
        return ['status' => 'ok', 'detail' => 'Los 3 kill switches de dinero están en false.'];
    }

    private function checkTokenGithubValido(): array
    {
        $token = config('deployment.github.token', '');
        $repo  = config('deployment.github.repo', '');

        if (empty($token) || empty($repo)) {
            return ['status' => 'fail', 'detail' => 'deployment.github.token o deployment.github.repo están vacíos.'];
        }

        $response = Http::withToken($token)->timeout(10)->get("https://api.github.com/repos/{$repo}");
        if ($response->successful()) {
            return ['status' => 'ok', 'detail' => "Token válido para {$repo}."];
        }
        return ['status' => 'fail', 'detail' => "GitHub respondió {$response->status()}: " . $response->body()];
    }

    private function checkChangelogNoTruncado(?array $cobertura, ?string $coberturaError): array
    {
        if ($coberturaError !== null) {
            return ['status' => 'warn', 'detail' => "No se pudo calcular la cobertura del changelog: {$coberturaError}"];
        }

        if (!($cobertura['truncado'] ?? false)) {
            return ['status' => 'ok', 'detail' => 'El changelog cubriría todos los commits del rango.'];
        }

        $total     = $cobertura['total_commits'] ?? 0;
        $resumidos = $cobertura['resumidos_commits'] ?? 0;
        $tag       = $cobertura['desde_tag'] ?? '(sin tag previo)';
        return ['status' => 'fail', 'detail' => "Se resumirían {$resumidos} de {$total} commits desde {$tag}."];
    }

    private function checkVersionPreviaPublicada(?string $prevTag, ?string $coberturaError): array
    {
        if ($coberturaError !== null) {
            return ['status' => 'warn', 'detail' => "No se pudo determinar la versión previa (coverage() falló): {$coberturaError}"];
        }

        if ($prevTag === null) {
            return ['status' => 'ok', 'detail' => 'Sin versión previa que verificar.'];
        }

        Artisan::call('releases:reconciliar', ['version' => $prevTag, '--json' => true]);
        $json      = json_decode(Artisan::output(), true);
        $veredicto = $json['items'][0]['veredicto'] ?? null;

        if ($veredicto === 'PUBLICADA') {
            return ['status' => 'ok', 'detail' => "{$prevTag} está PUBLICADA."];
        }
        return ['status' => 'fail', 'detail' => "{$prevTag} no está PUBLICADA (veredicto: " . ($veredicto ?? 'desconocido') . ').'];
    }

    // ------------------------------------------------------------------

    private function runGit(string $cmd, array $env, string $base, int $timeout = 30): Process
    {
        $process = Process::fromShellCommandline($cmd, $base, $env, null, $timeout);
        $process->run();
        return $process;
    }

    /**
     * Mismo patrón ya duplicado 3 veces en el repo (ReconcileReleasesCommand::gitEnv(),
     * NextVersionResolver::env(), ReleaseChangelogService::buildEnv()) — copiado tal cual a
     * propósito (item #9990681 pide no extraerlo a compartido en este item).
     */
    private function gitEnv(): array
    {
        $home = getenv('HOME')
            ?: (function_exists('posix_getpwuid') && function_exists('posix_getuid')
                ? (posix_getpwuid(posix_getuid())['dir'] ?? '/root')
                : '/root');

        return [
            'PATH'               => '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'               => $home,
            'LC_ALL'             => 'C',
            'LANG'               => 'C',
            'GIT_CONFIG_COUNT'   => '1',
            'GIT_CONFIG_KEY_0'   => 'safe.directory',
            'GIT_CONFIG_VALUE_0' => base_path(),
        ];
    }
}
