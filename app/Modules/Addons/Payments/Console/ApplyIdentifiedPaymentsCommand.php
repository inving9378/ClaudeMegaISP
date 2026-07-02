<?php

namespace App\Modules\Addons\Payments\Console;

use App\Modules\Addons\Payments\Jobs\ApplyIdentifiedPaymentJob;
use App\Modules\Addons\Payments\Models\WhatsappIdentificationSession as Session;
use Illuminate\Console\Command;

/**
 * FASE 4 (F4.4) — Barrido de RESPALDO de aplicación de pagos identificados.
 *
 * Procesa las sesiones resueltas aún no aplicadas (scopePendingApplication):
 * cada una pasa por la MISMA bifurcación (auto/espera-flag/Tere) del
 * ApplyIdentifiedPaymentJob. Respalda al disparo en tiempo real por si un job
 * no corrió, y sirve para "destapar" los candidatos frenados cuando Irving
 * encienda el freno maestro.
 *
 * Respeta el freno maestro (no aplica nada si auto_apply_enabled=false).
 * Uso: php artisan payments:apply-identified [--session=ID]
 */
class ApplyIdentifiedPaymentsCommand extends Command
{
    protected $signature = 'payments:apply-identified {--session= : Procesar solo esta sesión}';
    protected $description = 'Aplica (o rutea a Tere) los pagos de sesiones de identificación resueltas y pendientes.';

    public function handle(): int
    {
        $query = Session::pendingApplication();
        if ($id = $this->option('session')) {
            $query->where('id', (int) $id);
        }
        $sessions = $query->orderBy('id')->get();

        $this->info("Sesiones pendientes de aplicación: {$sessions->count()}");
        $this->line('Freno maestro auto_apply_enabled = ' . var_export(config('payments.auto_apply_enabled'), true));

        foreach ($sessions as $s) {
            // Reusa EXACTAMENTE la lógica de bifurcación del job (síncrono).
            ApplyIdentifiedPaymentJob::dispatchSync($s->id);
            $fresh = $s->fresh();
            $estado = $fresh->applied_payment_id
                ? "APLICADO (payment #{$fresh->applied_payment_id})"
                : 'no aplicado (frenado o encolado a Tere)';
            $this->line("  sesión #{$s->id} cliente {$s->resolved_client_id} [{$s->certainty}] → {$estado}");
        }

        return self::SUCCESS;
    }
}
