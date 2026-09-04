<?php

namespace App\Modules\Addons\DocumentacionCorporativa\Console;

use App\Modules\Addons\DocumentacionCorporativa\Models\DcEmpresa;
use App\Modules\Addons\DocumentacionCorporativa\Models\DcPendiente;
use Illuminate\Console\Command;

/**
 * Recordatorio de pendientes — mecanismo MÁS SIMPLE que cumple (nivel A).
 *
 * Marca `recordatorio_enviado_at` en los pendientes abiertos, con fecha
 * compromiso vencida o por vencer dentro de `DcPendiente::DIAS_AVISO` días, que
 * todavía no lo tienen marcado. NO envía nada por ningún canal — el envío real
 * (email/WhatsApp) es un paso aparte, registrado como sub-item porque el canal
 * ya existe (`WhatsAppGateway`) y conectarlo merece su propia vuelta.
 *
 * Idempotente por diseño: sólo toca los que aún no tienen marca, así que
 * correrlo varias veces no reprocesa lo mismo. La bandeja ya muestra
 * `recordatorio_enviado_at` para que se vea qué se marcó.
 */
class PendientesRecordatorioCommand extends Command
{
    protected $signature = 'dc:pendientes-recordatorio';

    protected $description = 'Marca recordatorio_enviado_at en pendientes vencidos o por vencer (sin enviar nada aún)';

    public function handle(): int
    {
        $total = 0;

        foreach (DcEmpresa::activas()->get() as $empresa) {
            $pendientes = DcPendiente::deEmpresa($empresa->id)
                ->porVencerOVencidos()
                ->whereNull('recordatorio_enviado_at')
                ->get();

            foreach ($pendientes as $pendiente) {
                $pendiente->update(['recordatorio_enviado_at' => now()]);
                $total++;
            }

            if ($pendientes->isNotEmpty()) {
                $this->line("{$empresa->etiqueta}: {$pendientes->count()} pendiente(s) marcado(s).");
            }
        }

        $this->info("{$total} pendiente(s) marcado(s) en total.");

        return self::SUCCESS;
    }
}
