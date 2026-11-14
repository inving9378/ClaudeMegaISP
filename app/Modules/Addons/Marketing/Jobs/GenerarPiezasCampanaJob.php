<?php

namespace App\Modules\Addons\Marketing\Jobs;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use App\Modules\Addons\Marketing\Services\Campanas360\CatalogosCampana360;
use App\Modules\Addons\Marketing\Services\Campanas360\GeneradorPiezas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Campañas 360 (Fase 2, §2.7): genera TODAS las variantes de UN tipo de pieza con una llamada a la IA. Cola `default`.
 *
 * Excepción (ajuste 13-nov, campaña real de DEV): los GUIONES van UNA variante por trabajo. Dos guiones de hasta 8
 * escenas en una sola respuesta no caben en MAX_TOKENS = 3500 (la respuesta se cortaba y las dos variantes fallaban), y
 * subir el tope no es opción: más tokens de salida = más tiempo, y llamada + reintento deben caber en los 170 s del
 * trabajo (75 s por llamada). Con una variante por trabajo cada guion tiene su propio tope, su reintento y su tiempo.
 * El trabajo «del tipo» (sin variante) solo reparte los de cada variante; quien lo despacha no cambia.
 * Una sola vez (cuesta dinero); si truena o se acaba el tiempo, las filas quedan `failed` con mensaje en español.
 */
class GenerarPiezasCampanaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 170;

    public function __construct(
        public readonly int $campanaId,
        public readonly string $pieza,
        public readonly ?string $variante = null,
    ) {}

    public function handle(GeneradorPiezas $generador): void
    {
        $campana = Campaign::query()->find($this->campanaId);

        if (! $campana) {
            return;
        }

        if ($this->variante === null && in_array($this->pieza, CatalogosCampana360::GUIONES, true)) {
            // Con una sola variante (p. ej. el Video 16:9) no hace falta repartir: se genera aquí mismo.
            $variantes = GeneratedContent::query()
                ->where('source_campaign_id', $campana->id)
                ->where('pieza', $this->pieza)
                ->where('status', 'pending')
                ->orderBy('variante')
                ->pluck('variante');

            if ($variantes->count() > 1) {
                foreach ($variantes as $variante) {
                    self::dispatch($campana->id, $this->pieza, $variante)->onQueue('default');
                }

                return;
            }
        }

        $generador->generarTipo($campana, $this->pieza, $this->variante === null ? null : [$this->variante]);
    }

    public function failed(\Throwable $e): void
    {
        GeneratedContent::query()
            ->where('source_campaign_id', $this->campanaId)
            ->where('pieza', $this->pieza)
            ->when($this->variante !== null, fn ($q) => $q->where('variante', $this->variante))
            ->whereIn('status', ['pending', 'generating'])
            ->update([
                'status'         => 'failed',
                'failure_reason' => 'No se pudo generar esta pieza (se acabó el tiempo). Intenta regenerarla.',
                'updated_at'     => now(),
            ]);
    }
}
