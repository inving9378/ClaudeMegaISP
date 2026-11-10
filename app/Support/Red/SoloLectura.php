<?php

namespace App\Support\Red;

use App\Services\Metricas\EntornoMetricas;
use App\Support\Entorno;
use GuzzleHttp\Promise\Create;
use Illuminate\Support\Facades\Log;
use Psr\Http\Message\RequestInterface;

/**
 * Candado de solo lectura de la red (config/red.php): decide si está puesto y qué comandos pasan.
 * Lo consultan ClienteRouterOs (MikroTik), el cliente HTTP de SmartOLT y HuaweiTransport::execWrite.
 */
final class SoloLectura
{
    /** Último segmento de un comando RouterOS que solo consulta. */
    private const ROUTEROS_LECTURA = ['print', 'getall', 'get', 'find', 'monitor', 'monitor-traffic', 'listen'];

    /** Comandos RouterOS completos que tampoco cambian nada. */
    private const ROUTEROS_EXACTOS = ['/ping', '/tool/ping', '/ip/route/check', '/cancel', '/quit'];

    public static function activo(): bool
    {
        $valor = config('red.solo_lectura');

        return self::decidir($valor, ($valor === null || $valor === '') && self::esProduccion());
    }

    /** Puesto salvo en producción, a menos que la configuración lo fuerce. */
    public static function decidir(mixed $valor, bool $esProduccion): bool
    {
        if ($valor === null || $valor === '') {
            return ! $esProduccion;
        }

        return filter_var($valor, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Producción por cualquiera de las señales que ya usa el sistema: APP_ENV, el actualizador
     * encendido o el nombre de la base. Basta una para que el candado automático no se ponga.
     */
    public static function esProduccion(): bool
    {
        return Entorno::esProduccion() || EntornoMetricas::esProduccion();
    }

    /** Dev se conecta a los routers reales de la base, solo con el candado puesto. */
    public static function routersRealesEnDev(): bool
    {
        return (bool) config('red.routers_reales_en_dev') && self::activo();
    }

    /** El comando ya viene normalizado por Request::setCommand (p. ej. «/ip/proxy/set»). */
    public static function permiteRouterOs(string $comando): bool
    {
        if (in_array($comando, self::ROUTEROS_EXACTOS, true)) {
            return true;
        }

        return in_array(substr($comando, strrpos($comando, '/') + 1), self::ROUTEROS_LECTURA, true);
    }

    /** Middleware de Guzzle: con el candado puesto, todo lo que no sea GET se rechaza sin salir. */
    public static function middlewareSoloConsultas(string $destino): callable
    {
        return static fn (callable $handler) => static function (RequestInterface $request, array $options) use ($handler, $destino) {
            if ($request->getMethod() !== 'GET' && self::activo()) {
                return Create::rejectionFor(self::bloqueo($destino, $request->getMethod() . ' ' . $request->getUri()->getPath()));
            }

            return $handler($request, $options);
        };
    }

    /** Deja rastro en el log y devuelve la excepción; quien llama la lanza o la rechaza. */
    public static function bloqueo(string $destino, string $operacion): RedSoloLecturaException
    {
        if (app()->bound('log')) {
            Log::warning('red-solo-lectura: bloqueado', ['destino' => $destino, 'operacion' => $operacion]);
        }

        return new RedSoloLecturaException("La red está en solo lectura: no se envió «{$operacion}» a {$destino}.");
    }
}
