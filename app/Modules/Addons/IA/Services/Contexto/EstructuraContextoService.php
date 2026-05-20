<?php

namespace App\Modules\Addons\IA\Services\Contexto;

class EstructuraContextoService
{
    public function obtenerContexto(): array
    {
        return [
            'controllers' => $this->listarPHP(app_path('Http/Controllers/Module')),
            'services' => $this->listarPHP(app_path('Services')),
            'models' => $this->listarPHP(app_path('Models')),
            'componentes_vue' => $this->listarVue(resource_path('js/components')),
            'arbol_modulos' => $this->arbol(app_path('Http/Controllers/Module'), 2),
        ];
    }

    protected function listarPHP(string $base): array
    {
        return $this->listarPorExtension($base, ['php']);
    }

    protected function listarVue(string $base): array
    {
        return $this->listarPorExtension($base, ['vue']);
    }

    protected function listarPorExtension(string $base, array $exts): array
    {
        if (!is_dir($base)) return [];
        $resultado = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, $exts, true)) continue;
            $resultado[] = str_replace(base_path() . '/', '', $file->getPathname());
        }
        sort($resultado);
        return $resultado;
    }

    public function arbol(string $base, int $profundidad = 2, int $nivel = 0): array
    {
        if (!is_dir($base) || $nivel > $profundidad) return [];

        $nodos = [];
        foreach (scandir($base) as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            $path = $base . DIRECTORY_SEPARATOR . $entry;
            $nodo = ['nombre' => $entry, 'tipo' => is_dir($path) ? 'dir' : 'file'];
            if (is_dir($path) && $nivel < $profundidad) {
                $nodo['hijos'] = $this->arbol($path, $profundidad, $nivel + 1);
            }
            $nodos[] = $nodo;
        }
        return $nodos;
    }
}
