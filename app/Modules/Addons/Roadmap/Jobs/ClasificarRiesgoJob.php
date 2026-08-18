<?php

namespace App\Modules\Addons\Roadmap\Jobs;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * FASE 2A.3 — CLASIFICAR AL INSERTAR, en vez de barrer cada 3 minutos.
 *
 * `circuito:priorizar-seguridad` corría en cron cada 3 minutos gastando Opus sobre un backlog que
 * casi siempre ya estaba clasificado (marca `⟪SEG-TRIAGE⟫`). Con el clasificador convertido en
 * ADVISORY —informa, no frena— ese gasto dejó de justificarse: la señal es útil, pero no urgente.
 *
 * El disparo pasa a ser por EVENTO (item nuevo → este job) más un barrido diario de respaldo, que
 * recoge lo que el job no haya alcanzado (worker caído, item creado por escritura cruda, etc.).
 *
 * FALLA-SEGURA por diseño: el clasificador ya no frena nada, así que si esto falla el circuito
 * sigue exactamente igual. Nunca debe tumbar la creación de un item ni el trabajo de una terminal
 * — por eso se encola `afterCommit` y se traga cualquier excepción con log.
 */
class ClasificarRiesgoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Una llamada a Opus; si falla dos veces, no se insiste: el barrido diario lo recogerá. */
    public int $tries = 2;

    public int $timeout = 240;

    public function __construct(public int $itemId)
    {
    }

    public function handle(): void
    {
        $item = RoadmapItem::find($this->itemId);
        if (! $item) {
            return;   // se borró o se revirtió la transacción que lo creó
        }

        try {
            // Se delega en el comando a propósito: ahí viven el pre-filtro de señales, la
            // clasificación con Opus, la marca de idempotencia y el guard anti-rebote. Duplicar
            // ese criterio aquí sería crear un segundo clasificador que se desincroniza.
            Artisan::call('circuito:priorizar-seguridad', [
                '--item'  => $this->itemId,
                '--limit' => 1,
            ]);
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('clasificar-riesgo-fallo', [
                'item'  => $this->itemId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
