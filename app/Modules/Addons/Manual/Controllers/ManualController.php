<?php

namespace App\Modules\Addons\Manual\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Addons\Manual\Models\ManualSection;
use App\Modules\Addons\Manual\Services\ManualGeneratorService;
use App\Modules\Core\ModuleManager\Services\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ManualController extends Controller
{
    public function view()
    {
        return view('addon-manual::index');
    }

    /**
     * Devuelve las secciones del manual (versión más reciente por módulo)
     * agrupadas por el campo `group` de la tabla `modules`.
     */
    public function index(): JsonResponse
    {
        $latest = DB::table('manual_sections as s1')
            ->select('s1.*')
            ->join(DB::raw('(SELECT module_slug, MAX(version) AS max_version FROM manual_sections GROUP BY module_slug) s2'),
                function ($join) {
                    $join->on('s1.module_slug', '=', 's2.module_slug')
                         ->on('s1.version', '=', 's2.max_version');
                })
            ->orderBy('s1.title')
            ->get();

        $modulesMeta = DB::table('modules')->get()->keyBy(function ($m) {
            return preg_replace('/[^A-Za-z0-9]+/', '-', strtolower((string) $m->name));
        });

        $grouped = [];
        foreach ($latest as $section) {
            $meta = $modulesMeta[$section->module_slug] ?? null;
            $group = $meta->group ?? 'General';
            $grouped[$group][] = [
                'id'           => $section->id,
                'module_slug'  => $section->module_slug,
                'title'        => $section->title,
                'content'      => $section->content,
                'version'      => (int) $section->version,
                'generated_at' => $section->generated_at,
            ];
        }

        $payload = [];
        foreach ($grouped as $group => $items) {
            $payload[] = [
                'group'    => $group,
                'sections' => $items,
            ];
        }

        $lastUpdate = ManualSection::max('generated_at');

        return response()->json([
            'groups'      => $payload,
            'last_update' => $lastUpdate,
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $section = ManualSection::where('module_slug', $slug)
            ->orderByDesc('version')
            ->first();

        if (!$section) {
            return response()->json(['message' => 'Sección no encontrada.'], 404);
        }

        return response()->json($section);
    }

    /**
     * Ayuda contextual para la pantalla actual (panel lateral estilo Splynx).
     *
     * Recibe ?url=/ruta/actual y devuelve los bloques de ayuda declarados por
     * el módulo dueño de esa pantalla: glosario (terms), pasos (steps) y
     * acciones disponibles (actions).
     *
     * Fuente primaria: `screens[]` de los manifests (ModuleRegistry::getScreenHelp).
     * Fallback: el bloque `doc` de `config_sections[]` cuya url coincida.
     * Si no hay coincidencia exacta, se usa el match de prefijo más largo
     * (p. ej. /cliente/123 → ayuda declarada para /cliente).
     */
    public function help(Request $request, ModuleRegistry $registry): JsonResponse
    {
        $url = $this->normalizePath((string) $request->query('url', ''));

        if ($url === '') {
            return response()->json(['found' => false, 'manual_url' => '/manual']);
        }

        // 1. Match exacto contra screens declaradas.
        $screen = $this->matchHelp($registry->getScreenHelp(), $url);

        // 2. Fallback: bloque doc de config_sections.
        if ($screen === null) {
            $sections = array_map(function ($section) {
                return array_merge($section['doc'] ?? [], [
                    'label' => $section['label'] ?? ($section['title'] ?? null),
                    'url'   => $section['url'] ?? null,
                ]);
            }, $registry->getConfigSectionsFlat());

            $screen = $this->matchHelp($sections, $url);
        }

        if ($screen === null) {
            return response()->json(['found' => false, 'manual_url' => '/manual']);
        }

        return response()->json([
            'found'      => true,
            'label'      => $screen['label'] ?? 'Ayuda de la pantalla',
            'terms'      => array_values($screen['terms'] ?? []),
            'steps'      => array_values($screen['steps'] ?? []),
            'actions'    => array_values($screen['actions'] ?? []),
            'manual_url' => '/manual',
        ]);
    }

    /**
     * Encuentra el bloque de ayuda cuyo url calce mejor con $path.
     * Prefiere coincidencia exacta; si no, el prefijo declarado más largo.
     */
    private function matchHelp(array $candidates, string $path): ?array
    {
        $best = null;
        $bestLen = -1;

        foreach ($candidates as $candidate) {
            $candUrl = $this->normalizePath((string) ($candidate['url'] ?? ''));
            if ($candUrl === '') {
                continue;
            }

            $isExact  = $candUrl === $path;
            $isPrefix = $candUrl !== '/' && str_starts_with($path, $candUrl . '/');

            if (($isExact || $isPrefix) && strlen($candUrl) > $bestLen) {
                $best = $candidate;
                $bestLen = strlen($candUrl);
                if ($isExact) {
                    break;
                }
            }
        }

        return $best;
    }

    private function normalizePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }
        $path = '/' . ltrim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function generate(Request $request, ManualGeneratorService $service): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->can('manual_generate')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $result = $service->generate();

        return response()->json([
            'message'   => 'Manual regenerado.',
            'generated' => $result['generated'],
            'errors'    => $result['errors'],
        ]);
    }
}
