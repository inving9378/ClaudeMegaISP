<?php

namespace App\Modules\Addons\Manual\Services;

use App\Modules\Addons\Manual\Models\ManualSection;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ManualGeneratorService
{
    /** @var (\Closure(string):void)|null */
    protected $progress = null;

    /**
     * Permite al Command (u otra invocación) recibir notificaciones por módulo procesado.
     * @param \Closure(string):void $cb
     */
    public function onProgress(\Closure $cb): self
    {
        $this->progress = $cb;
        return $this;
    }

    /**
     * Genera o actualiza las secciones del manual usando Claude API.
     * Si $onlySlug se proporciona, solo regenera el módulo cuyo slug coincida.
     *
     * @return array{generated: int, skipped: int, errors: array<int,string>}
     */
    public function generate(?string $onlySlug = null): array
    {
        // 122 módulos × ~10s = sobrepasa max_execution_time (30s en php-fpm).
        // Desactivar el corte para que el botón web y los listeners terminen el lote.
        @set_time_limit(0);
        @ignore_user_abort(true);

        $apiKey = config('services.anthropic.key');
        if (!$apiKey) {
            throw new RuntimeException('Falta CLAUDE_API_KEY en .env (config services.anthropic.key).');
        }

        $model    = config('services.anthropic.model', 'claude-sonnet-4-20250514');
        $endpoint = config('services.anthropic.endpoint', 'https://api.anthropic.com/v1/messages');

        $modules     = $this->loadActiveModules();
        if ($onlySlug !== null && $onlySlug !== '') {
            $modules = $modules->filter(fn ($m) => $this->moduleSlug($m) === $onlySlug)->values();
        }
        $routesIndex = $this->loadRoutesIndex();

        $generated = 0;
        $errors    = [];

        foreach ($modules as $module) {
            $slug  = $this->moduleSlug($module);
            $title = $module->name ?? $slug;
            try {
                $prompt  = $this->buildPrompt($module, $routesIndex);
                $content = $this->callClaude($apiKey, $model, $endpoint, $prompt);

                $latest = ManualSection::where('module_slug', $slug)->max('version');
                $next   = $latest ? ((int) $latest) + 1 : 1;

                ManualSection::create([
                    'module_slug'  => $slug,
                    'title'        => $title,
                    'content'      => $content,
                    'version'      => $next,
                    'generated_at' => now(),
                ]);

                $generated++;
                Log::info("[ManualGenerator] ✓ {$title} ({$slug}) v{$next}");
                if ($this->progress) {
                    ($this->progress)("✓ {$title} (v{$next})");
                }
            } catch (\Throwable $e) {
                $errors[] = $title . ': ' . $e->getMessage();
                Log::error('[ManualGenerator] ✗ ' . $title . ': ' . $e->getMessage(), ['module' => $title]);
                if ($this->progress) {
                    ($this->progress)("✗ {$title}: " . $e->getMessage());
                }
            }
        }

        return [
            'generated' => $generated,
            'skipped'   => 0,
            'errors'    => $errors,
        ];
    }

    /** @return \Illuminate\Support\Collection<int,object> */
    protected function loadActiveModules()
    {
        $legacy = DB::table('modules')
            ->whereNotNull('name')
            ->orderBy('group')
            ->orderBy('name')
            ->get();

        $registry = $this->loadRegistryModules();

        // Deduplicación por slug: los módulos legacy (tabla `modules`) tienen
        // prioridad; el registry solo aporta los addons/módulos que aún no
        // estén representados (mismo slug calculado vía moduleSlug()).
        $seen = [];
        foreach ($legacy as $m) {
            $seen[$this->moduleSlug($m)] = true;
        }

        $extras = $registry->reject(fn ($m) => isset($seen[$this->moduleSlug($m)]));

        return $legacy->concat($extras)->values();
    }

    /**
     * Módulos activos declarados vía manifiestos `module.json` (registrados en
     * `module_registry`), normalizados a la misma forma {name, group, description}
     * que la tabla legacy `modules`. El `group` se mapea desde el `type` del
     * manifiesto (addon/core), ya que los manifiestos no declaran `group`.
     *
     * Tolerante a fallos: si la tabla no existe o un manifiesto no se puede leer,
     * devuelve lo que tenga (nunca rompe la generación del manual).
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    protected function loadRegistryModules()
    {
        try {
            if (! Schema::hasTable('module_registry')) {
                return collect();
            }

            $activeSlugs = DB::table('module_registry')
                ->where('active', true)
                ->pluck('slug')
                ->all();

            if (empty($activeSlugs)) {
                return collect();
            }

            $activeSet = array_flip($activeSlugs);
            $manifests = app(ModuleManagerService::class)->manifests();

            $out = [];
            foreach ($manifests as $manifest) {
                $slug = $manifest['slug'] ?? null;
                if ($slug === null || ! isset($activeSet[$slug])) {
                    continue;
                }
                $out[] = (object) [
                    'name'        => $manifest['name'] ?? $slug,
                    'group'       => $manifest['type'] ?? 'addon',
                    'description' => $manifest['description'] ?? null,
                ];
            }

            return collect($out);
        } catch (\Throwable $e) {
            Log::warning('[ManualGenerator] No se pudieron cargar módulos del registry: ' . $e->getMessage());
            return collect();
        }
    }

    /** @return array{web: string, api: string} */
    protected function loadRoutesIndex(): array
    {
        $webPath = base_path('routes/web.php');
        $apiPath = base_path('routes/api.php');

        return [
            'web' => is_readable($webPath) ? $this->summarizeRoutes(file_get_contents($webPath)) : '',
            'api' => is_readable($apiPath) ? $this->summarizeRoutes(file_get_contents($apiPath)) : '',
        ];
    }

    /**
     * Reduce el contenido de un archivo de rutas a las líneas Route::* relevantes
     * para no inflar el prompt con código irrelevante.
     */
    protected function summarizeRoutes(string $source): string
    {
        $lines = preg_split('/\r?\n/', $source) ?: [];
        $kept  = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '//') || str_starts_with($trim, '#')) {
                continue;
            }
            if (preg_match('/Route::(get|post|put|patch|delete|any|resource|prefix|group|middleware)/', $trim)) {
                $kept[] = mb_substr($trim, 0, 240);
            }
        }
        return implode("\n", array_slice($kept, 0, 600));
    }

    protected function moduleSlug(object $module): string
    {
        $base = (string) ($module->name ?? 'modulo');
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($base));
        return trim($slug ?: 'modulo', '-');
    }

    protected function buildPrompt(object $module, array $routesIndex): string
    {
        $nombre = $module->name ?? 'Módulo';
        $grupo  = $module->group ?? 'general';
        $desc   = $module->description ?? '(sin descripción registrada)';

        return <<<PROMPT
Eres un redactor técnico que genera el manual de usuario tipo wiki profesional del sistema ISP MegaISP.
Genera la sección de wiki para el módulo "{$nombre}" (grupo interno: {$grupo}).

Descripción interna del módulo:
{$desc}

Contexto de rutas web disponibles (extracto):
```
{$routesIndex['web']}
```

Contexto de rutas API disponibles (extracto):
```
{$routesIndex['api']}
```

REQUISITOS DE SALIDA
- Idioma: español latinoamericano neutro, tono claro y directo, sin marketing.
- Formato: Markdown válido, sin envolverlo en bloques ```.
- No inventes funcionalidades que no estén implícitas en las rutas o la descripción.
- Sigue EXACTAMENTE la siguiente plantilla (anclas de la tabla de contenidos deben coincidir con los IDs slugificados de cada H2):

## {$nombre}

Breve descripción (1-2 oraciones) de qué hace este módulo en el contexto de un ISP.

## Tabla de contenidos
- [¿Qué es?](#que-es)
- [¿Cómo acceder?](#como-acceder)
- [Funcionalidades principales](#funcionalidades-principales)
- [Campos importantes](#campos-importantes)
- [Permisos requeridos](#permisos-requeridos)
- [Preguntas frecuentes](#preguntas-frecuentes)

## ¿Qué es?
Explicación detallada del módulo: para qué sirve, quién lo usa, qué problema resuelve.

## ¿Cómo acceder?
Ruta de navegación paso a paso desde el menú principal (Sidebar → Grupo → Submenú → {$nombre}).

## Funcionalidades principales
Lista con cada funcionalidad clave del módulo. Por cada una agrega 2-3 líneas explicando qué hace y cuándo se usa.

## Campos importantes
Tabla Markdown con las columnas exactas:

| Campo | Tipo | Descripción | Requerido |
|-------|------|-------------|-----------|
| ejemplo | texto | qué representa | Sí |

Incluye 4-8 filas con los campos más relevantes del módulo.

## Permisos requeridos
Lista de los permisos Spatie necesarios (estímalos a partir del nombre del módulo y de los verbos de las rutas, ej: `{$nombre}_view`, `{$nombre}_create`, `{$nombre}_edit`, `{$nombre}_delete`).

## Preguntas frecuentes
Exactamente 3 preguntas y respuestas comunes sobre el módulo, en formato:

**¿Pregunta 1?**
Respuesta concisa.

**¿Pregunta 2?**
Respuesta concisa.

**¿Pregunta 3?**
Respuesta concisa.
PROMPT;
    }

    protected function callClaude(string $apiKey, string $model, string $endpoint, string $prompt): string
    {
        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])->timeout(120)->post($endpoint, [
            'model'      => $model,
            'max_tokens' => 2000,
            'messages'   => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Claude API error: ' . $response->status() . ' ' . $response->body());
        }

        $json   = $response->json();
        $blocks = $json['content'] ?? [];
        $text   = '';
        foreach ($blocks as $b) {
            if (($b['type'] ?? '') === 'text') {
                $text .= $b['text'] ?? '';
            }
        }

        if (trim($text) === '') {
            throw new RuntimeException('Respuesta vacía de Claude API.');
        }

        return $text;
    }
}
