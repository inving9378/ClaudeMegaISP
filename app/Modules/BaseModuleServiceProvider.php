<?php

namespace App\Modules;

use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Illuminate\Support\ServiceProvider;

abstract class BaseModuleServiceProvider extends ServiceProvider
{
    /**
     * Each concrete module provider must set these.
     * - $moduleSlug:   kebab-case unique id, matches module.json "slug"
     * - $moduleType:   "core" | "addon"
     * - $viewNamespace: namespace used for view('<ns>::name') — defaults to slug
     */
    protected string $moduleSlug = '';
    protected string $moduleType = 'addon';
    protected ?string $viewNamespace = null;

    public function register(): void
    {
        $this->mergeManifest();
    }

    public function boot(): void
    {
        if (! $this->moduleIsActive()) {
            return;
        }

        $dir = $this->moduleDir();

        $routes = $dir . '/routes.php';
        if (file_exists($routes)) {
            $this->loadRoutesFrom($routes);
        }

        $views = $dir . '/views';
        if (is_dir($views)) {
            $this->loadViewsFrom($views, $this->viewNamespace ?? $this->moduleSlug);
        }

        $migrations = $dir . '/migrations';
        if (is_dir($migrations)) {
            $this->loadMigrationsFrom($migrations);
        }
    }

    protected function moduleDir(): string
    {
        $ref = new \ReflectionClass(static::class);
        return dirname($ref->getFileName());
    }

    protected function manifestPath(): string
    {
        return $this->moduleDir() . '/module.json';
    }

    protected function mergeManifest(): void
    {
        $path = $this->manifestPath();
        if (! file_exists($path)) {
            return;
        }
        $data = json_decode(file_get_contents($path), true);
        if (! is_array($data)) {
            return;
        }
        if (empty($this->moduleSlug) && isset($data['slug'])) {
            $this->moduleSlug = $data['slug'];
        }
        if (isset($data['type'])) {
            $this->moduleType = $data['type'];
        }
    }

    protected function moduleIsActive(): bool
    {
        if (empty($this->moduleSlug)) {
            return true;
        }

        try {
            return ModuleManagerService::instance()->isActive($this->moduleSlug);
        } catch (\Throwable $e) {
            // Registry table not yet migrated, DB unavailable during console setup, etc.
            // Fail-open so the system keeps booting; module:list/seeder will reconcile state.
            return true;
        }
    }
}
