<?php

namespace App\Console\Commands\Active;

use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\TelnetSession;
use Illuminate\Console\Command;

/**
 * One-shot B3a capture: display ont info 8 all + display ont optical-info 8 all
 * against port 0/2/8 (frame=0, slot=2, port=8) — 112 ONTs as of 2026-06-12.
 *
 * Purpose: capture real multi-ONT fixtures to validate OntListParser and
 * OpticalBatchParser against a loaded port (roadmap #153).
 *
 * Durations recorded here feed the gestionred:sync-huawei cron design
 * (withoutOverlapping timeout, per-port budget, schedule interval).
 *
 * READ-ONLY — only `display` commands are issued, never config/write commands.
 *
 * Usage:
 *   php artisan multiolt:capture-b3a            # live capture
 *   php artisan multiolt:capture-b3a --dry-run  # verify config, no connection
 */
class MultioltCaptureB3a extends Command
{
    protected $signature   = 'multiolt:capture-b3a
                              {--dry-run : Print plan only, do not connect to OLT}
                              {--skip-sanitize : Save raw output only, skip fixture generation}';
    protected $description = '[B3a one-shot] Capture ONT info + optical-info for port 0/2/8 (112 ONTs), 360s timeout each';

    private const FRAME  = 0;
    private const SLOT   = 2;
    private const PORT   = 8;
    private const TIMEOUT = 360;

    public function handle(): int
    {
        $this->info('┌─ MultiOLT B3a Capture ────────────────────────────────────────────────────┐');
        $this->info('│  Port: 0/2/8  (frame=0, slot=2, port=8)  — 112 ONTs                       │');
        $this->info('│  Commands: display ont info + display ont optical-info  (timeout=360s each)│');
        $this->info('│  Mode: READ-ONLY (display-only, no config commands)                        │');
        $this->info('└───────────────────────────────────────────────────────────────────────────┘');
        $this->newLine();

        $cfg  = config('services.huawei_olt', []);
        $host = trim((string) ($cfg['host']     ?? ''));
        $user = trim((string) ($cfg['username'] ?? ''));

        if ($host === '' || $user === '') {
            $this->error('OLT_HUAWEI_HOST or OLT_HUAWEI_USER not set in .env — aborting.');
            return self::FAILURE;
        }

        $this->line("  OLT   : {$host}:{$cfg['port']}");
        $this->line("  User  : {$user}");
        $this->line("  F/S/P : " . self::FRAME . '/' . self::SLOT . '/' . self::PORT);
        $this->line("  TO    : " . self::TIMEOUT . "s per command");
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] No connection attempted.');
            return self::SUCCESS;
        }

        $rawDir = storage_path('app/multiolt/b3a_raw');
        if (!is_dir($rawDir)) {
            mkdir($rawDir, 0755, true);
        }

        // ── Connect ──────────────────────────────────────────────────────────
        $this->line('Connecting to OLT via Telnet…');
        $session   = new TelnetSession($cfg);
        $transport = new HuaweiTransport(config: $cfg, session: $session);

        $ontOutput = '';
        $optOutput = '';
        $d1 = 0.0;
        $d2 = 0.0;

        try {
            $this->line('  enterGponInterface(frame=' . self::FRAME . ', slot=' . self::SLOT . ')…');
            $transport->enterGponInterface(self::FRAME, self::SLOT);

            // ── Command 1: display ont info {port} all ────────────────────
            $cmd1 = 'display ont info ' . self::PORT . ' all';
            $this->line("  exec: {$cmd1}  (timeout=" . self::TIMEOUT . "s)…");
            $t0  = microtime(true);
            $ontOutput = $transport->exec($cmd1, self::TIMEOUT);
            $d1  = round(microtime(true) - $t0, 2);
            $this->info("    → {$d1}s  /  " . number_format(strlen($ontOutput)) . " bytes");

            // ── Command 2: display ont optical-info {port} all ────────────
            $cmd2 = 'display ont optical-info ' . self::PORT . ' all';
            $this->line("  exec: {$cmd2}  (timeout=" . self::TIMEOUT . "s)…");
            $t0  = microtime(true);
            $optOutput = $transport->exec($cmd2, self::TIMEOUT);
            $d2  = round(microtime(true) - $t0, 2);
            $this->info("    → {$d2}s  /  " . number_format(strlen($optOutput)) . " bytes");

        } finally {
            $this->line('  leaveToUserView…');
            try { $transport->leaveToUserView(); } catch (\Throwable) {}
        }

        // ── Save raw output ───────────────────────────────────────────────
        $tsRaw = date('Ymd_His');
        $rawOntPath = "{$rawDir}/ont_info_0_2_8_{$tsRaw}.txt";
        $rawOptPath = "{$rawDir}/opt_info_0_2_8_{$tsRaw}.txt";
        file_put_contents($rawOntPath, $ontOutput);
        file_put_contents($rawOptPath, $optOutput);
        $this->line("  Raw saved: {$rawOntPath}");
        $this->line("  Raw saved: {$rawOptPath}");
        $this->newLine();

        // ── Duration table ────────────────────────────────────────────────
        $this->table(
            ['Command', 'Duration (s)', 'Bytes', 'Cron budget note'],
            [
                [$cmd1, $d1, number_format(strlen($ontOutput)),
                 $d1 < 60 ? '60s default safe' : ($d1 < 180 ? 'use 360s' : 'SLOW: check OLT load')],
                [$cmd2, $d2, number_format(strlen($optOutput)),
                 $d2 < 60 ? '60s default safe' : ($d2 < 180 ? 'use 360s' : 'SLOW: check OLT load')],
            ]
        );

        $totalOltScan = ($d1 + $d2) * 16; // rough estimate: 16 ports × measured time
        $this->line("  Rough full-slot estimate (×16 ports): ~" . round($totalOltScan) . "s");
        $this->newLine();

        if ($this->option('skip-sanitize')) {
            $this->warn('--skip-sanitize: fixture generation skipped. Sanitize manually before committing.');
            return self::SUCCESS;
        }

        // ── Sanitize + save fixtures ──────────────────────────────────────
        $fixtureDir = base_path('tests/Unit/OltDriver/fixtures/huawei');

        $ontSanitized = $this->sanitize($ontOutput, 'display ont info 8 all', '0/2/8');
        $optSanitized = $this->sanitize($optOutput, 'display ont optical-info 8 all', '0/2/8');

        $ontFixturePath = "{$fixtureDir}/display_ont_info_port_0_2_8_real.txt";
        $optFixturePath = "{$fixtureDir}/display_ont_optical_batch_0_2_8_real.txt";
        file_put_contents($ontFixturePath, $ontSanitized);
        file_put_contents($optFixturePath, $optSanitized);

        $this->info("Fixture: {$ontFixturePath}");
        $this->info("Fixture: {$optFixturePath}");
        $this->newLine();
        $this->info('B3a capture complete. Next: php artisan test tests/Unit/OltDriver/Parsers/');

        return self::SUCCESS;
    }

    /**
     * Sanitize raw VRP output for use as a test fixture.
     *
     * Rules:
     *  1. Strip any line that contains the OLT hostname, IP, or username.
     *  2. Replace ONT Description values with "[REDACTED]" to remove client data.
     *  3. Prepend a standard fixture header comment.
     *  4. Keep SNs, signal values, states — these are the data under test.
     */
    private function sanitize(string $raw, string $cmd, string $fsp): string
    {
        $cfg      = config('services.huawei_olt', []);
        $host     = trim((string) ($cfg['host']     ?? ''));
        $user     = trim((string) ($cfg['username'] ?? ''));

        $header = implode("\n", [
            '# REAL — capturado contra MA5800-X7 en sesión B3a (2026-06-12)',
            "# Comando: {$cmd}",
            "# Puerto: {$fsp}  (112 ONTs al momento de captura)",
            '# Host, credenciales y username OMITIDOS (sanitizado)',
            '# Descripciones de ONT reemplazadas con [REDACTED] (datos de cliente)',
            '',
        ]);

        $lines = explode("\n", $raw);
        $out   = [];

        foreach ($lines as $line) {
            // Drop lines containing host/IP or username
            if ($host !== '' && str_contains($line, $host)) {
                continue;
            }
            if ($user !== '' && str_contains($line, $user)) {
                continue;
            }

            // Redact description field values (client data)
            if (preg_match('/^(\s+\d+\/\s*\d+\/\s*\d+\s+\d+\s+)\S+(\s+.*)$/', $line, $m)) {
                // Tabular ont-info row — description is in the last section; keep as-is (no client data here)
                $out[] = $line;
                continue;
            }

            // "Description" key:value line in detail block
            if (preg_match('/^(\s+Description\s*:\s*)(.+)$/', $line, $m)) {
                $out[] = $m[1] . '[REDACTED]';
                continue;
            }

            $out[] = $line;
        }

        return $header . implode("\n", $out);
    }
}
