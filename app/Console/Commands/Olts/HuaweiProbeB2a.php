<?php

namespace App\Console\Commands\Olts;

use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\ReadOnlyGuard;
use App\Services\OltDriver\Huawei\TelnetSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * B2a — Sesión única de captura de fixtures contra Huawei MA5800-X7.
 *
 * Captura los outputs necesarios para desarrollar los parsers de B2b (offline).
 * NO parsea — solo guarda el output crudo sanitizado como archivos de fixture.
 *
 * Batería:
 *   1. display board 0               (desde config-view)
 *   2. display ont autofind all      (desde config-view)
 *   3. display ont info by-sn <SN>   (desde config-view)
 *   4. display ont info 0/3/2 all    (desde config-view, valida sintaxis user-view con barras)
 *   5. display ont info 2 all        (desde interface gpon 0/3 — puede ser LARGO)
 *   6. display ont optical-info 2 all (desde interface gpon 0/3)
 *
 * Seguridad: 100% READ-ONLY. ReadOnlyGuard activo. Sin reintentos.
 * Credenciales: .env (OLT_HUAWEI_HOST / OLT_HUAWEI_USER / OLT_HUAWEI_PASS)
 */
class HuaweiProbeB2a extends Command
{
    protected $signature = 'olt:huawei:probe-b2a
                            {--frame=0 : Frame de la OLT (default: 0)}
                            {--slot=3  : Slot GPON a sondear (default: 3)}
                            {--port=2  : Puerto del slot (default: 2)}
                            {--sn=HWTCFEFCC9A2 : SN de la ONU autorizada para by-sn}
                            {--out-dir= : Directorio de salida para fixtures (default: tests/Unit/OltDriver/fixtures/huawei/)}
                            {--raw     : Mostrar output crudo de cada comando en consola}';

    protected $description = 'B2a — Captura fixtures reales contra Huawei MA5800-X7 (READ-ONLY)';

    public function handle(): int
    {
        $cfg = config('services.huawei_olt', []);

        $host = trim((string) ($cfg['host']     ?? ''));
        $user = trim((string) ($cfg['username'] ?? ''));
        $pass = (string)       ($cfg['password'] ?? '');

        if ($host === '' || $user === '' || $pass === '') {
            $this->error('Credenciales incompletas. Revisar .env: OLT_HUAWEI_HOST / OLT_HUAWEI_USER / OLT_HUAWEI_PASS');
            return self::FAILURE;
        }

        $frame  = (int) $this->option('frame');
        $slot   = (int) $this->option('slot');
        $port   = (int) $this->option('port');
        $sn     = strtoupper(trim((string) $this->option('sn')));
        $outDir = $this->option('out-dir')
            ?: base_path('tests/Unit/OltDriver/fixtures/huawei');

        $rawLogPath = storage_path('logs/olt-probe-b2a-' . date('Ymd_His') . '.log');

        $this->info("=== olt:huawei:probe-b2a ===");
        $this->line("  Host    : {$host}:{$cfg['port']}");
        $this->line("  Frame/Slot/Port : {$frame}/{$slot}/{$port}");
        $this->line("  SN      : {$sn}");
        $this->line("  Out dir : {$outDir}");
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

        $this->info('Sesión abierta. Ejecutando batería B2a...');

        $captures = [];

        try {
            // ── Config-view batch ────────────────────────────────────────────────
            $transport->enterConfigView();
            $this->line("  [nav] config-view ✓");

            $captures['display_board_real'] = $this->capture(
                $transport, 'display board 0',
                'display_board_real.txt'
            );

            $captures['display_autofind_real'] = $this->capture(
                $transport, 'display ont autofind all',
                'display_autofind_real.txt'
            );

            $captures['display_ont_info_by_sn_real'] = $this->capture(
                $transport, "display ont info by-sn {$sn}",
                'display_ont_info_by_sn_real.txt'
            );

            // Validate user-view slash syntax (used in HuaweiDriver::collectAllOnts)
            $captures['display_ont_info_uv_real'] = $this->capture(
                $transport, "display ont info {$frame}/{$slot}/{$port} all",
                'display_ont_info_uv_real.txt'
            );

            // ── Interface-view batch ─────────────────────────────────────────────
            $transport->enterGponInterface($frame, $slot);
            $this->line("  [nav] interface gpon {$frame}/{$slot} ✓");

            // These can be long — generous 180-second timeout
            $captures['display_ont_info_port_real'] = $this->capture(
                $transport, "display ont info {$port} all",
                'display_ont_info_port_real.txt',
                timeout: 180
            );

            $captures['display_ont_optical_batch_real'] = $this->capture(
                $transport, "display ont optical-info {$port} all",
                'display_ont_optical_batch_real.txt',
                timeout: 180
            );

            $transport->leaveToUserView();
            $this->line("  [nav] user-view ✓");

        } catch (\Throwable $e) {
            $this->error("Error durante la captura: {$e->getMessage()}");
            $transport->close();
            file_put_contents($rawLogPath, $session->getRawLog());
            $this->warn("Raw log: {$rawLogPath}");
            return self::FAILURE;
        }

        $transport->close();
        file_put_contents($rawLogPath, $session->getRawLog());
        $this->line("  [raw-log] guardado: {$rawLogPath}");

        // ── Guardar fixtures ─────────────────────────────────────────────────────
        $this->line('');
        $this->info('── Fixtures guardados ────────────────────────────────────────');

        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        foreach ($captures as $label => [$filename, $content]) {
            $path = rtrim($outDir, '/') . '/' . $filename;
            file_put_contents($path, $content);
            $lines = substr_count($content, "\n");
            $this->line("  {$filename}  ({$lines} líneas)");

            if ($this->option('raw')) {
                $this->line('--- RAW ---');
                $this->line($content);
                $this->line('----------');
            }
        }

        $this->line('');
        $this->info('=== B2a captura completada ===');
        $this->line('Siguiente: commitear fixtures y arrancar B2b (parsers offline).');
        return self::SUCCESS;
    }

    /**
     * Execute a command, prepend a sanitized header, return [filename, content].
     */
    private function capture(
        HuaweiTransport $transport,
        string $command,
        string $filename,
        int $timeout = 60
    ): array {
        $this->line("  [cmd] {$command} ...");
        $raw    = $transport->exec($command, timeout: $timeout);
        $header = "# REAL — capturado contra MA5800-X7 en sesión B2a (" . date('Y-m-d') . ")\n"
                . "# Comando: {$command}\n"
                . "# Host, credenciales y username OMITIDOS (sanitizado)\n\n";
        $this->line("       → " . substr_count($raw, "\n") . " líneas");
        return [$filename, $header . $raw];
    }
}
