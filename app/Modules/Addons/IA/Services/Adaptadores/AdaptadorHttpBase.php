<?php

namespace App\Modules\Addons\IA\Services\Adaptadores;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Services\IAAdaptadorInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Lo común a los adaptadores que hablan HTTP con un proveedor: llave, opciones
 * por llamada y reintentos. Cada proveedor solo traduce payload/respuesta.
 *
 * Reintentos: misma política que ClaudeApiClient (la que usaban los módulos
 * antes de pasar por aquí) — solo ante 429/5xx, backoff 1s/5s/15s y un techo
 * total para que los reintentos no se apilen sin límite. Un timeout de conexión
 * NO se reintenta (ya consumió su ventana).
 */
abstract class AdaptadorHttpBase implements IAAdaptadorInterface
{
    protected const TIMEOUT_DEFAULT = 120;
    protected const CONNECT_TIMEOUT = 10;
    protected const REINTENTOS_DEFAULT = 2;
    protected const BACKOFFS = [1, 5, 15];

    public function __construct(protected IAProveedor $proveedor)
    {
    }

    /** Nombre que aparece en los errores ("Claude API error: ..."). */
    abstract protected function nombreApi(): string;

    public function probarConexion(): bool
    {
        try {
            $this->enviarMensaje([], 'ping', [], null, ['max_tokens' => 16, 'reintentos' => 0]);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * POST con reintentos. Devuelve el JSON decodificado o lanza RuntimeException
     * con el cuerpo del error (mismo formato que antes: "<Nombre> API error: <body>").
     */
    protected function postJson(string $endpoint, array $headers, array $payload, array $opciones): array
    {
        $timeout    = (int) ($opciones['timeout'] ?? self::TIMEOUT_DEFAULT);
        $reintentos = (int) ($opciones['reintentos'] ?? self::REINTENTOS_DEFAULT);
        $deadline   = microtime(true) + $timeout + 60;
        $intento    = 0;

        while (true) {
            try {
                $response = Http::withHeaders($headers)
                    ->connectTimeout(min(self::CONNECT_TIMEOUT, $timeout))
                    ->timeout($timeout)
                    ->post($endpoint, $payload);
            } catch (ConnectionException $e) {
                throw new RuntimeException("{$this->nombreApi()} API no respondió a tiempo (timeout de {$timeout}s): {$e->getMessage()}", 0, $e);
            }

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            $status = $response->status();
            $espera = self::BACKOFFS[$intento] ?? end(self::BACKOFFS);
            $reintentable = $status === 429 || $status >= 500;

            if ($reintentable && $intento < $reintentos && (microtime(true) + $espera) < $deadline) {
                Log::warning("IA {$this->proveedor->nombre}: {$status}, reintento " . ($intento + 1) . "/{$reintentos}");
                sleep($espera);
                $intento++;
                continue;
            }

            throw new RuntimeException("{$this->nombreApi()} API error: " . $response->body());
        }
    }

    protected function clave(): ?string
    {
        return $this->proveedor->claveApi();
    }

    /** Opción por llamada → config_extra del proveedor → default. */
    protected function opcion(array $opciones, string $clave, string $claveConfig, mixed $default = null): mixed
    {
        if (array_key_exists($clave, $opciones) && $opciones[$clave] !== null) {
            return $opciones[$clave];
        }
        return data_get($this->proveedor->config_extra, $claveConfig, $default);
    }

    protected function esPdf(array $adjunto): bool
    {
        return ($adjunto['mime'] ?? '') === 'application/pdf';
    }

    protected function exigirSoportePdf(array $adjuntos): void
    {
        foreach ($adjuntos as $a) {
            if ($this->esPdf($a) && !$this->proveedor->soportaPdf()) {
                throw new RuntimeException("El proveedor \"{$this->proveedor->nombre}\" no soporta leer PDF.");
            }
        }
    }
}
