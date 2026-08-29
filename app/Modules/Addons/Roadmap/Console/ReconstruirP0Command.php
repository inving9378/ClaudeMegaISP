<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Services\ReconstruccionP0Service;
use Illuminate\Console\Command;

/**
 * Item #744 (Fase 3 de #624) — mecanismo de reconstrucción de items P0 no-mergeados.
 *
 * ⚠️ Este comando SOLO construye/expone el mecanismo. NO decide qué ids reconstruir — eso lo
 * marca Irving en el reporte de la Fase 2 (sub-item hermano de #742, aún por crear). Hasta que
 * esa fase exista y entregue la lista de ids a reconstruir, este comando no se invoca en
 * producción; se probó con un caso sintético en tinker.
 */
class ReconstruirP0Command extends Command
{
    protected $signature = 'circuito:reconstruir-p0
        {id_original : id del item perdido en el incidente P0 (puede no existir en BD)}
        {--sid=terminal : quién ejecuta la reconstrucción}
        {--titulo= : título a copiar del original}
        {--descripcion= : descripción/spec a copiar del original}
        {--modulo= : módulo del item reconstruido, si se conoce}
        {--nota= : contexto adicional para el historial (ej. evidencia de log)}';

    protected $description = 'Crea un item nuevo que reconstruye uno perdido en el incidente P0 (reabre_item_id).';

    public function handle(ReconstruccionP0Service $servicio): int
    {
        $idOriginal = (int) $this->argument('id_original');

        $titulo = trim((string) $this->option('titulo'));
        if ($titulo === '') {
            $this->error('Falta --titulo.');

            return self::FAILURE;
        }

        $item = $servicio->reconstruir(
            $idOriginal,
            $titulo,
            $this->option('descripcion') ?: null,
            (string) $this->option('sid'),
            $this->option('modulo') ?: null,
            $this->option('nota') ?: null
        );

        $this->info("Item #{$item->id} creado (reabre_item_id={$idOriginal}, pendiente_revision).");

        return self::SUCCESS;
    }
}
