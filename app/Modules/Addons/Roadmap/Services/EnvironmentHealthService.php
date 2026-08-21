<?php

namespace App\Modules\Addons\Roadmap\Services;

use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Item #891 — "Consola fase 7: Salud del entorno". Los seis indicadores que hoy solo se ven
 * entrando por SSH: certificado TLS, disco, migraciones pendientes, jobs fallidos, último
 * respaldo y errores 24h agrupados por firma. SOLO LECTURA salvo `reintentarTrabajosFallidos()`
 * y `recalentarCaches()`, los dos únicos botones que declara el item.
 *
 * Cada indicador está aislado: si uno truena, los otros cinco igual se muestran (mismo patrón
 * `bloque()` que ya usa RoadmapController::torre() para que un solo bloque roto no apague el
 * panel entero).
 */
class EnvironmentHealthService
{
    private Migrator $migrator;

    /** Migrator NO se puede autowirear por el nombre de la clase: Laravel solo lo registra bajo
     *  el alias 'migrator' (MigrationServiceProvider), no como binding de Illuminate\Database\
     *  Migrations\Migrator. Se resuelve explícito para evitar el BindingResolutionException. */
    public function __construct()
    {
        $this->migrator = app('migrator');
    }

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
            'certificado'      => $this->seguro('certificado', fn () => $this->certificado()),
            'disco'            => $this->seguro('disco', fn () => $this->disco()),
            'migraciones'      => $this->seguro('migraciones', fn () => $this->migraciones()),
            'jobs_fallidos'    => $this->seguro('jobs_fallidos', fn () => $this->jobsFallidos()),
            'ultimo_respaldo'  => $this->seguro('ultimo_respaldo', fn () => $this->ultimoRespaldo()),
            'errores_24h'      => $this->seguro('errores_24h', fn () => $this->errores24h()),
            'caches'           => $this->seguro('caches', fn () => $this->estadoCaches()),
            'reactivacion_agendados' => $this->seguro('reactivacion_agendados', fn () => $this->reactivacionAgendados()),
            'generado_at'      => now()->toIso8601String(),
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

    private function disco(): array
    {
        $umbral = config('torre_salud.umbrales.disco');
        $ruta = base_path();

        $total = disk_total_space($ruta);
        $libre = disk_free_space($ruta);

        if ($total === false || $libre === false || $total <= 0) {
            return ['estado' => 'desconocido', 'error' => 'No se pudo leer el disco.'];
        }

        $usado = $total - $libre;
        $pct = round(($usado / $total) * 100, 1);

        $estado = 'verde';
        if ($pct > $umbral['rojo_pct']) {
            $estado = 'rojo';
        } elseif ($pct > $umbral['amarillo_pct']) {
            $estado = 'amarillo';
        }

        return [
            'estado'       => $estado,
            'porcentaje'   => $pct,
            'libre_gb'     => round($libre / 1024 / 1024 / 1024, 1),
            'total_gb'     => round($total / 1024 / 1024 / 1024, 1),
        ];
    }

    /** Mismo cálculo que MigrationGuardService::pendingMigrationFiles() — solo lectura, sin correr nada. */
    private function migraciones(): array
    {
        $paths = array_unique(array_merge([database_path('migrations')], $this->migrator->paths()));
        $files = $this->migrator->getMigrationFiles($paths);
        $ran = $this->migrator->getRepository()->getRan();
        $pendientes = array_diff_key($files, array_flip($ran));

        return [
            'estado'    => count($pendientes) > 0 ? 'amarillo' : 'verde',
            'cantidad'  => count($pendientes),
            'nombres'   => array_values(array_slice(array_keys($pendientes), 0, 10)),
        ];
    }

    private function jobsFallidos(): array
    {
        $umbral = config('torre_salud.umbrales.jobs_fallidos');

        if (! Schema::hasTable('failed_jobs')) {
            return ['estado' => 'verde', 'cantidad' => 0, 'mas_viejo_at' => null];
        }

        $cantidad = DB::table('failed_jobs')->count();
        $masViejo = DB::table('failed_jobs')->min('failed_at');
        $masViejoAt = $masViejo ? Carbon::parse($masViejo) : null;
        $horasMasViejo = $masViejoAt ? $masViejoAt->diffInHours(now()) : 0;

        $estado = 'verde';
        if ($cantidad > 0) {
            $estado = 'amarillo';
        }
        if ($cantidad > $umbral['rojo_cantidad'] || $horasMasViejo > $umbral['rojo_horas']) {
            $estado = 'rojo';
        }

        return [
            'estado'        => $estado,
            'cantidad'      => $cantidad,
            'mas_viejo_at'  => $masViejoAt?->toIso8601String(),
            'mas_viejo_hace_horas' => $masViejoAt ? $horasMasViejo : null,
        ];
    }

    private function ultimoRespaldo(): array
    {
        $umbral = config('torre_salud.umbrales.respaldo');
        $dir = (string) config('torre_salud.backup_dir');

        $archivos = @glob(rtrim($dir, '/') . '/megaisp-*.sql.gz') ?: [];
        if (! $archivos) {
            return ['estado' => 'rojo', 'ultimo_at' => null, 'error' => 'Sin respaldos encontrados en ' . $dir];
        }

        $masReciente = null;
        foreach ($archivos as $archivo) {
            $mtime = @filemtime($archivo);
            if ($mtime !== false && ($masReciente === null || $mtime > $masReciente)) {
                $masReciente = $mtime;
            }
        }

        if ($masReciente === null) {
            return ['estado' => 'desconocido', 'ultimo_at' => null, 'error' => 'No se pudo leer la fecha de los respaldos.'];
        }

        $fecha = Carbon::createFromTimestamp($masReciente);
        $horas = $fecha->diffInHours(now());

        $estado = 'verde';
        if ($horas > $umbral['rojo_horas']) {
            $estado = 'rojo';
        } elseif ($horas > $umbral['amarillo_horas']) {
            $estado = 'amarillo';
        }

        return [
            'estado'      => $estado,
            'ultimo_at'   => $fecha->toIso8601String(),
            'hace_horas'  => $horas,
        ];
    }

    /**
     * Errores de las últimas 24h, agrupados por firma (mensaje con dígitos normalizados) —
     * "1,129 repeticiones de 1 error" en vez de 1,129 líneas sueltas. Lee solo la COLA del
     * archivo (últimos ~3 MB) para no cargar un log grande completo en cada consulta.
     */
    private function errores24h(): array
    {
        $umbral = config('torre_salud.umbrales.errores_24h');
        $ruta = storage_path('logs/laravel.log');

        if (! is_file($ruta)) {
            return ['estado' => 'verde', 'total' => 0, 'grupos' => []];
        }

        $desde = now()->subDay();
        $maxBytes = 3_000_000;
        $tamano = filesize($ruta);

        $handle = fopen($ruta, 'r');
        if (! $handle) {
            return ['estado' => 'desconocido', 'error' => 'No se pudo abrir el log.'];
        }

        if ($tamano > $maxBytes) {
            fseek($handle, $tamano - $maxBytes);
            fgets($handle); // descarta la primera línea, queda partida por el seek
        }

        $grupos = [];
        $total = 0;

        while (($linea = fgets($handle)) !== false) {
            if (! preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \S+\.(ERROR|CRITICAL|ALERT|EMERGENCY): (.*)/', $linea, $m)) {
                continue;
            }

            try {
                $ts = Carbon::createFromFormat('Y-m-d H:i:s', $m[1]);
            } catch (Throwable) {
                continue;
            }

            if ($ts->lt($desde)) {
                continue;
            }

            $mensaje = trim(explode('{"exception"', $m[3])[0]);
            $firma = md5(preg_replace('/\d+/', '#', $mensaje) ?? $mensaje);

            $total++;
            if (! isset($grupos[$firma])) {
                $grupos[$firma] = [
                    'mensaje'  => Str::limit($mensaje, 180),
                    'cantidad' => 0,
                    'primera_at' => $ts->toIso8601String(),
                    'ultima_at'  => $ts->toIso8601String(),
                ];
            }
            $grupos[$firma]['cantidad']++;
            $grupos[$firma]['ultima_at'] = $ts->toIso8601String();
        }
        fclose($handle);

        usort($grupos, fn ($a, $b) => $b['cantidad'] <=> $a['cantidad']);
        $grupos = array_slice($grupos, 0, 20);

        $estado = 'verde';
        if ($total > $umbral['rojo']) {
            $estado = 'rojo';
        } elseif ($total > $umbral['amarillo']) {
            $estado = 'amarillo';
        }

        return ['estado' => $estado, 'total' => $total, 'grupos' => array_values($grupos)];
    }

    /**
     * #957 — ¿corrió `circuito:reactivar-agendados` (Fase 2 de #921) en las últimas N horas? Reusa
     * el pulso genérico de #808 (`RoadmapCircuitoService::latidos()`, ya sellado por el listener de
     * `CommandFinished` — nada que instrumentar aparte) en vez de duplicar el mecanismo de heartbeat.
     */
    private function reactivacionAgendados(): array
    {
        $proceso = collect(app(\App\Modules\Addons\Roadmap\Services\RoadmapCircuitoService::class)->latidos())
            ->firstWhere('comando', 'circuito:reactivar-agendados');

        if (! $proceso) {
            return ['estado' => 'desconocido', 'error' => 'Proceso no registrado en config(circuito.procesos_programados).'];
        }

        return [
            'estado'        => $proceso['vencido'] ? 'rojo' : 'verde',
            'ultimo_at'     => $proceso['at'],
            'hace_horas'    => $proceso['horas'],
            'nunca'         => $proceso['nunca'],
            'ultimo_fallo'  => $proceso['ultimo_fallo'],
        ];
    }

    /** #790/#794 — si config:auditar-env no está limpio, config:cache no es seguro. Solo lectura. */
    public function estadoCaches(): array
    {
        $exit = Artisan::call('config:auditar-env', ['--lista' => true]);

        return [
            'config_cache_seguro' => $exit === 0,
            'motivo' => $exit === 0
                ? null
                : 'config:auditar-env encontró llamadas a env() en runtime fuera de config/ (#790/#794) — config:cache dejaría de leer el .env hasta el próximo clear.',
        ];
    }

    /** Botón "Reintentar trabajos fallidos". */
    public function reintentarTrabajosFallidos(): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return ['ok' => true, 'reencolados' => 0];
        }

        $antes = DB::table('failed_jobs')->count();
        Artisan::call('queue:retry', ['id' => ['all']]);
        $despues = DB::table('failed_jobs')->count();

        return ['ok' => true, 'reencolados' => max(0, $antes - $despues), 'quedan' => $despues];
    }

    /** Botón "Limpiar y recalentar cachés". NUNCA corre config:cache si config:auditar-env no pasa limpio. */
    public function recalentarCaches(bool $incluirConfig): array
    {
        $pasos = [];
        foreach (['view:clear', 'config:clear', 'route:clear'] as $comando) {
            $pasos[$comando] = Artisan::call($comando) === 0;
        }
        $pasos['view:cache'] = Artisan::call('view:cache') === 0;

        $configCacheEjecutado = false;
        $motivo = null;

        if ($incluirConfig) {
            $estado = $this->estadoCaches();
            if ($estado['config_cache_seguro']) {
                $configCacheEjecutado = Artisan::call('config:cache') === 0;
            } else {
                $motivo = $estado['motivo'];
            }
        }

        return [
            'ok'                     => true,
            'pasos'                  => $pasos,
            'config_cache_ejecutado' => $configCacheEjecutado,
            'config_cache_motivo'    => $motivo,
        ];
    }
}
