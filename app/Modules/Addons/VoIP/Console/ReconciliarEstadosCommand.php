<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Models\Extension;
use App\Modules\Addons\VoIP\Services\LiberadorExtensiones;
use Illuminate\Console\Command;

/**
 * Reconcilia el estado del usuario contra su extensión VoIP. Idempotente; corre cada hora.
 *
 * Desde el 28-nov (pedido de Irving): la extensión de un usuario bloqueado, desactivado o que ya no
 * existe queda libre para asignársela a otra persona (LiberadorExtensiones). El observer de User lo hace
 * al momento; esto atrapa lo que se le haya escapado (Asterisk sin responder, cambios por script, restore
 * de la base, o lo de antes de que existiera esta regla). Una extensión en llamada se deja para la vuelta
 * siguiente.
 */
class ReconciliarEstadosCommand extends Command
{
    protected $signature = 'voip:reconciliar-estados {--dry-run : Muestra cambios sin aplicarlos}';
    protected $description = 'Libera las extensiones de usuarios bloqueados, inactivos o eliminados';

    private bool $dry;
    private int $fixes    = 0;
    private int $reportes = 0;

    public function handle(LiberadorExtensiones $liberador): int
    {
        $this->dry = (bool) $this->option('dry-run');
        // La instancia del comando se reusa en el mismo proceso (Artisan::call): los contadores, desde cero.
        $this->fixes = 0;
        $this->reportes = 0;

        $this->info($this->dry
            ? 'Modo DRY-RUN: sin cambios en la base ni en Asterisk'
            : 'Reconciliando estados usuario ↔ extensión…'
        );

        $extensiones = Extension::whereNotNull('user_id')->with('agente')->orderBy('numero')->get();
        $porLiberar = $extensiones->filter(fn (Extension $e) => ! $e->agente || ($e->agente->estado ?? 'activo') !== 'activo');

        // Solo se pregunta a Asterisk por llamadas en curso si hay algo que liberar.
        $canalesActivos = [];
        if ($porLiberar->isNotEmpty()) {
            $canalesActivos = $this->obtenerCanalesActivos();
            if ($canalesActivos === null) {
                $this->warn('  No se pudo ver las llamadas en curso (AMI): se asume que no hay.');
                $canalesActivos = [];
            }
        }

        foreach ($extensiones as $ext) {
            $user   = $ext->agente;
            $estado = $user ? ($user->estado ?? 'activo') : 'eliminado';

            if ($estado !== 'activo') {
                if ($this->enLlamada($ext, $canalesActivos)) {
                    $this->warn("  Ext {$ext->numero}: usuario {$estado}, pero está en llamada. Se libera en la siguiente vuelta.");
                    continue;
                }
                $quien = $user ? trim("{$user->name} {$user->father_last_name}") : "usuario #{$ext->user_id}";
                $this->warn("  Ext {$ext->numero} ({$quien}): usuario {$estado} → se libera.");
                $this->fixes++;
                if (! $this->dry) {
                    try {
                        $liberador->liberar($ext, "usuario {$estado} (reconciliación)");
                        $this->info('       → Libre.');
                    } catch (\Throwable $e) {
                        $this->error("       → No se pudo: {$e->getMessage()}");
                    }
                }
                continue;
            }

            if (! $ext->estaProvisionada()) {
                $this->line("  Ext {$ext->numero} ({$user->name}): usuario activo pero sin dar de alta en Asterisk. Revisar a mano.");
                $this->reportes++;
            }
        }

        $this->info('Resumen: ' . $this->fixes . ($this->dry ? ' por liberar' : ' liberada(s)') . ", {$this->reportes} para revisar a mano.");
        if ($this->dry && $this->fixes > 0) {
            $this->warn('Modo dry-run: corre sin --dry-run para aplicar los cambios.');
        }

        return Command::SUCCESS;
    }

    private function enLlamada(Extension $ext, array $canalesActivos): bool
    {
        foreach ($canalesActivos as $canal) {
            if (str_starts_with($canal, "PJSIP/{$ext->endpointId()}-")) {
                return true;
            }
        }

        return false;
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
