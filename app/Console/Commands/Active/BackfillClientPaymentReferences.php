<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\Payments\Models\ClientPaymentReference;
use App\Modules\Addons\Payments\Services\PaymentReferenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Backfill de la clave de conciliación para clientes existentes.
 * Idempotente: solo genera para clientes que aún NO tienen referencia
 * (los nuevos la reciben por ClientObserver). No regenera ni toca las existentes.
 */
class BackfillClientPaymentReferences extends Command
{
    protected $signature = 'payments:backfill-references {--dry-run : Solo reporta, no escribe}';

    protected $description = 'Genera la referencia de conciliación (MEG-id-CC) para clientes que no la tengan';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $yaConRef = ClientPaymentReference::pluck('client_id')->all();
        $pendientes = DB::table('clients')
            ->whereNotIn('id', $yaConRef ?: [0])
            ->pluck('id');

        $total = $pendientes->count();
        if ($total === 0) {
            $this->info('✅ Todos los clientes ya tienen referencia. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Clientes sin referencia: {$total}");

        if ($dry) {
            $muestra = $pendientes->take(5)
                ->map(fn ($id) => "  cliente {$id} → " . PaymentReferenceService::build((int) $id))
                ->implode("\n");
            $this->line($muestra);
            $this->info('[DRY-RUN] No se escribió nada.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $hechos = 0;

        foreach ($pendientes as $clientId) {
            // ensureFor es idempotente: si por carrera ya existe, no duplica.
            PaymentReferenceService::ensureFor((int) $clientId);
            $hechos++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Referencias generadas: {$hechos}");

        return self::SUCCESS;
    }
}
