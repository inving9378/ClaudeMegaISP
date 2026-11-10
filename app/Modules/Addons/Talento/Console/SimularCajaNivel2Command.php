<?php

namespace App\Modules\Addons\Talento\Console;

use App\Modules\Addons\Talento\Services\CajaNivel2SimulacionService;
use App\Modules\Addons\Talento\Support\PayWeek;
use Illuminate\Console\Command;

class SimularCajaNivel2Command extends Command
{
    protected $signature   = 'talento:simular-caja-nivel2 {--forzar : correr aunque el interruptor esté apagado}';
    protected $description = 'MR-34 regla 2: simula (sin pagar) el bono por caja de segundo nivel de la última semana de pago y muestra qué habría pagado.';

    public function handle(CajaNivel2SimulacionService $svc): int
    {
        if (!$svc->habilitada() && !$this->option('forzar')) {
            $this->warn('Regla caja_nivel2 apagada (TALENTO_CAJA_NIVEL2_ENABLED). Use --forzar para simular.');
            return self::SUCCESS;
        }
        $week = PayWeek::lastCompleted();
        $res = $svc->simular($week, true);
        $this->info("Semana {$week['period_start']} → {$week['period_end']}: evaluadas {$res['evaluadas']}, nuevas {$res['nuevas']}, ya existían {$res['ya_existian']}.");
        $rows = $svc->reporte(substr((string) $week['period_start'], 0, 10))
            ->map(fn ($f) => [$f->caja_ref, $f->colaborador_id, $f->power_dbm, $f->monto_simulado])->all();
        $this->table(['Caja', 'Colaborador', 'dBm', 'Habría pagado'], $rows);
        $this->line('Total simulado: ' . number_format(array_sum(array_column($rows, 3)), 2) . ' (NO se escribió nada en el ledger).');
        return self::SUCCESS;
    }
}
