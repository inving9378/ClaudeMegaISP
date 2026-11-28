<?php

namespace App\Console\Commands\Active;

use App\Services\Asistente\AdjuntosAsistente;
use Illuminate\Console\Command;

/**
 * Borra los archivos del chat de JARVIS que ya vencieron (limites.json → adjuntos.horas_de_vida) y
 * las subidas que se quedaron a medias. La fila queda como constancia, sin bytes.
 */
class AsistentePurgarAdjuntosCommand extends Command
{
    protected $signature = 'asistente:purgar-adjuntos';

    protected $description = 'Borra los archivos vencidos del chat de JARVIS';

    public function handle(AdjuntosAsistente $adjuntos): int
    {
        $n = $adjuntos->purgarVencidos();
        $this->info("Archivos borrados: {$n}");

        return self::SUCCESS;
    }
}
