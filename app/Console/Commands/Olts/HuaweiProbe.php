<?php

namespace App\Console\Commands\Olts;

use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\Parsers\OntInfoParser;
use App\Services\OltDriver\Huawei\Parsers\OpticalInfoParser;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use App\Services\OltDriver\Huawei\TelnetSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * B1c — Sesión única de validación Telnet contra Huawei MA5800-X7.
 *
 * Diseño de la sesión:
 *   login → enable → config → interface gpon {frame}/{slot}
 *   → display ont info {port} {ont-id}
 *   → display ont optical-info {port} {ont-id}
 *   → quit (×3) → user-view
 *   → display version
 *   → cierre limpio
 *
 * Plan B (automático): si interface-context falla, intenta
 *   display ont info by-sn <SN> desde config-view.
 *
 * Log raw: storage/logs/olt-probe-raw-<timestamp>.log (byte-level de toda la sesión).
 *
 * Credenciales desde .env: OLT_HUAWEI_HOST/PORT/USER/PASS
 */
class HuaweiProbe extends Command
{
    protected $signature = 'olt:huawei:probe
                            {onu? : ONU en formato F/S/P:ONT (default: 0/3/2:0)}
                            {--raw : Mostrar output crudo de cada comando además del parseado}';

    protected $description = 'Sonda de validación Telnet contra Huawei MA5800-X7 (B1c)';

    public function handle(): int
    {
        $cfg = config('services.huawei_olt', []);

        $host = trim((string) ($cfg['host']     ?? ''));
        $user = trim((string) ($cfg['username'] ?? ''));
        $pass = (string)       ($cfg['password'] ?? '');

        if ($host === '' || $user === '' || $pass === '') {
            $this->error('Credenciales incompletas. Revisar .env:');
            $this->line('  OLT_HUAWEI_HOST=<ip>');
            $this->line('  OLT_HUAWEI_USER=<usuario>');
            $this->line('  OLT_HUAWEI_PASS=<contraseña>');
            return self::FAILURE;
        }

        $onuStr = $this->argument('onu') ?? '0/3/2:0';
        [$frameSlot, $ontId] = $this->parseOnu($onuStr);
        [$frame, $slot, $port] = $this->parseFrameSlotPort($frameSlot, $onuStr);

        $rawLogPath = storage_path('logs/olt-probe-raw-' . date('Ymd_His') . '.log');

        $this->info("=== olt:huawei:probe ===");
        $this->line("  Host    : {$host}:{$cfg['port']}");
        $this->line("  ONU     : {$onuStr}  →  interface gpon {$frameSlot}  port={$port}  ont-id={$ontId}");
        $this->line("  Raw log : {$rawLogPath}");

        $logger  = Log::channel('daily');
        $session = new TelnetSession($cfg);
        $session->enableRawLog();

        $guard     = new ReadOnlyGuard($logger);
        $transport = new HuaweiTransport(
            config:  $cfg,
            session: $session,
            guard:   $guard,
            logger:  $logger,
        );

        $this->line('');
        $this->line('Abriendo sesión Telnet...');

        try {
            $transport->open();
        } catch (\Throwable $e) {
            $this->error("No se pudo conectar: {$e->getMessage()}");
            file_put_contents($rawLogPath, $session->getRawLog());
            return self::FAILURE;
        }

        $this->info('Sesión abierta. Ejecutando batería...');

        $rawOntInfo  = '';
        $rawOptical  = '';
        $rawVersion  = '';
        $planBUsed   = false;

        try {
            // ── Navegar: enable → config → interface gpon {frame}/{slot} ──────
            $transport->enterGponInterface($frame, $slot);
            $this->line("  [nav] interface gpon {$frameSlot} ✓");

            // ── display ont info {port} {ont-id} (interface-context) ───────────
            $rawOntInfo = $transport->exec("display ont info {$port} {$ontId}");
            $ontInfo    = OntInfoParser::parse($rawOntInfo, $logger);

            // ── Plan B: si no parseó, intentar by-sn desde config ─────────────
            if (empty($ontInfo)) {
                $this->warn("  [plan-B] interface-context vacío — intentando by-sn desde config");
                $transport->leaveToUserView();  // a user-view
                // re-navegar a config (enterGponInterface va user→enable→config→interface,
                // pero necesitamos quedarnos en config para by-sn)
                $transport->enterGponInterface($frame, $slot);
                $transport->leaveToUserView();   // volver a user-view para no quedar en interface
                // navegar solo hasta config manualmente no está expuesto;
                // usamos exec desde user-view con sintaxis user-view:
                $rawOntInfo = $transport->exec("display ont info by-sn HWTCFEFCC9A2");
                $ontInfo    = OntInfoParser::parse($rawOntInfo, $logger);
                $planBUsed  = true;
            }

            // ── display ont optical-info {port} {ont-id} (re-entrar interface) ─
            $transport->enterGponInterface($frame, $slot);
            $rawOptical  = $transport->exec("display ont optical-info {$port} {$ontId}");
            $opticalInfo = OpticalInfoParser::parse($rawOptical, $logger);
            $transport->leaveToUserView();

            // ── display version (user-view) ────────────────────────────────────
            $rawVersion = $transport->exec('display version');

        } catch (\Throwable $e) {
            $this->error("Error durante la consulta: {$e->getMessage()}");
            $transport->close();
            file_put_contents($rawLogPath, $session->getRawLog());
            $this->warn("Raw log guardado en: {$rawLogPath}");
            return self::FAILURE;
        }

        $transport->close();
        file_put_contents($rawLogPath, $session->getRawLog());
        $this->line("  [raw-log] guardado: {$rawLogPath}");

        // ── Resultados ────────────────────────────────────────────────────────
        $this->line('');
        $this->info('── ONT INFO' . ($planBUsed ? ' (by-sn)' : ' (interface-context)') . ' ─────────────────────');
        if ($this->option('raw')) {
            $this->line('[RAW] >>> ' . $rawOntInfo . ' <<<');
        }
        if (empty($ontInfo)) {
            $this->warn('  Sin datos — ver raw log.');
        } else {
            foreach ($ontInfo as $onu) {
                $this->table(['Campo', 'Valor'], $this->flattenForTable($onu));
            }
        }

        $this->line('');
        $this->info('── OPTICAL INFO ──────────────────────────────────────────────');
        if ($this->option('raw')) {
            $this->line('[RAW] >>> ' . $rawOptical . ' <<<');
        }
        if (empty($opticalInfo)) {
            $this->warn('  Sin datos — ver raw log.');
        } else {
            foreach ($opticalInfo as $opt) {
                $this->table(['Campo', 'Valor'], $this->flattenForTable($opt));
            }
            $rxOnu = $opticalInfo[0]['rx_onu'] ?? null;
            $rxOlt = $opticalInfo[0]['rx_olt'] ?? null;
            $this->line('');
            $this->info(sprintf('  Rx ONU (downstream 1490 nm) = %s dBm  [esperado ≈ -8.54]',
                $rxOnu !== null ? $rxOnu : '(null)'));
            $this->info(sprintf('  Rx OLT (upstream   1310 nm) = %s dBm  [esperado ≈ -13.04]',
                $rxOlt !== null ? $rxOlt : '(null)'));
        }

        $this->line('');
        $this->info('── VERSION (raw) ─────────────────────────────────────────────');
        $this->line($rawVersion ?: '(vacío)');

        $this->line('');
        $this->info('=== probe completado ===');
        return self::SUCCESS;
    }

    /** "0/3/2:0" → ["0/3", 0] */
    private function parseOnu(string $onuStr): array
    {
        $parts = preg_split('/[\/:]/', $onuStr);
        $frame = $parts[0] ?? '0';
        $slot  = $parts[1] ?? '3';
        $ontId = (int) ($parts[3] ?? 0);
        return ["{$frame}/{$slot}", $ontId];
    }

    /** "0/3" + original "0/3/2:0" → [frame=0, slot=3, port=2] */
    private function parseFrameSlotPort(string $frameSlot, string $onuStr): array
    {
        [$frame, $slot] = explode('/', $frameSlot);
        $parts = preg_split('/[\/:]/', $onuStr);
        $port  = (int) ($parts[2] ?? 0);
        return [(int) $frame, (int) $slot, $port];
    }

    private function flattenForTable(array $data): array
    {
        $rows = [];
        foreach ($data as $k => $v) {
            $rows[] = [$k, is_null($v) ? '(null)' : (string) $v];
        }
        return $rows;
    }
}
