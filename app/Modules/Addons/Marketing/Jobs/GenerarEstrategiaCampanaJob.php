<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Campaign;
use App\Modules\Addons\Marketing\Services\Campanas360\GeneradorEstrategia;
use App\Modules\Addons\Marketing\Services\Campanas360\GeneradorPiezas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Campañas 360 (Fase 2, §2.7): genera la estrategia con la IA. Con alcance `todo`, al terminar bien crea las filas
 * `pending` de las piezas y despacha un GenerarPiezasCampanaJob por tipo. Cola `default`. Una sola vez (cuesta dinero).
 */
class GenerarEstrategiaCampanaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 170;

    public function __construct(
        public readonly int $campanaId,
        public readonly string $alcance = 'todo',
    ) {}

    public function handle(GeneradorEstrategia $generador, GeneradorPiezas $piezas): void
    {
        $campana = Campaign::query()->find($this->campanaId);

        if (! $campana || $campana->estrategia_estado !== 'generando') {
            return;
        }

        if (! $generador->generar($campana) || $this->alcance !== 'todo') {
            return;
        }

        foreach ($piezas->crearFilas($campana->fresh()) as $tipo) {
            GenerarPiezasCampanaJob::dispatch($campana->id, $tipo)->onQueue('default');
        }
    }

    public function failed(\Throwable $e): void
    {
        $campana = Campaign::query()->find($this->campanaId);

        if ($campana && $campana->estrategia_estado === 'generando') {
            app(GeneradorEstrategia::class)->marcarError($campana, 'No se pudo generar la estrategia (se acabó el tiempo). Intenta de nuevo.');
        }
    }
}
