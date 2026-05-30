<?php

namespace App\Modules\Core\ModuleManager\Console;

use App\Modules\Core\ModuleManager\Models\ModuleMigration;
use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use App\Modules\Core\ModuleManager\Services\ModuleLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class ModuleLifecycleCommand extends Command
{
    protected $signature = 'module:lifecycle
        {action : install|upgrade|uninstall|preview-uninstall}
        {slug   : Slug del módulo (ej. addon-demo)}
        {--force         : Forzar instalación ignorando errores de dependencias}
        {--keep-data     : Al desinstalar, conservar las tablas de datos}';

    protected $description = 'Gestiona el ciclo de vida de un módulo (install/upgrade/uninstall).';

    public function handle(ModuleLifecycleService $lifecycle): int
    {
        $action = $this->argument('action');
        $slug   = $this->argument('slug');

        try {
            switch ($action) {
                case 'install':
                    $result = $lifecycle->install($slug, (bool) $this->option('force'));
                    break;

                case 'upgrade':
                    $result = $lifecycle->upgrade($slug);
                    break;

                case 'uninstall':
                    $preview = $lifecycle->previewUninstall($slug);
                    if ($preview['roles'] > 0 || $preview['users'] > 0) {
                        $this->warn("ADVERTENCIA: Desinstalar '{$slug}' afectará:");
                        $this->line("  • {$preview['roles']} rol(es)");
                        $this->line("  • {$preview['users']} usuario(s)");
                        $this->line("  • Permisos: " . implode(', ', $preview['permissions']));
                        if (! $this->confirm('¿Continuar?', false)) {
                            $this->info('Desinstalación cancelada.');
                            return self::SUCCESS;
                        }
                    }
                    $keepData = (bool) $this->option('keep-data');
                    $result   = $lifecycle->uninstall($slug, $keepData);
                    break;

                case 'preview-uninstall':
                    $preview = $lifecycle->previewUninstall($slug);
                    $this->info("Preview de desinstalación para '{$slug}':");
                    $this->table(
                        ['Campo', 'Valor'],
                        [
                            ['Permisos afectados', $preview['permission_count']],
                            ['Roles afectados',    $preview['roles']],
                            ['Usuarios afectados', $preview['users']],
                        ]
                    );
                    if (! empty($preview['permissions'])) {
                        $this->line('Permisos: ' . implode(', ', $preview['permissions']));
                    }
                    return self::SUCCESS;

                default:
                    $this->error("Acción desconocida: {$action}. Usa install|upgrade|uninstall|preview-uninstall.");
                    return self::FAILURE;
            }

            $this->info("✓ {$result['action']}: {$slug}");
            foreach ($result as $k => $v) {
                if (in_array($k, ['success', 'slug', 'action'], true)) continue;
                $this->line("  {$k}: " . (is_bool($v) ? ($v ? 'true' : 'false') : $v));
            }

            // Estado post-acción
            $reg = ModuleRegistry::where('slug', $slug)->first();
            if ($reg) {
                $this->line("  registry.active: " . ($reg->active ? 'true' : 'false'));
                $this->line("  registry.installed_version: " . ($reg->installed_version ?? 'null'));
            }

            $migsCount = ModuleMigration::where('module_slug', $slug)->count();
            $this->line("  module_migrations registradas: {$migsCount}");

            if (Schema::hasTable('demo_items') && $slug === 'addon-demo') {
                $this->line("  demo_items count: " . \DB::table('demo_items')->count());
            }

        } catch (\Throwable $e) {
            $this->error("Error: " . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
