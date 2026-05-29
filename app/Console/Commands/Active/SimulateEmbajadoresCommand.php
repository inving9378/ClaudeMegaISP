<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SimulateEmbajadoresCommand extends Command
{
    protected $signature = 'embajadores:simulate {--confirm : Omitir prompt interactivo (peligroso)}';
    protected $description = 'Genera clientes sintéticos de prueba para validar el motor de comisiones Embajadores';

    // ── Configuración de simulación ───────────────────────────────────────────
    const ROOT_CLIENTS = 5;      // raíces del árbol (nivel 0)
    const LEVELS       = 4;      // profundidad máxima (niveles 0..LEVELS)
    const BRANCHING    = 3;      // hijos por nodo
    const MONTHS       = 3;      // meses de facturas simuladas
    const SIM_BASE_INV = 100_000_000; // base para invoice_ids sintéticos

    // Planes reales (id, precio, speed_mbps)
    const PLANS = [
        'basic'    => ['id' => 29,  'price' => 270.00, 'speed' => 20],
        'standard' => ['id' => 36,  'price' => 449.00, 'speed' => 100],
        'premium'  => ['id' => 18,  'price' => 599.00, 'speed' => 244],
    ];
    const PLAN_DIST = ['basic' => 30, 'standard' => 50, 'premium' => 20]; // % acumulativo: 30 / 80 / 100

    // Tiers de comisión por nivel y tier de plan (copiado de la BD)
    const TIER_PCTS = [
        'basic'    => [1 => 3.0,  2 => 1.5,  3 => 1.0,  4 => 0.5,  5 => 0.3],
        'standard' => [1 => 10.0, 2 => 5.0,  3 => 3.0,  4 => 2.0,  5 => 1.0],
        'premium'  => [1 => 15.0, 2 => 7.0,  3 => 4.0,  4 => 2.5,  5 => 1.5],
    ];

    private int $adminUserId = 3986;
    private float $startTime;

    // ── Contadores para métricas ──────────────────────────────────────────────
    private int $totalClients       = 0;
    private int $totalProfiles      = 0;
    private int $totalReferrals     = 0;
    private int $totalCommissions   = 0;
    private int $totalRewards       = 0;
    private float $totalFacturado   = 0;
    private float $totalComisiones  = 0;
    private float $comisionesAplic  = 0;
    private float $comisionesPend   = 0;
    private int $recompensasAvail   = 0;
    private int $recompensasApplied = 0;
    private int $cancelledReferrals = 0;

    private array $commsByLevel  = [];
    private array $amtByLevel    = [];
    private array $amtByTier     = ['basic' => 0.0, 'standard' => 0.0, 'premium' => 0.0];
    private array $profilesByLvl = [];
    private array $topGainers    = [];

    public function handle(): int
    {
        $this->startTime = microtime(true);
        for ($l = 1; $l <= self::LEVELS; $l++) {
            $this->commsByLevel[$l] = 0;
            $this->amtByLevel[$l]   = 0.0;
        }
        for ($l = 0; $l < self::LEVELS; $l++) {
            $this->profilesByLvl[$l] = 0;
        }

        if (!$this->confirmRun()) {
            return 1;
        }

        $this->info('');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('  SIMULACIÓN EMBAJADORES MEGANET — INICIANDO               ');
        $this->info('═══════════════════════════════════════════════════════════');

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            $this->runSimulation();
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->printReport();

        return 0;
    }

    // ── Flujo principal ───────────────────────────────────────────────────────

    private function runSimulation(): void
    {
        $now = now()->toDateTimeString();

        // ── 1. Generar árbol de clientes ─────────────────────────────────────
        $this->info('');
        $total = self::ROOT_CLIENTS * (self::BRANCHING ** self::LEVELS - 1) / (self::BRANCHING - 1) + self::ROOT_CLIENTS;
        $this->line('  [1/5] Generando clientes (árbol ' . self::ROOT_CLIENTS . '×' . self::BRANCHING . '^' . self::LEVELS . ')...');

        [$levels, $parentOf, $planOf, $chainOf] = $this->generateClients($now);

        $this->line("        ✓ {$this->totalClients} clientes creados");

        // ── 2. Perfiles de embajador ──────────────────────────────────────────
        $this->line('  [2/5] Creando perfiles de embajador...');

        $profilePlanOf = $this->generateProfiles($levels, $now);

        $this->line("        ✓ {$this->totalProfiles} perfiles creados");

        // ── 3. Referrals ──────────────────────────────────────────────────────
        $this->line('  [3/5] Creando referidos (chain_path)...');

        $referralIdOf = $this->generateReferrals($levels, $parentOf, $chainOf, $now);

        $this->line("        ✓ {$this->totalReferrals} referidos creados ({$this->cancelledReferrals} cancelados)");

        // ── 4. Comisiones ─────────────────────────────────────────────────────
        $this->line('  [4/5] Calculando y acreditando comisiones...');

        $this->generateCommissions($levels, $parentOf, $chainOf, $planOf, $profilePlanOf, $referralIdOf, $now);

        $this->line("        ✓ {$this->totalCommissions} comisiones generadas");

        // ── 5. Recompensas (Plan A) ───────────────────────────────────────────
        $this->line('  [5/5] Generando recompensas Plan A...');

        $this->generateRewards($levels, $profilePlanOf, $referralIdOf, $planOf, $now);

        $this->line("        ✓ {$this->totalRewards} recompensas generadas");
    }

    // ── Generación de clientes ────────────────────────────────────────────────

    private function generateClients(string $now): array
    {
        $levels   = [];
        $parentOf = [];
        $planOf   = [];
        $chainOf  = []; // clientId => [ancestor_ids...] from root to direct parent

        $rand = fn(int $max) => mt_rand(0, $max - 1);
        $planKeys = array_keys(self::PLANS);

        for ($lvl = 0; $lvl <= self::LEVELS; $lvl++) {
            $count = (int) round(pow(self::BRANCHING, $lvl) * ($lvl === 0 ? 1 : 1));
            // Level 0: 1 batch of 10 actually
            $count = $lvl === 0 ? self::ROOT_CLIENTS : count($levels[$lvl - 1]) * self::BRANCHING;

            $batch = [];
            for ($i = 0; $i < $count; $i++) {
                $rnd = $rand(100);
                $tier = $rnd < 30 ? 'basic' : ($rnd < 80 ? 'standard' : 'premium');

                $batch[] = [
                    'created_by'           => $this->adminUserId,
                    'updated_by'           => $this->adminUserId,
                    'is_test_data'         => 1,
                    'active_promise_payment' => 0,
                    'migrated_from_splynt' => 0,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }

            // Bulk insert (500 per batch)
            // MySQL lastInsertId() after a multi-row INSERT returns the FIRST assigned ID.
            $insertedIds = [];
            foreach (array_chunk($batch, 500) as $chunk) {
                DB::table('clients')->insert($chunk);
                $firstId = (int) DB::getPdo()->lastInsertId();
                for ($k = 0; $k < count($chunk); $k++) {
                    $insertedIds[] = $firstId + $k;
                }
            }

            // Assign plan tier and parent
            $prevLevel = $lvl > 0 ? $levels[$lvl - 1] : [];
            foreach ($insertedIds as $idx => $clientId) {
                // Plan tier: deterministic from index % 100
                $rnd  = ($idx * 7 + $lvl * 13) % 100;
                $tier = $rnd < 30 ? 'basic' : ($rnd < 80 ? 'standard' : 'premium');
                $planOf[$clientId] = $tier;

                if ($lvl > 0) {
                    $parentIdx         = (int) floor($idx / self::BRANCHING);
                    $parentId          = $prevLevel[$parentIdx];
                    $parentOf[$clientId] = $parentId;
                    $chainOf[$clientId]  = array_merge($chainOf[$parentId] ?? [], [$parentId]);
                } else {
                    $chainOf[$clientId] = [];
                }
            }

            $levels[$lvl] = $insertedIds;
            $this->totalClients += count($insertedIds);
        }

        return [$levels, $parentOf, $planOf, $chainOf];
    }

    // ── Perfiles de embajador ─────────────────────────────────────────────────

    private function generateProfiles(array $levels, string $now): array
    {
        $profilePlanOf = [];
        $batch         = [];

        // Levels 0..LEVELS-1 son embajadores; el nivel LEVELS son referidos hoja sin perfil
        for ($lvl = 0; $lvl <= self::LEVELS - 1; $lvl++) {
            foreach ($levels[$lvl] as $idx => $clientId) {
                $isMultilevel   = (($idx * 3 + $lvl) % 10) < 6; // 60% multilevel
                $profilePlan    = $isMultilevel ? 'multilevel' : 'single_reward';
                $profilePlanOf[$clientId] = $profilePlan;

                // Elegibilidad: 70% eligible, 20% parcial, 10% nuevo
                $eligRoll = ($idx * 11 + $lvl * 7) % 100;
                $eligible   = $eligRoll < 70;
                $threshPaid = $eligible ? 1500.0 + ($eligRoll * 50)
                                        : ($eligRoll < 90 ? (500.0 + $eligRoll * 10) : ($eligRoll * 5));

                $code = 'SIM' . str_pad($clientId, 7, '0', STR_PAD_LEFT);

                $batch[] = [
                    'client_id'                => $clientId,
                    'plan_type'                => $profilePlan,
                    'referral_code'            => $code,
                    'referral_link'            => url('/registro?ref=' . $code),
                    'is_eligible'              => $eligible ? 1 : 0,
                    'threshold_amount_paid'    => $threshPaid,
                    'total_referrals'          => self::BRANCHING,
                    'total_commissions_earned' => 0,
                    'total_rewards_earned'     => 0,
                    'activated_at'             => now()->subMonths(6)->toDateTimeString(),
                    'created_at'               => $now,
                    'updated_at'               => $now,
                ];

                $this->profilesByLvl[$lvl]++;
                $this->totalProfiles++;
            }
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('client_referral_profiles')->insert($chunk);
        }

        return $profilePlanOf;
    }

    // ── Referrals ─────────────────────────────────────────────────────────────

    private function generateReferrals(array $levels, array $parentOf, array $chainOf, string $now): array
    {
        $referralIdOf = [];
        $allBatch     = [];
        $referralIdx  = 0;

        for ($lvl = 1; $lvl <= self::LEVELS; $lvl++) {
            foreach ($levels[$lvl] as $idx => $clientId) {
                $parentId  = $parentOf[$clientId];
                $ancestors = $chainOf[$clientId]; // [root, ..., grandparent]
                $chainPath = '/' . implode('/', array_merge($ancestors, [$parentId])) . '/';
                $depth     = $lvl;

                // 15% se cancelan (baja anticipada)
                $isCancelled = ($referralIdx % 100) < 15;
                $status      = $isCancelled ? 'cancelled' : (($referralIdx % 100) < 25 ? 'pending_threshold' : 'active');

                if ($isCancelled) {
                    $this->cancelledReferrals++;
                }

                $threshCoveredAt = $status === 'active' ? now()->subMonths(4)->toDateTimeString() : null;

                $allBatch[] = [
                    'embajador_id'                   => $parentId,
                    'referred_client_id'             => $clientId,
                    'chain_path'                     => $chainPath,
                    'chain_depth'                    => $depth,
                    'status'                         => $status,
                    'referred_threshold_amount_paid' => $status === 'active' ? 1500.0 : ($status === 'pending_threshold' ? mt_rand(200, 1400) : 0),
                    'referred_threshold_covered_at'  => $threshCoveredAt,
                    'commission_window_start'         => $threshCoveredAt,
                    'commissions_paid_count'          => 0,
                    'created_at'                     => now()->subMonths(6)->toDateTimeString(),
                    'updated_at'                     => $now,
                ];

                $referralIdx++;
            }
        }

        // Bulk insert and capture IDs (MySQL lastInsertId = FIRST assigned ID for bulk INSERT)
        foreach (array_chunk($allBatch, 500) as $chunk) {
            DB::table('referrals')->insert($chunk);
            $firstId = (int) DB::getPdo()->lastInsertId();
            foreach ($chunk as $k => $row) {
                $referralIdOf[$row['referred_client_id']] = $firstId + $k;
            }
        }

        $this->totalReferrals = count($allBatch);
        return $referralIdOf;
    }

    // ── Comisiones ────────────────────────────────────────────────────────────

    private function generateCommissions(
        array $levels,
        array $parentOf,
        array $chainOf,
        array $planOf,
        array $profilePlanOf,
        array $referralIdOf,
        string $now
    ): void {
        $commBatch   = [];
        $invoiceBase = self::SIM_BASE_INV;
        $invIdx      = 0;

        // Only process ACTIVE referrals (levels 1-5)
        for ($lvl = 1; $lvl <= self::LEVELS; $lvl++) {
            foreach ($levels[$lvl] as $idx => $clientId) {
                $referralId = $referralIdOf[$clientId] ?? null;
                if (!$referralId) continue;

                // Only active referrals generate commissions
                $statusRoll = $idx % 100;
                $isActive   = $statusRoll >= 25 && $statusRoll >= 15; // mirrors status calc
                if (!$isActive) continue;

                $clientTier   = $planOf[$clientId];
                $planPrice    = self::PLANS[$clientTier]['price'];
                $this->totalFacturado += $planPrice * self::MONTHS;

                // Chain: ancestors from root to direct parent
                $fullChain = array_merge($chainOf[$clientId], [$parentOf[$clientId]]);

                for ($month = 1; $month <= self::MONTHS; $month++) {
                    $invIdx++;
                    $syntheticInvoiceId = $invoiceBase + $invIdx;

                    $periodMonth = ((now()->subMonths(self::MONTHS - $month)->month));
                    $periodYear  = (now()->subMonths(self::MONTHS - $month)->year);

                    // Each ancestor at its depth earns a commission
                    foreach (array_reverse($fullChain) as $depthIdx => $ancestorId) {
                        $depth = $depthIdx + 1;
                        if ($depth > self::LEVELS) break;

                        // Ancestor must be multilevel
                        if (($profilePlanOf[$ancestorId] ?? '') !== 'multilevel') continue;

                        $pct    = self::TIER_PCTS[$clientTier][$depth] ?? 0;
                        $amount = round($planPrice * $pct / 100, 2);

                        if ($amount <= 0) continue;

                        // 80% applied, 20% approved (pending)
                        $isApplied  = ($invIdx % 10) < 8;
                        $status     = $isApplied ? 'applied' : 'approved';
                        $appliedAt  = $isApplied ? now()->subDays(5)->toDateTimeString() : null;
                        $applyAfter = now()->subDays(15)->toDateTimeString();

                        $commBatch[] = [
                            'beneficiary_id'   => $ancestorId,
                            'referral_id'      => $referralId,
                            'invoice_id'       => $syntheticInvoiceId,
                            'level'            => $depth,
                            'commission_pct'   => $pct,
                            'base_amount'      => $planPrice,
                            'commission_amount'=> $amount,
                            'period_month'     => $periodMonth,
                            'period_year'      => $periodYear,
                            'status'           => $status,
                            'apply_after_at'   => $applyAfter,
                            'applied_at'       => $appliedAt,
                            'created_at'       => now()->subMonths(self::MONTHS - $month)->toDateTimeString(),
                            'updated_at'       => $now,
                        ];

                        // Metrics
                        $this->totalComisiones += $amount;
                        $this->amtByTier[$clientTier] += $amount;
                        $this->amtByLevel[$depth]     += $amount;
                        $this->commsByLevel[$depth]++;
                        if ($isApplied) {
                            $this->comisionesAplic += $amount;
                        } else {
                            $this->comisionesPend  += $amount;
                        }

                        // Track top earners
                        if (!isset($this->topGainers[$ancestorId])) {
                            $this->topGainers[$ancestorId] = 0.0;
                        }
                        $this->topGainers[$ancestorId] += $amount;
                    }
                }

                // Flush batch to avoid memory explosion
                if (count($commBatch) >= 2000) {
                    DB::table('referral_commissions')->insert($commBatch);
                    $this->totalCommissions += count($commBatch);
                    $commBatch = [];
                }
            }
        }

        if (!empty($commBatch)) {
            DB::table('referral_commissions')->insert($commBatch);
            $this->totalCommissions += count($commBatch);
        }
    }

    // ── Recompensas (Plan A) ──────────────────────────────────────────────────

    private function generateRewards(
        array $levels,
        array $profilePlanOf,
        array $referralIdOf,
        array $planOf,
        string $now
    ): void {
        $batch = [];

        for ($lvl = 1; $lvl <= self::LEVELS; $lvl++) {
            foreach ($levels[$lvl] as $idx => $clientId) {
                $referralId  = $referralIdOf[$clientId] ?? null;
                $parentId    = $this->getParentFromReferral($clientId, $levels, $idx, $lvl);
                if (!$referralId || !$parentId) continue;

                // Only active referrals with single_reward embajador
                $statusRoll = $idx % 100;
                $isActive   = $statusRoll >= 25 && $statusRoll >= 15;
                if (!$isActive) continue;
                if (($profilePlanOf[$parentId] ?? '') !== 'single_reward') continue;

                $planPrice = self::PLANS[$planOf[$clientId]]['price'];
                $isApplied = ($idx % 100) < 30;

                $batch[] = [
                    'embajador_id'        => $parentId,
                    'referral_id'         => $referralId,
                    'type'                => 'free_month',
                    'plan_value_snapshot' => $planPrice,
                    'status'              => $isApplied ? 'applied' : 'available',
                    'available_at'        => now()->subMonths(3)->toDateTimeString(),
                    'applied_at'          => $isApplied ? now()->subMonths(1)->toDateTimeString() : null,
                    'expires_at'          => now()->addMonths(9)->toDateTimeString(),
                    'created_at'          => now()->subMonths(4)->toDateTimeString(),
                    'updated_at'          => $now,
                ];

                if ($isApplied) {
                    $this->recompensasApplied++;
                } else {
                    $this->recompensasAvail++;
                }
                $this->totalRewards++;

                if (count($batch) >= 500) {
                    DB::table('referral_rewards')->insert($batch);
                    $batch = [];
                }
            }
        }

        foreach (array_chunk($batch, 500) as $chunk) {
            DB::table('referral_rewards')->insert($chunk);
        }
    }

    private function getParentFromReferral(int $clientId, array $levels, int $idx, int $lvl): ?int
    {
        if ($lvl === 0) return null;
        $parentLevel = $levels[$lvl - 1];
        $parentIdx   = (int) floor($idx / self::BRANCHING);
        return $parentLevel[$parentIdx] ?? null;
    }

    // ── Confirmación ─────────────────────────────────────────────────────────

    private function confirmRun(): bool
    {
        if ($this->option('confirm')) {
            return true;
        }

        $this->warn('');
        $this->warn('  ⚠  ADVERTENCIA: Esta operación creará ~39,060 clientes ficticios en la BD ACTIVA.');
        $this->warn('     Se recomienda hacer backup antes de proceder.');
        $this->warn('');
        $response = $this->ask('  Escriba exactamente "GENERAR 39060 CLIENTES" para confirmar');

        if (trim($response) !== 'GENERAR 39060 CLIENTES') {
            $this->error('Texto incorrecto. Operación cancelada.');
            return false;
        }

        return true;
    }

    // ── Reporte de métricas ───────────────────────────────────────────────────

    private function printReport(): void
    {
        $elapsed   = round(microtime(true) - $this->startTime, 1);
        $totalClts = $this->totalClients;
        $pct       = fn(float $v, float $t): string => $t > 0 ? number_format($v / $t * 100, 1) . '%' : '0%';
        $fmt       = fn(float $v): string => '$' . number_format($v, 2);
        $fmtN      = fn(int $v): string   => number_format($v);

        arsort($this->topGainers);
        $top10 = array_slice($this->topGainers, 0, 10, true);

        $this->info('');
        $this->line(str_repeat('═', 65));
        $this->info('  SIMULACIÓN EMBAJADORES MEGANET — RESULTADOS');
        $this->line(str_repeat('═', 65));

        $this->info('');
        $this->line('  Población generada:');
        $this->line("    Total clientes:            {$fmtN($totalClts)}");
        $maxEmbLvl = self::LEVELS - 1;
        $this->line("    Embajadores (niveles 0-{$maxEmbLvl}): {$fmtN($this->totalProfiles)}");
        $this->line("    Referrals creados:         {$fmtN($this->totalReferrals)}");
        $this->line("    Cancelaciones:             {$fmtN($this->cancelledReferrals)} ({$pct($this->cancelledReferrals, $this->totalReferrals)})");

        $this->info('');
        $this->line('  Distribución por nivel:');
        foreach ($this->profilesByLvl as $lvl => $cnt) {
            $amtTotal = $this->amtByLevel[$lvl + 1] ?? 0;
            $this->line("    Nivel {$lvl}:  {$fmtN($cnt)} embajadores  →  comisiones recibidas: {$fmt($amtTotal)}");
        }

        $this->info('');
        $this->line('  Resultado financiero (' . self::MONTHS . ' meses simulados):');
        $this->line("    Total facturado:               {$fmt($this->totalFacturado)}");
        $comisionesPct = $this->totalFacturado > 0 ? round($this->totalComisiones / $this->totalFacturado * 100, 2) : 0;
        $this->line("    Total comisiones generadas:    {$fmt($this->totalComisiones)} ({$comisionesPct}% del facturado)");
        $this->line("      - Aplicadas a balance:       {$fmt($this->comisionesAplic)}");
        $this->line("      - Pendientes (aprobadas):    {$fmt($this->comisionesPend)}");
        $this->line("    Recompensas Plan A (disponib): {$fmtN($this->recompensasAvail)} mensualidades");
        $this->line("    Recompensas Plan A (aplicadas):{$fmtN($this->recompensasApplied)} mensualidades");

        $this->info('');
        $this->line('  Comisiones por tier de plan del referido:');
        foreach ($this->amtByTier as $tier => $amt) {
            $this->line("    " . ucfirst($tier) . ":  {$fmt($amt)} ({$pct($amt, $this->totalComisiones)})");
        }

        $this->info('');
        $this->line('  Comisiones por nivel del beneficiario:');
        for ($lvl = 1; $lvl <= self::LEVELS; $lvl++) {
            $amt = $this->amtByLevel[$lvl] ?? 0;
            $cnt = $this->commsByLevel[$lvl] ?? 0;
            $this->line("    Nivel {$lvl}: {$fmt($amt)} ({$pct($amt, $this->totalComisiones)}) — {$fmtN($cnt)} registros");
        }

        $this->info('');
        $this->line('  Top 10 embajadores por ganancia simulada:');
        $rank = 1;
        foreach ($top10 as $clientId => $total) {
            $this->line("    #{$rank}  {$fmt($total)}  (cliente #{$clientId})");
            $rank++;
        }

        $this->info('');
        $this->line("  Performance:");
        $this->line("    Tiempo total:   {$elapsed} segundos");
        $commPerSec = $elapsed > 0 ? round($this->totalCommissions / $elapsed) : 0;
        $this->line("    Comisiones/seg: {$commPerSec}");

        $this->info('');
        $this->line(str_repeat('═', 65));
        $this->info('  RECOMENDACIONES PARA PILOTO REAL:');
        $this->line(str_repeat('═', 65));

        if ($comisionesPct > 8) {
            $this->warn("  · Los tiers generan el {$comisionesPct}% de ingresos en comisiones.");
            $this->warn('    Considera reducir % del tier premium si la rentabilidad es prioritaria.');
        } else {
            $this->line("  · Tasa de comisiones del {$comisionesPct}% — sostenible para el piloto.");
        }

        $activeRate = $this->totalReferrals > 0
            ? round(($this->totalReferrals - $this->cancelledReferrals) / $this->totalReferrals * 100, 1)
            : 0;
        $this->line("  · Tasa de retención simulada: {$activeRate}% — ajustar umbral si es menor a 60% en producción.");
        $this->line("  · Para el piloto real: empezar con 10-20 embajadores reales y monitorear las métricas.');");
        $this->line("  · Ejecutar 'embajadores:simulate-clean' para limpiar estos datos de prueba.");

        $this->info('');
        $this->line(str_repeat('═', 65));
        $this->info('');
    }
}
