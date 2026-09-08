<?php

namespace App\Modules\Core\Release\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\DeployJob;
use App\Jobs\GenerateReleaseChangelogJob;
use App\Models\DeploymentLog;
use App\Models\Release;
use App\Models\ReleaseDescription;
use App\Services\ReleaseChangelogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\Process\Process;
class ReleaseController extends Controller
{
    /** Datos que se pasan a la vista (evita "Creation of dynamic property" en PHP 8.2). */
    protected array $data = [];

    public function __construct()
    {
        $model = 'Release';
        $this->data['url'] = 'meganet.module.releases';
        $this->data['module'] = 'Release';
        $this->data['model'] = 'App\Models\\' . $model;
    }

    public function index(Request $request)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);

        // Carga solo la primera página
        $perPage = 10;
        $releases = Release::orderByDesc('release_date')->paginate($perPage);

        // Marca cada release con `tag_exists`: si su tag NO existe en git, la release
        // quedó "fantasma" (registrada en BD pero el pipeline se abortó antes de
        // git_tag) → el front ofrece "Re-desplegar". Si no se pudo consultar git
        // (null) se asume publicado (true) para NO ofrecer un re-deploy sin verificar.
        $tags = $this->gitTags();
        $reversibilidad = app(\App\Services\ReleaseReversibilityService::class);
        $releases->getCollection()->transform(function ($r) use ($tags, $reversibilidad) {
            $r->tag_exists = $tags === null ? true : in_array($r->version, $tags, true);
            // Item #1020: estado de reversibilidad (limpio/con_perdida/no_reversible/sin_datos)
            // para pintarlo en la tarjeta sin tener que abrirla.
            $r->reversibilidad = $reversibilidad->estadoPara($r);
            return $r;
        });

        // Si la solicitud es AJAX, devuelve solo JSON
        if ($request->ajax()) {
            return response()->json($releases);
        }

        $this->data['releases'] = $releases->items(); // solo los datos
        $this->data['next_page_url'] = $releases->nextPageUrl();

        return view('meganet.module.releases.index', $this->data);
    }

    public function show(string $version)
    {
        $this->data['notifications'] = $this->userNotification();
        $this->includeLibraryDinamic($this->data['model']);
        $release = Release::where('version', $version)->firstOrFail();
        $descriptions = ReleaseDescription::where('release_id', $release->id)->get();
        $this->data['release'] = $release;
        $this->data['descriptions'] = $descriptions;
        return view('meganet.module.releases.show', $this->data);
    }

    public function store(Request $request)
    {
        // Dev SÍ puede crear Release y correr el pipeline para probarlo end-to-end, pero
        // los pasos que publican de verdad (push a GitHub, GitHub Release, deploy remoto)
        // quedan gateados por entorno en config/deployment.php ('skip_if_not_production').
        // Ver DeploymentService::run(). Decisión item roadmap #245.

        try {
            $validator = Validator::make($request->all(), [
                'version' => ['required', 'string', 'max:50', 'unique:releases,version'],
                'title' => ['nullable', 'string', 'max:255'],
                'summary' => ['nullable', 'string'],
                'release_date' => ['required', 'date'],
            ], [
                'version.required' => 'La version es obligatoria.',
                'version.unique' => 'Ya existe una version con ese nombre.',
                'title.required' => 'El titulo es obligatorio.',
                'release_date.required' => 'La fecha de lanzamiento es obligatoria.',
            ]);
            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();

            // #versionado-2026-09-08 — GUARD ANTI-RETROCESO. La validación de arriba sólo exige que
            // el nombre no exista en la tabla; no impide un consecutivo MENOR al ya publicado. Éste
            // es el punto donde el número deja de poder retroceder «sin importar quién lance el
            // release»: se rechaza cualquier build <= al máximo real en los tags git. Fail-closed
            // (si no se puede consultar el remoto, NextVersionResolver lanza y el release se aborta).
            try {
                $resolver = app(\App\Services\Updates\NextVersionResolver::class);
                $maxPublicado = $resolver->buildMaximoPublicado();
                if (preg_match('/^V\d+\.(\d+)-/', (string) $data['version'], $mv)) {
                    $buildPedido = (int) $mv[1];
                    if ($buildPedido <= $maxPublicado) {
                        return response()->json([
                            'success' => false,
                            'message' => "La versión {$data['version']} (build {$buildPedido}) es MENOR o igual "
                                . "al último build publicado ({$maxPublicado}). Emitir ese número haría que "
                                . 'producción no viera el release. Usa el consecutivo sugerido (build '
                                . ($maxPublicado + 1) . ').',
                        ], 422);
                    }
                }
            } catch (\Throwable $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo verificar el consecutivo contra los tags del remoto: '
                        . $e->getMessage(),
                ], 503);
            }

            $data['created_by'] = auth()->user()->id;

            // El respaldo de la BD ya NO corre aquí (síncrono): se movió al pipeline
            // de deploy como primer paso crítico ('db_backup'), que corre en el worker
            // sin límite de memoria/timeout web y se ve en el modal de avance.

            DB::beginTransaction();
            $release = Release::create($data);

            // Si el usuario generó un resumen con IA, crearlo como release_description
            $aiDescription = trim($request->input('ai_description', ''));
            if ($aiDescription !== '') {
                ReleaseDescription::create([
                    'release_id'  => $release->id,
                    'title'       => 'Mejoras de esta versión (generado por IA)',
                    'description' => nl2br(e($aiDescription)),
                    'created_by'  => auth()->user()->id,
                ]);
            }

            $deployLog = DeploymentLog::create([
                'release_id'   => $release->id,
                'triggered_by' => auth()->user()->id,
                'status'       => 'pending',
            ]);

            $this->dispatchDeploy($deployLog, $release->version, $release->title ?? '');

            DB::commit();
            return response()->json([
                'success'       => true,
                'message'       => 'Versión creada. El pipeline de release ha sido iniciado.',
                'model'         => $release,
                'deployment_id' => $deployLog->id,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear version: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al crear la version.',
            ], 500);
        }
    }

    /**
     * Elige cómo ejecutar el deploy según el entorno:
     * - Con worker (queue.default = database): encola el job → progreso en vivo vía polling
     * - Sin worker (queue.default = sync, ej. local): lanza artisan en background → mismo polling
     */
    private function dispatchDeploy(DeploymentLog $deployLog, string $version, string $title): void
    {
        if (config('queue.default') !== 'sync') {
            DeployJob::dispatch($deployLog, $version, $title)
                ->onConnection('database')
                ->onQueue('deploy');
            return;
        }

        // Local sin worker: proceso artisan en background (nohup)
        $artisan = base_path('artisan');
        $logFile = storage_path('logs/deploy-' . $deployLog->id . '.log');
        $cmd     = sprintf(
            'nohup %s %s release:deploy %d > %s 2>&1 &',
            PHP_BINARY,
            escapeshellarg($artisan),
            $deployLog->id,
            escapeshellarg($logFile)
        );
        shell_exec($cmd);
        Log::info("Deploy #{$deployLog->id} lanzado como proceso background (local).");
    }

    /**
     * Tags git existentes en el repo local — fuente de verdad de "publicado".
     * Devuelve null si no se pudo consultar git (el caller lo trata como
     * "publicado": fail-safe, no ofrece re-deploy de algo que no pudo verificar).
     * safe.directory replica el patrón de RemoteDeployCommand para evitar el
     * bloqueo de git por dueño distinto (www-data vs. el del checkout).
     */
    private function gitTags(): ?array
    {
        try {
            $env = array_merge(getenv() ?: [], [
                'PATH'               => (getenv('PATH') ?: '') . ':/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                'HOME'               => getenv('HOME') ?: '/tmp',
                'GIT_CONFIG_COUNT'   => '1',
                'GIT_CONFIG_KEY_0'   => 'safe.directory',
                'GIT_CONFIG_VALUE_0' => base_path(),
            ]);

            $process = Process::fromShellCommandline('git tag', base_path(), $env, null, 15);
            $process->run();

            if (!$process->isSuccessful()) {
                Log::warning('gitTags(): git tag falló — ' . trim($process->getErrorOutput()));
                return null;
            }

            return array_values(array_filter(array_map('trim', explode("\n", $process->getOutput()))));
        } catch (\Throwable $e) {
            Log::warning('gitTags(): excepción — ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Re-lanza el pipeline de una release "fantasma": registrada en BD pero cuyo
     * tag git nunca se creó (el deploy se abortó antes de git_tag). Condición de
     * seguridad REFORZADA en el server: si el tag YA existe, la versión está
     * publicada y re-desplegar la re-taggearía/duplicaría → 422.
     */
    public function redeploy(int $id)
    {
        $release = Release::find($id);
        if (!$release) {
            return response()->json(['success' => false, 'message' => 'La versión no existe.'], 404);
        }

        $tags = $this->gitTags();
        if ($tags === null) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo verificar el estado de git. Reintenta en un momento.',
            ], 503);
        }

        if (in_array($release->version, $tags, true)) {
            return response()->json([
                'success' => false,
                'message' => "El tag {$release->version} ya existe en git: la versión ya está publicada.",
            ], 422);
        }

        $deployLog = DeploymentLog::create([
            'release_id'   => $release->id,
            'triggered_by' => auth()->user()->id,
            'status'       => 'pending',
        ]);

        $this->dispatchDeploy($deployLog, $release->version, $release->title ?? '');

        return response()->json([
            'success'       => true,
            'message'       => 'Re-deploy iniciado.',
            'deployment_id' => $deployLog->id,
            'version'       => $release->version,
        ], 200);
    }

    /**
     * Item roadmap #1021 (sub-item 5/5 de #1012) — "Generar plan de regreso": documento
     * markdown de SOLO LECTURA para producción (el circuito nunca ejecuta nada en prod).
     */
    public function planRegreso(int $id)
    {
        $release = Release::find($id);
        if (!$release) {
            return response()->json(['success' => false, 'message' => 'La versión no existe.'], 404);
        }

        $markdown = app(\App\Services\ReleaseRollbackPlanService::class)->generarMarkdown($release);

        return response()->json([
            'success'  => true,
            'version'  => $release->version,
            'markdown' => $markdown,
        ]);
    }

    /**
     * Item roadmap #9990626 (Fase 2+3 de #9990624) — antes esto llamaba a
     * ReleaseChangelogService::generate() de forma SÍNCRONA (2-4 min con rangos grandes de
     * commits), bloqueando el request HTTP. Ahora solo encola el job y responde de inmediato;
     * el front hace polling a changelogStatus() con el request_id devuelto aquí.
     */
    public function generateChangelog(Request $request)
    {
        $version = trim($request->input('version', 'nueva'));
        // #966 Fase 5 — rama opcional (la rama de release recién construida en Fase 4): sin ella,
        // el comportamiento es idéntico al de antes (describe HEAD/main).
        $branch  = trim((string) $request->input('branch', '')) ?: null;

        $requestId = (string) Str::uuid();
        Cache::put(
            GenerateReleaseChangelogJob::cacheKey($requestId),
            ['status' => 'pendiente'],
            now()->addMinutes(GenerateReleaseChangelogJob::CACHE_TTL_MINUTES)
        );

        GenerateReleaseChangelogJob::dispatch($requestId, $version, $branch);

        return response()->json([
            'success'    => true,
            'request_id' => $requestId,
        ]);
    }

    /**
     * Polling del resultado de generateChangelog() (item roadmap #9990626). Devuelve
     * {status: 'pendiente'|'listo'|'error', ...}. 'listo' trae los mismos campos que antes
     * devolvía generateChangelog() de forma síncrona (title/summary/improvements/cobertura).
     */
    public function changelogStatus(string $requestId)
    {
        $cached = Cache::get(GenerateReleaseChangelogJob::cacheKey($requestId));

        if (!$cached) {
            return response()->json([
                'status'  => 'error',
                'message' => 'La solicitud expiró o no existe. Intenta generar de nuevo.',
            ], 404);
        }

        if ($cached['status'] === 'listo') {
            $result = $cached['result'];
            return response()->json([
                'status'             => 'listo',
                'title'              => $result['title'] ?? '',
                'summary'            => $result['summary'] ?? '',
                'improvements'       => $result['improvements'] ?? '',
                // Cobertura del rango (item roadmap #892): permite a la UI avisar si el resumen
                // quedó incompleto en vez de presentarlo como si cubriera todo el rango.
                'total_commits'      => $result['total_commits'] ?? 0,
                'resumidos_commits'  => $result['resumidos_commits'] ?? 0,
                'desde_tag'          => $result['desde_tag'] ?? null,
                'truncado'           => $result['truncado'] ?? false,
                'aviso_truncamiento' => $result['aviso_truncamiento'] ?? null,
            ]);
        }

        if ($cached['status'] === 'error') {
            return response()->json([
                'status'  => 'error',
                'message' => $cached['message'] ?? 'No se pudo generar el resumen.',
            ], 500);
        }

        return response()->json(['status' => 'pendiente']);
    }

    /**
     * Sugiere la siguiente versión a partir de la mayor estilo V{mayor}.{menor} existente:
     * sube el menor +1 y anexa la fecha de hoy DD.MM.AAAA. Ej.: máx V1.2.x → V1.3-19.06.2026.
     * El campo es editable en el front; la regla NO la pone la IA.
     */
    public function nextVersion()
    {
        // #versionado-2026-09-08 — el consecutivo sale de los TAGS DE GIT, no de la tabla `releases`.
        // Antes se calculaba con `Release::pluck('version')`, y cuando esa tabla perdió los builds
        // 16–32 el número RETROCEDIÓ (dev emitió V1.20 con prod en V1.32). Los tags son la historia
        // real e inmutable de lo publicado. Ver App\Services\Updates\NextVersionResolver.
        try {
            $r = app(\App\Services\Updates\NextVersionResolver::class)->resolver();

            return response()->json([
                'success'       => true,
                'version'       => $r['label'],
                'build'         => $r['build'],
                'max_detectado' => $r['max_detectado'],
                'origen'        => $r['origen'],
            ]);
        } catch (\Throwable $e) {
            // Fail-closed: sin la historia real del remoto NO se sugiere un número (adivinar es lo
            // que trajo la divergencia). El front debe mostrar el error, no un consecutivo inventado.
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 503);
        }
    }

    public function update(Request $request, $id)
    {
        $release = Release::find($id);
        if (!$release) {
            return response()->json([
                'success' => false,
                'message' => 'La version no existe.',
            ], 404);
        }
        $validator = Validator::make($request->all(), [
            'version' => [
                'required',
                'string',
                'max:50',
                Rule::unique('releases', 'version')->ignore($release->id),
            ],
            'title' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'release_date' => ['required', 'date'],
        ], [
            'version.required' => 'La versión es obligatoria.',
            'version.unique' => 'Ya existe una versión con ese nombre.',
            'release_date.required' => 'La fecha de lanzamiento es obligatoria.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            DB::beginTransaction();
            $data = $validator->validated();
            $data['updated_by'] = auth()->user()->id;
            $release->update($data);
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'La versión se ha actualizado correctamente.',
                'model' => $release,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar version: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error al actualizar la version.',
            ], 500);
        }
    }
}
