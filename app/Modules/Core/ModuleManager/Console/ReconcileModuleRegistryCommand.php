<?php

namespace App\Modules\Core\ModuleManager\Console;

use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliación de module_registry contra los module.json en disco (item #9990762).
 *
 * Un módulo puede llegar a prod con código, migraciones y permisos, pero sin fila en
 * module_registry — y como el sidebar dinámico y ModuleRegistry::getMenu() (Services\)
 * solo pintan módulos con active=true en esa tabla, queda invisible hasta que alguien
 * corre `module:lifecycle install <slug>` a mano (pasó con addon-mapa-red en prod,
 * 2026-09-11). Este comando cierra ese hueco: crea la fila faltante para cualquier
 * módulo con manifest en disco pero sin registro. Gemelo de `permissions:sync-roles
 * --manifests` para el hueco equivalente de permisos.
 *
 * Política de activación (decisión de Irving sobre el item):
 *  - Si el module.json declara `"active": true` explícito → nace ACTIVO.
 *  - Si no lo declara, o lo declara false → nace INACTIVO + se deja constancia en el log.
 *    Nunca se auto-activa a ciegas un módulo cuya intención declarada no es "true".
 *
 * NO destructivo: solo crea filas faltantes (firstOrCreate por slug vía exists()+create).
 * Nunca toca una fila ya existente (ni `active` ni ningún otro campo) — así no puede
 * desactivar/reactivar un módulo que un admin haya tocado a mano. Idempotente: correrlo
 * dos veces seguidas no cambia nada la segunda vez.
 *
 * Manifiestos con JSON inválido ya los descarta ModuleManagerService::manifests() (el
 * json_decode nulo se filtra ahí). Un manifest sin `slug`, o cualquier error inesperado
 * al procesar uno, se salta con una advertencia — no aborta el resto ni el paso de
 * deploy que invoca este comando (siempre sale con exit 0).
 */
class ReconcileModuleRegistryCommand extends Command
{
    protected $signature = 'modules:reconcile-registry
        {--dry-run : Solo reporta qué se registraría, sin escribir}';

    protected $description = 'Registra en module_registry los módulos con manifest en disco que aún no tienen fila (idempotente, no destructivo).';

    public function handle(ModuleManagerService $manager): int
    {
        $dryRun    = (bool) $this->option('dry-run');
        $manifests = $manager->manifests();

        $registered    = [];
        $skippedNoSlug = 0;
        $skippedError  = 0;
        $alreadyOk     = 0;

        foreach ($manifests as $manifest) {
            try {
                $slug = $manifest['slug'] ?? null;
                if (empty($slug)) {
                    $skippedNoSlug++;
                    Log::channel('stack')->warning('modules:reconcile-registry: module.json sin slug, se omite', [
                        'dir' => $manifest['_dir'] ?? null,
                    ]);
                    continue;
                }

                if (ModuleRegistry::where('slug', $slug)->exists()) {
                    $alreadyOk++;
                    continue;
                }

                $activeDeclared = ($manifest['active'] ?? false) === true;

                $row = [
                    'slug'              => $slug,
                    'name'              => $manifest['name'] ?? $slug,
                    'installed_version' => $manifest['version'] ?? '0.1.0',
                    'type'              => $manifest['type'] ?? 'addon',
                    'instance_role'     => $manifest['instance_role'] ?? null,
                    'active'            => $activeDeclared,
                    'installed_at'      => now(),
                ];

                if (! $dryRun) {
                    ModuleRegistry::create($row);
                }

                $registered[] = $row;

                $level = $activeDeclared ? 'info' : 'warning';
                Log::channel('stack')->{$level}('modules:reconcile-registry: módulo auto-registrado', [
                    'slug'   => $slug,
                    'active' => $activeDeclared,
                    'reason' => $activeDeclared
                        ? 'module.json declara active:true'
                        : 'module.json NO declara active:true — nace inactivo, requiere activación manual',
                ]);
            } catch (\Throwable $e) {
                $skippedError++;
                Log::channel('stack')->error('modules:reconcile-registry: error procesando manifest, se omite', [
                    'slug'  => $manifest['slug'] ?? null,
                    'dir'   => $manifest['_dir'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($registered && ! $dryRun) {
            \App\Modules\Core\ModuleManager\Services\ModuleRegistry::clearCache();
        }

        if ($registered) {
            $this->table(
                ['slug', 'active', 'type', 'version'],
                array_map(
                    fn ($r) => [$r['slug'], $r['active'] ? 'sí' : 'NO', $r['type'], $r['installed_version']],
                    $registered
                )
            );
        }

        $this->info(
            ($dryRun ? '[dry-run] ' : '') . 'Módulos registrados: ' . count($registered)
            . ' | ya estaban: ' . $alreadyOk
            . ' | sin slug (omitidos): ' . $skippedNoSlug
            . ' | con error (omitidos): ' . $skippedError
        );

        $inactivos = array_filter($registered, fn ($r) => ! $r['active']);
        if ($inactivos) {
            $this->warn(
                'Registrados INACTIVOS (module.json no declara active:true) — requieren activación manual: '
                . implode(', ', array_column($inactivos, 'slug'))
            );
        }

        return self::SUCCESS;
    }
}
