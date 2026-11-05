<?php

namespace App\Modules\Addons\Talento\Console;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRule;
use App\Modules\Addons\Talento\Models\TalentoWeeklyRuleEvaluation;
use App\Modules\Addons\Talento\Services\TalentoWeeklyRuleEvaluationService as Svc;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** UNI-T J5 (#1009): SOLO LECTURA. Qué regla semanal resolvería hoy cada colaborador y cuáles cuentas operativas caen en las reglas DEMO. */
class SimularAislamientoReglasCommand extends Command
{
    protected $signature   = 'talento:simular-aislamiento-reglas {--dry-run : (siempre activo, el comando nunca escribe)}';
    protected $description = 'Simula (solo lectura) el aislamiento de las reglas semanales DEMO (las que su nombre empieza con «DEMO»): regla actual vs. regla con el interruptor reglas_demo_aisladas.';

    public function handle(Svc $svc): int
    {
        $metricTypes = TalentoWeeklyRule::active()->distinct()->pluck('metric_type');
        // Reglas DEMO por nombre (no por id: los ids cambian entre instancias).
        $demoIds = TalentoWeeklyRule::all(['id', 'name'])->filter(fn ($r) => Svc::esReglaDemo($r))->pluck('id')->all();
        $rows = [];
        $operativosEnDemo = 0;

        foreach (TalentoColaborador::where('status', 'active')->with('user')->get() as $c) {
            if (!$c->user) {
                continue;
            }
            $roles = $svc->rolesDeNegocio($c->user);
            $login = $c->user->login_user;
            $demo = Svc::esCuentaDemo($login);
            foreach ($metricTypes as $mt) {
                $hoy = $svc->reglaQueAplicaA($roles, $mt, null, false);
                if (!$hoy) {
                    continue;
                }
                $aislada = $svc->reglaQueAplicaA($roles, $mt, $login, true);
                $cae = in_array($hoy->id, $demoIds, true);
                if ($cae && !$demo) {
                    $operativosEnDemo++;
                }
                $rows[] = [$c->id, $login, $demo ? 'demo' : 'operativa', implode('+', $roles), $mt,
                    '#' . $hoy->id, $aislada ? '#' . $aislada->id : '(sin regla)', $cae && !$demo ? 'SI' : ''];
            }
        }

        $this->table(['colab', 'login', 'tipo', 'roles', 'metric', 'regla hoy', 'regla aislada', 'operativa en DEMO'], $rows);
        $this->info("Cuentas operativas que hoy caen en reglas DEMO (" . implode('/', $demoIds) . "): {$operativosEnDemo}");

        if (Schema::hasTable('talento_weekly_rule_evaluations')) {
            $evals = TalentoWeeklyRuleEvaluation::whereIn('rule_id', $demoIds)
                ->select('rule_id', 'outcome', DB::raw('count(*) n'))->groupBy('rule_id', 'outcome')->get();
            foreach ($evals as $e) {
                $this->line("evaluaciones regla #{$e->rule_id} · {$e->outcome}: {$e->n}");
            }
            $this->line('evaluaciones totales en reglas DEMO: ' . $evals->sum('n'));
        }

        return self::SUCCESS;
    }
}
