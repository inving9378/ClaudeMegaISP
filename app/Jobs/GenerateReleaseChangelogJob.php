<?php

namespace App\Jobs;

use App\Services\ReleaseChangelogService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Item roadmap #9990626 (Fase 2 de #9990624) — antes ReleaseController::generateChangelog()
 * llamaba a ReleaseChangelogService::generate() de forma síncrona dentro del request HTTP
 * (2-4 min con rangos grandes de commits), lo que podía colgar el navegador o topar con el
 * timeout del proxy/servidor. Ahora corre en background y el resultado se deja en cache,
 * bajo la misma clave que ReleaseController::changelogStatus() consulta por polling.
 *
 * Aún no hay un `Release` persistido en este punto (el changelog se genera ANTES de
 * guardar la versión) — por eso el resultado se guarda en cache keyed por un request_id
 * generado en el POST, no en una columna de `releases`.
 */
class GenerateReleaseChangelogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    /** Debe ser mayor al peor caso de tries+backoff (30+120+600=750s) + margen de polling. */
    public const CACHE_TTL_MINUTES = 20;

    public function __construct(
        private readonly string $requestId,
        private readonly string $version,
        private readonly ?string $branch = null,
    ) {
    }

    public static function cacheKey(string $requestId): string
    {
        return "release_changelog:{$requestId}";
    }

    public function handle(ReleaseChangelogService $service): void
    {
        // Sin try/catch amplio a propósito: si falla, debe propagar para que el queue
        // worker aplique los reintentos con backoff (contrario al patrón de
        // RegenerateManualJob, que no necesita reintentos).
        $result = $service->generate($this->version, $this->branch);

        Cache::put(self::cacheKey($this->requestId), [
            'status' => 'listo',
            'result' => $result,
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    public function failed(\Throwable $e): void
    {
        Log::error("GenerateReleaseChangelogJob falló tras agotar reintentos: {$e->getMessage()}", [
            'request_id' => $this->requestId,
            'version'    => $this->version,
        ]);

        Cache::put(self::cacheKey($this->requestId), [
            'status'  => 'error',
            'message' => 'No se pudo generar el resumen: ' . $e->getMessage(),
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));
    }
}
