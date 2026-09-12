<?php

namespace App\Console\Commands\Active;

use App\Modules\Addons\Talento\Models\TalentoColaborador;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 de #9990778 (Identidad unificada) — puebla `colaborador_id` en
 * `client_main_information` y `whatsapp_conversations` a partir del `seller_id` existente.
 *
 * El diagnóstico de Fase 1 (#9990801) determinó que en estas dos tablas `seller_id` es en
 * realidad `users.id` (no `sellers.id`). Por eso la resolución es un JOIN directo y determinista
 * `talento_colaboradores.user_id = seller_id` — el mismo bridge por `user_id` que ya usa el resto
 * del sistema (Actor::seller(), item #123). No hay coincidencia por nombre ni por ID cruzado de
 * entornos: es una relación interna dentro de la MISMA base de datos.
 *
 * Idempotente: solo toca filas con `colaborador_id IS NULL` y `seller_id IS NOT NULL`. No pisa
 * un valor ya poblado, no toca `seller_id`.
 *
 * Filas sin match (seller_id que no tiene talento_colaboradores.user_id correspondiente, ej.
 * Irving/TERE/kathya/kevin o cuentas espejo — ver §5.2 del diagnóstico) quedan `colaborador_id`
 * NULL y se reportan, no se adivinan.
 */
class BackfillColaboradorIdBridgeCommand extends Command
{
    protected $signature = 'identidad:backfill-colaborador-id {--dry-run : Solo reporta, no escribe}';

    protected $description = 'Puebla colaborador_id en client_main_information y whatsapp_conversations vía seller_id=users.id (#9990778 Fase 2)';

    /** @var array<int,string> */
    private array $tablas = ['client_main_information', 'whatsapp_conversations'];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $colaboradorPorUserId = TalentoColaborador::pluck('id', 'user_id');

        foreach ($this->tablas as $tabla) {
            $this->procesarTabla($tabla, $colaboradorPorUserId, $dry);
        }

        if ($dry) {
            $this->info('[DRY-RUN] No se escribió nada.');
        }

        return self::SUCCESS;
    }

    private function procesarTabla(string $tabla, $colaboradorPorUserId, bool $dry): void
    {
        $pendientes = DB::table($tabla)
            ->whereNull('colaborador_id')
            ->whereNotNull('seller_id')
            ->select('id', 'seller_id')
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info("[{$tabla}] Nada pendiente (0 filas con seller_id sin colaborador_id).");
            return;
        }

        $matched = 0;
        $sinMatch = [];

        foreach ($pendientes as $fila) {
            $colaboradorId = $colaboradorPorUserId->get($fila->seller_id);
            if ($colaboradorId === null) {
                $sinMatch[$fila->seller_id] = ($sinMatch[$fila->seller_id] ?? 0) + 1;
                continue;
            }
            $matched++;
            if (!$dry) {
                DB::table($tabla)->where('id', $fila->id)->update(['colaborador_id' => $colaboradorId]);
            }
        }

        $prefix = $dry ? '[DRY-RUN] ' : '';
        $this->info("{$prefix}[{$tabla}] filas con seller_id sin colaborador_id: {$pendientes->count()} → resueltas: {$matched}");

        if (!empty($sinMatch)) {
            $this->warn("{$prefix}[{$tabla}] seller_id (=users.id) sin talento_colaboradores correspondiente (quedan NULL, no se adivinan):");
            foreach ($sinMatch as $userId => $count) {
                $this->line("  users.id={$userId} → {$count} fila(s)");
            }
        }
    }
}
