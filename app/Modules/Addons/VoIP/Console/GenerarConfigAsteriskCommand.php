<?php

namespace App\Modules\Addons\VoIP\Console;

use App\Modules\Addons\VoIP\Services\AsteriskProvisioningService;
use App\Modules\Addons\VoIP\Services\GeneradorConfigAsterisk;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Regenera la configuración de Asterisk desde las plantillas SIN el resto de la
 * provisión. `voip:provisionar` también vuelve a publicar TODAS las extensiones; esto
 * solo escribe los .conf, para aplicar un cambio de plantilla (p. ej. el transporte
 * para teléfonos de fuera) en una central ya instalada.
 *
 * Escribe en /etc/asterisk, así que corre como root, igual que el instalador:
 *   sudo env LOG_CHANNEL=stderr CACHE_DRIVER=array php artisan voip:generar-config --solo=pjsip.conf
 *
 * Respeta la regla del generador: un archivo editado a mano no se pisa, queda la
 * propuesta al lado como .nuevo.
 */
class GenerarConfigAsteriskCommand extends Command
{
    protected $signature = 'voip:generar-config
                            {--solo=* : Solo estos archivos (p. ej. --solo=pjsip.conf); sin él, todos}
                            {--sin-recargar : No pedirle a Asterisk que relea la configuración}';

    protected $description = 'Regenera la configuración de Asterisk desde las plantillas, sin re-provisionar extensiones.';

    public function handle(GeneradorConfigAsterisk $generador): int
    {
        if (function_exists('posix_geteuid') && posix_geteuid() !== 0) {
            $this->error('Hay que correrlo como root (escribe en /etc/asterisk):');
            $this->line('  sudo env LOG_CHANNEL=stderr CACHE_DRIVER=array php artisan voip:generar-config --solo=pjsip.conf');

            return self::FAILURE;
        }

        $solo = $this->option('solo') ?: null;
        $r = $generador->generar(null, $solo);

        $this->line('Escritos:    ' . ($r['escritos'] ? implode(', ', $r['escritos']) : '—'));
        $this->line('Sin cambio:  ' . ($r['sin_cambio'] ? implode(', ', $r['sin_cambio']) : '—'));
        if ($r['propuestos_nuevo']) {
            $this->warn('NO se aplicaron (alguien los editó a mano): ' . implode(', ', $r['propuestos_nuevo']));
            $this->line('  La propuesta quedó al lado como .nuevo. Revisa con: diff /etc/asterisk/<archivo> /etc/asterisk/<archivo>.nuevo');
            $this->line('  y, si está bien, reemplázalo con mv y vuelve a correr este comando.');
        }

        if (! $this->option('sin-recargar') && $r['escritos']) {
            if (in_array('pjsip.conf', $r['escritos'], true)) {
                $this->line('pjsip reload: ' . trim($this->asterisk('pjsip reload')));
            }
            $otros = array_diff($r['escritos'], ['pjsip.conf']);
            if ($otros) {
                $this->warn('Para aplicar ' . implode(', ', $otros) . ': asterisk -rx "core reload" (o reinicia Asterisk).');
            }
        }

        if (trim((string) config('voip.asterisk.bind_externo', '')) !== '') {
            $transportes = $this->asterisk('pjsip show transports');
            if (str_contains($transportes, AsteriskProvisioningService::TRANSPORTE_EXTERNO)) {
                $this->info('Transporte para teléfonos de fuera activo: ' . config('voip.asterisk.bind_externo'));
            } else {
                $this->warn('Asterisk todavía no tiene ' . AsteriskProvisioningService::TRANSPORTE_EXTERNO
                    . '. Un transporte nuevo a veces solo se levanta al reiniciar: systemctl restart asterisk');
            }
        }

        return self::SUCCESS;
    }

    private function asterisk(string $comando): string
    {
        $p = new Process(['asterisk', '-rx', $comando]);
        $p->setTimeout(30);
        $p->run();

        return $p->getOutput() . $p->getErrorOutput();
    }
}
