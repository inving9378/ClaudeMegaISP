<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use Illuminate\Console\Command;

/**
 * GRAN DES-TRABE de la bandeja (#338). Opus re-triajea items de requiere_irving: los TÉCNICOS/seguros
 * (y los falsos positivos de la denylist) vuelven al POOL para auto-resolverse; los genuinamente de
 * Irving (negocio/dinero/seguridad/prod) quedan con TAG de categoría + BRIEF de Opus, agrupables.
 * Tanda ACOTADA (Opus cuesta). Cron para drenar de a poco. Solo dev. Frontera dura intacta.
 */
class DestrabeCommand extends Command
{
    protected $signature = 'circuito:destrabe {--limit=6 : máximo de items a des-trabar por pasada}';

    protected $description = 'Opus re-triajea la bandeja: re-aprueba los técnicos/seguros, agrupa lo de Irving (#338).';

    public function handle(RevisorService $revisor): int
    {
        $limit = max(1, (int) $this->option('limit'));

        // Items de la bandeja SIN des-trabar aún (sin el sello DES-TRABE en comentarios).
        $items = RoadmapItem::where('estado_aprobacion', 'requiere_irving')
            ->whereNotIn('status', ['done', 'cancelado'])
            ->where('en_desarrollo_humano', false)
            // #507 — NUNCA re-aprobar al pool un item parqueado (espera-merge / anti-bucle) ni uno
            // rotulado: eso era una de las puertas por las que el churn volvía a arrancar.
            ->elegibleParaPool()
            ->where(function ($q) {
                $q->whereNull('comentarios_claude')->orWhere('comentarios_claude', 'not like', '%DES-TRABE (Opus)%');
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($items->isEmpty()) {
            $this->info('Bandeja sin items nuevos que des-trabar.');

            return self::SUCCESS;
        }

        $cont = ['reaprobados' => 0, 'negocio' => 0, 'dinero' => 0, 'seguridad' => 0, 'prod' => 0];
        foreach ($items as $item) {
            $r = $revisor->destrabar($item);
            if ($r['reejecutable']) {
                $cont['reaprobados']++;
                $this->line("#{$item->id} → RE-APROBADO (al pool): " . mb_strimwidth($r['razon'], 0, 70, '…'));
            } else {
                $cont[$r['categoria']] = ($cont[$r['categoria']] ?? 0) + 1;
                $this->line("#{$item->id} → PARA TI [{$r['categoria']}]: " . mb_strimwidth($r['razon'], 0, 60, '…'));
            }
        }

        $this->info('Des-trabe: ' . $cont['reaprobados'] . ' re-aprobados al pool · para Irving → '
            . "negocio {$cont['negocio']} · dinero {$cont['dinero']} · seguridad {$cont['seguridad']} · prod {$cont['prod']}.");

        return self::SUCCESS;
    }
}
