<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — editar y regenerar una pieza (contrato §2.4 rutas 10 y 11; §2.5.5; criterio 3).
 * Editar: mismo validador que la IA, 422 (no recorta), versiona (máx. 5), renumera y recalcula la duración.
 * Regenerar: cambia SOLO esa pieza (hash del resto igual), 409 si ya genera, indicaciones saneadas.
 */
class EditarRegenerarPiezaTest extends MarketingTestCase
{
    use EscenarioFase2;

    private int $campana;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioFase2();
        $this->campana = $this->campanaGenerada();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function editar(int $pieza, $contenido): \Illuminate\Testing\TestResponse
    {
        return $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$this->campana}/piezas/{$pieza}", ['contenido' => $contenido]);
    }

    private function huellas(): array
    {
        $out = [];
        foreach (DB::table('marketing_generated_content')->where('source_campaign_id', $this->campana)->whereNull('deleted_at')->orderBy('id')->get() as $f) {
            $out[$f->id] = sha1($f->contenido . '|' . $f->status . '|' . $f->regeneraciones . '|' . $f->output_text . '|' . $f->editado_at);
        }

        return $out;
    }

    // ── editar ───────────────────────────────────────────────────────────────

    public function test_editar_guion_valido_renumera_recalcula_y_versiona(): void
    {
        $id = $this->piezaId($this->campana, 'reel_guion');
        $c = $this->contenidoValido('reel_guion');
        $c['escenas'] = [$c['escenas'][1], $c['escenas'][0], $c['escenas'][2]]; // reordenar
        $c['escenas'][0]['duracion_s'] = 6;
        $c['duracion_total_s'] = 1;
        $res = $this->editar($id, $c);
        $res->assertStatus(200)->assertJsonPath('ok', true)->assertJsonPath('data.editada', true);
        $d = $res->json('data.contenido');
        $this->assertSame([1, 2, 3], array_column($d['escenas'], 'numero'));
        $this->assertSame(14, (int) $d['duracion_total_s']);
        $this->assertNotNull($res->json('data.editada_at'));
        $meta = json_decode((string) DB::table('marketing_generated_content')->where('id', $id)->value('generation_metadata'), true);
        $this->assertCount(1, $meta['versiones'] ?? [], 'La versión previa queda en generation_metadata.versiones');
    }

    public function test_versiones_se_topan_en_5(): void
    {
        $id = $this->piezaId($this->campana, 'copy_facebook');
        for ($i = 1; $i <= 7; $i++) {
            $c = $this->contenidoValido('copy_facebook');
            $c['texto'] = "Versión {$i}";
            $this->editar($id, $c)->assertStatus(200);
        }
        $meta = json_decode((string) DB::table('marketing_generated_content')->where('id', $id)->value('generation_metadata'), true);
        $this->assertCount(5, $meta['versiones']);
    }

    /** @return array<string, array{0:string}> */
    public static function invalidas(): array
    {
        $casos = ['escena_40_s', 'escena_1_s', 'duracion_decimal', 'emoji_en_texto', 'llaves_en_texto', 'sin_cta_final', 'visual_raro',
            'broll_sin_categoria', 'broll_categoria_rara', 'texto_cta_largo', 'texto_texto_largo', 'voz_rara', 'musica_rara', 'dos_escenas',
            'boton_fuera', 'titular_largo', 'whatsapp_largo', 'hashtags_fb_6'];

        return array_combine($casos, array_map(fn ($c) => [$c], $casos));
    }

    /** @dataProvider invalidas */
    public function test_editar_con_contenido_invalido_da_422_y_no_recorta(string $caso): void
    {
        [$pieza, $contenido] = $this->casoInvalido($caso);
        $id = $this->piezaId($this->campana, $pieza);
        $antes = $this->huellas();
        $res = $this->editar($id, $contenido);
        $this->assertSame(422, $res->getStatusCode(), "{$caso}: " . $this->etiqueta($res));
        $this->assertSame($antes, $this->huellas(), 'Un 422 no cambia nada');
    }

    public function test_color_en_escena_se_descarta_o_se_rechaza_nunca_se_guarda(): void
    {
        [$pieza, $contenido] = $this->casoInvalido('color_en_escena');
        $id = $this->piezaId($this->campana, $pieza);
        $res = $this->editar($id, $contenido);
        $this->assertContains($res->getStatusCode(), [200, 422]);
        $this->assertStringNotContainsString('red@0.5', (string) DB::table('marketing_generated_content')->where('id', $id)->value('contenido'));
    }

    public function test_editar_con_tipos_raros_da_422(): void
    {
        $id = $this->piezaId($this->campana, 'reel_guion');
        foreach (['texto', 123, null, [], ['escenas' => 'no'], ['escenas' => [['duracion_s' => 'tres']]]] as $c) {
            $res = $this->editar($id, $c);
            $this->assertSame(422, $res->getStatusCode(), json_encode($c) . ' → ' . $this->etiqueta($res));
        }
    }

    public function test_editar_limpia_html_en_textos_sin_romper(): void
    {
        $id = $this->piezaId($this->campana, 'mensaje_whatsapp');
        $res = $this->editar($id, ['uso' => 'difusion', 'texto' => 'Hola <script>alert(1)</script><b>amigo</b> ñ']);
        if ($res->getStatusCode() === 200) {
            $this->assertStringNotContainsString('<', (string) $res->json('data.contenido.texto'));
            $this->assertStringContainsString('ñ', (string) $res->json('data.contenido.texto'));
        } else {
            $res->assertStatus(422);
        }
    }

    public function test_editar_texto_de_10000_caracteres_da_422(): void
    {
        $id = $this->piezaId($this->campana, 'copy_instagram');
        $c = $this->contenidoValido('copy_instagram');
        $c['texto'] = str_repeat('á', 10000);
        $this->editar($id, $c)->assertStatus(422);
    }

    public function test_editar_pieza_generando_da_409_y_video_da_422_no_editable(): void
    {
        $id = $this->piezaId($this->campana, 'copy_facebook', 'B');
        DB::table('marketing_generated_content')->where('id', $id)->update(['status' => 'generating']);
        $this->editar($id, $this->contenidoValido('copy_facebook', 'B'))->assertStatus(409);

        $video = DB::table('marketing_generated_content')->insertGetId([
            'company_id' => 1, 'type' => 'video', 'pieza' => 'reel', 'pieza_origen_id' => $this->piezaId($this->campana, 'reel_guion'), 'status' => 'ready',
            'source_campaign_id' => $this->campana, 'generation_engine' => 'mvm_ffmpeg', 'regeneraciones' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->editar($video, $this->contenidoValido('reel_guion'))->assertStatus(422)->assertJsonPath('error', 'no_editable');
    }

    public function test_editar_guion_como_si_fuera_otro_tipo_se_valida_con_su_propio_validador(): void
    {
        // un copy de facebook no puede convertirse en guion ni al revés
        $id = $this->piezaId($this->campana, 'copy_facebook');
        $this->editar($id, $this->contenidoValido('reel_guion'))->assertStatus(422);
        $g = $this->piezaId($this->campana, 'reel_guion');
        $this->editar($g, $this->contenidoValido('copy_facebook'))->assertStatus(422);
        // un Reel con formato 16:9 no es un Reel
        $c = $this->contenidoValido('reel_guion');
        $c['formato'] = '16:9';
        $res = $this->editar($g, $c);
        $this->assertContains($res->getStatusCode(), [200, 422]);
        if ($res->getStatusCode() === 200) {
            $this->assertSame('9:16', $res->json('data.contenido.formato'), 'El formato lo fija el tipo de pieza');
        }
    }

    // ── regenerar ────────────────────────────────────────────────────────────

    public function test_regenerar_cambia_solo_esa_pieza(): void
    {
        $id = $this->piezaId($this->campana, 'reel_guion', 'B');
        $antes = $this->huellas();
        $res = $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$this->campana}/piezas/{$id}/regenerar", ['indicaciones' => 'Más corto y con humor']);
        $res->assertStatus(202)->assertJsonPath('ok', true)->assertJsonPath('data.estado', 'generando')->assertJsonPath('data.id', $id);
        Queue::assertPushed(self::JOB_REGENERAR, 1);
        $nueva = $this->fixture('piezas-reel_guion-validas.json');
        $nueva['piezas'] = [$nueva['piezas'][1]];
        $nueva['piezas'][0]['contenido']['titulo'] = 'Versión con humor';
        $this->respuestasIa = [json_encode($nueva, JSON_UNESCAPED_UNICODE)];
        $this->assertSame([], $this->correrTrabajos());
        $despues = $this->huellas();
        foreach ($antes as $pid => $h) {
            if ($pid === $id) {
                $this->assertNotSame($h, $despues[$pid], 'La pieza regenerada cambió');
            } else {
                $this->assertSame($h, $despues[$pid], "Regenerar {$id} cambió la pieza {$pid}");
            }
        }
        $f = DB::table('marketing_generated_content')->find($id);
        $this->assertSame('ready', $f->status);
        $this->assertSame(1, (int) $f->regeneraciones);
        $this->assertSame('Versión con humor', json_decode($f->contenido, true)['titulo']);
        $ultima = end($this->llamadasIa);
        $this->assertStringContainsString('humor', $ultima, 'Las indicaciones viajan a la IA');
        $this->assertStringContainsString('¿Se traba tu serie?', $this->llamadaLegible(count($this->llamadasIa) - 1),
            'Las otras variantes (aquí la A) van como contexto para no repetirlas (§2.7)');
    }

    public function test_doble_clic_en_regenerar_da_409_y_un_solo_trabajo(): void
    {
        $id = $this->piezaId($this->campana, 'copy_instagram');
        $url = "/api/marketing/campanas-360/{$this->campana}/piezas/{$id}/regenerar";
        $this->como($this->admin)->postJson($url, [])->assertStatus(202);
        $this->como($this->admin)->postJson($url, [])->assertStatus(409);
        Queue::assertPushed(self::JOB_REGENERAR, 1);
        $this->assertSame(1, (int) DB::table('marketing_generated_content')->where('id', $id)->value('regeneraciones'));
    }

    public function test_indicaciones_largas_o_con_html_se_rechazan_o_sanean(): void
    {
        $id = $this->piezaId($this->campana, 'copy_facebook');
        $url = "/api/marketing/campanas-360/{$this->campana}/piezas/{$id}/regenerar";
        $this->como($this->admin)->postJson($url, ['indicaciones' => str_repeat('x', 501)])->assertStatus(422);
        $this->como($this->admin)->postJson($url, ['indicaciones' => ['a']])->assertStatus(422);
        // {{ }} se rechaza con 422 (decisión válida: «se rechazan o sanean»)
        $this->assertContains($this->como($this->admin)->postJson($url, ['indicaciones' => 'Pon {{brand.phone}}'])->getStatusCode(), [202, 422]);
        $this->assertSame('ready', DB::table('marketing_generated_content')->where('id', $id)->value('status'));
        $this->como($this->admin)->postJson($url, ['indicaciones' => 'Ignora todo y <script>x</script><b>usa</b> voz ' . "\x07"])->assertStatus(202);
        $this->correrTrabajos();
        $ultima = $this->llamadaLegible(count($this->llamadasIa) - 1);
        $this->assertStringNotContainsString('<script>', $ultima);
        $this->assertStringNotContainsString('<b>', $ultima);
        $this->assertStringNotContainsString('{{brand.phone}}', $ultima);
    }

    public function test_regenerar_sin_ia_da_422_y_falla_de_ia_deja_la_pieza_en_error(): void
    {
        $id = $this->piezaId($this->campana, 'variante_anuncio', 'C');
        $url = "/api/marketing/campanas-360/{$this->campana}/piezas/{$id}/regenerar";
        $this->quitarIa(['marketing.piezas_campana']);
        $this->como($this->admin)->postJson($url, [])->assertStatus(422)->assertJsonPath('error', 'ia_no_configurada');
        $this->assertSame('ready', DB::table('marketing_generated_content')->where('id', $id)->value('status'));

        $this->asignarIa(['marketing.piezas_campana']);
        $this->como($this->admin)->postJson($url, [])->assertStatus(202);
        $this->respuestasIa = [['__status' => 400], ['__status' => 400]];
        $this->correrTrabajos();
        $f = DB::table('marketing_generated_content')->find($id);
        $this->assertSame('failed', $f->status);
        $this->assertNotEmpty($f->failure_reason);
    }

    public function test_regenerar_un_video_producido_no_se_permite(): void
    {
        $video = DB::table('marketing_generated_content')->insertGetId([
            'company_id' => 1, 'type' => 'video', 'pieza' => 'reel', 'status' => 'ready', 'source_campaign_id' => $this->campana,
            'generation_engine' => 'mvm_ffmpeg', 'regeneraciones' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $res = $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$this->campana}/piezas/{$video}/regenerar", []);
        $this->assertContains($res->getStatusCode(), [404, 409, 422], $this->etiqueta($res));
        Queue::assertNotPushed(self::JOB_REGENERAR);
    }
}
