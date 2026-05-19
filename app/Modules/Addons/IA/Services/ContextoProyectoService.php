<?php

namespace App\Modules\Addons\IA\Services;

use App\Modules\Addons\IA\Models\IAConversacion;
use App\Modules\Addons\IA\Models\IAMensaje;
use App\Modules\Addons\IA\Models\IANotaProyecto;
use App\Modules\Addons\IA\Models\IASesionTrabajo;
use App\Modules\Addons\IA\Models\IATarea;
use Illuminate\Support\Str;
use App\Modules\Addons\IA\Services\Contexto\BaseDatosContextoService;
use App\Modules\Addons\IA\Services\Contexto\EstructuraContextoService;
use App\Modules\Addons\IA\Services\Contexto\GitContextoService;
use App\Modules\Addons\IA\Services\Contexto\ModulosContextoService;
use App\Modules\Addons\IA\Services\Contexto\SesionTrabajoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

/**
 * Orquesta la construcción del contexto del proyecto que se inyecta como
 * system prompt al inicio de cada conversación con la IA.
 *
 * Devuelve un Markdown legible por el modelo, no JSON, para reducir tokens
 * y aumentar la comprensión semántica.
 */
class ContextoProyectoService
{
    public function __construct(
        protected GitContextoService $git,
        protected EstructuraContextoService $estructura,
        protected BaseDatosContextoService $bd,
        protected SesionTrabajoService $sesiones,
        protected ModulosContextoService $modulos,
    ) {
    }

    /**
     * Construye el system prompt completo. Se cachea por unos segundos
     * para evitar re-recorrer FS y git en cada mensaje de la misma conversación.
     */
    /**
     * Set de cache donde trackeamos los buckets vivos para poder invalidarlos todos.
     * TTL largo (1 día) porque solo lo limpiamos manualmente cuando invalidamos.
     */
    protected const CACHE_USERS_KEY = 'ia_contexto_users_activos';

    public function buildSystemPrompt(?int $userId = null): string
    {
        $bucket = $userId ?? 'anon';
        $cacheKey = 'ia_contexto_proyecto_' . $bucket;

        $this->registrarBucketActivo($bucket);

        return Cache::remember($cacheKey, 30, function () use ($userId) {
            return $this->ensamblar($userId);
        });
    }

    protected function registrarBucketActivo(string|int $bucket): void
    {
        $activos = Cache::get(self::CACHE_USERS_KEY, []);
        if (!in_array($bucket, $activos, true)) {
            $activos[] = $bucket;
            Cache::put(self::CACHE_USERS_KEY, $activos, now()->addDay());
        }
    }

    public function invalidarCache(?int $userId = null): void
    {
        Cache::forget('ia_contexto_proyecto_' . ($userId ?? 'anon'));
    }

    /**
     * Invalida el cache para todos los buckets que hayan generado system prompt
     * recientemente. Lee la lista de buckets activos trackeados por
     * registrarBucketActivo() en lugar de adivinar.
     */
    public function invalidarTodos(): void
    {
        $activos = Cache::get(self::CACHE_USERS_KEY, []);
        foreach ($activos as $bucket) {
            Cache::forget('ia_contexto_proyecto_' . $bucket);
        }
        Cache::forget(self::CACHE_USERS_KEY);
    }

    protected function ensamblar(?int $userId): string
    {
        $git = $this->git->obtenerContexto();
        $bd = $this->bd->obtenerContexto();
        $sesionesPrev = $this->sesiones->ultimas($userId, 5);
        $tareasPendientes = IATarea::pendientes()->orderByRaw("FIELD(prioridad,'alta','media','baja')")
            ->orderByDesc('id')->limit(30)->get();
        $notasImportantes = IANotaProyecto::importantes()->orderByDesc('id')->limit(20)->get();

        $secciones = [];
        $secciones[] = $this->seccionIdentidad();
        $secciones[] = $this->seccionRepositorio($git, $bd['migraciones_pendientes'] ?? []);
        $secciones[] = $this->seccionCommits($git['commits_recientes'] ?? []);
        $secciones[] = $this->seccionSesionAnterior($sesionesPrev);
        $secciones[] = $this->seccionConversacionesPrevias($userId);
        $secciones[] = $this->seccionTareas($tareasPendientes);
        $secciones[] = $this->seccionNotas($notasImportantes);
        $secciones[] = $this->seccionModulos();
        $secciones[] = $this->seccionEntorno($bd);
        $secciones[] = $this->seccionClaudeMd();

        return implode("\n\n", array_filter($secciones));
    }

    /**
     * Resume las últimas 5 conversaciones IA del usuario para que el modelo tenga
     * memoria entre sesiones distintas sin necesidad de invocar /ultimo manualmente.
     * Solo se incluye título, fecha y los últimos 2 mensajes truncados, para no inflar tokens.
     */
    protected function seccionConversacionesPrevias(?int $userId): string
    {
        if ($userId === null) return '';

        $conversaciones = IAConversacion::query()
            ->where('user_id', $userId)
            ->whereNotNull('ultimo_mensaje_at')
            ->orderByDesc('ultimo_mensaje_at')
            ->limit(5)
            ->get();

        if ($conversaciones->isEmpty()) return '';

        $lineas = ['## Conversaciones IA recientes (memoria entre sesiones)'];
        foreach ($conversaciones as $c) {
            $cuando = optional($c->ultimo_mensaje_at)?->format('Y-m-d H:i');
            $lineas[] = "### {$c->titulo} ({$cuando})";

            $ultimos = IAMensaje::query()
                ->where('ia_conversacion_id', $c->id)
                ->orderByDesc('id')
                ->limit(2)
                ->get()
                ->reverse();

            foreach ($ultimos as $m) {
                $rolLabel = $m->rol === 'user' ? 'Tú' : 'Asistente';
                $resumen = Str::limit(trim((string) $m->contenido), 200);
                $lineas[] = "- **{$rolLabel}:** {$resumen}";
            }
        }
        return implode("\n", $lineas);
    }

    protected function seccionIdentidad(): string
    {
        return <<<MD
# Contexto del proyecto MegaISP (auto-inyectado)

## Quién eres
Eres un asistente experto en desarrollo de MegaISP (Laravel 10 + Vue 3 + Quasar).
Ayudas a Irving a programar, migrar módulos y resolver problemas del sistema.
Responde en español, sé conciso y técnico. Cuando hagas referencia a código,
usa el formato `archivo:linea` para facilitar la navegación.
MD;
    }

    protected function seccionRepositorio(array $git, array $migracionesPendientes): string
    {
        $rama = $git['rama_actual'] ?? 'desconocida';
        $tag = $git['ultimo_tag'] ?? 'sin tag';
        $status = $git['status'] ?? [];
        $statusTxt = empty($status) ? 'limpio' : (count($status) . ' archivos sin commit');
        $migs = empty($migracionesPendientes) ? 'ninguna' : implode(', ', $migracionesPendientes);

        return <<<MD
## Estado actual del repositorio
- Rama: `{$rama}`
- Último tag: `{$tag}`
- Status: {$statusTxt}
- Migraciones pendientes: {$migs}
MD;
    }

    protected function seccionCommits(array $commits): string
    {
        if (empty($commits)) return '';
        $lineas = ['## Últimos commits'];
        foreach ($commits as $c) {
            $lineas[] = "- `{$c['hash']}` ({$c['fecha']}) {$c['autor']}: {$c['mensaje']}";
        }
        return implode("\n", $lineas);
    }

    protected function seccionSesionAnterior(array $sesiones): string
    {
        if (empty($sesiones)) return '';
        $lineas = ['## Sesiones de trabajo recientes'];
        foreach ($sesiones as $s) {
            $inicio = optional($s->inicio_sesion)?->format('Y-m-d H:i');
            $resumen = $s->resumen ? trim($s->resumen) : '(sin resumen)';
            $archivos = is_array($s->archivos_modificados) ? count($s->archivos_modificados) : 0;
            $lineas[] = "- {$inicio} ({$s->proveedor_ia_usado}): {$resumen} — {$archivos} archivos tocados";
        }
        return implode("\n", $lineas);
    }

    protected function seccionTareas($tareas): string
    {
        if ($tareas->isEmpty()) return '';
        $lineas = ['## Tareas pendientes / en progreso'];
        foreach ($tareas as $t) {
            $modulo = $t->modulo_relacionado ? " [{$t->modulo_relacionado}]" : '';
            $lineas[] = "- ({$t->prioridad}/{$t->estado}){$modulo} **{$t->titulo}**" . ($t->descripcion ? " — {$t->descripcion}" : '');
        }
        return implode("\n", $lineas);
    }

    protected function seccionNotas($notas): string
    {
        if ($notas->isEmpty()) return '';
        $lineas = ['## Notas importantes del desarrollador'];
        foreach ($notas as $n) {
            $cat = $n->categoria ? " [{$n->categoria}]" : '';
            $lineas[] = "- **{$n->titulo}**{$cat}: " . trim($n->contenido);
        }
        return implode("\n", $lineas);
    }

    protected function seccionModulos(): string
    {
        $ctx = $this->modulos->obtenerContexto();
        if (empty($ctx['modulos'])) return '';
        $lineas = ['## Módulos activos'];
        foreach ($ctx['modulos'] as $m) {
            $rutasCount = count($m['rutas']);
            $lineas[] = "- **{$m['nombre']}**: {$m['controllers']} controllers, {$m['componentes_vue']} componentes Vue, {$rutasCount} rutas (mod. {$m['ultima_modificacion']})";
        }
        return implode("\n", $lineas);
    }

    protected function seccionEntorno(array $bd): string
    {
        $integraciones = $this->detectarIntegraciones();
        $intStr = empty($integraciones) ? 'ninguna detectada' : implode(', ', $integraciones);

        $php = PHP_VERSION;
        $laravel = app()->version();
        $env = config('app.env');
        $debug = config('app.debug') ? 'on' : 'off';

        return <<<MD
## Entorno
- PHP {$php} / Laravel {$laravel}
- APP_ENV={$env}, APP_DEBUG={$debug}
- Driver BD: {$bd['driver']}
- Integraciones configuradas: {$intStr}
MD;
    }

    protected function seccionClaudeMd(): string
    {
        $path = base_path('CLAUDE.md');
        if (!File::exists($path)) return '';
        $contenido = File::get($path);
        // Si el archivo es muy largo limitamos a 8000 chars para no inflar el contexto
        if (strlen($contenido) > 8000) {
            $contenido = substr($contenido, 0, 8000) . "\n\n[...] (truncado)";
        }
        return "## CLAUDE.md\n" . $contenido;
    }

    protected function detectarIntegraciones(): array
    {
        $envKeys = [
            'MIKROTIK_HOST' => 'MikroTik',
            'RADIUS_HOST' => 'Radius',
            'WHATSAPP_TOKEN' => 'WhatsApp',
            'TELEGRAM_BOT_TOKEN' => 'Telegram',
            'GOOGLE_MAPS_API_KEY' => 'Google Maps',
            'STRIPE_KEY' => 'Stripe',
            'PAYPAL_CLIENT_ID' => 'PayPal',
            'MERCADOPAGO_TOKEN' => 'Mercado Pago',
        ];
        $detectadas = [];
        foreach ($envKeys as $key => $label) {
            if (!empty(env($key))) $detectadas[] = $label;
        }
        return $detectadas;
    }
}
