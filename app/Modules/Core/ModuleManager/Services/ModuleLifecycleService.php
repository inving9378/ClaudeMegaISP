<?php

namespace App\Modules\Core\ModuleManager\Services;

use App\Modules\Contracts\ModuleDefinition;
use App\Modules\Core\ModuleManager\Models\ModuleMigration;
use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ModuleLifecycleService
{
    // ── Consultas previas (sin efectos) ─────────────────────────────────────

    /**
     * Devuelve cuántos roles y usuarios se verían afectados si se desinstalara
     * este módulo (pierden los permisos del módulo).
     */
    public function previewUninstall(string $slug): array
    {
        $manifest  = $this->requireManifest($slug);
        $permNames = $this->permissionNamesFromManifest($manifest);

        if (empty($permNames)) {
            return ['roles' => 0, 'users' => 0, 'permission_count' => 0, 'permissions' => []];
        }

        $perms = Permission::whereIn('name', $permNames)->get();
        $permIds = $perms->pluck('id');

        $rolesAffected = Role::whereHas('permissions', fn ($q) => $q->whereIn('id', $permIds))->count();
        $usersAffected = DB::table('model_has_permissions')
            ->whereIn('permission_id', $permIds)
            ->distinct('model_id')
            ->count('model_id');

        return [
            'roles'            => $rolesAffected,
            'users'            => $usersAffected,
            'permission_count' => $perms->count(),
            'permissions'      => $permNames,
        ];
    }

    // ── Instalación ──────────────────────────────────────────────────────────

    /**
     * Instala un módulo addon.
     *
     * @throws \RuntimeException si las dependencias no se cumplen (y force=false)
     * @throws \LogicException   si el módulo es core o ya está instalado
     */
    public function install(string $slug, bool $force = false): array
    {
        $manifest = $this->requireManifest($slug);

        if (($manifest['type'] ?? 'addon') === 'core') {
            throw new \LogicException("El módulo '{$slug}' es core y se gestiona automáticamente.");
        }

        $registry = ModuleRegistry::where('slug', $slug)->first();
        if ($registry && $registry->active) {
            throw new \LogicException("El módulo '{$slug}' ya está instalado y activo.");
        }

        // Validar dependencias
        $depErrors = $this->checkDependencies($manifest);
        if (! empty($depErrors) && ! $force) {
            throw new \RuntimeException(
                "Dependencias no cumplidas:\n" . implode("\n", $depErrors)
            );
        }

        $moduleDir = $this->moduleDir($slug);

        // Migraciones fuera de transacción (DDL)
        $this->runMigrations($slug, $moduleDir . '/migrations');

        // DML en transacción: permisos, registro, hook, bitácora
        DB::transaction(function () use ($slug, $manifest, $registry) {
            $this->registerPermissions($manifest);

            $version = $manifest['version'] ?? '0.1.0';
            if ($registry) {
                $registry->update(['active' => true, 'installed_version' => $version, 'installed_at' => now()]);
            } else {
                ModuleRegistry::create([
                    'slug'              => $slug,
                    'name'              => $manifest['name'] ?? $slug,
                    'installed_version' => $version,
                    'type'              => $manifest['type'] ?? 'addon',
                    'active'            => true,
                    'installed_at'      => now(),
                ]);
            }

            $this->callHook($slug, 'install');
            $this->auditLog('install', $slug, ['version' => $version]);
        });

        \App\Modules\Core\ModuleManager\Services\ModuleRegistry::clearCache();

        return ['success' => true, 'slug' => $slug, 'action' => 'installed'];
    }

    // ── Actualización ────────────────────────────────────────────────────────

    /**
     * Actualiza un módulo ya instalado a la versión del manifiesto actual.
     * Solo corre las migraciones delta (las no registradas en module_migrations).
     */
    public function upgrade(string $slug): array
    {
        $manifest = $this->requireManifest($slug);
        $registry = ModuleRegistry::where('slug', $slug)->firstOrFail();

        $fromVersion = $registry->installed_version ?? '0.0.0';
        $toVersion   = $manifest['version'] ?? '0.1.0';

        if (version_compare($fromVersion, $toVersion, '>=')) {
            return [
                'success' => true,
                'slug'    => $slug,
                'action'  => 'already_up_to_date',
                'version' => $toVersion,
            ];
        }

        $moduleDir = $this->moduleDir($slug);

        // Migraciones delta fuera de transacción (DDL)
        $this->runMigrations($slug, $moduleDir . '/migrations');

        // DML en transacción
        DB::transaction(function () use ($slug, $manifest, $registry, $fromVersion, $toVersion) {
            $this->registerPermissions($manifest);
            $registry->update(['installed_version' => $toVersion]);
            $this->callHook($slug, 'upgrade', [$fromVersion, $toVersion]);
            $this->auditLog('upgrade', $slug, ['from' => $fromVersion, 'to' => $toVersion]);
        });

        \App\Modules\Core\ModuleManager\Services\ModuleRegistry::clearCache();

        return [
            'success'      => true,
            'slug'         => $slug,
            'action'       => 'upgraded',
            'from_version' => $fromVersion,
            'to_version'   => $toVersion,
        ];
    }

    // ── Desinstalación ───────────────────────────────────────────────────────

    /**
     * Desinstala un módulo.
     *
     * @param bool $keepData  Si true, no hace rollback de migraciones (conserva tablas y datos).
     * @throws \LogicException si el módulo es core, o si otros módulos dependen de él.
     */
    public function uninstall(string $slug, bool $keepData = false): array
    {
        $manifest = $this->requireManifest($slug);

        if (($manifest['type'] ?? 'addon') === 'core') {
            throw new \LogicException("Los módulos core no se pueden desinstalar.");
        }

        // Verificar dependientes activos
        $dependents = $this->activeDependents($slug);
        if (! empty($dependents)) {
            throw new \LogicException(
                "No se puede desinstalar '{$slug}'. Los siguientes módulos activos dependen de él: "
                . implode(', ', $dependents)
            );
        }

        // Preview para bitácora
        $preview = $this->previewUninstall($slug);

        $moduleDir = $this->moduleDir($slug);

        // Hook del módulo antes de borrar (fuera de transacción: puede hacer DDL propio)
        $this->callHook($slug, 'uninstall', [$keepData]);

        // Rollback de migraciones PRIMERO y fuera de transacción: DDL en MySQL
        // causa commit implícito y rompería cualquier transacción abierta.
        if (! $keepData) {
            $this->rollbackMigrations($slug, $moduleDir . '/migrations');
        }

        // Solo DML en transacción: permisos, registro, bitácora
        DB::transaction(function () use ($slug, $manifest, $keepData, $preview) {
            // Con keep_data:true se conservan permisos y sus asignaciones —
            // solo se desactiva el módulo en el registry.
            // Con keep_data:false (desinstalación definitiva) se retiran todo.
            if (! $keepData) {
                $this->removePermissions($manifest);
            }

            // Marcar como inactivo (no borramos el registro para conservar historial)
            ModuleRegistry::where('slug', $slug)->update(['active' => false]);

            $this->auditLog('uninstall', $slug, [
                'keep_data'      => $keepData,
                'roles_affected' => $keepData ? 0 : $preview['roles'],
                'users_affected' => $keepData ? 0 : $preview['users'],
            ]);
        });

        \App\Modules\Core\ModuleManager\Services\ModuleRegistry::clearCache();

        return [
            'success'        => true,
            'slug'           => $slug,
            'action'         => 'uninstalled',
            'keep_data'      => $keepData,
            'roles_affected' => $keepData ? 0 : $preview['roles'],
            'users_affected' => $keepData ? 0 : $preview['users'],
        ];
    }

    // ── Migraciones ──────────────────────────────────────────────────────────

    private function runMigrations(string $slug, string $migrationsDir): void
    {
        if (! is_dir($migrationsDir)) {
            return;
        }

        // Archivos .php en el directorio (excluyendo .gitkeep)
        $files = array_filter(
            glob($migrationsDir . '/*.php') ?: [],
            fn ($f) => basename($f) !== '.gitkeep'
        );

        if (empty($files)) {
            return;
        }

        // Migrations ya registradas para este módulo
        $alreadyRan = ModuleMigration::where('module_slug', $slug)
            ->pluck('migration')
            ->toArray();

        // Registrar las nuevas que ya están en la tabla migrations de Laravel
        // (pudo haberlas corrido BaseModuleServiceProvider al boot)
        $laravelRan = DB::table('migrations')->pluck('migration')->toArray();

        $relativePath = str_replace(base_path() . '/', '', $migrationsDir);

        // Antes de migrate, capturar estado actual de la tabla migrations
        $beforeMigrations = DB::table('migrations')->pluck('migration')->toArray();

        Artisan::call('migrate', [
            '--path'  => $relativePath,
            '--force' => true,
        ]);

        $afterMigrations = DB::table('migrations')->pluck('migration')->toArray();
        $newlyRan        = array_diff($afterMigrations, $beforeMigrations);

        foreach ($newlyRan as $migration) {
            if (! in_array($migration, $alreadyRan, true)) {
                ModuleMigration::create([
                    'module_slug' => $slug,
                    'migration'   => $migration,
                ]);
            }
        }

        // Registrar también las que ya habían corrido (vía boot del provider) pero no
        // estaban en module_migrations
        foreach ($files as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            if (
                in_array($migrationName, $laravelRan, true)
                && ! in_array($migrationName, $alreadyRan, true)
                && ! in_array($migrationName, $newlyRan, true)
            ) {
                ModuleMigration::create([
                    'module_slug' => $slug,
                    'migration'   => $migrationName,
                ]);
            }
        }
    }

    private function rollbackMigrations(string $slug, string $migrationsDir): void
    {
        if (! is_dir($migrationsDir)) {
            return;
        }

        $moduleMigrations = ModuleMigration::where('module_slug', $slug)
            ->orderByDesc('id')
            ->pluck('migration')
            ->toArray();

        if (empty($moduleMigrations)) {
            return;
        }

        foreach ($moduleMigrations as $migrationName) {
            $file = $migrationsDir . '/' . $migrationName . '.php';
            if (! file_exists($file)) {
                // Intentar sin subdirectorio (a veces el nombre no tiene path)
                $matches = glob($migrationsDir . '/**/' . $migrationName . '.php');
                $file    = ! empty($matches) ? $matches[0] : null;
            }

            if ($file && file_exists($file)) {
                try {
                    $migration = require $file;
                    $migration->down();
                    DB::table('migrations')->where('migration', $migrationName)->delete();
                } catch (\Throwable $e) {
                    Log::error("ModuleLifecycle: error rolling back {$migrationName}: {$e->getMessage()}");
                }
            }

            ModuleMigration::where('module_slug', $slug)
                ->where('migration', $migrationName)
                ->delete();
        }
    }

    // ── Permisos Spatie ──────────────────────────────────────────────────────

    private function registerPermissions(array $manifest): void
    {
        $permissions = $manifest['permissions'] ?? [];
        $syncService = app(PermissionSyncService::class);

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['name' => $perm['name'], 'guard_name' => 'web'],
                ['description' => $perm['description'] ?? '']
            );

            // Auto-sync al rol base: super-administrator + DESARROLLADOR siempre;
            // permisos .view también a todos los demás roles (decisión 2026-06-04).
            $syncService->syncPermissionToBaseRoles($perm['name']);
        }
    }

    private function removePermissions(array $manifest): void
    {
        $permissions = $manifest['permissions'] ?? [];
        if (empty($permissions)) {
            return;
        }

        $names = array_column($permissions, 'name');
        $perms = Permission::whereIn('name', $names)->get();

        foreach ($perms as $perm) {
            // Retirar asignaciones directas (model_has_permissions)
            DB::table('model_has_permissions')->where('permission_id', $perm->id)->delete();
            // Retirar de roles (role_has_permissions)
            DB::table('role_has_permissions')->where('permission_id', $perm->id)->delete();
            $perm->delete();
        }

        // Limpiar caché de Spatie
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function permissionNamesFromManifest(array $manifest): array
    {
        return array_column($manifest['permissions'] ?? [], 'name');
    }

    // ── Validación de dependencias ───────────────────────────────────────────

    /**
     * Verifica que las dependencias declaradas en el manifiesto estén instaladas
     * y en versión suficiente. Devuelve array de errores (vacío = ok).
     */
    private function checkDependencies(array $manifest): array
    {
        $errors = [];
        foreach ($manifest['dependencies'] ?? [] as $dep) {
            $depSlug    = $dep['slug'] ?? null;
            $minVersion = $dep['min_version'] ?? '0.0.0';

            if (! $depSlug) {
                continue;
            }

            $installed = ModuleRegistry::where('slug', $depSlug)->where('active', true)->first();

            if (! $installed) {
                $errors[] = "Falta el módulo '{$depSlug}' (requerido: >= {$minVersion}).";
                continue;
            }

            if (version_compare($installed->installed_version ?? '0.0.0', $minVersion, '<')) {
                $errors[] = "El módulo '{$depSlug}' está en v{$installed->installed_version} pero se requiere >= {$minVersion}.";
            }
        }

        return $errors;
    }

    /**
     * Devuelve slugs de módulos activos que declaran dependencia de $slug.
     */
    private function activeDependents(string $slug): array
    {
        $dependents = [];
        $manifests  = app(ModuleManagerService::class)->manifests();
        $activeSlugs = ModuleRegistry::where('active', true)->pluck('slug')->toArray();

        foreach ($manifests as $manifest) {
            $mSlug = $manifest['slug'] ?? null;
            if ($mSlug === $slug || ! in_array($mSlug, $activeSlugs, true)) {
                continue;
            }
            foreach ($manifest['dependencies'] ?? [] as $dep) {
                if (($dep['slug'] ?? null) === $slug) {
                    $dependents[] = $mSlug;
                    break;
                }
            }
        }

        return $dependents;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function requireManifest(string $slug): array
    {
        foreach (app(ModuleManagerService::class)->manifests() as $manifest) {
            if (($manifest['slug'] ?? null) === $slug) {
                return $manifest;
            }
        }
        throw new \InvalidArgumentException("Módulo '{$slug}' no encontrado.");
    }

    private function moduleDir(string $slug): string
    {
        foreach (app(ModuleManagerService::class)->manifests() as $manifest) {
            if (($manifest['slug'] ?? null) === $slug) {
                return $manifest['_dir'];
            }
        }
        throw new \InvalidArgumentException("Módulo '{$slug}' no encontrado.");
    }

    private function callHook(string $slug, string $method, array $args = []): void
    {
        $dir         = $this->moduleDir($slug);
        $defFile     = $dir . '/ModuleDefinition.php';
        if (! file_exists($defFile)) {
            return;
        }

        // Intentar resolver por namespace convencional
        $rel   = str_replace([app_path() . '/', '.php'], ['App/', ''], $dir . '/ModuleDefinition');
        $class = str_replace('/', '\\', $rel);

        if (class_exists($class)) {
            $instance = new $class();
            if (method_exists($instance, $method)) {
                $instance->{$method}(...$args);
            }
        }
    }

    private function auditLog(string $action, string $slug, array $extra = []): void
    {
        Log::channel('stack')->info("ModuleLifecycle:{$action}", array_merge(['slug' => $slug], $extra));
    }
}
