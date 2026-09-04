<?php

namespace App\Modules\Addons\Roadmap\Services;

use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * ITEM #711 (Jarvis Parte 1, sub-item de #644) — "el conocimiento se DERIVA, no se redacta".
 *
 * Esta clase construye el índice vivo recorriendo el sistema REAL en disco (module.json,
 * migraciones, routes.php, Console/, Kernel.php) con grep-con-línea — nunca copia texto de
 * un doc a mano. Es exactamente lo que #644 prohíbe repetir: "megaisp-conventions ya afirma
 * cosas que no ocurren... un doc a mano se desincroniza y miente con autoridad".
 *
 * QUÉ SE INDEXA AQUÍ (estructural, solo cambia con un commit) vs QUÉ NO:
 *   - módulos/tablas-que-declaran/rutas/comandos/schedule → SÍ, aquí. Derivan de archivos,
 *     así que solo pueden quedar viejos si el repo avanzó — por eso se marcan con el commit.
 *   - esquema REAL de la BD y valores de config → NO se cachean nunca (ver
 *     JarvisConocimientoService::tablaReal()/configKey()): se miden en vivo en cada pregunta,
 *     porque pueden cambiar sin commit (un `migrate` suelto, un valor de .env). Cachearlos
 *     aquí sería reintroducir el mismo riesgo de "miente con autoridad" que motivó el item.
 */
class JarvisIndiceService
{
    private const RUTA_RELATIVA = 'jarvis/indice.json';

    public function construir(): array
    {
        $inicio = microtime(true);

        $modulos = $this->indexarModulos();

        $comandosCircuito = [];
        foreach ($modulos as $mod) {
            foreach ($mod['comandos'] as $c) {
                if (str_starts_with((string) $c['comando'], 'circuito:')) {
                    $comandosCircuito[] = $c;
                }
            }
        }

        return [
            'commit'           => $this->commitActual(),
            'construido_at'    => now()->toIso8601String(),
            'duracion_ms'      => (int) round((microtime(true) - $inicio) * 1000),
            'modulos'          => $modulos,
            'comandos_circuito' => $comandosCircuito,
            'schedule_kernel'  => $this->indexarSchedule(),
        ];
    }

    public function guardar(array $indice): string
    {
        $ruta = $this->rutaArchivo();
        File::ensureDirectoryExists(dirname($ruta));
        File::put($ruta, json_encode($indice, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        return $ruta;
    }

    public function construirYGuardar(): array
    {
        $indice = $this->construir();
        $this->guardar($indice);

        return $indice;
    }

    public function cargar(): ?array
    {
        $ruta = $this->rutaArchivo();
        if (! is_file($ruta)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($ruta), true);

        return is_array($data) ? $data : null;
    }

    /** Best-effort: quien la llama (ej. un merge) NUNCA debe tronar porque esto falle. */
    public function regenerarSilencioso(): bool
    {
        try {
            $this->construirYGuardar();

            return true;
        } catch (\Throwable $e) {
            Log::channel('roadmap_externo')->warning('jarvis-indice-regenerar-fallo', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function commitActual(): ?string
    {
        $p = new Process(['git', 'rev-parse', 'HEAD'], base_path());
        $p->run();

        if (! $p->isSuccessful()) {
            return null;
        }

        $sha = trim($p->getOutput());

        return $sha !== '' ? $sha : null;
    }

    private function rutaArchivo(): string
    {
        return storage_path('app/' . self::RUTA_RELATIVA);
    }

    // ── Derivación ──────────────────────────────────────────────────────────

    private function indexarModulos(): array
    {
        $mgr = ModuleManagerService::instance();
        $out = [];

        foreach ($mgr->manifests() as $manifest) {
            $slug = $manifest['slug'] ?? null;
            $dir  = $manifest['_dir'] ?? null;
            if ($slug === null || $dir === null) {
                continue;
            }

            $rel = $this->relativo($dir);

            $permisos = [];
            foreach ((array) ($manifest['permissions'] ?? []) as $p) {
                $permisos[] = [
                    'name'        => $p['name'] ?? null,
                    'description' => $p['description'] ?? null,
                    'archivo'     => $rel . '/module.json',
                ];
            }

            $out[] = [
                'slug'         => $slug,
                'name'         => $manifest['name'] ?? $slug,
                'dir'          => $rel,
                'activo'       => $mgr->isActive($slug),
                'permisos'     => $permisos,
                'dependencias' => $manifest['dependencies'] ?? [],
                'tablas'       => $this->grepSchemaEnDir($dir . '/migrations', $rel . '/migrations'),
                'rutas'        => $this->grepRutas($dir, $rel),
                'comandos'     => $this->grepComandos($dir, $rel),
            ];
        }

        return $out;
    }

    private function relativo(string $abs): string
    {
        return ltrim(str_replace(base_path(), '', $abs), DIRECTORY_SEPARATOR);
    }

    /**
     * grep de Schema::create()/Schema::table() dentro de un directorio de migraciones, con
     * línea real. Se reusa desde JarvisConocimientoService::tablaReal() pasando otros $dir
     * para responder "¿en qué migración se creó/alteró esta tabla?" en vivo.
     */
    public function grepSchemaEnDir(string $dirAbs, string $dirRel, ?string $tablaFiltro = null): array
    {
        if (! is_dir($dirAbs)) {
            return [];
        }

        $out = [];
        foreach (glob($dirAbs . '/*.php') ?: [] as $file) {
            $lineas = file($file) ?: [];
            foreach ($lineas as $i => $linea) {
                if (! preg_match('/Schema::(create|table)\(\s*[\'"]([a-zA-Z0-9_]+)[\'"]/', $linea, $m)) {
                    continue;
                }
                if ($tablaFiltro !== null && $m[2] !== $tablaFiltro) {
                    continue;
                }
                $out[] = [
                    'tabla'     => $m[2],
                    'operacion' => $m[1] === 'create' ? 'create' : 'alter',
                    'archivo'   => $dirRel . '/' . basename($file),
                    'linea'     => $i + 1,
                ];
            }
        }

        return $out;
    }

    private function grepRutas(string $dirAbs, string $dirRel): array
    {
        $file = $dirAbs . '/routes.php';
        if (! is_file($file)) {
            return [];
        }

        $out = [];
        $lineas = file($file) ?: [];
        foreach ($lineas as $i => $linea) {
            if (preg_match('/Route::(get|post|put|patch|delete|any|resource)\(/', $linea, $m)) {
                $out[] = [
                    'metodo'  => strtoupper($m[1]),
                    'archivo' => $dirRel . '/routes.php',
                    'linea'   => $i + 1,
                    'texto'   => trim($linea),
                ];
            }
        }

        return $out;
    }

    private function grepComandos(string $dirAbs, string $dirRel): array
    {
        $dirConsole = $dirAbs . '/Console';
        if (! is_dir($dirConsole)) {
            return [];
        }

        $out = [];
        foreach (glob($dirConsole . '/*.php') ?: [] as $file) {
            $contenido = (string) file_get_contents($file);

            if (! preg_match('/class\s+(\w+)/', $contenido, $mClase)) {
                continue;
            }
            if (! preg_match('/\$signature\s*=\s*\'([^\']*)\'/s', $contenido, $mSig)) {
                continue;
            }

            $comando = trim(strtok($mSig[1], "\n {"));
            $descripcion = null;
            if (preg_match('/\$description\s*=\s*\'([^\']*)\'/', $contenido, $mDesc)) {
                $descripcion = $mDesc[1];
            }

            $lineaClase = 1;
            foreach (explode("\n", $contenido) as $i => $l) {
                if (str_contains($l, 'class ' . $mClase[1])) {
                    $lineaClase = $i + 1;
                    break;
                }
            }

            $out[] = [
                'comando'     => $comando,
                'clase'       => $mClase[1],
                'descripcion' => $descripcion,
                'archivo'     => $dirRel . '/Console/' . basename($file),
                'linea'       => $lineaClase,
            ];
        }

        return $out;
    }

    /** Comandos y jobs programados en app/Console/Kernel.php, con su línea real. */
    private function indexarSchedule(): array
    {
        $file = base_path('app/Console/Kernel.php');
        if (! is_file($file)) {
            return [];
        }

        $out = [];
        $lineas = file($file) ?: [];
        foreach ($lineas as $i => $linea) {
            $trim = trim($linea);
            if (str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '/*')) {
                continue; // no confundir código comentado con schedule real
            }
            if (preg_match('/\$schedule->command\(\s*[\'"]([^\'"]+)[\'"]/', $linea, $m)) {
                $out[] = ['tipo' => 'command', 'objetivo' => $m[1], 'archivo' => 'app/Console/Kernel.php', 'linea' => $i + 1];
            } elseif (preg_match('/\$schedule->job\(\s*new\s+([^)]+)\)/', $linea, $m)) {
                $out[] = ['tipo' => 'job', 'objetivo' => trim($m[1]), 'archivo' => 'app/Console/Kernel.php', 'linea' => $i + 1];
            }
        }

        return $out;
    }
}
