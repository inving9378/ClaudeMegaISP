<?php

namespace App\Services\Metricas;

use App\Models\Router;
use App\Services\Metricas\RouterOs\PearRouterOsApi;
use App\Support\Red\ClienteRouterOs;
use App\Support\Red\SoloLectura;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Métricas reales desde el MikroTik de cada dedicado (item #988), con el registro de
 * routers/NAS de siempre (routers + mikrotiks). En producción (EntornoMetricas) se conecta al router
 * registrado; fuera de ella, solo al de laboratorio, y sin laboratorio a ninguno. Excepción: con
 * RED_ROUTERS_REALES_EN_DEV y el candado de solo lectura puesto (config/red.php), al registrado.
 *
 * Usuario: el de solo lectura (login_api_ro) si está capturado; si no, el de la API de siempre
 * como respaldo, y la sesión lo dice (soloLectura() = false) para que el dry-run lo muestre.
 * Timeout corto: todo tiene que caber en el ciclo de 60 s.
 */
final class MikrotikMetricasDriver implements MetricasDriver
{
    public function nombre(): string
    {
        return 'mikrotik';
    }

    public function abrir(Router $router): SesionRouter
    {
        $mikrotik = $router->mikrotik ?? throw new RuntimeException('El router no tiene datos de API (mikrotiks).');
        [$login, $password, $soloLectura] = $this->credenciales($mikrotik);

        $destino = self::destino(
            EntornoMetricas::esProduccion() || SoloLectura::routersRealesEnDev(),
            ['ip' => $router->ip_host, 'login' => $login, 'password' => $password, 'port' => $mikrotik->port_api],
            (array) config('metricas.laboratorio', [])
        );
        if ($destino === null) {
            throw new SinConexionRouter('sin destino', 'fuera de producción no hay router de laboratorio configurado (MIKROTIK_DEV_*): no se conecta a ningún router.');
        }
        $redirigido0 = $destino['ip'] !== $router->ip_host;
        // API-SSL (#998): si el router tiene capturado su puerto API-SSL se usa cifrado (los
        // routers usan certificado autofirmado → sin verificar el par, solo se cifra el canal).
        $usaSsl = ! $redirigido0 && (int) $mikrotik->port_api_ssl > 0;
        if ($usaSsl) {
            $destino['port'] = (int) $mikrotik->port_api_ssl;
        }
        $puerto = (int) ($destino['port'] ?: 8728);
        $host = $destino['ip'] . ':' . $puerto;
        $redirigido = $destino['ip'] !== $router->ip_host || $destino['login'] !== $login
            || (! $usaSsl && $puerto !== (int) ($mikrotik->port_api ?: 8728));

        try {
            $client = new ClienteRouterOs(
                $destino['ip'],
                $destino['login'],
                (string) $destino['password'],
                $puerto,
                false,
                (int) config('metricas.mikrotik.timeout_segundos', 5),
                $usaSsl ? \PEAR2\Net\Transmitter\NetworkStream::CRYPTO_TLS : \PEAR2\Net\Transmitter\NetworkStream::CRYPTO_OFF,
                $usaSsl ? stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true, 'ciphers' => 'ADH:ALL@SECLEVEL=0']]) : null
            );
        } catch (Throwable $e) {
            throw new SinConexionRouter($host . ($redirigido ? ' (redirección MIKROTIK_DEV_*)' : ''), $e->getMessage(), $e);
        }

        return new MikrotikSesion(
            new PearRouterOsApi($client),
            $host,
            (string) $destino['login'],
            $soloLectura && ! $redirigido,
            $redirigido,
        );
    }

    /**
     * Con qué host y credenciales se abre la conexión: las del router en producción; las del
     * laboratorio fuera de ella; null si no hay laboratorio (nunca un router de respaldo fijo).
     *
     * @param  array{ip: mixed, login: string, password: string, port: mixed}  $registrado
     * @return array{ip: mixed, login: mixed, password: mixed, port: mixed}|null
     */
    public static function destino(bool $esProduccion, array $registrado, array $laboratorio): ?array
    {
        if ($esProduccion) {
            return $registrado;
        }

        return EntornoMetricas::laboratorioCompleto($laboratorio)
            ? ['ip' => $laboratorio['host'], 'login' => $laboratorio['usuario'], 'password' => $laboratorio['password'], 'port' => $laboratorio['puerto'] ?? null]
            : null;
    }

    /** @return array{0: string, 1: string, 2: bool} login, contraseña, ¿es el de solo lectura? */
    private function credenciales(object $mikrotik): array
    {
        if (trim((string) $mikrotik->login_api_ro) !== '' && (string) $mikrotik->password_api_ro !== '') {
            try {
                return [(string) $mikrotik->login_api_ro, Crypt::decryptString($mikrotik->password_api_ro), true];
            } catch (Throwable $e) {
                Log::warning("metricas: no se pudo descifrar la contraseña de solo lectura del router {$mikrotik->router_id}; se usa el usuario de respaldo.");
            }
        }

        return [(string) $mikrotik->login_api, (string) $mikrotik->password_api, false];
    }
}
