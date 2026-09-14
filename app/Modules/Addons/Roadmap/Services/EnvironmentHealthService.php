<?php

namespace App\Modules\Addons\Roadmap\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Item #891 — "Consola fase 7: Salud del entorno". Item #9990968 (CIRC-09 Fase 1): se dio de
 * baja la pestaña completa (disco, migraciones, jobs fallidos, respaldo, errores 24h y sus
 * botones de gestión); este servicio sobrevive achicado a solo `certificado()` porque alimenta
 * el banner de certificado TLS de la cabecera de la Torre (item #891 §3, independiente de la
 * pestaña) — ver RoadmapController::saludEntorno().
 */
class EnvironmentHealthService
{
    private function seguro(string $nombre, callable $fn): array
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            Log::warning("salud-entorno: el indicador «{$nombre}» falló.", ['error' => $e->getMessage()]);

            return ['estado' => 'desconocido', 'error' => $e->getMessage()];
        }
    }

    public function resumen(): array
    {
        return [
            'certificado' => $this->seguro('certificado', fn () => $this->certificado()),
            'checkout_principal' => $this->seguro('checkout_principal', fn () => $this->checkoutPrincipal()),
            'generado_at' => now()->toIso8601String(),
        ];
    }

    /** Certificado TLS leído EN VIVO por conexión local (SNI), no del filesystem de letsencrypt
     *  (/etc/letsencrypt/archive es 700 root:root — www-data no puede leerlo). */
    private function certificado(): array
    {
        $host   = (string) config('torre_salud.cert_host');
        $puerto = (int) config('torre_salud.cert_puerto');
        $umbral = config('torre_salud.umbrales.certificado');

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'peer_name'         => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $client = @stream_socket_client(
            "ssl://127.0.0.1:{$puerto}",
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $client) {
            return ['estado' => 'desconocido', 'error' => "No se pudo conectar a {$host}:{$puerto} ({$errstr})"];
        }

        $params = stream_context_get_params($client);
        fclose($client);
        $cert = $params['options']['ssl']['peer_certificate'] ?? null;
        $parsed = $cert ? openssl_x509_parse($cert) : null;

        if (! $parsed || empty($parsed['validTo_time_t'])) {
            return ['estado' => 'desconocido', 'error' => 'No se pudo leer el certificado presentado.'];
        }

        $expiraEn = Carbon::createFromTimestamp($parsed['validTo_time_t']);
        $diasRestantes = (int) floor(($parsed['validTo_time_t'] - time()) / 86400);

        $estado = 'verde';
        if ($diasRestantes < $umbral['rojo_dias']) {
            $estado = 'rojo';
        } elseif ($diasRestantes < $umbral['amarillo_dias']) {
            $estado = 'amarillo';
        }

        return [
            'estado'          => $estado,
            'host'            => $host,
            'dias_restantes'  => $diasRestantes,
            'expira_at'       => $expiraEn->toIso8601String(),
        ];
    }

    /**
     * Item #9991088 (Fase 3 de #9991086) — expone en la Torre lo que antes se disfrazaba de bug
     * de frontend (#9991082): el checkout principal (`base_path()`, mismo path que
     * `MergeRunner::checkoutPrincipalPath()`, no reusable aquí porque es `protected` y esa clase
     * no se toca en este item) puede quedar con archivos en disco que no reflejan su propio HEAD
     * si `MergeRunner::syncCheckoutPrincipal()` no corrió o falló.
     *
     * OJO: como este checkout comparte `.git` (via `git worktree`) con todos los demás worktrees
     * del circuito, `refs/heads/main` es un ref COMPARTIDO — si el checkout está parado en la
     * rama `main`, `rev-parse HEAD` ahí SIEMPRE coincide con `rev-parse main` (es el mismo ref
     * dereferenciado), incluso cuando los ARCHIVOS en disco siguen viejos: mover el puntero
     * (`update-ref`, lo que hace el merge) no mueve por sí solo el árbol de trabajo. Por eso la
     * señal real de desync NO es comparar esos dos shas (siempre iguales en el caso común) sino
     * `git status --porcelain`: cuántos archivos difieren entre el índice/árbol de trabajo y el
     * HEAD que dicen tener — eso es justo lo que se queda "sucio" cuando el reset posterior al
     * merge no se aplicó.
     */
    private function checkoutPrincipal(): array
    {
        $principal = base_path();
        $umbral = config('torre_salud.umbrales.checkout_principal');

        $rama = trim($this->git($principal, ['rev-parse', '--abbrev-ref', 'HEAD']));
        $shaPrincipal = trim($this->git($principal, ['rev-parse', '--short', 'HEAD']));
        $shaMain = trim($this->git($principal, ['rev-parse', '--short', 'main']));

        $rangos = preg_split('/\s+/', trim($this->git($principal, ['rev-list', '--left-right', '--count', "{$shaPrincipal}...main"])));
        $commitsAdelante = (int) ($rangos[0] ?? 0); // commits que el checkout tiene y main no (ej. edición a mano)
        $commitsDetras   = (int) ($rangos[1] ?? 0); // commits que main tiene y el checkout no

        $bundlePath = is_file(public_path('js/app.js')) ? public_path('js/app.js') : public_path('mix-manifest.json');
        $bundleGeneradoAt = is_file($bundlePath) ? Carbon::createFromTimestamp(filemtime($bundlePath))->toIso8601String() : null;

        if ($rama !== 'main') {
            return [
                'estado'                   => 'rojo',
                'rama'                     => $rama,
                'sha_principal'            => $shaPrincipal,
                'sha_main'                 => $shaMain,
                'commits_detras'           => $commitsDetras,
                'commits_adelante'         => $commitsAdelante,
                'archivos_desincronizados' => null,
                'bundle_generado_at'       => $bundleGeneradoAt,
                'mensaje'                  => "El checkout principal está en la rama «{$rama}», no en main.",
            ];
        }

        $status = trim($this->git($principal, ['status', '--porcelain']));
        $archivosDesincronizados = $status === '' ? 0 : count(preg_split('/\R/', $status));

        $ultimoCommitMainTs = (int) trim($this->git($principal, ['log', '-1', '--format=%ct', 'main']));
        $ultimoCommitMainAt = $ultimoCommitMainTs > 0 ? Carbon::createFromTimestamp($ultimoCommitMainTs) : null;
        $minutosDesdeUltimoCommit = $ultimoCommitMainAt ? $ultimoCommitMainAt->diffInMinutes(now()) : null;

        $estado = 'verde';
        $mensaje = null;
        if ($archivosDesincronizados > 0) {
            if ($minutosDesdeUltimoCommit !== null && $minutosDesdeUltimoCommit < $umbral['rojo_minutos']) {
                $estado = 'amarillo';
                $mensaje = "{$archivosDesincronizados} archivo(s) del checkout principal aún no reflejan el último merge (hace {$minutosDesdeUltimoCommit} min) — puede estar en proceso.";
            } else {
                $estado = 'rojo';
                $mensaje = "{$archivosDesincronizados} archivo(s) del checkout principal llevan desincronizados de su propio HEAD desde hace más de {$umbral['rojo_minutos']} min.";
            }
        }

        return [
            'estado'                   => $estado,
            'rama'                     => $rama,
            'sha_principal'            => $shaPrincipal,
            'sha_main'                 => $shaMain,
            'commits_detras'           => $commitsDetras,
            'commits_adelante'         => $commitsAdelante,
            'archivos_desincronizados' => $archivosDesincronizados,
            'ultimo_commit_main_at'    => $ultimoCommitMainAt?->toIso8601String(),
            'bundle_generado_at'       => $bundleGeneradoAt,
            'mensaje'                  => $mensaje,
        ];
    }

    /** Ejecuta un git de solo lectura en `$cwd`; lanza si falla (capturado por `seguro()` en `resumen()`). */
    private function git(string $cwd, array $args): string
    {
        $process = new Process(array_merge(['git'], $args), $cwd);
        $process->setTimeout(10);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('git ' . implode(' ', $args) . ' falló: ' . trim($process->getErrorOutput()));
        }

        return $process->getOutput();
    }

}
