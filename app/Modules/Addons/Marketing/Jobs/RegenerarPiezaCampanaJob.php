<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\GeneratedContent;
use App\Modules\Addons\Marketing\Services\Campanas360\GeneradorPiezas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Campañas 360 (Fase 2, §2.7): regenera UNA pieza (una variante) con la IA; las otras variantes van como contexto
 * para no repetirlas. Solo cambia esa fila. Cola `default`. Una sola vez (cuesta dinero).
 */
class RegenerarPiezaCampanaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 170;

    public function __construct(
        public readonly int $piezaId,
        public readonly string $indicaciones = '',
    ) {}

    public function handle(GeneradorPiezas $generador): void
    {
        $pieza = GeneratedContent::query()->find($this->piezaId);

        if ($pieza && $pieza->status === 'generating') {
            $generador->regenerar($pieza, $this->indicaciones);
        }
    }

    public function failed(\Throwable $e): void
    {
        GeneratedContent::query()
            ->where('id', $this->piezaId)
            ->where('status', 'generating')
            ->update([
                'status'         => 'failed',
                'failure_reason' => 'No se pudo regenerar esta pieza (se acabó el tiempo). Intenta de nuevo.',
                'updated_at'     => now(),
            ]);
    }
}
