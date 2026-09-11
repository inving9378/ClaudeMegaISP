<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Services\ProvisionadorAsterisk;
use Illuminate\Console\Command;

/**
 * Punto de entrada de la provisión. Lo invoca el proceso de actualización de
 * MegaISP, que ya corre con privilegios en el servidor.
 */
class ProvisionarAsteriskCommand extends Command
{
    protected $signature = 'voip:provisionar
                            {--descubrimiento : Primera vez: no exige esquema_realtime, lo reporta}
                            {--nueva : Empieza una corrida limpia en vez de retomar la anterior}
                            {--estado= : SOLO muestra el estado de una ejecución por UUID; no provisiona}
                            {--desinstalar : DESTRUCTIVO. Borra Asterisk por completo para probar la provisión de cero}
                            {--confirmar= : Obligatorio con --desinstalar. Debe ser SI-BORRAR-ASTERISK}';

    protected $description = 'Instala y configura Asterisk según config/requisitos-voip.php.';

    public function handle(): int
    {
        if ($uuid = $this->option('estado')) {
            return $this->mostrarEstado($uuid);
        }

        if ($this->option('desinstalar')) {
            return $this->desinstalar();
        }

        $descubrimiento = (bool) $this->option('descubrimiento');

        if ($descubrimiento) {
            $this->warn('MODO DESCUBRIMIENTO: se aceptará la revisión de Alembic que resulte y se reportará.');
            $this->warn('No usar en la instalación de un cliente: ahí el manifiesto viene completo.');
        }

        $p = new ProvisionadorAsterisk(
            $descubrimiento,
            fn (string $m) => $this->line($m),
            (bool) $this->option('nueva')
        );
        $r = $p->ejecutar();

        $this->newLine();
        $this->table(['paso', 'estado', 'versión', 'error'],
            collect($r['pasos'])->map(fn ($d, $paso) => [
                $paso, $d['estado'], $d['version'], \Illuminate\Support\Str::limit($d['error'] ?? '', 46),
            ])->values()->all());

        $this->newLine();

        if (! $r['ok']) {
            $this->error($r['mensaje']);
            $this->newLine();
            $this->line('  Reintentar (retoma esta misma corrida, sin repetir lo ya hecho):');
            $this->line('      php artisan voip:provisionar'
                . ($descubrimiento ? ' --descubrimiento' : ''));
            $this->newLine();
            $this->line('  Ver el estado sin provisionar nada:');
            $this->line("      php artisan voip:provisionar --estado={$r['uuid']}");

            return self::FAILURE;
        }

        $this->info($r['mensaje']);

        if ($descubrimiento && $r['revision']) {
            $this->newLine();
            $this->line('  ┌─────────────────────────────────────────────────────────────┐');
            $this->line('  │ FIJA ESTO EN config/requisitos-voip.php                     │');
            $this->line("  │   'esquema_realtime' => '{$r['revision']}',");
            $this->line('  │ A partir de ahí se valida contra él y una revisión distinta │');
            $this->line('  │ aborta en vez de aplicarse en silencio.                     │');
            $this->line('  └─────────────────────────────────────────────────────────────┘');
        }

        return self::SUCCESS;
    }

    /**
     * Borra Asterisk por completo, para poder probar la provisión de cero.
     *
     * ⚠️ DESTRUCTIVO. Existe por la prueba obligatoria del item #9990718: la única
     * forma de saber si el provisionador está completo es desinstalar y volver a
     * provisionar sin tocar el servidor a mano. Si en algún punto hay que
     * intervenir, ese punto es el trabajo que falta.
     *
     * ─── POR QUÉ VIVE AQUÍ Y NO SE INVOCA EL SCRIPT DIRECTO ──────────────────
     *
     * El script pide la confirmación por entorno, y `sudo` la vacía en el camino
     * (`env_reset`). Pasarla como argumento tampoco alcanza: un sudoers que
     * autoriza `bash <script>` NO autoriza `bash <script> --loquesea`, y `sudo -E`
     * exige la etiqueta SETENV, que es configuración de un servidor ajeno.
     *
     * Este comando, en cambio, ya corre con privilegios cuando lo invoca el
     * proceso de actualización, así que desde aquí el script se ejecuta sin sudo
     * de por medio y la confirmación llega entera.
     *
     * No añade exposición: quien puede correr artisan como root ya puede hacer
     * cualquier cosa en ese servidor. Y sin las DOS banderas explícitas esto es
     * código inerte — el proceso de actualización invoca `voip:provisionar` a
     * secas y nunca pasa por aquí.
     */
    private function desinstalar(): int
    {
        if ($this->option('confirmar') !== 'SI-BORRAR-ASTERISK') {
            $this->error('Esto borra Asterisk por completo: binario, configuración, base realtime,');
            $this->error('usuario de sistema y unit de systemd. No se puede deshacer.');
            $this->newLine();
            $this->line('  Si es lo que quieres:');
            $this->line('      php artisan voip:provisionar --desinstalar --confirmar=SI-BORRAR-ASTERISK');

            return self::FAILURE;
        }

        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            $this->error('Hay que ser root: se borran archivos de /usr, /etc y /var y una unit de systemd.');

            return self::FAILURE;
        }

        $script = base_path('app/Modules/Addons/VoIP/provisioning/desinstalar-asterisk.sh');

        if (! is_file($script)) {
            $this->error("No encuentro el desinstalador en {$script}.");

            return self::FAILURE;
        }

        $p = \Symfony\Component\Process\Process::fromShellCommandline(
            'bash ' . escapeshellarg($script) . ' --confirmar=SI-BORRAR-ASTERISK',
            base_path(), null, null, 600
        );
        $p->run(fn ($tipo, $buf) => $this->output->write($buf));

        return $p->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    private function mostrarEstado(string $uuid): int
    {
        $filas = \App\Modules\Addons\VoIP\Models\ProvisionEstado::deEjecucion($uuid)->orderBy('id')->get();

        if ($filas->isEmpty()) {
            $this->error("No hay registros para la ejecución {$uuid}.");

            return self::FAILURE;
        }

        $this->table(['paso', 'estado', 'versión', 'intentos', 'terminado'],
            $filas->map(fn ($f) => [$f->paso, $f->estado, $f->version_provisionador, $f->intentos, $f->terminado_at])->all());

        return self::SUCCESS;
    }
}
