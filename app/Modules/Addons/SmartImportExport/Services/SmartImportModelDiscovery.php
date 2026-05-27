<?php

namespace App\Modules\Addons\SmartImportExport\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use Throwable;

class SmartImportModelDiscovery
{
    /** @var array<string, class-string<Model>>|null */
    private ?array $tableModelCache = null;

    /** @var array<string, list<class-string<Model>>>|null */
    private ?array $tableModelsCache = null;

    /**
     * @return array<string, class-string<Model>>
     */
    public function tableModelMap(): array
    {
        if ($this->tableModelCache !== null) {
            return $this->tableModelCache;
        }

        $map = [];
        foreach ($this->tableModelsMap() as $table => $models) {
            if ($models !== []) {
                $map[$table] = $models[0];
            }
        }

        return $this->tableModelCache = $map;
    }

    /**
     * @return array<string, list<class-string<Model>>>
     */
    public function tableModelsMap(): array
    {
        if ($this->tableModelsCache !== null) {
            return $this->tableModelsCache;
        }

        $map = [];
        foreach ($this->modelFiles() as $path) {
            $class = $this->classFromFile($path);
            if (!$class) {
                continue;
            }

            try {
                if (!class_exists($class) || !is_subclass_of($class, Model::class)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                if ($reflection->isAbstract()) {
                    continue;
                }

                /** @var Model $model */
                $model = $reflection->newInstanceWithoutConstructor();
                $table = $model->getTable();
                if ($table !== '') {
                    $map[$table][] = $class;
                }
            } catch (Throwable) {
                continue;
            }
        }

        foreach ($map as $table => $models) {
            $models = array_values(array_unique($models));
            sort($models);
            $map[$table] = $models;
        }

        ksort($map);

        return $this->tableModelsCache = $map;
    }

    /**
     * @return class-string<Model>|null
     */
    public function modelForTable(string $table, ?string $preferred = null): ?string
    {
        $models = $this->modelsForTable($table);
        if ($models === []) {
            return null;
        }

        if ($preferred && in_array($preferred, $models, true)) {
            return $preferred;
        }

        return $models[0];
    }

    /**
     * @return list<class-string<Model>>
     */
    public function modelsForTable(string $table): array
    {
        return $this->tableModelsMap()[$table] ?? [];
    }

    public function moduleLabelForModel(?string $modelClass): ?string
    {
        if (!$modelClass) {
            return null;
        }

        if (preg_match('/^App\\\\Modules\\\\(?:Core|Addons)\\\\([^\\\\]+)/', $modelClass, $match)) {
            return Str::headline($match[1]);
        }

        if (str_starts_with($modelClass, 'App\\Models\\')) {
            return 'Aplicacion';
        }

        return null;
    }

    public function hasTable(string $table): bool
    {
        return $this->modelsForTable($table) !== [];
    }

    /**
     * @return string[]
     */
    private function modelFiles(): array
    {
        $paths = config('smart_import.model_discovery.paths', [
            app_path('Models'),
            app_path('Modules'),
        ]);

        $files = [];
        foreach ($paths as $path) {
            if (!is_string($path) || !File::exists($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                $pathname = $file->getPathname();
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                if (str_contains($pathname, DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR)
                    && !str_contains($pathname, DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR)) {
                    continue;
                }

                $files[] = $pathname;
            }
        }

        sort($files);

        return $files;
    }

    private function classFromFile(string $path): ?string
    {
        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            return null;
        }

        if (!preg_match('/^\s*namespace\s+([^;]+);/m', $contents, $namespaceMatch)) {
            return null;
        }

        if (!preg_match('/^\s*(?:abstract\s+|final\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $classMatch)) {
            return null;
        }

        return trim($namespaceMatch[1]) . '\\' . trim($classMatch[1]);
    }
}
