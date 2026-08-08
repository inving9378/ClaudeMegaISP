<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RoadmapIntakeService;
use Illuminate\Console\Command;

/**
 * TORRE V2 — la terminal crea un SUB-ITEM de seguimiento.
 *
 * Para el caso que el encargo llama "tarea que requiere fases": la terminal hace la fase que le
 * toca y deja las siguientes registradas como items propios en vez de (a) intentar hacerlo todo y
 * pasarse del turno, o (b) escalar a Irving solo para avisar que falta más trabajo.
 *
 * El sub-item hereda el módulo del padre y NACE `pendiente_revision`: la terminal no puede
 * auto-aprobarse trabajo futuro; lo tría el revisor/autopilot como a cualquier item. Tampoco
 * declara nivel de riesgo — clasificar es del revisor, no de quien pide.
 */
class SubItemCommand extends Command
{
    protected $signature = 'circuito:sub-item
        {padre : ID del item del que cuelga}
        {--sid= : tu slot de terminal (wt-K)}
        {--titulo= : título del sub-item}
        {--spec= : qué hay que hacer, con el detalle que ya conoces}';

    protected $description = 'Crea un sub-item de seguimiento colgando de un item (nace pendiente_revision).';

    public function handle(RoadmapIntakeService $intake): int
    {
        $padre = RoadmapItem::find($this->argument('padre'));
        if (! $padre) {
            $this->error('Item padre no encontrado.');

            return self::FAILURE;
        }

        $titulo = trim((string) $this->option('titulo'));
        if ($titulo === '') {
            $this->error('Falta --titulo.');

            return self::FAILURE;
        }

        $autor = trim((string) $this->option('sid')) ?: (string) ($padre->worker_sid ?: 'terminal');

        try {
            $sub = $intake->crear([
                'title'          => $titulo,
                'description'    => $this->option('spec') ?: null,
                'origen_item_id' => $padre->id,
            ], $autor, true);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sub-item #{$sub->id} creado bajo #{$padre->id} (módulo «{$sub->modulo}», pendiente_revision).");

        return self::SUCCESS;
    }
}
