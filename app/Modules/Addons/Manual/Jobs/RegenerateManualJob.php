<?php

namespace App\Modules\Addons\Manual\Jobs;

use App\Modules\Addons\Manual\Services\ManualGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Regeneración completa (o de una sección puntual) del manual, encolada.
 * Reemplaza las corridas síncronas de ManualGeneratorService::generate()
 * dentro de un request HTTP o de la corrida de `migrate` (item #165).
 */
class RegenerateManualJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [30, 120, 600];

    public function __construct(public ?string $section = null) {}

    public function handle(ManualGeneratorService $service): void
    {
        try {
            $result = $service->generate($this->section);
            Log::info('[Manual] Regeneración encolada completada (job)', [
                'section'   => $this->section,
                'generated' => $result['generated'] ?? 0,
                'errors'    => $result['errors'] ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::error('[Manual] Falló regeneración encolada (job): ' . $e->getMessage(), [
                'section' => $this->section,
            ]);
        }
    }
}
