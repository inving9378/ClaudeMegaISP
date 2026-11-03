<?php

namespace App\Modules\Core\ModuleManager\Console;

use App\Modules\Core\ModuleManager\Services\ModuleSystemService;
use Illuminate\Console\Command;

/**
 * Corre el script de sistema de un add-on (module.json → "system").
 *
 *   php artisan module:system install addon-demo        # en terminal: pide la contraseña ahí
 *   php artisan module:system purge   addon-demo
 *   php artisan module:system install addon-x --lifecycle   # y luego instala el módulo
 *
 * --run lo usa la página de add-ons (ModuleSystemService::start): la salida va al log de
 * la corrida y la contraseña llega por su FIFO. Ver deploy/system/lib.sh.
 */
class ModuleSystemCommand extends Command
{
    protected $signature = 'module:system
        {action : install | purge}
        {slug : slug del módulo (ej. addon-whatsapp-agent)}
        {--run= : id de corrida creada por la página de add-ons}
        {--lifecycle : si install.sh termina bien, instalar también el módulo (tablas, permisos, menú)}
        {--force : con --lifecycle, instalar aunque falten dependencias de módulo}';

    protected $description = 'Instala o purga las dependencias de sistema que declara un add-on.';

    public function handle(ModuleSystemService $system): int
    {
        $action = (string) $this->argument('action');
        $slug   = (string) $this->argument('slug');
        $runId  = $this->option('run') ?: null;

        try {
            return $system->execute($slug, $action, $runId, (bool) $this->option('lifecycle'), (bool) $this->option('force'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }
    }
}
