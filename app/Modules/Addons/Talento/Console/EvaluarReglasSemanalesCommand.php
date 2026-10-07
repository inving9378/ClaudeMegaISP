<?php

namespace App\Modules\Addons\Talento\Console;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Services\TalentoWeeklyRuleEvaluationService;
use App\Modules\Addons\Talento\Support\PayWeek;
use Illuminate\Console\Command;

class EvaluarReglasSemanalesCommand extends Command
{
    protected $signature   = 'talento:evaluar-reglas-semanales';
    protected $description = 'Evalúa a cada colaborador activo contra la regla de desempeño semanal que le corresponda por su combinación de roles. Idempotente.';

    public function __construct(private TalentoWeeklyRuleEvaluationService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $week = PayWeek::lastCompleted();
        $this->info("Evaluando semana {$week['period_start']} a {$week['period_end']}…");

        $headers = ['colaborador_id', 'Nombre', 'Prospectos', 'Ventas', 'Resultado'];
        $rows = [];
        $counts = ['penalty_pending' => 0, 'bonus' => 0, 'meets_quota' => 0, 'no_rule' => 0, 'ya_evaluado' => 0];

        $colaboradores = TalentoColaborador::where('status', 'active')->with('user')->get();

        foreach ($colaboradores as $colaborador) {
            $yaExistia = \App\Modules\Addons\Talento\Models\TalentoWeeklyRuleEvaluation::where('colaborador_id', $colaborador->id)
                ->where('week_start', $week['period_start'])
                ->exists();

            $eval = $this->service->evaluateColaborador($colaborador, $week);
            if (!$eval) {
                continue;
            }

            if ($yaExistia) {
                $counts['ya_evaluado']++;
            } else {
                $counts[$eval->outcome] = ($counts[$eval->outcome] ?? 0) + 1;
            }

            $rows[] = [
                $colaborador->id,
                $colaborador->user->name ?? '—',
                $eval->prospects_count,
                $eval->sales_count,
                $yaExistia ? 'ya evaluado antes' : $eval->outcome,
            ];
        }

        $this->table($headers, $rows);
        $this->info(sprintf(
            'Pendientes de confirmar: %d | Bonos: %d | Cumplen cuota: %d | Sin regla: %d | Ya evaluados antes: %d',
            $counts['penalty_pending'],
            $counts['bonus'],
            $counts['meets_quota'],
            $counts['no_rule'],
            $counts['ya_evaluado']
        ));

        return self::SUCCESS;
    }
}
