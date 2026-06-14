<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Models\User;
use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reconcilia el estado del usuario (activo/bloqueado/inactivo) contra el estado
 * de provisión de su extensión VoIP. Idempotente.
 *
 * Usar cuando el Observer no pudo actuar (ej. cambios masivos por script,
 * restore de BD, o antes de que el Observer existiera).
 */
class ReconciliarEstadosCommand extends Command
{
    protected $signature = 'voip:reconciliar-estados {--dry-run : Muestra cambios sin aplicarlos}';
    protected $description = 'Reconcilia estado del usuario (activo/bloqueado/inactivo) con su extensión VoIP';

    private bool $dry;
    private int $fixes    = 0;
    private int $reportes = 0;

    public function handle(AsteriskProvisioningService $prov): int
    {
        $this->dry = (bool) $this->option('dry-run');

        $this->info($this->dry
            ? '🔍  Modo DRY-RUN — sin cambios en BD ni en Asterisk'
            : '🔧  Aplicando reconciliación de estados usuario ↔ extensión...'
        );
        $this->newLine();

        // Verificar canales activos antes de tocar nada
        $canalesActivos = $this->obtenerCanalesActivos();
        if ($canalesActivos === null) {
            $this->warn('  ⚠️   No se pudo verificar canales AMI — se asumirá sin canales activos.');
            $canalesActivos = [];
        }
        if (!empty($canalesActivos)) {
            $this->warn('  ℹ️   Canales activos: ' . implode(', ', $canalesActivos));
        }

        $extensiones = Extension::whereNotNull('user_id')->with('agente')->get();

        foreach ($extensiones as $ext) {
            $user = $ext->agente; // relación con User
            if (!$user) {
                $this->warn("  ⚠️   Ext {$ext->numero}: user_id={$ext->user_id} no existe en users.");
                continue;
            }

            $this->reconciliar($ext, $user, $canalesActivos, $prov);
        }

        $this->newLine();
        $this->info("Resumen: {$this->fixes} corrección(es) aplicada(s), {$this->reportes} para revisión manual.");

        if ($this->dry && $this->fixes > 0) {
            $this->warn('Modo dry-run: corre sin --dry-run para aplicar los cambios.');
        }

        return Command::SUCCESS;
    }

    private function reconciliar(Extension $ext, User $user, array $canalesActivos, AsteriskProvisioningService $prov): void
    {
        $estado      = $user->estado ?? 'activo';
        $provisionada = $ext->estaProvisionada();
        $ctxActual   = DB::connection('asterisk_rt')
            ->table('ps_endpoints')
            ->where('id', $ext->endpointId())
            ->value('context');

        // ── Usuario INACTIVO + extensión provisionada → desprovisionar ────────
        if ($estado === 'inactivo' && $provisionada) {
            // Verificar que no esté en llamada
            $endpointId = $ext->endpointId();
            foreach ($canalesActivos as $canal) {
                if (str_contains($canal, "PJSIP/{$ext->numero}") || str_contains($canal, $endpointId)) {
                    $this->warn("  ⏭️   Ext {$ext->numero} (user {$user->name}): EN LLAMADA — omitida. Revisar después.");
                    return;
                }
            }

            $this->warn("  🔧  Ext {$ext->numero} (user {$user->name}): usuario INACTIVO pero provisionada. → Desprovisionar.");
            $this->fixes++;
            if (!$this->dry) {
                $prov->desprovisionarExtension($ext);
                $this->info("       → Hecho.");
            }
            return;
        }

        // ── Usuario BLOQUEADO + contexto incorrecto → restringir ─────────────
        if ($estado === 'bloqueado' && $ctxActual === 'from-internal') {
            $this->warn("  🔒  Ext {$ext->numero} (user {$user->name}): usuario BLOQUEADO con context=from-internal. → Restringir.");
            $this->fixes++;
            if (!$this->dry) {
                $prov->restringirExtension($ext);
                $this->info("       → Hecho.");
            }
            return;
        }

        // ── Usuario ACTIVO + extensión desprovisionada → solo reportar ────────
        if ($estado === 'activo' && !$provisionada) {
            $this->warn("  📋  Ext {$ext->numero} (user {$user->name}): usuario ACTIVO pero sin provisionar. Revisar manualmente.");
            $this->reportes++;
            return;
        }

        // ── Sin desalineación ─────────────────────────────────────────────────
        $ctxDesc = $ctxActual ? "context={$ctxActual}" : 'no-provisioned';
        $this->line("  ✅  Ext {$ext->numero} (user {$user->name}): estado={$estado}, {$ctxDesc} — OK");
    }

    private function obtenerCanalesActivos(): ?array
    {
        try {
            $host = config('voip.ami_host', '127.0.0.1');
            $port = (int) config('voip.ami_port', 5038);
            $user = config('voip.ami_user', 'megaisp');
            $pass = config('voip.ami_pass', '');

            $sock = fsockopen($host, $port, $errno, $errstr, 5);
            if (!$sock) return null;

            fread($sock, 1024);
            fwrite($sock, "Action: Login\r\nUsername: {$user}\r\nSecret: {$pass}\r\n\r\n");
            fread($sock, 1024);
            fwrite($sock, "Action: CoreShowChannels\r\n\r\n");

            $buf = '';
            $start = time();
            while (!feof($sock) && (time() - $start) < 5) {
                $buf .= fread($sock, 4096);
                if (str_contains($buf, 'CoreShowChannelsComplete')) break;
            }
            fclose($sock);

            preg_match_all('/Channel: (PJSIP\/[^\r\n]+)/', $buf, $m);
            return $m[1] ?? [];
        } catch (\Throwable) {
            return null;
        }
    }
}
