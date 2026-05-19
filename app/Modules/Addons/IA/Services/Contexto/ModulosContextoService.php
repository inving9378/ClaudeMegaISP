<?php

namespace App\Modules\Addons\IA\Services\Contexto;

class ModulosContextoService
{
    public function obtenerContexto(): array
    {
        $rutas = $this->rutasPorModulo();
        $estructura = $this->modulosDisponibles();

        $modulos = [];
        foreach ($estructura as $nombre => $datos) {
            $modulos[] = [
                'nombre' => $nombre,
                'controllers' => $datos['controllers'] ?? 0,
                'models_estimados' => $datos['models_estimados'] ?? [],
                'componentes_vue' => $datos['componentes_vue'] ?? 0,
                'ultima_modificacion' => $datos['ultima_modificacion'] ?? null,
                'rutas' => $rutas[strtolower($nombre)] ?? [],
            ];
        }

        return ['modulos' => $modulos];
    }

    protected function modulosDisponibles(): array
    {
        $base = app_path('Http/Controllers/Module');
        $resultado = [];
        if (!is_dir($base)) return $resultado;

        foreach (scandir($base) as $modulo) {
            if ($modulo === '.' || $modulo === '..') continue;
            $path = $base . DIRECTORY_SEPARATOR . $modulo;
            if (!is_dir($path)) continue;

            $controllers = $this->contarPHP($path);
            $componentDir = resource_path('js/components/module/' . strtolower($modulo));
            $componentes = is_dir($componentDir) ? $this->contarVue($componentDir) : 0;

            $resultado[$modulo] = [
                'controllers' => $controllers,
                'componentes_vue' => $componentes,
                'ultima_modificacion' => $this->ultimaModificacionDir($path),
            ];
        }
        return $resultado;
    }

    protected function rutasPorModulo(): array
    {
        $contenido = @file_get_contents(base_path('routes/web.php'));
        if (!$contenido) return [];

        $rutas = [];
        if (preg_match_all('/Route::group\(\[\'prefix\' => \'([^\']+)\',\s*\'namespace\' => \'([^\']+)\'\][^{]*\{([^}]+)\}/s', $contenido, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $prefix = $m[1];
                $bloque = $m[3];
                $rutasModulo = [];
                if (preg_match_all('/Route::(get|post|put|patch|delete)\([\'"]([^\'"]+)[\'"]\s*,\s*[\'"]?([^\'",\)]+)/i', $bloque, $rm, PREG_SET_ORDER)) {
                    foreach ($rm as $r) {
                        $rutasModulo[] = strtoupper($r[1]) . ' /' . $prefix . $r[2];
                    }
                }
                if (!empty($rutasModulo)) {
                    $rutas[strtolower($m[2])] = $rutasModulo;
                }
            }
        }
        return $rutas;
    }

    protected function contarPHP(string $dir): int
    {
        return $this->contarExt($dir, 'php');
    }

    protected function contarVue(string $dir): int
    {
        return $this->contarExt($dir, 'vue');
    }

    protected function contarExt(string $dir, string $ext): int
    {
        if (!is_dir($dir)) return 0;
        $c = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === $ext) {
                $c++;
            }
        }
        return $c;
    }

    protected function ultimaModificacionDir(string $dir): ?string
    {
        if (!is_dir($dir)) return null;
        $max = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $max = max($max, $f->getMTime());
            }
        }
        return $max ? date('Y-m-d H:i:s', $max) : null;
    }
}
