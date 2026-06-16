<?php

namespace App\Console\Commands\Olts;

use App\Services\OltDriver\Huawei\HuaweiDriver;
use App\Services\OltDriver\Huawei\HuaweiTransport;
use App\Services\OltDriver\Huawei\TelnetSession;
use Illuminate\Console\Command;

/**
 * W3 — Write supervisado de descripción sobre ONT PRUEBA (HWTCFEFCC9A2).
 *
 * Protocolo de seguridad (no modificar):
 *   - dry_run=false SOLO cuando se pasa --confirm. Sin --confirm → dry-run siempre.
 *   - Allow-list activa: ReadOnlyGuard::assertWriteTargetAllowed() se ejerce vía
 *     withWriteTarget() antes de cualquier byte de escritura.
 *   - NUNCA genera save (flash). El cambio queda solo en running-config (volátil).
 *   - Solo contra MA5800-X7 (config services.huawei_olt = OLT_HUAWEI_*).
 *
 * Uso:
 *   php artisan multiolt:w3-write --desc="PRUEBA-W3"            # dry-run
 *   php artisan multiolt:w3-write --desc="PRUEBA-W3" --confirm  # write real
 *   php artisan multiolt:w3-write --rollback                     # dry-run rollback
 *   php artisan multiolt:w3-write --rollback --confirm           # write real rollback
 */
class W3WriteCommand extends Command
{
    protected $signature = 'multiolt:w3-write
        {--desc=            : Descripción a escribir en el ONT PRUEBA}
        {--confirm          : Sin este flag corre en dry-run; con él envía a la OLT real}
        {--rollback         : Restaura la descripción original capturada en Fase 1}';

    protected $description = '[W3] Cambia/restaura la descripción del ONT PRUEBA (HWTCFEFCC9A2). Sin --confirm = dry-run.';

    // ONT PRUEBA — única SN allow-listada en ReadOnlyGuard::WRITE_ALLOW_LIST
    public const ONU_ID    = '1:0/3/2:0';
    public const TARGET_SN = 'HWTCFEFCC9A2';

    // Valor original capturado en el backup W3 Fase 1 (2026-06-16 16:57:15)
    // Referencia: docs/multiolt-w3-backups/w3_backup_20260616_165715.txt línea 30
    public const ROLLBACK_DESC = 'PRUEBA_zone_D1Z10_authd_20260610';

    // ─── handle ──────────────────────────────────────────────────────────────

    public function handle(): int
    {
        $isConfirm  = (bool) $this->option('confirm');
        $isRollback = (bool) $this->option('rollback');
        $desc       = $isRollback
            ? self::ROLLBACK_DESC
            : (string) ($this->option('desc') ?? '');

        if (! $isRollback && $desc === '') {
            $this->error('Especifica --desc=<texto> o usa --rollback para restaurar el valor original.');
            return self::FAILURE;
        }

        $cfg = $this->oltConfig();

        if (empty($cfg['host']) || empty($cfg['username']) || empty($cfg['password'])) {
            $this->error(
                'Credenciales incompletas. Revisa OLT_HUAWEI_HOST / OLT_HUAWEI_USER / OLT_HUAWEI_PASS en .env.'
            );
            return self::FAILURE;
        }

        $this->line('');
        $this->line('  OLT  : ' . ($cfg['host'] ?? '?') . ':' . ($cfg['port'] ?? 23));
        $this->line('  ONT  : ' . self::ONU_ID . '  (SN: ' . self::TARGET_SN . ')');
        $this->line('  Desc : ' . ($desc !== '' ? $desc : '(vaciar campo)'));
        $this->line('  Modo : ' . (
            $isConfirm
                ? '<fg=red;options=bold>ESCRITURA REAL — dry_run=false</>'
                : '<fg=yellow>DRY-RUN (pasa --confirm para escribir)</>'
        ));
        $this->line('');

        if (! $isConfirm) {
            return $this->runDryRun($cfg, $desc);
        }

        return $this->runLiveWrite($cfg, $desc);
    }

    // ─── Rutas de ejecución ──────────────────────────────────────────────────

    private function runDryRun(array $cfg, string $desc): int
    {
        $driver = $this->makeDriver($cfg, dryRun: true);
        $result = $driver->descOnt(self::ONU_ID, $desc);

        if (! $result['success']) {
            $this->error('Dry-run fallido: ' . ($result['message'] ?? '?'));
            return self::FAILURE;
        }

        $this->info('=== Secuencia VRP (dry-run — nada fue enviado) ===');
        foreach ($result['commands'] ?? [] as $cmd) {
            $this->line('    ' . $cmd);
        }
        $this->line('');
        $this->comment('Para ejecutar en la OLT real:');
        $this->comment('  php artisan multiolt:w3-write --desc="' . $desc . '" --confirm');
        return self::SUCCESS;
    }

    private function runLiveWrite(array $cfg, string $desc): int
    {
        // 1 — pre-read (dry_run=true, solo lectura)
        $this->info('[1/3] Leyendo descripción actual antes del write...');
        $before = $this->readDesc($cfg);
        $this->line('  Descripción actual : ' . ($before ?? '(no disponible)'));
        $this->line('');

        // 2 — write real (dry_run=false)
        $this->info('[2/3] Ejecutando write en OLT real...');
        $driver = $this->makeDriver($cfg, dryRun: false);
        $result = $driver->descOnt(self::ONU_ID, $desc);

        if (! $result['success']) {
            $this->error('Write fallido: ' . ($result['message'] ?? '?'));
            if (! empty($result['aborted_at'])) {
                $this->error('  Abortado en : ' . $result['aborted_at']);
            }
            if (! empty($result['response'])) {
                $this->error('  Resp OLT    : ' . $result['response']);
            }
            return self::FAILURE;
        }

        $this->info('  Transcript:');
        foreach ($result['transcript'] ?? [] as $step) {
            $this->line('    [cmd] ' . $step['cmd']);
            $resp = trim($step['response'] ?? '');
            if ($resp !== '') {
                $this->line('    [rsp] ' . $resp);
            }
        }
        $this->line('');

        // 3 — post-read (breve pausa para que VRP finalice, luego verificación)
        sleep(2);
        $this->info('[3/3] Verificando descripción después del write...');
        $after = $this->readDesc($cfg);
        $this->line('  Descripción leída  : ' . ($after ?? '(no disponible)'));
        $this->line('  Descripción esperada: ' . $desc);
        $this->line('');

        if ($after !== null && $after === $desc) {
            $this->info('VERIFICADO correctamente.');
        } elseif ($after !== null) {
            $this->warn('ADVERTENCIA: el valor leído no coincide con el esperado.');
            $this->warn('  Leído   : ' . $after);
            $this->warn('  Esperado: ' . $desc);
        } else {
            $this->warn('No se pudo leer la descripción post-write para verificar.');
        }

        return self::SUCCESS;
    }

    // ─── Helpers internos (protected → overrideables en tests) ──────────────

    /**
     * Credenciales OLT de la config verificada en Fase 1.
     * Variables: OLT_HUAWEI_HOST / OLT_HUAWEI_PORT / OLT_HUAWEI_USER / OLT_HUAWEI_PASS
     */
    protected function oltConfig(): array
    {
        return config('services.huawei_olt', []);
    }

    /**
     * Instancia un HuaweiDriver listo para usar.
     * dry_run=false SOLO cuando el caller lo solicita explícitamente (--confirm).
     */
    protected function makeDriver(array $cfg, bool $dryRun): HuaweiDriver
    {
        $session   = new TelnetSession($cfg);
        $transport = new HuaweiTransport($cfg, $session);

        return new HuaweiDriver(
            transport: $transport,
            config:    array_merge($cfg, [
                'dry_run' => $dryRun,
                'olt_id'  => 'ma5800-x7',
            ]),
        );
    }

    /**
     * Lee la descripción actual del ONT PRUEBA vía display ont info (read-only).
     *
     * @return string|null — null si la lectura falla o la descripción no está disponible
     */
    private function readDesc(array $cfg): ?string
    {
        $driver = $this->makeDriver($cfg, dryRun: true);
        $result = $driver->getOnuDetails(self::ONU_ID);

        return ($result['success'] ?? false)
            ? ($result['onu_details']['description'] ?? null)
            : null;
    }
}
