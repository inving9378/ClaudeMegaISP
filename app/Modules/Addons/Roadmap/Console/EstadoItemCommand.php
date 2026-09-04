<?php

namespace App\Modules\Addons\Roadmap\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * #914 (Fase 3 de #911) — AVISO TEMPRANO al perdedor de una colisión en vuelo.
 *
 * Antes de esto, `detectarColisionesEnVuelo()` (scheduler, cada minuto) ya marca
 * `colision_pausada_por` en BD en cuanto detecta el solape, pero el ÚNICO punto donde la
 * terminal en vuelo se enteraba era su propio `circuito:integrar` al final — pudiendo perder
 * varios minutos de trabajo hecho sobre archivos que el ganador ya iba a pisar. Este comando es
 * la auto-verificación barata (una sola query, sin git) que el protocolo del ejecutor
 * (`deploy/circuito/prompt-item.txt`) instruye correr en puntos naturales de la vuelta —
 * después de cada commit — para enterarse EN VIVO en vez de esperar al cierre.
 *
 * Exit 0 = sigue libre, continúa. Exit 1 = YA fue marcado perdedor: detente ahora (no sigas
 * editando archivos que el ganador va a pisar), deja el trabajo ya commiteado tal cual (NO lo
 * integra, NO lo revierte — `circuito:integrar` es quien formaliza la pausa) y cierra la vuelta
 * con el META (`ejecuto:false`). Solo lee, nunca escribe: la pausa real ya la escribió el
 * scheduler; esto solo se la avisa a la terminal.
 */
class EstadoItemCommand extends Command
{
    protected $signature = 'circuito:estado-item
        {id : ID del item a consultar}
        {--sid= : tu slot de terminal (wt-K), solo para el mensaje}';

    protected $description = '#914 — chequeo barato: ¿ya me marcaron perdedor de una colisión en vuelo? (exit 1 = sí, detente).';

    public function handle(): int
    {
        $id = (int) $this->argument('id');

        $row = DB::table('roadmap_items')
            ->where('id', $id)
            ->first(['id', 'colision_pausada_por', 'colision_pausada_at']);

        if (! $row) {
            $this->error("Item #{$id} no encontrado.");

            return self::FAILURE;
        }

        if ($row->colision_pausada_por) {
            $this->error(
                "PAUSADO por colisión en vuelo con #{$row->colision_pausada_por} (desde {$row->colision_pausada_at}). "
                . "DETENTE: deja lo ya commiteado tal cual (no lo integres, no lo reviertas) y cierra la vuelta "
                . "con el META (ejecuto=false, resumen=\"pausado por colisión con #{$row->colision_pausada_por}\")."
            );

            return self::FAILURE;
        }

        $this->info("Item #{$id} libre, sin colisión — continúa.");

        return self::SUCCESS;
    }
}
