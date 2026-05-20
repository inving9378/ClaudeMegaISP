<?php

namespace App\Modules\Core\ModuleManager\Services;

use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use Illuminate\Support\Facades\Schema;

class ModuleManagerService
{
    private static ?self $singleton = null;

    /** In-memory cache for the duration of one request. */
    private ?array $activeMap = null;

    public static function instance(): self
    {
        return self::$singleton ??= new self();
    }

    /**
     * Returns the absolute paths of every ModuleServiceProvider.php found
     * under app/Modules/{Core,Addons}/*.
     */
    public function discoverProviders(): array
    {
        $base = app_path('Modules');
        $paths = [];
        foreach (['Core', 'Addons'] as $tier) {
            $tierDir = $base . DIRECTORY_SEPARATOR . $tier;
            if (! is_dir($tierDir)) {
                continue;
            }
            foreach (glob($tierDir . '/*/ModuleServiceProvider.php') ?: [] as $file) {
                $paths[] = $file;
            }
        }
        sort($paths);
        return $paths;
    }

    /**
     * Maps each discovered provider file to its FQCN.
     */
    public function discoverProviderClasses(): array
    {
        $classes = [];
        foreach ($this->discoverProviders() as $file) {
            $rel = str_replace(app_path() . DIRECTORY_SEPARATOR, '', $file);
            $rel = str_replace(['/', '.php'], ['\\', ''], $rel);
            $classes[] = 'App\\' . $rel;
        }
        return $classes;
    }

    /**
     * Reads module.json files for every discovered module.
     */
    public function manifests(): array
    {
        $out = [];
        foreach ($this->discoverProviders() as $file) {
            $dir = dirname($file);
            $manifest = $dir . '/module.json';
            if (! file_exists($manifest)) {
                continue;
            }
            $data = json_decode(file_get_contents($manifest), true);
            if (is_array($data)) {
                $data['_dir'] = $dir;
                $out[] = $data;
            }
        }
        return $out;
    }

    public function isActive(string $slug): bool
    {
        return $this->activeMap()[$slug] ?? true;
    }

    /**
     * Loads the slug => active map from module_registry.
     * If the table doesn't exist yet (pre-migration), returns an empty map,
     * which causes isActive() to fail-open (true) for every slug.
     */
    private function activeMap(): array
    {
        if ($this->activeMap !== null) {
            return $this->activeMap;
        }

        try {
            if (! Schema::hasTable('module_registry')) {
                return $this->activeMap = [];
            }
            $this->activeMap = ModuleRegistry::query()
                ->pluck('active', 'slug')
                ->map(fn ($v) => (bool) $v)
                ->all();
        } catch (\Throwable $e) {
            $this->activeMap = [];
        }

        return $this->activeMap;
    }
}
