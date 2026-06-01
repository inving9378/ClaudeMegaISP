<?php

namespace App\Modules\Core\Configuracion\Services;

use Illuminate\Support\Facades\File;

class CatalogoApiService
{
    public function getAllEndpoints(): array
    {
        $modules = [];

        foreach ($this->moduleJsonPaths() as $path) {
            $json = $this->parseJson($path);
            if (!$json || empty($json['api_endpoints'])) {
                continue;
            }

            $endpoints = array_filter($json['api_endpoints'], fn($e) => !empty($e['method']) && !empty($e['path']));
            if (empty($endpoints)) {
                continue;
            }

            $modules[] = [
                'slug'        => $json['slug'] ?? basename(dirname($path)),
                'name'        => $json['name'] ?? $json['slug'] ?? '?',
                'version'     => $json['version'] ?? null,
                'endpoints'   => array_values($endpoints),
                'scopes'      => $this->extractScopes($endpoints),
            ];
        }

        usort($modules, fn($a, $b) => strcmp($a['name'], $b['name']));

        return $modules;
    }

    public function getAllScopes(): array
    {
        $scopes = [];
        foreach ($this->getAllEndpoints() as $module) {
            foreach ($module['scopes'] as $scope) {
                $scopes[$scope] = $scope;
            }
        }
        ksort($scopes);
        return array_values($scopes);
    }

    private function extractScopes(array $endpoints): array
    {
        $scopes = [];
        foreach ($endpoints as $ep) {
            if (!empty($ep['scope'])) {
                $scopes[] = $ep['scope'];
            }
            if (!empty($ep['permission'])) {
                $scopes[] = $ep['permission'];
            }
        }
        return array_values(array_unique($scopes));
    }

    private function moduleJsonPaths(): array
    {
        $basePath = base_path('app/Modules');
        $pattern  = $basePath . '/*/module.json';
        $paths    = glob($pattern) ?: [];

        // Buscar también en subdirectorios (Core/, Addons/)
        $patternDeep = $basePath . '/*/*/module.json';
        $pathsDeep   = glob($patternDeep) ?: [];

        return array_merge($paths, $pathsDeep);
    }

    private function parseJson(string $path): ?array
    {
        try {
            $content = File::get($path);
            return json_decode($content, true);
        } catch (\Throwable) {
            return null;
        }
    }
}
