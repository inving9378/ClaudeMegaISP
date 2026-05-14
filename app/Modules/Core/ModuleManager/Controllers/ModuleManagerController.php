<?php

namespace App\Modules\Core\ModuleManager\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\ModuleManager\Models\MigrationLog;
use App\Modules\Core\ModuleManager\Models\ModuleRegistry;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class ModuleManagerController extends Controller
{
    /** Claude Sonnet — coincide con app/Http/Controllers/Module/IA/IAChatController.php */
    private const CLAUDE_MODEL_DEFAULT = 'claude-sonnet-4-20250514';
    private const CLAUDE_MAX_TOKENS = 4096;

    public function index()
    {
        $modules = $this->buildModuleRows();

        $totalModules = count($modules);
        $migratedCount = count(array_filter($modules, fn ($m) => $m['migrated']));
        $pendingCount = $totalModules - $migratedCount;
        $totalCostUsd = (float) MigrationLog::sum('cost_usd');

        return view('core-module-manager::index', [
            'modules' => $modules,
            'totalModules' => $totalModules,
            'migratedCount' => $migratedCount,
            'pendingCount' => $pendingCount,
            'totalCostUsd' => $totalCostUsd,
        ]);
    }

    public function migrate(Request $request, string $slug): JsonResponse
    {
        $module = $this->findModule($slug);
        if ($module === null) {
            return response()->json(['success' => false, 'error' => "Módulo '{$slug}' no existe."], 404);
        }

        $migrationDoc = $module['_dir'] . '/MIGRATION.md';
        if (! file_exists($migrationDoc)) {
            return response()->json([
                'success' => false,
                'error' => "Este módulo no tiene MIGRATION.md — nada que pasarle a Claude.",
            ], 422);
        }

        $apiKey = env('CLAUDE_API_KEY', '');
        if (empty($apiKey)) {
            return response()->json(['success' => false, 'error' => 'CLAUDE_API_KEY no configurada en .env'], 500);
        }

        $log = MigrationLog::create([
            'module_slug' => $slug,
            'status' => 'running',
            'started_at' => Carbon::now(),
        ]);

        try {
            $migrationMd = file_get_contents($migrationDoc);
            $manifestJson = json_encode($module, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $systemPrompt = "Eres un asistente experto en refactorizaciones de Laravel 10. "
                . "Vas a recibir el documento MIGRATION.md de un módulo del sistema MegaISP. "
                . "Tu tarea es analizarlo y proponer, en español, un plan de implementación "
                . "concreto paso a paso (mover archivos, actualizar namespaces, registrar rutas, "
                . "verificar). Sé conciso y operativo: cada paso debe ser ejecutable. "
                . "No inventes archivos que no existan según el documento.";

            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => env('CLAUDE_MODEL', self::CLAUDE_MODEL_DEFAULT),
                'max_tokens' => self::CLAUDE_MAX_TOKENS,
                'system' => $systemPrompt,
                'messages' => [[
                    'role' => 'user',
                    'content' => "Manifiesto del módulo:\n\n```json\n{$manifestJson}\n```\n\n"
                        . "Contenido de MIGRATION.md:\n\n```md\n{$migrationMd}\n```",
                ]],
            ]);

            if (! $response->successful()) {
                $log->update([
                    'status' => 'failed',
                    'completed_at' => Carbon::now(),
                    'notes' => 'Claude API error ' . $response->status() . ': ' . $response->body(),
                ]);
                return response()->json([
                    'success' => false,
                    'error' => 'Claude API respondió ' . $response->status(),
                ], 502);
            }

            $payload = $response->json();
            $inputTokens = (int) ($payload['usage']['input_tokens'] ?? 0);
            $outputTokens = (int) ($payload['usage']['output_tokens'] ?? 0);
            $cost = MigrationLog::computeCost($inputTokens, $outputTokens);
            $text = $payload['content'][0]['text'] ?? '';

            $log->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $cost,
                'notes' => mb_substr($text, 0, 5000),
            ]);

            return response()->json([
                'success' => true,
                'plan' => $text,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'cost_usd' => $cost,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'completed_at' => Carbon::now(),
                'notes' => 'Excepción: ' . $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function toggle(string $slug): JsonResponse|RedirectResponse
    {
        $module = $this->findModule($slug);
        if ($module === null) {
            return response()->json(['success' => false, 'error' => "Módulo '{$slug}' no existe."], 404);
        }

        if (($module['type'] ?? 'addon') === 'core') {
            return response()->json([
                'success' => false,
                'error' => 'Los módulos core no se pueden desactivar.',
            ], 422);
        }

        $registry = ModuleRegistry::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $module['name'] ?? $slug,
                'version' => $module['version'] ?? '0.1.0',
                'type' => $module['type'] ?? 'addon',
                'active' => true,
                'installed_at' => Carbon::now(),
            ]
        );
        $registry->active = ! $registry->active;
        $registry->save();

        return response()->json([
            'success' => true,
            'slug' => $slug,
            'active' => $registry->active,
        ]);
    }

    private function buildModuleRows(): array
    {
        $service = app(ModuleManagerService::class);
        $manifests = $service->manifests();

        $registry = ModuleRegistry::query()->get()->keyBy('slug');
        $logs = MigrationLog::query()
            ->select('module_slug', DB::raw('SUM(cost_usd) as total_cost'), DB::raw('SUM(input_tokens) as total_input'), DB::raw('SUM(output_tokens) as total_output'), DB::raw('COUNT(*) as runs'))
            ->groupBy('module_slug')
            ->get()
            ->keyBy('module_slug');

        $rows = [];
        foreach ($manifests as $manifest) {
            $slug = $manifest['slug'] ?? null;
            if ($slug === null) {
                continue;
            }
            $dir = $manifest['_dir'];
            $reg = $registry->get($slug);
            $log = $logs->get($slug);

            $rows[] = [
                'slug' => $slug,
                'name' => $manifest['name'] ?? $slug,
                'version' => $manifest['version'] ?? '0.0.0',
                'type' => $manifest['type'] ?? 'addon',
                'description' => $manifest['description'] ?? '',
                '_dir' => $dir,
                'active' => $reg ? (bool) $reg->active : ($manifest['active'] ?? true),
                'migrated' => $this->isMigrated($dir),
                'files_count' => $this->countModuleFiles($dir),
                'total_cost' => $log ? (float) $log->total_cost : 0.0,
                'total_input_tokens' => $log ? (int) $log->total_input : 0,
                'total_output_tokens' => $log ? (int) $log->total_output : 0,
                'runs' => $log ? (int) $log->runs : 0,
            ];
        }

        usort($rows, fn ($a, $b) => [$a['type'], $a['slug']] <=> [$b['type'], $b['slug']]);
        return $rows;
    }

    /**
     * Un módulo se considera "migrado" si su carpeta Controllers/ contiene
     * .php reales (no sólo .gitkeep). Coincide con la realidad observable
     * de los Core modules ya migrados (auth/layout/dashboard/crm/configuracion).
     */
    private function isMigrated(string $dir): bool
    {
        $controllers = $dir . '/Controllers';
        if (! is_dir($controllers)) {
            return false;
        }
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllers, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
                return true;
            }
        }
        return false;
    }

    private function countModuleFiles(string $dir): int
    {
        if (! is_dir($dir)) {
            return 0;
        }
        $count = 0;
        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if ($file->isFile() && basename($file->getFilename()) !== '.gitkeep') {
                $count++;
            }
        }
        return $count;
    }

    private function findModule(string $slug): ?array
    {
        foreach (app(ModuleManagerService::class)->manifests() as $m) {
            if (($m['slug'] ?? null) === $slug) {
                return $m;
            }
        }
        return null;
    }

    /** Used by Vue for expandable history row. */
    public function history(string $slug): JsonResponse
    {
        $rows = MigrationLog::where('module_slug', $slug)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'status', 'input_tokens', 'output_tokens', 'cost_usd', 'started_at', 'completed_at']);
        return response()->json(['success' => true, 'history' => $rows]);
    }
}
