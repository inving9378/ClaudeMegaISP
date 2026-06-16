<?php

namespace App\Console\Commands\Active;

use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use App\Services\OltDriver\Huawei\TelnetSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * W0 — Captura de configuración de provisión contra MA5800-X7 (READ-ONLY).
 *
 * Deriba la secuencia REAL de comandos necesaria para proveer un ONT
 * leyendo la config de un ONT ya provisionado y en línea (HWTCFEFCC9A2).
 *
 * Batería:
 *   1. display ont lineprofile all          — perfiles de línea disponibles
 *   2. display ont srvprofile all           — perfiles de servicio disponibles
 *   3. display dba-profile all              — perfiles DBA (T-CONTs)
 *   4. display ont lineprofile by-lineprofile-id 1   — detalle SMARTOLT_FLEXIBLE_GPON
 *   5. display ont srvprofile by-srvprofile-id 2     — detalle HG8145V5
 *   6. display service-port port 0/3/2 ont 0 — service-port del ONT de prueba
 *   7. display vlan all                     — VLANs configuradas en la OLT
 *
 * Todo desde config-view. READ-ONLY — solo comandos display.
 * Credenciales: .env (OLT_HUAWEI_HOST / OLT_HUAWEI_USER / OLT_HUAWEI_PASS)
 */
class MultioltCaptureW0 extends Command
{
    protected $signature = 'multiolt:capture-w0
                            {--frame=0   : Frame de la OLT (default: 0)}
                            {--slot=3    : Slot del ONT de referencia (default: 3)}
                            {--port=2    : Puerto del ONT de referencia (default: 2)}
                            {--ont-id=0  : ONT-ID del ONT de referencia (default: 0)}
                            {--line-profile=1 : Line profile ID a inspeccionar (default: 1)}
                            {--srv-profile=2  : Service profile ID a inspeccionar (default: 2)}
                            {--dry-run   : Mostrar plan sin conectar a la OLT}
                            {--raw       : Mostrar output crudo en consola}';

    protected $description = '[W0 one-shot] Captura config de provisión de ONT de prueba (READ-ONLY)';

    public function handle(): int
    {
        $cfg  = config('services.huawei_olt', []);
        $host = trim((string) ($cfg['host']     ?? ''));
        $user = trim((string) ($cfg['username'] ?? ''));
        $pass = (string) ($cfg['password']       ?? '');

        if ($host === '' || $user === '' || $pass === '') {
            $this->error('Credenciales incompletas. Revisar .env: OLT_HUAWEI_HOST / OLT_HUAWEI_USER / OLT_HUAWEI_PASS');
            return self::FAILURE;
        }

        $frame      = (int) $this->option('frame');
        $slot       = (int) $this->option('slot');
        $port       = (int) $this->option('port');
        $ontId      = (int) $this->option('ont-id');
        $lineProf   = (int) $this->option('line-profile');
        $srvProf    = (int) $this->option('srv-profile');

        $this->info('┌─ MultiOLT W0 Capture ────────────────────────────────────────────────────┐');
        $this->info('│  ONT referencia: 0/3/2:0 (SN=HWTCFEFCC9A2) — en línea y provisionado     │');
        $this->info('│  Objetivo: derivar secuencia REAL de comandos de provisión                │');
        $this->info('│  Modo: READ-ONLY — solo comandos display                                  │');
        $this->info('└───────────────────────────────────────────────────────────────────────────┘');
        $this->newLine();

        $this->line("  OLT    : {$host}:{$cfg['port']}");
        $this->line("  F/S/P  : {$frame}/{$slot}/{$port}  ONT-ID: {$ontId}");
        $this->line("  Line profile: {$lineProf}  Srv profile: {$srvProf}");

        $commands = [
            'lineprofile_all'      => ['display ont lineprofile all',                     'display_lineprofile_all.txt',   60],
            'srvprofile_all'       => ['display ont srvprofile all',                      'display_srvprofile_all.txt',    60],
            'dba_profile_all'      => ['display dba-profile all',                         'display_dba_profile_all_w0.txt',60],
            'lineprofile_detail'   => ["display ont lineprofile by-lineprofile-id {$lineProf}", "display_lineprofile_{$lineProf}.txt", 60],
            'srvprofile_detail'    => ["display ont srvprofile by-srvprofile-id {$srvProf}",    "display_srvprofile_{$srvProf}.txt",  60],
            'service_port_ont'     => ["display service-port port {$frame}/{$slot}/{$port} ont {$ontId}", 'display_serviceport_0_3_2_ont0.txt', 60],
            'vlan_all'             => ['display vlan all',                                'display_vlan_all.txt',          60],
        ];

        $this->newLine();
        $this->line('Comandos a ejecutar (todos en config-view):');
        foreach ($commands as $key => [$cmd]) {
            $this->line("  • {$cmd}");
        }
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] Sin conexión. Plan verificado.');
            return self::SUCCESS;
        }

        $outDir = base_path('docs/multiolt-w0-fixtures');
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $logger    = Log::channel('daily');
        $session   = new TelnetSession($cfg);
        $guard     = new ReadOnlyGuard($logger);
        $transport = new HuaweiTransport(
            config:  $cfg,
            session: $session,
            guard:   $guard,
            logger:  $logger,
        );

        $this->line('Abriendo sesión Telnet...');
        try {
            $transport->open();
        } catch (\Throwable $e) {
            $this->error("No se pudo conectar: {$e->getMessage()}");
            return self::FAILURE;
        }
        $this->info('Sesión abierta. Entrando a config-view...');

        $captures = [];
        try {
            $transport->enterConfigView();
            $this->line('  [nav] config-view ✓');

            foreach ($commands as $key => [$cmd, $filename, $timeout]) {
                $this->line("  [cmd] {$cmd} ...");
                $t0    = microtime(true);
                $raw   = $transport->exec($cmd, $timeout);
                $delta = round(microtime(true) - $t0, 2);
                $lines = substr_count($raw, "\n");
                $this->info("       → {$delta}s  /  {$lines} líneas");

                $captures[$key] = [$filename, $cmd, $raw];

                if ($this->option('raw')) {
                    $this->line('--- RAW ---');
                    $this->line($raw);
                    $this->line('----------');
                }
            }

            $transport->leaveToUserView();
            $this->line('  [nav] user-view ✓');

        } catch (\Throwable $e) {
            $this->error("Error durante captura: {$e->getMessage()}");
        } finally {
            try { $transport->close(); } catch (\Throwable) {}
        }

        $this->newLine();
        $this->info('── Guardando fixtures ────────────────────────────────────────');

        $ts = date('Y-m-d');
        foreach ($captures as $key => [$filename, $cmd, $raw]) {
            $header = "# W0 — capturado contra MA5800-X7 ({$ts})\n"
                    . "# Comando: {$cmd}\n"
                    . "# Host y credenciales OMITIDOS (sanitizado)\n\n";

            $path = "{$outDir}/{$filename}";
            file_put_contents($path, $header . $raw);
            $this->line("  docs/multiolt-w0-fixtures/{$filename}");
        }

        $this->newLine();
        $this->info('=== W0 captura completada ===');
        $this->line('Siguiente: revisar fixtures y redactar docs/MULTIOLT_PROVISION_HUAWEI.md');
        return self::SUCCESS;
    }
}
