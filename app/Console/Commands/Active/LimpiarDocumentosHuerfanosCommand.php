<?php

namespace App\Console\Commands\Active;

use App\Models\DocumentClient;
use App\Modules\Core\CRM\Models\DocumentCrm;
use Illuminate\Console\Command;

/**
 * Item roadmap #81 — documentos (CRM y clientes) cuyo `file` morphOne es null
 * (registro de documento sin archivo asociado en `files`).
 *
 * Seguro por defecto: solo reporta. Borra únicamente con --apply explícito
 * (escritura en BD: antes de correrlo en producción, bitácora PREVIA + respaldo).
 */
class LimpiarDocumentosHuerfanosCommand extends Command
{
    protected $signature = 'documentos:limpiar-huerfanos
        {--apply : Borra los registros huérfanos (sin esto es solo reporte)}
        {--dry-run : Alias explícito del modo reporte (es el default)}';

    protected $description = 'Detecta (y con --apply borra) documentos CRM/cliente sin file asociado (item #81).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply') && ! $this->option('dry-run');
        $this->info($apply ? 'Modo APLICAR — se borran los huérfanos.' : 'Modo REPORTE (dry-run) — no se escribe nada.');

        $total = 0;
        foreach ([
            'CRM' => DocumentCrm::class,
            'Cliente' => DocumentClient::class,
        ] as $etiqueta => $modelo) {
            $ids = $modelo::query()->whereDoesntHave('file')->pluck('id');
            $total += $ids->count();
            $this->line(sprintf('%s: %d documento(s) sin archivo%s', $etiqueta, $ids->count(),
                $ids->isNotEmpty() ? ' (ids: ' . $ids->take(50)->implode(',') . ($ids->count() > 50 ? ',…' : '') . ')' : ''));

            if ($apply && $ids->isNotEmpty()) {
                $modelo::whereIn('id', $ids)->delete();
            }
        }

        $this->info($apply ? "Borrados: {$total}." : "Huérfanos detectados: {$total}.");

        return self::SUCCESS;
    }
}
