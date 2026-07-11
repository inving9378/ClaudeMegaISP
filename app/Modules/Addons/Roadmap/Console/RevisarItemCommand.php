<?php

namespace App\Modules\Addons\Roadmap\Console;

use App\Modules\Addons\Roadmap\Models\RoadmapItem;
use App\Modules\Addons\Roadmap\Services\RevisorService;
use Illuminate\Console\Command;

/**
 * #338 — Revisa UN item con el revisor adversarial. Lo llama el ejecutor on-box cuando decide
 * un B (si el flag circuito_revisor está ON y el item está en alcance). Imprime el veredicto en
 * formato machine-readable (REVISOR_VEREDICTO=...) y, con --apply, fija el estado.
 */
class RevisarItemCommand extends Command
{
    protected $signature = 'circuito:revisar {id : ID del item B} {--apply : Fija el estado según el veredicto}';

    protected $description = 'Revisor adversarial (#338): evalúa un item B (autoriza|escala) y opcionalmente aplica el estado.';

    public function handle(RevisorService $revisor): int
    {
        $item = RoadmapItem::find((int) $this->argument('id'));
        if (! $item) {
            $this->error('Item no encontrado.');

            return self::FAILURE;
        }

        // #341: no tocar items que trabaja un humano/otra sesión.
        if ($item->estaEnDesarrollo()) {
            $this->error("Item #{$item->id} en desarrollo (candado #341): el revisor no lo toca.");

            return self::FAILURE;
        }

        $v = $revisor->revisar($item, $item->comentarios_claude);

        $this->line('REVISOR_VEREDICTO=' . $v['veredicto']);
        $this->line('REVISOR_ALCANCE=' . ($v['en_alcance'] ? '1' : '0'));
        $this->line('REVISOR_CATEGORIA=' . ($v['categoria_escalada'] ?? '-'));
        $this->line('REVISOR_CONFIANZA=' . ($v['confianza'] ?? '-'));
        $this->line('REVISOR_RAZON=' . mb_strimwidth((string) ($v['razon'] ?? ''), 0, 300, '…'));

        if ($this->option('apply')) {
            $fresh = $revisor->aplicarVeredicto($item, $v, 'revisor:item');
            $this->line('REVISOR_APLICADO=' . $fresh->estado_aprobacion);
        }

        return self::SUCCESS;
    }
}
