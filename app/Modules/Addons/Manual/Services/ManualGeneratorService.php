<?php

namespace App\Modules\Addons\Manual\Services;

use App\Modules\Addons\IA\Models\IAProveedor;
use App\Modules\Addons\IA\Models\IAUsoToken;
use App\Modules\Addons\IA\Services\IAAdaptadorFactory;
use App\Modules\Addons\IA\Services\IAAdaptadorInterface;
use App\Modules\Addons\IA\Services\IAPricingService;
use App\Modules\Addons\Manual\Models\ManualSection;
use App\Modules\Core\ModuleManager\Services\ModuleManagerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ManualGeneratorService
{
    /**
     * Cuántas versiones se conservan por module_slug tras cada generación.
     * Sin esto, cada corrida agrega una fila nueva por módulo sin límite
     * (ver item #263: ~122 filas por click, manual_sections crecía indefinido).
     */
    protected const RETENTION_VERSIONS = 3;

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

        $proveedor = IAProveedor::where('activo', true)->orderByDesc('id')->first();
        if (!$proveedor) {
            throw new RuntimeException('No hay proveedor de IA activo. Configure uno en /ia/configuracion.');
        }

        $adaptador = IAAdaptadorFactory::crear($proveedor);

        // Candado contra corridas concurrentes: dos clicks del botón (o botón +
        // comando) a la vez duplicarían las 122 llamadas a la IA sin control de costo.
        $lock = Cache::lock('manual-generate-run', 3600);
        if (!$lock->get()) {
            throw new RuntimeException('Ya hay una generación de manual en curso. Intenta de nuevo en unos minutos.');
        }

        try {
            $modules = $this->loadActiveModules();
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
                    $content = $this->callClaude($adaptador, $proveedor, $prompt);

                    $latest = ManualSection::where('module_slug', $slug)->max('version');
                    $next   = $latest ? ((int) $latest) + 1 : 1;

                    ManualSection::create([
                        'module_slug'  => $slug,
                        'title'        => $title,
                        'content'      => $content,
                        'version'      => $next,
                        'generated_at' => now(),
                    ]);

                    $this->pruneOldVersions($slug, $next);

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
        } finally {
            $lock->release();
        }
    }

    /**
     * Conserva solo las últimas RETENTION_VERSIONS versiones de un module_slug
     * y borra el resto. Se llama tras cada insert, así el historial nunca
     * crece sin límite (antes: una fila nueva por módulo en cada corrida, sin poda).
     */
    protected function pruneOldVersions(string $slug, int $latestVersion): void
    {
        $threshold = $latestVersion - self::RETENTION_VERSIONS;
        if ($threshold < 1) {
            return;
        }

        ManualSection::where('module_slug', $slug)
            ->where('version', '<=', $threshold)
            ->delete();
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
                    // Slug canónico del manifiesto (registry), no el slugificado
                    // del name: evita que dos módulos con nombres que slugifican
                    // igual se pisen/omitan (ver item #263, FASE 3).
                    'slug'        => $slug,
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
        // Los módulos del registry traen su slug canónico del manifiesto
        // (module.json); ese es el que evita colisiones entre dos módulos
        // cuyo nombre slugifica igual. La tabla legacy `modules` no tiene
        // columna de slug propia, así que ahí se sigue derivando del name.
        if (!empty($module->slug)) {
            $slug = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower((string) $module->slug));
            return trim($slug ?: 'modulo', '-');
        }

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

    protected function callClaude(IAAdaptadorInterface $adaptador, IAProveedor $proveedor, string $prompt): string
    {
        $respuesta = $adaptador->enviarMensaje([], $prompt, []);

        $text = trim($respuesta['texto'] ?? '');
        if ($text === '') {
            throw new RuntimeException('Respuesta vacía del proveedor IA.');
        }

        $this->registrarUso($proveedor, $respuesta);

        return $text;
    }

    /**
     * Registro best-effort en ia_uso_tokens (mismo criterio que IAPricingService::registrarUso):
     * un fallo aquí nunca debe tumbar la generación del manual.
     */
    protected function registrarUso(IAProveedor $proveedor, array $respuesta): void
    {
        try {
            $tokensInput  = (int) ($respuesta['tokens_input'] ?? 0);
            $tokensOutput = (int) ($respuesta['tokens_output'] ?? 0);
            $costo        = app(IAPricingService::class)->calcularCosto($proveedor->modelo_default, $tokensInput, $tokensOutput);

            IAUsoToken::create([
                // generate() corre desde el botón web (con sesión) o desde Command/Job
                // en background (sin auth); id=1 sigue el mismo fallback de "usuario
                // sistema" que ya usa PaymentApplicationService::resolveSystemUserId().
                'user_id'         => auth()->id() ?? 1,
                'ia_proveedor_id' => $proveedor->id,
                'proveedor'       => $proveedor->driver,
                'modelo'          => $proveedor->modelo_default,
                'tokens_input'    => $tokensInput,
                'tokens_output'   => $tokensOutput,
                'tokens_total'    => $tokensInput + $tokensOutput,
                'costo_estimado'  => $costo,
                'fecha'           => now()->toDateString(),
                'origen'          => 'manual',
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ManualGenerator] No se pudo registrar uso de tokens IA: ' . $e->getMessage());
        }
    }
}
