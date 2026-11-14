<?php

namespace Tests\Feature\Marketing\Fase2\Qa\Concerns;

use App\Models\Core\ApiIntegration;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use App\Models\User;
use App\Modules\Addons\IA\Models\IAAsignacion;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Escenario común de QA · Marketing Fase 2 (contrato docs/marketing/fase-2-paquetes.md §5).
 *
 * - Acceso (regla B de Fase 1): `admin` = fila ACTIVA `rol=admin` en la empresa 1 (la marca del equipo la pone
 *   MarketingTestCase). Además: sin equipo, fila marketing, fila ventas, super-administrador SIN fila, admin de
 *   OTRA empresa e inactivo.
 * - 2 nichos (`familia`, `home_office`) y un Brand Kit mínimo (nombre y teléfono) en la empresa 1.
 * - IA SIMULADA: una integración ficticia del Hub (proveedor `openai`, llave ficticia, endpoint `ia-qa.invalid`)
 *   asignada a `marketing.campana_360`, `marketing.piezas_campana` y `marketing.voz_videos`; `Http::fake` responde
 *   el chat con los fixtures y `/audio/speech` con un mp3 corto generado con ffmpeg. Nada sale a internet:
 *   cualquier otra URL responde 599 y queda registrada en `$this->peticionesAjenas`.
 * - Los trabajos se corren a mano (`correrTrabajos`) sobre `Queue::fake()`: así cada prueba decide el orden y la
 *   respuesta de la IA de cada llamada.
 * Ningún permiso se siembra: los del motor llegan por la migración 2026_11_13_200300.
 */
trait EscenarioFase2
{
    protected const PIEZAS_TEXTO = ['reel_guion', 'video_guion', 'copy_facebook', 'copy_instagram', 'mensaje_whatsapp', 'variante_anuncio'];
    protected const IA_HOST = 'ia-qa.invalid';
    protected const JOB_ESTRATEGIA = 'App\\Modules\\Addons\\Marketing\\Jobs\\GenerarEstrategiaCampanaJob';
    protected const JOB_PIEZAS = 'App\\Modules\\Addons\\Marketing\\Jobs\\GenerarPiezasCampanaJob';
    protected const JOB_REGENERAR = 'App\\Modules\\Addons\\Marketing\\Jobs\\RegenerarPiezaCampanaJob';
    protected const JOB_RENDER = 'App\\Modules\\Addons\\Marketing\\Jobs\\RenderVideoJob';

    protected User $admin;
    protected User $sinEquipo;
    protected User $filaMarketing;
    protected User $filaVentas;
    protected User $superSinFila;
    protected User $adminOtra;
    protected User $inactivo;
    protected Empresa $empresaB;
    protected ?ApiIntegration $integracionIa = null;

    /** Respuestas forzadas de la IA (FIFO). Vacía → respuesta automática con el fixture de la pieza en curso. */
    protected array $respuestasIa = [];
    /** Cuerpos de cada llamada al chat de la IA simulada. */
    protected array $llamadasIa = [];
    /** Llamadas a /audio/speech. */
    protected array $llamadasVoz = [];
    /** Cualquier petición HTTP que NO fue a la IA simulada (Evolution, Graph, etc.). */
    protected array $peticionesAjenas = [];
    /** Pieza que se está generando (la fija correrTrabajos leyendo el trabajo). */
    protected ?string $piezaEnCurso = null;
    protected ?string $varianteEnCurso = null;
    protected bool $vozFalla = false;
    protected ?string $mp3Qa = null;
    /** @var array<int, true> trabajos ya corridos (spl_object_id) */
    protected array $trabajosCorridos = [];
    /** Archivos a borrar en tearDown. */
    protected array $archivosQa = [];

    /** Archivos que el motor deja por diseño o por defecto existente (temp de CTA y voz): se fotografían al empezar. */
    protected array $archivosMotorAntes = [];

    protected function fotografiarArchivosMotor(): void
    {
        $this->archivosMotorAntes = $this->archivosMotor();
    }

    protected function archivosMotor(): array
    {
        return array_merge(
            glob(storage_path('app/marketing/temp/cta1_*.mp4')) ?: [],
            glob(storage_path('app/marketing/audio/voiceover/1/*.mp3')) ?: [],
        );
    }

    protected function montarEscenarioFase2(bool $conIa = true): void
    {
        $this->fotografiarArchivosMotor();
        $this->contexto()->limpiar();
        Queue::fake();
        $this->fakeHttp();

        $principal = Empresa::PRINCIPAL_ID;
        $this->empresaB = $this->crearEmpresa('QA2 Empresa B');

        $this->admin = $this->crearUsuario('qa2_admin', [$principal => 'admin'], ['super-administrator']);
        $this->sinEquipo = $this->crearUsuario('qa2_sin_equipo', [], []);
        $this->filaMarketing = $this->crearUsuario('qa2_mkt', [$principal => 'marketing'], []);
        $this->filaVentas = $this->crearUsuario('qa2_ventas', [$principal => 'ventas'], []);
        $this->superSinFila = $this->crearUsuario('qa2_super', [], ['super-administrator']);
        $this->adminOtra = $this->crearUsuario('qa2_admin_b', [$this->empresaB->id => 'admin'], ['super-administrator']);
        $this->inactivo = $this->crearUsuario('qa2_inactivo', [$principal => 'admin'], ['super-administrator'], ['estado' => 'inactivo']);

        $this->nicho('familia', 'Familia QA', 'nova', 'uplifting_family');
        $this->nicho('home_office', 'Home office QA', 'echo', 'corporate_clean');
        foreach (['brand_company_name' => 'Meganet QA', 'brand_phone' => '55 0000 0000', 'brand_website' => 'qa.invalid'] as $k => $v) {
            DB::table('marketing_settings')->updateOrInsert(
                ['company_id' => $principal, 'key' => $k],
                ['value' => $v, 'encrypted' => 0, 'group' => 'brand', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null]
            );
        }

        if ($conIa) {
            $this->asignarIa(['marketing.campana_360', 'marketing.piezas_campana', 'marketing.voz_videos']);
        }
    }

    protected function contexto(): EmpresaActiva
    {
        return app(EmpresaActiva::class);
    }

    // ── IA simulada ──────────────────────────────────────────────────────────

    protected function asignarIa(array $claves): void
    {
        if (! $this->integracionIa) {
            $i = new ApiIntegration();
            $i->forceFill([
                'company_id' => 1, 'provider' => 'openai', 'type' => 'ia', 'slug' => 'qa2-ia-' . Str::lower(Str::random(8)),
                'name' => 'QA2 IA simulada', 'config' => ['endpoint' => 'https://' . self::IA_HOST . '/v1/chat/completions'],
                'active' => 1, 'is_default_for_provider' => 0,
            ]);
            $i->value = 'qa-llave-ficticia-sin-valor';
            $i->save();
            $this->integracionIa = $i;
        }
        foreach ($claves as $clave) {
            IAAsignacion::query()->updateOrCreate(['clave' => $clave], [
                'api_integration_id' => $this->integracionIa->id,
                'modelo' => $clave === 'marketing.voz_videos' ? 'tts-1' : 'gpt-qa-simulado',
            ]);
        }
    }

    protected function quitarIa(array $claves): void
    {
        IAAsignacion::query()->whereIn('clave', $claves)->delete();
        // también la general del grupo, por si alguien la puso
        IAAsignacion::query()->where('clave', 'like', 'grupo:%')->where('clave', 'like', '%arketing%')->delete();
    }

    protected function fakeHttp(): void
    {
        Http::fake(function (Request $req) {
            $url = $req->url();
            if (! str_contains($url, self::IA_HOST)) {
                $this->peticionesAjenas[] = $req->method() . ' ' . $url;

                return Http::response(['error' => 'QA: salida bloqueada'], 599);
            }
            if (str_ends_with($url, '/audio/speech')) {
                $this->llamadasVoz[] = $req->data();
                if ($this->vozFalla) {
                    return Http::response(['error' => ['message' => 'QA: voz caída']], 500);
                }

                return Http::response($this->mp3(), 200, ['Content-Type' => 'audio/mpeg']);
            }
            $this->llamadasIa[] = $req->body();
            $r = $this->respuestasIa ? array_shift($this->respuestasIa) : $this->respuestaAutomatica();
            if ($r instanceof \Closure) {
                $r = $r($req);
            }
            if (is_array($r) && isset($r['__status'])) {
                return Http::response(['error' => ['message' => $r['__mensaje'] ?? 'QA: IA caída']], $r['__status']);
            }

            return Http::response([
                'id' => 'qa', 'object' => 'chat.completion', 'model' => 'gpt-qa-simulado',
                'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => is_string($r) ? $r : json_encode($r, JSON_UNESCAPED_UNICODE)]]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 10],
            ], 200);
        });
    }

    /** Cuerpo de la llamada $i a la IA, con el JSON decodificado (acentos legibles). */
    protected function llamadaLegible(int $i): string
    {
        $b = $this->llamadasIa[$i] ?? '';
        $d = json_decode($b, true);

        return is_array($d) ? json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $b;
    }

    protected function respuestaAutomatica(): string
    {
        if ($this->piezaEnCurso === null) {
            return $this->fixtureCrudo('estrategia-valida.json');
        }
        $d = $this->fixture("piezas-{$this->piezaEnCurso}-validas.json");
        if ($this->varianteEnCurso !== null) {
            $d['piezas'] = array_values(array_filter($d['piezas'], fn ($p) => $p['variante'] === $this->varianteEnCurso));
        }

        return json_encode($d, JSON_UNESCAPED_UNICODE);
    }

    protected function fixture(string $nombre): array
    {
        return json_decode($this->fixtureCrudo($nombre), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function fixtureCrudo(string $nombre): string
    {
        return (string) file_get_contents(__DIR__ . '/../fixtures/ia/' . $nombre);
    }

    /** contenido válido de una pieza/variante, tal cual el fixture. */
    protected function contenidoValido(string $pieza, string $variante = 'A'): array
    {
        foreach ($this->fixture("piezas-{$pieza}-validas.json")['piezas'] as $p) {
            if ($p['variante'] === $variante) {
                return $p['contenido'];
            }
        }
        $this->fail("No hay fixture {$pieza}/{$variante}");
    }

    /** Aplica un caso de fixtures/ia/piezas-invalidas.json y devuelve [pieza, contenido]. */
    protected function casoInvalido(string $caso): array
    {
        $c = $this->fixture('piezas-invalidas.json')[$caso];
        $contenido = $this->contenidoValido($c['pieza']);
        $valor = $c['valor'];
        if ($valor === '__REPETIR_701__') {
            $valor = str_repeat('a', 701);
        }
        if ($valor === '__QUITAR_ULTIMAS_1__') {
            $valor = array_slice($contenido['escenas'], 0, count($contenido['escenas']) - 1);
        }
        data_set($contenido, $c['ruta'], $valor);

        return [$c['pieza'], $contenido];
    }

    protected function mp3(): string
    {
        if ($this->mp3Qa === null) {
            $base = tempnam(sys_get_temp_dir(), 'qa2-voz-');
            $f = $base . '.mp3';
            exec('ffmpeg -nostdin -loglevel error -y -f lavfi -i sine=frequency=440:duration=2 -c:a libmp3lame -b:a 64k ' . escapeshellarg($f));
            $this->mp3Qa = (string) @file_get_contents($f);
            @unlink($f);
            @unlink($base);
        }

        return $this->mp3Qa;
    }

    // ── Trabajos ────────────────────────────────────────────────────────────

    /**
     * Corre (en este proceso) los trabajos empujados a la cola falsa que aún no corrieron, hasta que no quede
     * ninguno nuevo. Emula al worker con tries=1: si handle() lanza, llama failed() y sigue.
     *
     * @param  array<int,string>  $clases  qué clases correr (por defecto las 3 de IA; el render solo si se pide)
     * @return array<int,string> errores de handle()
     */
    protected function correrTrabajos(array $clases = [self::JOB_ESTRATEGIA, self::JOB_PIEZAS, self::JOB_REGENERAR], int $tope = 30): array
    {
        $errores = [];
        for ($vuelta = 0; $vuelta < $tope; $vuelta++) {
            $pendientes = [];
            foreach ($clases as $clase) {
                foreach (Queue::pushed($clase) as $job) {
                    if (! isset($this->trabajosCorridos[spl_object_id($job)])) {
                        $pendientes[] = $job;
                    }
                }
            }
            if (! $pendientes) {
                return $errores;
            }
            foreach ($pendientes as $job) {
                $this->trabajosCorridos[spl_object_id($job)] = true;
                $this->prepararIaPara($job);
                try {
                    app()->call([$job, 'handle']);
                } catch (\Throwable $e) {
                    $errores[] = get_class($job) . ': ' . $e->getMessage();
                    if (method_exists($job, 'failed')) {
                        $job->failed($e);
                    }
                } finally {
                    $this->piezaEnCurso = null;
                    $this->varianteEnCurso = null;
                }
            }
        }
        $this->fail('correrTrabajos: más de ' . $tope . ' vueltas (¿un trabajo se re-despacha solo?)');
    }

    /** Lee del trabajo (sin conocer el nombre de sus propiedades) qué pieza / qué fila genera. */
    protected function prepararIaPara(object $job): void
    {
        $this->piezaEnCurso = null;
        $this->varianteEnCurso = null;
        $valores = [];
        foreach ((new \ReflectionObject($job))->getProperties() as $p) {
            $p->setAccessible(true);
            if ($p->isInitialized($job)) {
                $valores[$p->getName()] = $p->getValue($job);
            }
        }
        if (get_class($job) === self::JOB_PIEZAS) {
            foreach ($valores as $n => $v) {
                if (is_string($v) && in_array($v, self::PIEZAS_TEXTO, true)) {
                    $this->piezaEnCurso = $v;
                }
                // Decisión de Henry (13-nov): los guiones con 2+ variantes van UNA variante por trabajo.
                if ($n === 'variante' && is_string($v) && in_array($v, ['A', 'B', 'C'], true)) {
                    $this->varianteEnCurso = $v;
                }
            }
        }
        if (get_class($job) === self::JOB_REGENERAR) {
            foreach ($valores as $n => $v) {
                if (is_int($v) && str_contains(strtolower($n), 'pieza')) {
                    $fila = DB::table('marketing_generated_content')->find($v);
                    if ($fila) {
                        $this->piezaEnCurso = $fila->pieza;
                        $this->varianteEnCurso = $fila->variante;
                    }
                }
            }
        }
    }

    protected function trabajosDe(string $clase): array
    {
        return Queue::pushed($clase)->all();
    }

    // ── Datos ───────────────────────────────────────────────────────────────

    protected function nicho(string $slug, string $nombre, string $voz, string $musica, int $empresa = 1): void
    {
        DB::table('marketing_niches')->updateOrInsert(['company_id' => $empresa, 'slug' => $slug], [
            'name' => $nombre, 'description' => 'QA', 'motivators' => '["Series sin pausas"]', 'pain_points' => '["Se traba"]',
            'objections' => '["Precio"]', 'vocabulary' => '["hogar"]', 'emotional_tone' => 'warm', 'music_mood' => $musica,
            'broll_tags' => '[]', 'voice_style' => 'warm', 'preferred_voice_id' => $voz, 'preferred_voice_model' => 'tts-1',
            'active' => 1, 'display_order' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now('America/Mexico_City');
    }

    /** Cuerpo válido del asistente (§2.4.2). */
    protected function cuerpoCampana(array $cambios = []): array
    {
        $base = [
            'nombre' => 'QA2 Fibra 500 ' . Str::upper(Str::random(4)),
            'objetivo' => 'nuevos_clientes',
            'publico' => ['descripcion' => 'Familias en colonias con fibra nueva', 'zonas' => ['Santa Inés', 'Centro'],
                'edad_min' => 25, 'edad_max' => 55, 'nichos' => ['familia', 'home_office'], 'intereses' => ['streaming']],
            'oferta' => ['descripcion' => '500 Megas con instalación gratis', 'plan' => 'Fibra 500', 'precio_mxn' => '599',
                'condiciones' => 'Primeros 100 clientes', 'vigencia_hasta' => $this->hoy()->addDays(40)->toDateString()],
            'canales' => ['facebook', 'instagram', 'reels', 'video', 'anuncios_meta', 'whatsapp'],
            'presupuesto_mxn' => '15000.00',
            'fecha_inicio' => $this->hoy()->addDay()->toDateString(),
            'fecha_fin' => $this->hoy()->addDays(31)->toDateString(),
        ];
        foreach ($cambios as $k => $v) {
            data_set($base, $k, $v);
        }

        return $base;
    }

    /** Crea por la API (como admin) y devuelve el id. */
    protected function crearCampanaApi(array $cambios = []): int
    {
        $r = $this->como($this->admin)->postJson('/api/marketing/campanas-360', $this->cuerpoCampana($cambios));
        $r->assertStatus(201);

        return (int) $r->json('data.id');
    }

    /** Campaña por la API + estrategia + piezas generadas con la IA simulada. */
    protected function campanaGenerada(array $cambios = []): int
    {
        $id = $this->crearCampanaApi($cambios);
        $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'todo', 'reemplazar' => false])->assertStatus(202);
        $err = $this->correrTrabajos();
        $this->assertSame([], $err, 'Los trabajos de generación lanzaron: ' . implode(' | ', $err));

        return $id;
    }

    /** id de la fila de una pieza de la campaña. */
    protected function piezaId(int $campanaId, string $pieza, string $variante = 'A'): int
    {
        $id = DB::table('marketing_generated_content')->where('source_campaign_id', $campanaId)
            ->where('pieza', $pieza)->where('variante', $variante)->whereNull('deleted_at')->value('id');
        $this->assertNotNull($id, "No existe la pieza {$pieza}/{$variante} de la campaña {$campanaId}");

        return (int) $id;
    }

    /** Campaña creada directo en BD (para ids ajenos / otra empresa). */
    protected function campanaDirecta(int $companyId, array $attrs = []): Campaign
    {
        $this->contexto()->limpiar();
        $c = new Campaign();
        $c->forceFill(array_merge([
            'company_id' => $companyId, 'name' => 'QA2 directa ' . Str::random(5), 'slug' => 'qa2-' . Str::lower(Str::random(12)),
            'type' => 'mixed', 'status' => 'draft', 'currency' => 'MXN', 'budget' => '1000.00', 'spent' => '0.00',
            'start_date' => $this->hoy()->addDay()->toDateString(), 'end_date' => $this->hoy()->addDays(10)->toDateString(),
            'objetivo' => 'promocion', 'canales' => ['reels'], 'estrategia_estado' => 'sin_generar',
            'publico' => ['descripcion' => 'QA'], 'oferta' => ['descripcion' => 'QA'],
        ], $attrs));
        $c->save();

        return $c;
    }

    protected function contarFilas(string $tabla, array $donde = []): int
    {
        return DB::table($tabla)->where($donde)->count();
    }

    // ── Sesión ──────────────────────────────────────────────────────────────

    protected function como(User $u): static
    {
        $this->contexto()->limpiar();
        $this->flushSession();
        $this->actingAs($u);

        return $this;
    }

    protected function anonimo(): static
    {
        $this->contexto()->limpiar();
        $this->flushSession();
        app('auth')->forgetGuards();

        return $this;
    }

    protected function crearEmpresa(string $nombre): Empresa
    {
        $id = DB::table('marketing_empresas')->insertGetId([
            'nombre' => $nombre . ' ' . Str::upper(Str::random(5)), 'slug' => 'qa2-' . Str::lower(Str::random(12)),
            'color_primario' => '#1F3A93', 'color_secundario' => null, 'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return Empresa::query()->findOrFail($id);
    }

    protected function crearUsuario(string $prefijo, array $asignaciones, array $roles, array $extra = []): User
    {
        $u = User::factory()->create(array_merge([
            'name' => 'QA2 ' . $prefijo, 'father_last_name' => 'PRUEBA',
            'login_user' => $prefijo . '_' . Str::lower(Str::random(8)), 'estado' => 'activo', 'is_system' => 0,
        ], $extra));
        foreach ($roles as $r) {
            $u->assignRole($r);
        }
        foreach ($asignaciones as $companyId => $rol) {
            DB::table('marketing_empresa_usuario')->updateOrInsert(
                ['company_id' => $companyId, 'user_id' => $u->id],
                ['rol' => $rol, 'activo' => 1, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        return $u->fresh();
    }

    protected function etiqueta(TestResponse $r): string
    {
        return $r->getStatusCode() . ' ' . Str::limit(preg_replace('/\s+/', ' ', strip_tags((string) $r->getContent())), 300);
    }

    protected function limpiarArchivosQa(): void
    {
        // Solo videos de campañas de ESTA prueba (nombre «QA2 …»): nunca archivos de la vista previa PREV-F2 de frontend.
        $campanas = DB::table('marketing_campaigns')->where('name', 'like', 'QA2 %')->pluck('id');
        foreach (DB::table('marketing_generated_content')->whereIn('source_campaign_id', $campanas)->where('type', 'video')
            ->whereNotNull('output_path')->get(['output_path', 'thumbnail_path']) as $v) {
            $this->archivosQa[] = storage_path('app/' . $v->output_path);
            if ($v->thumbnail_path) {
                $this->archivosQa[] = storage_path('app/' . $v->thumbnail_path);
            }
        }
        // Lo que el render dejó en temp/ y en audio/voiceover/ durante ESTA prueba (ver hallazgo del reporte de QA).
        if ($this->archivosMotorAntes !== [] || $this->archivosMotor() !== []) {
            foreach (array_diff($this->archivosMotor(), $this->archivosMotorAntes) as $f) {
                $this->archivosQa[] = $f;
            }
        }
        foreach (array_unique($this->archivosQa) as $f) {
            if (is_string($f) && str_starts_with($f, storage_path('app/marketing/')) && is_file($f)) {
                @unlink($f);
            }
        }
        $this->archivosQa = [];
    }
}
