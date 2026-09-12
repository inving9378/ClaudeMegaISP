<?php

namespace App\Modules\Addons\Roadmap\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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

}
