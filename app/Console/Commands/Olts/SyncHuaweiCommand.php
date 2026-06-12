<?php

namespace App\Console\Commands\Olts;

use App\Models\Olt;
use App\Models\OltOnu;
use App\Models\OltUnconfiguredOnu;
use App\Services\OltDriver\OltDriverManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Full Huawei OLT scan using ONE Telnet session.
 *
 * Login once → iterate all boards/ports → close cleanly.
 * This avoids the 80-login-per-run pattern that triggers the OLT anti-attack ACL.
 *
 * Lock semantics:
 *  - Cron acquires Cache::lock("olt-huawei-telnet-{$oltId}", 900) — long TTL
 *  - Web calls acquire the same key with TTL 60 — short TTL
 *  - If the cron lock is held, HuaweiDriver::withSession() returns syncing=true
 *    and web controllers fall back to cached DB data.
 *
 * Duration logging:
 *  - Each run records duration_s, onus_scan_s, signals_scan_s.
 *  - This is the metric that determines whether the 10-min interval is sufficient
 *    or must be bumped to 15 min. Decision threshold: if duration_s > 8 min, bump.
 */
class SyncHuaweiCommand extends Command
{
    protected $signature = 'gestionred:sync-huawei
                            {olt_id? : ID de OLT Huawei (opcional; sincroniza todas si se omite)}';

    protected $description = 'Sincroniza inventario y señales ópticas desde OLT Huawei por Telnet (sesión única)';

    public function handle(OltDriverManager $driverManager): int
    {
        $olts = Olt::where('driver', Olt::DRIVER_HUAWEI)->get();

        if ($this->argument('olt_id')) {
            $oltId = (int) $this->argument('olt_id');
            $olts  = $olts->where('id', $oltId);
        }

        if ($olts->isEmpty()) {
            Log::info('[gestionred:sync-huawei] No Huawei OLTs configured. Skipping.');
            return 0;
        }

        foreach ($olts as $olt) {
            $this->syncOlt($olt, $driverManager);
        }

        return 0;
    }

    private function syncOlt(Olt $olt, OltDriverManager $driverManager): void
    {
        $lockKey = "olt-huawei-telnet-{$olt->id}";
        $lock    = Cache::lock($lockKey, 900);

        if (! $lock->get()) {
            Log::warning("[gestionred:sync-huawei] OLT {$olt->id}: lock ya tomado — otro proceso activo. Saltando.");
            return;
        }

        $startedAt = microtime(true);
        Log::info("[gestionred:sync-huawei] OLT {$olt->id} ({$olt->name}): iniciando scan.");

        $driver        = $driverManager->driverFor($olt);
        $onusDuration  = 0.0;
        $sigsDuration  = 0.0;
        $onusCount     = 0;
        $sigsCount     = 0;

        try {
            // ONE login for the entire scan — avoids anti-attack ACL
            $driver->openSession();

            // ── Scan 1: inventario completo ────────────────────────────────
            $t0          = microtime(true);
            $onusResult  = $driver->getOnusByOlt((string) $olt->id);
            $onusDuration = round(microtime(true) - $t0, 1);

            if (! ($onusResult['success'] ?? false)) {
                Log::error("[gestionred:sync-huawei] OLT {$olt->id}: getOnusByOlt falló: " . ($onusResult['message'] ?? ''));
            } else {
                $onus      = $onusResult['onus'] ?? [];
                $onusCount = count($onus);
                $this->persistOnus($onus, $olt);
            }

            // ── Scan 2: señales ópticas ─────────────────────────────────────
            $t1          = microtime(true);
            $sigsResult  = $driver->getOnusSignals((string) $olt->id);
            $sigsDuration = round(microtime(true) - $t1, 1);

            if (! ($sigsResult['success'] ?? false)) {
                Log::error("[gestionred:sync-huawei] OLT {$olt->id}: getOnusSignals falló: " . ($sigsResult['message'] ?? ''));
            } else {
                $sigs      = $sigsResult['response'] ?? [];
                $sigsCount = count($sigs);
                $this->persistSignals($sigs, $olt);
            }
        } catch (Throwable $e) {
            Log::error("[gestionred:sync-huawei] OLT {$olt->id}: excepción: {$e->getMessage()}");
        } finally {
            try { $driver->closeSession(); } catch (Throwable) {}
            $lock->release();

            $totalDuration = round(microtime(true) - $startedAt, 1);
            Log::info("[gestionred:sync-huawei] OLT {$olt->id}: scan completado.", [
                'duration_s'     => $totalDuration,
                'onus_scan_s'    => $onusDuration,
                'signals_scan_s' => $sigsDuration,
                'onus_count'     => $onusCount,
                'signals_count'  => $sigsCount,
            ]);
        }
    }

    private function persistOnus(array $onus, Olt $olt): void
    {
        if (empty($onus)) {
            return;
        }

        $now  = now();
        $rows = array_map(fn($o) => [
            'sn'                 => $o['sn'],
            'unique_external_id' => $o['unique_external_id'],
            'olt_id'             => $olt->id,
            'board'              => $o['board'],
            'port'               => $o['port'],
            'onu'                => $o['onu'],
            'status'             => $o['status'],
            'signal'             => $o['signal'],
            'signal_1310'        => $o['signal_1310'],
            'signal_1490'        => $o['signal_1490'],
            'updated_at'         => $now,
            'last_synced_at'     => $now,
        ], $onus);

        foreach (array_chunk($rows, 200) as $chunk) {
            OltOnu::upsert($chunk, ['unique_external_id'], [
                'sn', 'board', 'port', 'onu', 'status',
                'signal', 'signal_1310', 'signal_1490',
                'updated_at', 'last_synced_at',
            ]);
        }
    }

    private function persistSignals(array $signals, Olt $olt): void
    {
        if (empty($signals)) {
            return;
        }

        $now  = now();
        $rows = array_map(fn($s) => [
            'unique_external_id' => $s['unique_external_id'],
            'signal'             => $s['signal'],
            'signal_1310'        => $s['signal_1310'],
            'signal_1490'        => $s['signal_1490'],
            'updated_at'         => $now,
            'last_synced_at'     => $now,
        ], $signals);

        foreach (array_chunk($rows, 200) as $chunk) {
            OltOnu::upsert($chunk, ['unique_external_id'], [
                'signal', 'signal_1310', 'signal_1490',
                'updated_at', 'last_synced_at',
            ]);
        }
    }
}
