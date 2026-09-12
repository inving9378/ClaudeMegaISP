<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fase 2 continuación de #9990877 (Identidad unificada) — puebla `colaborador_id` en las tablas
 * legado donde `seller_id` SÍ es `sellers.id` (a diferencia de #9990778, donde era `users.id`).
 *
 * Resolución determinista por DOBLE join, nunca por nombre:
 *   sellers.id (=seller_id) → sellers.user_id → talento_colaboradores.user_id
 *
 * Idempotente: solo toca filas con `colaborador_id IS NULL` y `seller_id IS NOT NULL`. No pisa un
 * valor ya poblado, no toca `seller_id`.
 *
 * Filas sin match (seller_id sin `talento_colaboradores` correspondiente — ver
 * docs/identidad-vendedores-diagnostico-8-seller-id-item-9990802.md, cuentas espejo 23/25,
 * sellers con is_seller=0, o sin alta en Talento) quedan `colaborador_id` NULL y se reportan, no
 * se adivinan. Su tratamiento caso por caso queda pendiente de que Irving decida (frontera dura
 * de identidad/comisiones, fuera de alcance de este backfill).
 *
 * `history_sellers_rules` y `sales` (fase "siguiente" q2, #9990930) se sumaron aquí mismo en vez
 * de un comando nuevo: mismo esquema (`seller_id`→`sellers.id`), misma resolución determinista.
 */
class BackfillColaboradorIdSellersLegacyCommand extends Command
{
    protected $signature = 'identidad:backfill-colaborador-id-sellers {--dry-run : Solo reporta, no escribe}';

    protected $description = 'Puebla colaborador_id en tablas legado de sellers.id vía sellers.user_id=talento_colaboradores.user_id (#9990877, #9990930)';

    /** @var array<int,string> */
    private array $tablas = [
        'commissions',
        'commissions_rules_sellers',
        'discounts',
        'payment_by_rule',
        'payments_sellers',
        'transactions_sellers',
        'history_sellers_rules',
        'sales',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $colaboradorPorUserId = DB::table('talento_colaboradores')->pluck('id', 'user_id');
        $userIdPorSellerId = DB::table('sellers')->pluck('user_id', 'id');

        foreach ($this->tablas as $tabla) {
            $this->procesarTabla($tabla, $userIdPorSellerId, $colaboradorPorUserId, $dry);
        }

        if ($dry) {
            $this->info('[DRY-RUN] No se escribió nada.');
        }

        return self::SUCCESS;
    }

    private function procesarTabla(string $tabla, $userIdPorSellerId, $colaboradorPorUserId, bool $dry): void
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
            $userId = $userIdPorSellerId->get($fila->seller_id);
            $colaboradorId = $userId !== null ? $colaboradorPorUserId->get($userId) : null;

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
            $this->warn("{$prefix}[{$tabla}] seller_id sin talento_colaboradores correspondiente (quedan NULL, no se adivinan):");
            foreach ($sinMatch as $sellerId => $count) {
                $this->line("  sellers.id={$sellerId} → {$count} fila(s)");
            }
        }
    }
}
