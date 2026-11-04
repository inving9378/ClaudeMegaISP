<?php

namespace App\Services\Metricas;

use App\Http\Traits\RouterConnection;
use App\Models\Router;
use App\Services\Metricas\RouterOs\PearRouterOsApi;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use PEAR2\Net\RouterOS\Client;
use RuntimeException;
use Throwable;

/**
 * Métricas reales desde el MikroTik de cada dedicado (item #988), con el registro de
 * routers/NAS de siempre (routers + mikrotiks) y el mismo destino que RouterConnection.
 *
 * Usuario: el de solo lectura (login_api_ro) si está capturado; si no, el de la API de siempre
 * como respaldo, y la sesión lo dice (soloLectura() = false) para que el dry-run lo muestre.
 * Timeout corto: todo tiene que caber en el ciclo de 60 s.
 */
final class MikrotikMetricasDriver implements MetricasDriver
{
    use RouterConnection;

    public function nombre(): string
    {
        return 'mikrotik';
    }

    public function abrir(Router $router): SesionRouter
    {
        $mikrotik = $router->mikrotik ?? throw new RuntimeException('El router no tiene datos de API (mikrotiks).');
        [$login, $password, $soloLectura] = $this->credenciales($mikrotik);

        $destino = $this->destinoConexion($router->ip_host, $login, $password, $mikrotik->port_api);
        $puerto = (int) ($destino['port'] ?: 8728);
        $host = $destino['ip'] . ':' . $puerto;
        $redirigido = $destino['ip'] !== $router->ip_host || $destino['login'] !== $login
            || $puerto !== (int) ($mikrotik->port_api ?: 8728);

        try {
            $client = new Client(
                $destino['ip'],
                $destino['login'],
                (string) $destino['password'],
                $puerto,
                false,
                (int) config('metricas.mikrotik.timeout_segundos', 5)
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
