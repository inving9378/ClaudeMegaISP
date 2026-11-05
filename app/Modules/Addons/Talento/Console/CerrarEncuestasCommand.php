<?php

namespace App\Modules\Addons\Talento\Console;

use App\Modules\Addons\Talento\Services\FieldFlowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CerrarEncuestasCommand extends Command
{
    protected $signature   = 'talento:cerrar-encuestas {--dry-run : Solo lista lo que se cerraría, sin cambiar nada}';
    protected $description = 'Cierra las encuestas de instalación sin respuesta tras los días configurados y valida la orden que las esperaba (nunca una ya validada).';

    public function handle(FieldFlowService $service): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $resultado = $service->autoCloseSurveys($dryRun);

        foreach ($resultado as $fila) {
            $this->line("Encuesta #{$fila['encuesta']} (creada {$fila['creada']}): {$fila['efecto']}");
        }
        $this->info(($dryRun ? 'Se cerrarían ' : 'Encuestas cerradas: ') . count($resultado));

        if (! $dryRun && $resultado) {
            Log::info('talento:cerrar-encuestas', ['encuestas' => $resultado]);
        }

        return self::SUCCESS;
    }
}
