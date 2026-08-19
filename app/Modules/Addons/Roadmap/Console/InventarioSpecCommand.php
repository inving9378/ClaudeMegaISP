<?php

namespace App\Modules\Addons\Roadmap\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * FASE 2B · PASO 0 — ¿contra qué mediría `AuditorService::medirContraSpec()`?
 *
 * Antes de escribir el detector hay que saber si hay algo que medir. Este comando responde dos
 * preguntas sobre los `module.json`, y es READ-ONLY:
 *
 *   1. **¿Cuánto declaran?** endpoints, permisos, pantallas, menú, tarjetas.
 *   2. **¿Lo declarado es CIERTO?** cada endpoint contra la tabla de rutas y cada permiso contra la
 *      tabla `permissions`.
 *
 * Existe como comando y no como una tabla pegada en un doc porque una foto se queda vieja — y un
 * número viejo que nadie vuelve a medir es la enfermedad que la fase 2A se pasó cerrando.
 *
 * ⚠️ Un endpoint declarado sin ruta NO significa "falta construirlo": significa que la declaración
 * y la realidad no coinciden, y puede ser el `module.json` el que envejeció (medido: Flotas declara
 * `/api/flotas/*` y lo que existe son 65 rutas bajo `flotas/api/*`). El detector de 2B tiene que
 * emitir esa ambigüedad, no resolverla a la cómoda.
 */
class InventarioSpecCommand extends Command
{
    protected $signature = 'circuito:inventario-spec
                            {--detalle : tabla por módulo, además del resumen}';

    protected $description = 'READ-ONLY (Fase 2B paso 0): qué declaran los module.json y cuánto de eso corresponde con la realidad.';

    public function handle(): int
    {
        $specs = $this->leerSpecs();
        if (! $specs) {
            $this->error('No encontré ningún module.json en app/Modules.');

            return self::FAILURE;
        }

        $rutas    = $this->rutasRegistradas();
        $permisos = DB::table('permissions')->pluck('name')->flip();

        $tot = ['endp' => 0, 'endp_ok' => 0, 'perm' => 0, 'perm_ok' => 0, 'scr' => 0, 'scr_ok' => 0];
        $desajustes = [];
        $filas = [];

        foreach ($specs as $mod => $d) {
            $f = ['mod' => $mod, 'endp' => 0, 'perm' => count($d['permissions'] ?? []),
                  'menu' => count($d['menu'] ?? []), 'card' => count($d['admin_cards'] ?? []),
                  'scr' => 0, 'malos' => 0];

            foreach ($d['api_endpoints'] ?? [] as $e) {
                $f['endp']++;
                $tot['endp']++;
                if (isset($rutas[$this->clave($e['method'] ?? 'GET', $e['path'] ?? '')])) {
                    $tot['endp_ok']++;
                } else {
                    $f['malos']++;
                    $desajustes[$mod]['endpoints'][] = strtoupper($e['method'] ?? 'GET') . ' ' . ($e['path'] ?? '?');
                }
                if (! empty($e['permission'])) {
                    $tot['perm']++;
                    if (isset($permisos[$e['permission']])) {
                        $tot['perm_ok']++;
                    } else {
                        $desajustes[$mod]['permisos'][] = $e['permission'];
                    }
                }
            }

            foreach ($d['screens'] ?? [] as $s) {
                $f['scr']++;
                $tot['scr']++;
                if (isset($rutas[$this->clave('GET', $s['url'] ?? '')])) {
                    $tot['scr_ok']++;
                } else {
                    $f['malos']++;
                    $desajustes[$mod]['screens'][] = $s['url'] ?? '?';
                }
            }

            $filas[] = $f;
        }

        $n = count($specs);
        $rutasTotal = count(array_unique(array_keys($rutas)));

        $this->newLine();
        $this->info("INVENTARIO DE SPECS (module.json) — {$n} módulos");
        $this->newLine();

        $this->line('<options=bold>1. Cuánto declaran</>');
        $this->line('   con api_endpoints ....... ' . $this->cuenta($filas, fn ($f) => $f['endp'] > 0) . "/{$n}");
        $this->line('   con screens ............. ' . $this->cuenta($filas, fn ($f) => $f['scr'] > 0) . "/{$n}");
        $this->line('   con permissions ......... ' . $this->cuenta($filas, fn ($f) => $f['perm'] > 0) . "/{$n}");
        $vacios = array_filter($filas, fn ($f) => $f['endp'] === 0 && $f['scr'] === 0 && $f['perm'] === 0);
        $this->line('   CASI VACÍOS ............. ' . count($vacios) . "/{$n}  ("
            . implode(', ', array_slice(array_column($vacios, 'mod'), 0, 8))
            . (count($vacios) > 8 ? '…' : '') . ')');

        $this->newLine();
        $sup = app(\App\Modules\Addons\Roadmap\Services\AuditorService::class)->superficieDeclarada();
        $this->line('<options=bold>2. Superficie declarada (métrica de convergencia de 2B)</>');
        $this->line("   <options=bold>{$sup['pct']} %</>  =  {$sup['declarados']} endpoints declarados / "
            . "{$sup['rutas_modulo']} rutas atribuibles a un módulo");
        $this->line("   {$sup['rutas_sin_modulo']} rutas viven en controllers legacy fuera de app/Modules: no pertenecen");
        $this->line('   a ningún manifiesto y quedan FUERA del denominador (es el techo honesto de esta vía).');
        $this->comment('   Mientras este número suba, el generador tiene trabajo.');

        $this->newLine();
        $this->line('<options=bold>3. ¿Lo declarado es cierto?</>');
        $this->tablaVerdad($tot);

        if ($desajustes) {
            $this->newLine();
            $this->line('<options=bold>4. Dónde NO coincide (' . count($desajustes) . ' módulos)</>');
            $this->comment('   Ojo: "sin ruta" NO es "falta construirlo". Puede ser la declaración la que envejeció.');
            foreach ($desajustes as $mod => $d) {
                $partes = [];
                foreach (['endpoints', 'screens', 'permisos'] as $k) {
                    if (! empty($d[$k])) {
                        $partes[] = count($d[$k]) . " {$k}";
                    }
                }
                $this->line("   · <fg=yellow>{$mod}</>: " . implode(' · ', $partes));
                foreach (array_slice($d['endpoints'] ?? [], 0, 3) as $e) {
                    $this->line("       {$e}");
                }
            }
        }

        if ($this->option('detalle')) {
            $this->newLine();
            usort($filas, fn ($a, $b) => [$b['endp'], $b['scr']] <=> [$a['endp'], $a['scr']]);
            $this->table(['módulo', 'endp', 'perm', 'menu', 'cards', 'screens', 'desajustes'],
                array_map(fn ($f) => [$f['mod'], $f['endp'], $f['perm'], $f['menu'], $f['card'], $f['scr'],
                    $f['malos'] ?: '—'], $filas));
        }

        $this->newLine();

        return self::SUCCESS;
    }

    private function tablaVerdad(array $t): void
    {
        $pct = fn ($ok, $de) => $de ? round(100 * $ok / $de, 0) . ' %' : '—';
        $this->table(
            ['señal', 'declarado', 'corresponde con la realidad'],
            [
                ['api_endpoints → ruta registrada', $t['endp'], "{$t['endp_ok']}  (" . $pct($t['endp_ok'], $t['endp']) . ')'],
                ['permission → fila en `permissions`', $t['perm'], "{$t['perm_ok']}  (" . $pct($t['perm_ok'], $t['perm']) . ')'],
                ['screens[].url → ruta GET', $t['scr'], "{$t['scr_ok']}  (" . $pct($t['scr_ok'], $t['scr']) . ')'],
            ]
        );
    }

    /** Rutas registradas, normalizadas a `MÉTODO /uri` con los `{param}` colapsados. */
    private function rutasRegistradas(): array
    {
        $out = [];
        foreach (Route::getRoutes() as $r) {
            foreach ($r->methods() as $m) {
                $out[$this->clave($m, $r->uri())] = true;
            }
        }

        return $out;
    }

    private function clave(string $metodo, string $path): string
    {
        $p = '/' . ltrim($path, '/');
        $p = rtrim(preg_replace('/\{[^}]+\}/', '{}', $p), '/');

        return strtoupper($metodo) . ' ' . $p;
    }

    /** @return array<string,array> módulo => contenido del module.json */
    private function leerSpecs(): array
    {
        $out = [];
        foreach (glob(base_path('app/Modules/*/*/module.json')) ?: [] as $f) {
            $d = json_decode(file_get_contents($f), true);
            if (is_array($d)) {
                $out[basename(dirname($f))] = $d;
            } else {
                $this->warn('module.json ilegible: ' . str_replace(base_path() . '/', '', $f));
            }
        }
        ksort($out);

        return $out;
    }

    private function cuenta(array $filas, callable $pred): int
    {
        return count(array_filter($filas, $pred));
    }
}
