<?php

namespace App\Console\Commands\Active;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\Process;

/**
 * Fase 4a del inventario de permisos (item #9990763, sub-item de #9990745).
 *
 * SOLO LECTURA: cuenta los permisos de Spatie por módulo/guard (punto 1) y detecta cuáles se
 * consultan de verdad en el código vs. cuáles son huérfanos (punto 2). No modifica permisos,
 * roles ni código — solo reporta.
 *
 * Atribución de módulo: usa `permissions[]` de cada `module.json` como fuente autoritativa
 * (es donde cada addon DECLARA sus permisos). Lo que no aparece en ningún module.json cae a un
 * grupo heurístico derivado del prefijo del nombre (permisos legacy sin module.json, creados a
 * mano en `PermissionsSeeder`/migraciones sueltas).
 *
 * Detección de uso: grep -F de una sola pasada del nombre literal de cada permiso contra
 * app/, resources/, routes/, config/ (las 4 rutas que pide el item). Un match dentro de un
 * `module.json`, en la línea de su propia declaración (`"name": "..."`), NO cuenta como uso —
 * es donde nace, no donde se consume; un match en `"permission": "..."` (gate de menú/ruta del
 * propio module.json) SÍ cuenta. Limitación conocida: no detecta permisos construidos
 * dinámicamente (`can('modulo.' . $accion)`) porque el nombre completo no aparece literal en
 * ningún archivo — se documenta como advertencia, no se puede resolver sin un AST parser
 * (Q3 del item ya descartó esa opción por sobre-ingeniería).
 */
class AuditoriaPermisosReporteCommand extends Command
{
    protected $signature = 'auditoria:permisos-reporte
                            {--csv= : ruta del CSV de salida (default: docs/auditoria/permisos-punto1-2.csv)}';

    protected $description = 'SOLO LECTURA: cuenta permisos por módulo/guard y detecta huérfanos vs. usados en código (Fase 4a, item #9990763)';

    private const DIRS = ['app', 'resources', 'routes', 'config'];

    private const EXTENSIONS = ['php', 'blade.php', 'vue', 'js', 'json'];

    public function handle(): int
    {
        $permissions = Permission::orderBy('name')->get(['id', 'name', 'guard_name']);

        if ($permissions->isEmpty()) {
            $this->error('No hay permisos en la tabla `permissions`.');

            return self::FAILURE;
        }

        $moduleByPermission = $this->declaredModules();
        $usage = $this->detectUsage($permissions->pluck('name')->all(), $moduleByPermission);

        $porModulo = [];
        $usados = 0;
        $huerfanos = [];

        foreach ($permissions as $permiso) {
            $grupo = $moduleByPermission[$permiso->name]['label']
                ?? $this->prefijoHeuristico($permiso->name);

            $porModulo[$grupo] ??= ['total' => 0, 'usados' => 0, 'huerfanos' => 0];
            $porModulo[$grupo]['total']++;

            if ($usage[$permiso->name]) {
                $porModulo[$grupo]['usados']++;
                $usados++;
            } else {
                $porModulo[$grupo]['huerfanos']++;
                $huerfanos[] = [
                    'name' => $permiso->name,
                    'guard' => $permiso->guard_name,
                    'modulo' => $grupo,
                ];
            }
        }

        ksort($porModulo);

        $this->info("Total de permisos en BD: {$permissions->count()} (guard_name: "
            . $permissions->pluck('guard_name')->unique()->implode(', ') . ')');
        $this->line('');

        $this->table(
            ['Módulo / grupo', 'Total', 'Usados', 'Huérfanos'],
            collect($porModulo)->map(fn ($v, $k) => [$k, $v['total'], $v['usados'], $v['huerfanos']])->values()
        );

        $this->line('');
        $this->info("Usados en código: {$usados} / {$permissions->count()}");
        $this->line("Huérfanos (sin match en app/, resources/, routes/, config/): " . count($huerfanos));
        $this->line('');

        if ($huerfanos) {
            $this->warn('Lista completa de huérfanos:');
            foreach ($huerfanos as $h) {
                $this->line("  - {$h['name']}  [{$h['modulo']}]");
            }
        }

        $this->line('');
        $this->comment('⚠ Limitación conocida: la búsqueda es literal (nombre exacto del permiso). '
            . 'Un permiso construido dinámicamente (ej. can(\'modulo.\' . $accion)) puede salir como '
            . 'huérfano aunque sí se use — revisar antes de asumir que un huérfano es basura real.');

        $csvPath = $this->option('csv') ?: base_path('docs/auditoria/permisos-punto1-2.csv');
        $this->escribirCsv($csvPath, $permissions, $usage, $moduleByPermission);
        $this->line('');
        $this->info("CSV de trabajo escrito en: {$csvPath}");

        return self::SUCCESS;
    }

    /**
     * @return array<string, array{label: string}>
     */
    private function declaredModules(): array
    {
        $map = [];

        $files = array_merge(
            glob(base_path('app/Modules/Core/*/module.json')) ?: [],
            glob(base_path('app/Modules/Addons/*/module.json')) ?: []
        );

        foreach ($files as $file) {
            $data = json_decode(File::get($file), true);
            if (! is_array($data) || empty($data['permissions']) || ! is_array($data['permissions'])) {
                continue;
            }

            $label = $data['name'] ?? basename(dirname($file));

            foreach ($data['permissions'] as $p) {
                $name = is_array($p) ? ($p['name'] ?? null) : null;
                if ($name && ! isset($map[$name])) {
                    $map[$name] = ['label' => $label];
                }
            }
        }

        return $map;
    }

    private function prefijoHeuristico(string $name): string
    {
        if (str_contains($name, '.')) {
            return strstr($name, '.', true) . '.* (heurístico, sin module.json)';
        }

        if (str_contains($name, '-') && ! str_contains($name, '_')) {
            return strstr($name, '-', true) . '-* (heurístico, sin module.json)';
        }

        $primero = strstr($name, '_', true);

        return ($primero ?: $name) . '_* (heurístico, sin module.json)';
    }

    /**
     * @param string[] $names
     * @param array<string, array{label: string}> $declared
     * @return array<string, bool> nombre => usado?
     */
    private function detectUsage(array $names, array $declared): array
    {
        $usage = array_fill_keys($names, false);

        $patternsFile = tempnam(sys_get_temp_dir(), 'perm_names_');
        File::put($patternsFile, implode("\n", $names) . "\n");

        $includeArgs = [];
        foreach (self::EXTENSIONS as $ext) {
            $includeArgs[] = '--include=*.' . $ext;
        }

        $process = new Process(array_merge(
            ['grep', '-rnF', '-f', $patternsFile],
            $includeArgs,
            self::DIRS
        ), base_path());
        $process->setTimeout(120);
        $process->run();

        @unlink($patternsFile);

        $output = $process->getOutput();
        if ($output === '') {
            return $usage;
        }

        foreach (explode("\n", $output) as $line) {
            if ($line === '') {
                continue;
            }

            // formato: ruta:numero_linea:contenido
            if (! preg_match('/^([^:]+):(\d+):(.*)$/', $line, $m)) {
                continue;
            }
            $path = $m[1];
            $content = $m[3];
            $esModuleJson = str_ends_with($path, 'module.json');

            foreach ($names as $name) {
                if ($usage[$name] || ! str_contains($content, $name)) {
                    continue;
                }

                if ($esModuleJson && preg_match('/"name"\s*:\s*"' . preg_quote($name, '/') . '"/', $content)) {
                    // Es la propia línea de declaración en permissions[] — no cuenta como uso.
                    continue;
                }

                $usage[$name] = true;
            }
        }

        return $usage;
    }

    /**
     * @param array<string, bool> $usage
     * @param array<string, array{label: string}> $declared
     */
    private function escribirCsv(string $path, $permissions, array $usage, array $declared): void
    {
        File::ensureDirectoryExists(dirname($path));

        $fh = fopen($path, 'w');
        fputcsv($fh, ['name', 'guard_name', 'modulo', 'estado']);

        foreach ($permissions as $permiso) {
            $grupo = $declared[$permiso->name]['label'] ?? $this->prefijoHeuristico($permiso->name);
            fputcsv($fh, [
                $permiso->name,
                $permiso->guard_name,
                $grupo,
                $usage[$permiso->name] ? 'usado' : 'huerfano',
            ]);
        }

        fclose($fh);
    }
}
