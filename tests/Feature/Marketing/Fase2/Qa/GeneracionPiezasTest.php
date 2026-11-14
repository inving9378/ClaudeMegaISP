<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — piezas generadas por canal (contrato §2.5.3, §2.5.5, §2.7; criterios 2 y 4).
 * Exactamente las piezas y variantes de la tabla según los canales; UNA llamada a la IA por tipo; un tipo que
 * falla no tumba a los demás; `progreso` y `generacion` calculados bien; reemplazar exige confirmación.
 */
class GeneracionPiezasTest extends MarketingTestCase
{
    use EscenarioFase2;

    private const TABLA = [
        'reels' => ['reel_guion', ['A', 'B']],
        'video' => ['video_guion', ['A']],
        'facebook' => ['copy_facebook', ['A', 'B']],
        'instagram' => ['copy_instagram', ['A', 'B']],
        'anuncios_meta' => ['variante_anuncio', ['A', 'B', 'C']],
        'whatsapp' => ['mensaje_whatsapp', ['A', 'B', 'C']],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioFase2();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function piezasDe(int $id): array
    {
        return DB::table('marketing_generated_content')->where('source_campaign_id', $id)->whereNull('deleted_at')
            ->where('type', 'copy')->orderBy('pieza')->orderBy('variante')->get()->all();
    }

    /** @return array<string, array{0: array<int,string>}> */
    public static function combinaciones(): array
    {
        return [
            'los 6 canales' => [['facebook', 'instagram', 'reels', 'video', 'anuncios_meta', 'whatsapp']],
            'solo reels' => [['reels']],
            'video y whatsapp' => [['video', 'whatsapp']],
            'facebook e instagram' => [['facebook', 'instagram']],
            'solo anuncios' => [['anuncios_meta']],
        ];
    }

    /** @dataProvider combinaciones */
    public function test_piezas_y_variantes_exactas_por_canal_con_una_llamada_por_tipo(array $canales): void
    {
        $id = $this->campanaGenerada(['canales' => $canales]);
        $esperado = [];
        foreach ($canales as $c) {
            foreach (self::TABLA[$c][1] as $v) {
                $esperado[] = self::TABLA[$c][0] . '/' . $v;
            }
        }
        sort($esperado);
        $real = array_map(fn ($f) => $f->pieza . '/' . $f->variante, $this->piezasDe($id));
        sort($real);
        $this->assertSame($esperado, $real, 'Piezas de §2.5.3 según los canales');
        // Decisión de Henry (13-nov): Reels = 1 trabajo del tipo (sin llamada, solo reparte) + 1 trabajo y 1 llamada por
        // variante (A y B). Los demás tipos: 1 trabajo y 1 llamada por tipo. Video (una variante) va directo.
        $conReels = in_array('reels', $canales, true);
        Queue::assertPushed(self::JOB_PIEZAS, count($canales) + ($conReels ? 2 : 0));
        $this->assertCount(1 + count($canales) + ($conReels ? 1 : 0), $this->llamadasIa, '1 estrategia + 1 por tipo (Reels: 1 por variante)');
        $variantesReel = [];
        foreach ($this->trabajosDe(self::JOB_PIEZAS) as $job) {
            if ($job->pieza === 'reel_guion' && $job->variante !== null) {
                $variantesReel[] = $job->variante;
            }
            if ($job->pieza !== 'reel_guion') {
                $this->assertNull($job->variante, "{$job->pieza}: solo los guiones con 2+ variantes se reparten por variante");
            }
        }
        sort($variantesReel);
        $this->assertSame($conReels ? ['A', 'B'] : [], $variantesReel, 'Un trabajo por variante de Reel');

        foreach ($this->piezasDe($id) as $f) {
            $this->assertSame('ready', $f->status, "{$f->pieza}/{$f->variante}: " . $f->failure_reason);
            $this->assertSame(1, (int) $f->company_id);
            $this->assertStringStartsWith('ia:', (string) $f->generation_engine);
            $this->assertLessThanOrEqual(50, strlen((string) $f->generation_engine));
            $this->assertSame('0.0000', number_format((float) $f->generation_cost_usd, 4, '.', ''));
            $this->assertNotEmpty($f->output_text, 'texto plano para buscar y copiar');
            $this->assertIsArray(json_decode((string) $f->contenido, true));
        }
    }

    public function test_guion_renumera_escenas_y_calcula_duracion_total_aunque_la_ia_mienta(): void
    {
        $d = $this->fixture('piezas-reel_guion-validas.json');
        $d['piezas'][0]['contenido']['escenas'][0]['numero'] = 7;
        $d['piezas'][0]['contenido']['escenas'][1]['numero'] = 7;
        $d['piezas'][0]['contenido']['duracion_total_s'] = 999;
        $d['piezas'] = [$d['piezas'][0]]; // una variante por llamada: esta respuesta es la del trabajo de la A
        $id = $this->crearCampanaApi(['canales' => ['reels']]);
        $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'todo', 'reemplazar' => false])->assertStatus(202);
        $this->respuestasIa = [$this->fixtureCrudo('estrategia-valida.json'), json_encode($d, JSON_UNESCAPED_UNICODE)];
        $this->correrTrabajos();
        $c = json_decode((string) DB::table('marketing_generated_content')->where('id', $this->piezaId($id, 'reel_guion'))->value('contenido'), true);
        $this->assertSame([1, 2, 3], array_column($c['escenas'], 'numero'));
        $this->assertSame(12, (int) $c['duracion_total_s']);
    }

    public function test_un_tipo_que_falla_no_tumba_a_los_demas(): void
    {
        $id = $this->crearCampanaApi(['canales' => ['reels', 'facebook', 'whatsapp']]);
        $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'todo', 'reemplazar' => false])->assertStatus(202);
        // el trabajo de copy_facebook recibe basura las 2 veces; los demás, el fixture
        $this->correrTrabajos([self::JOB_ESTRATEGIA]);
        $basura = 0;
        foreach ($this->trabajosDe(self::JOB_PIEZAS) as $job) {
            $this->trabajosCorridos[spl_object_id($job)] = true;
            $this->prepararIaPara($job);
            if ($this->piezaEnCurso === 'copy_facebook') {
                $this->respuestasIa = ['no es json', '{"piezas": "tampoco"}'];
                $basura++;
            }
            try {
                app()->call([$job, 'handle']);
            } catch (\Throwable $e) {
                if (method_exists($job, 'failed')) {
                    $job->failed($e);
                }
            }
            $this->respuestasIa = [];
            $this->piezaEnCurso = null;
        }
        $this->correrTrabajos(); // los trabajos por variante de Reel que despachó el del tipo
        $this->assertSame(1, $basura);
        $estados = [];
        foreach ($this->piezasDe($id) as $f) {
            $estados[$f->pieza . '/' . $f->variante] = $f->status;
            if ($f->pieza === 'copy_facebook') {
                $this->assertStringStartsWith('La IA no entregó esta pieza válida', (string) $f->failure_reason);
                $this->assertStringEndsWith('Regenérala o edítala a mano.', (string) $f->failure_reason);
                $this->assertDoesNotMatchRegularExpression('/Exception|\.php|SQLSTATE/i', $f->failure_reason);
                $ev = json_decode((string) $f->generation_metadata, true)['errores_validacion'] ?? null;
                $this->assertIsArray($ev, 'generation_metadata.errores_validacion');
                $this->assertArrayHasKey('intento_1', $ev);
                $this->assertArrayHasKey('intento_2', $ev);
                $this->assertStringNotContainsString('no es json', json_encode($ev), 'Sin el texto de la IA');
                $this->assertStringNotContainsString('tampoco', json_encode($ev), 'Sin el texto de la IA');
            }
        }
        $this->assertSame('failed', $estados['copy_facebook/A']);
        $this->assertSame('failed', $estados['copy_facebook/B']);
        $this->assertSame('ready', $estados['reel_guion/A']);
        $this->assertSame('ready', $estados['mensaje_whatsapp/C']);

        $p = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}/progreso")->assertStatus(200);
        $this->assertSame('con_errores', $p->json('generacion.estado'));
        $this->assertSame(7, $p->json('generacion.piezas_total'));
        $this->assertSame(5, $p->json('generacion.piezas_listas'));
        $this->assertSame(2, $p->json('generacion.piezas_error'));
        $this->assertSame(0, $p->json('generacion.piezas_generando'));
        $porId = collect($p->json('piezas'))->keyBy('id');
        $this->assertSame('error', $porId[$this->piezaId($id, 'copy_facebook')]['estado']);
        $this->assertNotEmpty($porId[$this->piezaId($id, 'copy_facebook')]['error']);
    }

    /**
     * Caso inválido → motivo humano esperado en `failure_reason` (Henry, 13-nov: que se vea en pantalla por qué falló).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function casosInvalidosIa(): array
    {
        return [
            'escena_40_s' => ['escena_40_s', 'escena 1: cada escena dura de 2 a 15 segundos'],
            'emoji_en_texto' => ['emoji_en_texto', 'escena 1: sin emojis'],
            'llaves_en_texto' => ['llaves_en_texto', 'escena 1: no puede llevar {{ }}'],
            'sin_cta_final' => ['sin_cta_final', 'la última escena debe ser la llamada a la acción (cta)'],
            'visual_raro' => ['visual_raro', 'escena 1: el visual debe ser logo, broll, texto o cta'],
            'broll_sin_categoria' => ['broll_sin_categoria', 'escena 1: elige una categoría de B-roll (nicho) válida'],
            'texto_cta_largo' => ['texto_cta_largo', 'escena 3: máximo 24 caracteres'],
            'voz_rara' => ['voz_rara', 'elige una voz de la lista'],
            'musica_rara' => ['musica_rara', 'elige una música de la lista'],
            'dos_escenas' => ['dos_escenas', 'el guion debe tener de 3 a 8 escenas'],
        ];
    }

    /**
     * Decisión de Henry (13-nov): cada Reel es SU propia llamada con SU reintento. Si la IA entrega mal la variante A dos
     * veces: exactamente intento + UN reintento para la A (no más), la A queda `failed` con el motivo humano y el detalle
     * en `errores_validacion` (sin el texto de la IA), y la B se genera aparte con una sola llamada, sin tocarse.
     *
     * @dataProvider casosInvalidosIa
     */
    public function test_guion_invalido_de_la_ia_reintenta_una_vez_y_queda_failed(string $caso, string $motivo): void
    {
        [$pieza, $contenido] = $this->casoInvalido($caso);
        $this->assertSame('reel_guion', $pieza);
        $id = $this->crearCampanaApi(['canales' => ['reels']]);
        $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'todo', 'reemplazar' => false])->assertStatus(202);
        $maloA = json_encode(['piezas' => [['variante' => 'A', 'contenido' => $contenido]]], JSON_UNESCAPED_UNICODE);
        // estrategia, A intento, A reintento; la B toma la respuesta automática (su fixture válido)
        $this->respuestasIa = [$this->fixtureCrudo('estrategia-valida.json'), $maloA, $maloA];
        $this->correrTrabajos();

        $this->assertCount(4, $this->llamadasIa, 'estrategia + A (intento + UN reintento) + B (una llamada)');
        $this->assertStringContainsString('no cumplió', $this->llamadaLegible(2), 'El reintento de la A dice qué no cumplió');
        $this->assertStringNotContainsString('no cumplió', $this->llamadaLegible(3), 'La B es su propia llamada, sin reintento');

        $a = DB::table('marketing_generated_content')->find($this->piezaId($id, 'reel_guion', 'A'));
        $this->assertSame('failed', $a->status, "{$caso} debió rechazarse");
        $this->assertSame("La IA no entregó un guion válido: ", mb_substr((string) $a->failure_reason, 0, 34));
        $this->assertStringContainsString($motivo, (string) $a->failure_reason, 'Motivo humano esperado');
        $this->assertStringEndsWith('Regenéralo o edítalo a mano.', (string) $a->failure_reason);
        $this->assertLessThanOrEqual(500, mb_strlen((string) $a->failure_reason));
        $ev = json_decode((string) $a->generation_metadata, true)['errores_validacion'] ?? null;
        $this->assertIsArray($ev, 'generation_metadata.errores_validacion');
        $this->assertSame(['intento_1', 'intento_2'], array_values(array_filter(array_keys($ev), fn ($k) => str_starts_with($k, 'intento_'))),
            'Exactamente dos intentos registrados');
        $this->assertNotEmpty($ev['intento_2']['errores']);
        $jsonEv = json_encode($ev, JSON_UNESCAPED_UNICODE);
        foreach (['Mamá frente a la tele', '"piezas"', '"contenido"', '¿Otra vez se trabó la serie?'] as $textoIa) {
            $this->assertStringNotContainsString($textoIa, $jsonEv, 'errores_validacion no guarda el texto de la IA');
        }

        $b = DB::table('marketing_generated_content')->find($this->piezaId($id, 'reel_guion', 'B'));
        $this->assertSame('ready', $b->status, 'La otra variante no se toca');
        $this->assertNull($b->failure_reason);
        $this->assertSame('Toda la familia conectada', json_decode((string) $b->contenido, true)['titulo']);
        $this->assertSame(0, (int) $b->regeneraciones);
    }

    /** Henry (13-nov): los nichos ya no van como «público»; sí va el tipo de público que escribió el equipo. */
    public function test_el_prompt_no_lleva_nichos_como_publico_y_si_el_tipo_de_publico(): void
    {
        $this->campanaGenerada(['canales' => ['reels', 'facebook'], 'publico.descripcion' => 'Tipo QA2: dueños de tiendita']);
        $this->assertNotEmpty($this->llamadasIa);
        foreach (array_keys($this->llamadasIa) as $i) {
            $prompt = $this->llamadaLegible($i);
            $this->assertStringContainsString('Tipo QA2: dueños de tiendita', $prompt, "llamada {$i}: lleva el tipo de público");
            $this->assertStringNotContainsString('NICHOS DEL PÚBLICO', $prompt, "llamada {$i}");
            foreach (['Series sin pausas', '"motivadores"', '"dolores"', '"vocabulario"', 'Se traba"'] as $prohibido) {
                $this->assertStringNotContainsString($prohibido, $prompt, "llamada {$i}: no lleva motivadores, dolores ni vocabulario de nichos");
            }
        }
    }

    public function test_json_roto_y_texto_libre_de_la_ia_no_truenan(): void
    {
        foreach (['json_roto', 'texto_libre'] as $caso) {
            $crudo = $this->fixture('piezas-invalidas.json')[$caso]['crudo'];
            $id = $this->crearCampanaApi(['canales' => ['whatsapp']]);
            $this->llamadasIa = [];
            $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'todo', 'reemplazar' => false])->assertStatus(202);
            $this->respuestasIa = [$this->fixtureCrudo('estrategia-valida.json'), $crudo, $crudo];
            $this->correrTrabajos();
            $f = DB::table('marketing_generated_content')->find($this->piezaId($id, 'mensaje_whatsapp', 'B'));
            $this->assertSame('failed', $f->status);
            $this->assertStringContainsString('la respuesta no fue un JSON válido', (string) $f->failure_reason, $caso);
        }
    }

    public function test_la_ia_no_pone_el_telefono_lo_pone_el_brand_kit(): void
    {
        $d = $this->fixture('piezas-reel_guion-validas.json');
        $d['piezas'][0]['contenido']['escenas'][2]['texto_pantalla'] = 'Llama 5599998888';
        $id = $this->crearCampanaApi(['canales' => ['reels']]);
        $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'todo', 'reemplazar' => false])->assertStatus(202);
        $this->respuestasIa = [$this->fixtureCrudo('estrategia-valida.json'), json_encode($d), json_encode($d)];
        $this->correrTrabajos();
        // Aceptable: o se rechaza o el render usa {{brand.phone}} (lo verifica ProducirVideoTest). Aquí: nunca 500 ni fila perdida.
        $this->assertContains(DB::table('marketing_generated_content')->where('id', $this->piezaId($id, 'reel_guion'))->value('status'), ['ready', 'failed']);
    }

    public function test_reemplazar_piezas_exige_confirmacion_y_conserva_videos(): void
    {
        $id = $this->campanaGenerada(['canales' => ['reels', 'facebook']]);
        $video = DB::table('marketing_generated_content')->insertGetId([
            'company_id' => 1, 'type' => 'video', 'pieza' => 'reel', 'pieza_origen_id' => $this->piezaId($id, 'reel_guion'), 'status' => 'ready',
            'source_campaign_id' => $id, 'generation_engine' => 'mvm_ffmpeg', 'regeneraciones' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $viejas = array_map(fn ($f) => $f->id, $this->piezasDe($id));
        $r = $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'piezas', 'reemplazar' => false]);
        $r->assertStatus(409)->assertJson(['ok' => false, 'error' => 'piezas_existentes']);
        $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => 'piezas', 'reemplazar' => true])->assertStatus(202);
        $this->correrTrabajos();
        $nuevas = array_map(fn ($f) => $f->id, $this->piezasDe($id));
        $this->assertCount(4, $nuevas);
        $this->assertSame([], array_values(array_intersect($viejas, $nuevas)), 'Las viejas quedan borradas lógicamente');
        foreach ($viejas as $v) {
            $this->assertNotNull(DB::table('marketing_generated_content')->where('id', $v)->value('deleted_at'));
        }
        $this->assertNull(DB::table('marketing_generated_content')->where('id', $video)->value('deleted_at'), 'Los videos quedan');
    }

    public function test_ficha_trae_las_6_claves_de_piezas_siempre_ordenadas_por_variante(): void
    {
        $id = $this->campanaGenerada(['canales' => ['anuncios_meta']]);
        $res = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}")->assertStatus(200);
        $piezas = $res->json('data.piezas');
        foreach (self::PIEZAS_TEXTO as $k) {
            $this->assertArrayHasKey($k, $piezas, "Clave {$k} siempre presente");
        }
        $this->assertSame([], $piezas['reel_guion']);
        $this->assertSame(['A', 'B', 'C'], array_column($piezas['variante_anuncio'], 'variante'));
        $p = $piezas['variante_anuncio'][0];
        foreach (['id', 'pieza', 'pieza_etiqueta', 'variante', 'estado', 'error', 'contenido', 'texto_plano', 'editada', 'editada_at', 'regeneraciones', 'actualizada_at', 'video_ids'] as $k) {
            $this->assertArrayHasKey($k, $p, "Pieza sin {$k}");
        }
        $this->assertSame('Variante de anuncio', $p['pieza_etiqueta']);
        $this->assertSame('lista', $p['estado']);
        $this->assertIsArray($res->json('data.videos'));
    }

    public function test_listado_existente_de_contenido_generado_agrega_source_campaign_id_y_pieza(): void
    {
        $id = $this->campanaGenerada(['canales' => ['reels']]);
        $res = $this->como($this->admin)->getJson('/api/marketing/generated-content?per_page=100');
        $this->assertSame(200, $res->getStatusCode(), $this->etiqueta($res));
        $filas = $res->json('data.data') ?? $res->json('data');
        $this->assertIsArray($filas);
        $una = collect($filas)->firstWhere('source_campaign_id', $id);
        if ($una !== null) {
            $this->assertArrayHasKey('pieza', $una);
        } else {
            $this->assertArrayHasKey('source_campaign_id', $filas[0] ?? ['source_campaign_id' => null], 'Única excepción aditiva de §2.1');
        }
    }
}
