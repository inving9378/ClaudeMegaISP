<?php

namespace App\Console\Commands\Olts;

use App\Services\OltDriver\Huawei\HuaweiDriver;
use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\Parsers\OntInfoParser;
use App\Services\OltDriver\Huawei\Parsers\OpticalInfoParser;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use App\Services\OltDriver\Huawei\TelnetSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * B1b-5 SP3 — Smoke-test manual de la conexión Telnet contra la MA5800-X7.
 *
 * Uso:
 *   php artisan olt:huawei:probe
 *   php artisan olt:huawei:probe 0/3/2:0
 *
 * Credenciales desde config/services.php → .env:
 *   OLT_HUAWEI_HOST, OLT_HUAWEI_PORT, OLT_HUAWEI_USER, OLT_HUAWEI_PASS
 *
 * SP4 (disparo contra OLT real) está bloqueado hasta B1c + OK explícito de Irving.
 */
class HuaweiProbe extends Command
{
    protected $signature = 'olt:huawei:probe
                            {onu? : ONU en formato F/S/P:ONT (default: 0/3/2:0)}';

    protected $description = 'Sonda de conexión Telnet contra Huawei MA5800-X7 — muestra ont info + optical-info de la ONU indicada';

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

        // Parseo ONU: "0/3/2:0" → frame/slot="0/3", port=2, ont-id=0
        $onuStr = $this->argument('onu') ?? '0/3/2:0';
        [$frameSlot, $ontId] = $this->parseOnu($onuStr);
        [$frame, $slot, $port] = $this->parseFrameSlotPort($frameSlot, $onuStr);

        $this->info("=== olt:huawei:probe ===");
        $this->line("  Host  : {$host}:{$cfg['port']}");
        $this->line("  ONU   : {$onuStr}  →  interface gpon {$frameSlot}  port={$port}  ont-id={$ontId}");

        $logger  = Log::channel('daily');
        $session = new TelnetSession($cfg);
        $guard   = new ReadOnlyGuard($logger);
        $transport = new HuaweiTransport(
            config:  $cfg,
            session: $session,
            guard:   $guard,
            logger:  $logger,
        );
        $driver = new HuaweiDriver(
            transport: $transport,
            config:    array_merge($cfg, ['olt_id' => 'probe', 'frame' => $frame]),
            logger:    $logger,
        );

        $this->line('');
        $this->line('Abriendo sesión Telnet...');

        try {
            $transport->open();
        } catch (\Throwable $e) {
            $this->error("No se pudo conectar: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info('Sesión abierta. Ejecutando comandos...');

        try {
            $ontInfo     = $this->fetchOntInfo($transport, $frameSlot, $slot, $port, $ontId);
            $opticalInfo = $this->fetchOpticalInfo($transport, $frameSlot, $slot, $port, $ontId);
            $versionRaw  = $this->fetchVersion($transport);
        } catch (\Throwable $e) {
            $this->error("Error durante la consulta: {$e->getMessage()}");
            $transport->close();
            return self::FAILURE;
        }

        $transport->close();

        // ── Mostrar resultados ────────────────────────────────────────────────
        $this->line('');
        $this->info('── ONT INFO ──────────────────────────────────────────────────');
        if (empty($ontInfo)) {
            $this->warn('  Sin datos (ONU no encontrada o parser pendiente de validación).');
        } else {
            foreach ($ontInfo as $onu) {
                $this->table(
                    ['Campo', 'Valor'],
                    $this->flattenForTable($onu),
                );
            }
        }

        $this->line('');
        $this->info('── OPTICAL INFO ──────────────────────────────────────────────');
        if (empty($opticalInfo)) {
            $this->warn('  Sin datos (ONU no encontrada o parser pendiente de validación).');
        } else {
            foreach ($opticalInfo as $opt) {
                $this->table(
                    ['Campo', 'Valor'],
                    $this->flattenForTable($opt),
                );
            }
            // Mostrar valores clave
            $o = $opticalInfo[0];
            $rxOnu = $o['rx_onu'] ?? null;
            $rxOlt = $o['rx_olt'] ?? null;
            $this->line('');
            $this->info(sprintf('  Rx ONU (downstream, 1490 nm) = %s dBm  [esperado: ~-8.54]',
                $rxOnu !== null ? $rxOnu : '(null)'));
            $this->info(sprintf('  Rx OLT (upstream,   1310 nm) = %s dBm  [esperado: ~-13.04]',
                $rxOlt !== null ? $rxOlt : '(null)'));
        }

        $this->line('');
        $this->info('── VERSION (raw) ─────────────────────────────────────────────');
        $this->line($versionRaw ?? '(sin output)');

        $this->line('');
        $this->info('=== probe completado ===');
        return self::SUCCESS;
    }

    private function fetchOntInfo(HuaweiTransport $transport, string $frameSlot, int $slot, int $port, int $ontId): array
    {
        $transport->enterGponInterface((int) explode('/', $frameSlot)[0], $slot);
        $raw = $transport->exec("display ont info {$port} {$ontId}");
        $transport->leaveToUserView();
        return OntInfoParser::parse($raw);
    }

    private function fetchOpticalInfo(HuaweiTransport $transport, string $frameSlot, int $slot, int $port, int $ontId): array
    {
        $transport->enterGponInterface((int) explode('/', $frameSlot)[0], $slot);
        $raw = $transport->exec("display ont optical-info {$port} {$ontId}");
        $transport->leaveToUserView();
        return OpticalInfoParser::parse($raw);
    }

    /** Captura output crudo de "display version" en user-view (sin parser — fixture para B1c). */
    private function fetchVersion(HuaweiTransport $transport): string
    {
        return $transport->exec('display version');
    }

    /** "0/3/2:0" → ["0/3", 0] */
    private function parseOnu(string $onuStr): array
    {
        $parts  = preg_split('/[\/:]/', $onuStr);
        $frame  = $parts[0] ?? '0';
        $slot   = $parts[1] ?? '3';
        $ontId  = (int) ($parts[3] ?? 0);
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
