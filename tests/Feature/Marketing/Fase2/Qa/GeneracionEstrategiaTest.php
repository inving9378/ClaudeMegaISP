<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — «Generar con IA»: estrategia (contrato §2.4 ruta 7, 8 y 9; §2.5.4; §2.7; criterios 2 y 9).
 * IA simulada (EscenarioFase2). Reparto: la suma de `monto_mxn` es EXACTAMENTE el presupuesto (bcmath, half-up,
 * el último absorbe la diferencia); porcentajes que no suman 100 se normalizan; presupuesto 0 → "0.00".
 */
class GeneracionEstrategiaTest extends MarketingTestCase
{
    use EscenarioFase2;

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

    private function generar(int $id, array $cuerpo = ['alcance' => 'estrategia', 'reemplazar' => false]): \Illuminate\Testing\TestResponse
    {
        return $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", $cuerpo);
    }

    private function estrategiaCon(array $reparto): string
    {
        $e = $this->fixture('estrategia-valida.json');
        $e['reparto_presupuesto'] = $reparto;

        return json_encode($e, JSON_UNESCAPED_UNICODE);
    }

    private function sumaReparto(int $id): string
    {
        $e = json_decode((string) DB::table('marketing_campaigns')->where('id', $id)->value('estrategia'), true);
        $s = '0.00';
        foreach ($e['reparto_presupuesto'] as $r) {
            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', (string) $r['monto_mxn'], 'monto_mxn es cadena con 2 decimales');
            $s = bcadd($s, (string) $r['monto_mxn'], 2);
        }

        return $s;
    }

    // ── 202 / 409 / 422 ──────────────────────────────────────────────────────

    public function test_generar_estrategia_responde_202_y_despacha_solo_el_job_de_estrategia_en_default(): void
    {
        $id = $this->crearCampanaApi();
        $res = $this->generar($id);
        $res->assertStatus(202)->assertJsonPath('ok', true)->assertJsonStructure(['generacion' => ['estado']]);
        Queue::assertPushed(self::JOB_ESTRATEGIA, 1);
        Queue::assertNotPushed(self::JOB_PIEZAS);
        foreach ($this->trabajosDe(self::JOB_ESTRATEGIA) as $job) {
            $this->assertContains($job->queue ?? null, [null, 'default'], 'Los trabajos de IA van en la cola default (F2-8)');
            $this->assertSame(1, (int) ($job->tries ?? 1), 'tries = 1: nunca se reintenta solo (cuesta dinero)');
        }
        $this->assertSame([], $this->correrTrabajos());

        $c = DB::table('marketing_campaigns')->find($id);
        $this->assertSame('lista', $c->estrategia_estado);
        $this->assertNull($c->estrategia_error);
        $this->assertNotNull($c->estrategia_generada_at);
        $e = json_decode($c->estrategia, true);
        foreach (['resumen', 'propuesta_valor', 'mensajes_clave', 'publico_detalle', 'por_canal', 'calendario', 'reparto_presupuesto', 'kpis', 'riesgos'] as $k) {
            $this->assertArrayHasKey($k, $e);
        }
        $this->assertCount(1, $this->llamadasIa, 'Una sola llamada a la IA para la estrategia');
        $this->assertSame(0, $this->contarFilas('marketing_generated_content', ['source_campaign_id' => $id]), 'alcance estrategia no crea piezas');
    }

    public function test_reparto_suma_exactamente_el_presupuesto_y_recalcula_montos_de_la_ia(): void
    {
        $id = $this->crearCampanaApi(['presupuesto_mxn' => '15000.00']);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $this->assertSame('15000.00', $this->sumaReparto($id));
        $e = json_decode((string) DB::table('marketing_campaigns')->where('id', $id)->value('estrategia'), true);
        $montos = array_column($e['reparto_presupuesto'], 'monto_mxn', 'canal');
        $this->assertSame('9000.00', $montos['anuncios_meta'], 'La IA dijo 1.00: el backend recalcula desde el porcentaje');
        $this->assertSame('3750.00', $montos['reels']);
        $this->assertSame('2250.00', $montos['video']);
    }

    public function test_reparto_con_centavos_que_no_dividen_exacto(): void
    {
        $id = $this->crearCampanaApi(['presupuesto_mxn' => '100.01']);
        $this->respuestasIa[] = $this->estrategiaCon([
            ['canal' => 'reels', 'porcentaje' => '33.33', 'monto_mxn' => '0'],
            ['canal' => 'video', 'porcentaje' => '33.33', 'monto_mxn' => '0'],
            ['canal' => 'anuncios_meta', 'porcentaje' => '33.34', 'monto_mxn' => '0'],
        ]);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $this->assertSame('100.01', $this->sumaReparto($id));
    }

    public function test_porcentajes_que_no_suman_100_se_normalizan(): void
    {
        $id = $this->crearCampanaApi(['presupuesto_mxn' => '1000.00']);
        $this->respuestasIa[] = $this->estrategiaCon([
            ['canal' => 'reels', 'porcentaje' => '50', 'monto_mxn' => '0'],
            ['canal' => 'anuncios_meta', 'porcentaje' => '70', 'monto_mxn' => '0'],
        ]);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $c = DB::table('marketing_campaigns')->find($id);
        $this->assertSame('lista', $c->estrategia_estado, (string) $c->estrategia_error);
        $this->assertSame('1000.00', $this->sumaReparto($id));
        $e = json_decode($c->estrategia, true);
        $suma = '0.00';
        foreach ($e['reparto_presupuesto'] as $r) {
            $suma = bcadd($suma, (string) $r['porcentaje'], 2);
        }
        $this->assertSame('100.00', $suma, 'Porcentajes normalizados a 100.00');
    }

    public function test_presupuesto_cero_reparte_ceros(): void
    {
        $id = $this->crearCampanaApi(['presupuesto_mxn' => '0']);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $e = json_decode((string) DB::table('marketing_campaigns')->where('id', $id)->value('estrategia'), true);
        foreach ($e['reparto_presupuesto'] as $r) {
            $this->assertSame('0.00', (string) $r['monto_mxn']);
        }
    }

    public function test_canales_que_no_son_de_la_campana_se_quitan_del_reparto_y_por_canal(): void
    {
        $id = $this->crearCampanaApi(['canales' => ['reels', 'whatsapp'], 'presupuesto_mxn' => '500.00']);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $c = DB::table('marketing_campaigns')->find($id);
        $e = json_decode($c->estrategia, true);
        $canales = array_merge(array_column($e['por_canal'], 'canal'), array_column($e['reparto_presupuesto'], 'canal'));
        $this->assertSame([], array_values(array_diff($canales, ['reels', 'whatsapp'])), 'Solo canales de la campaña');
        $this->assertSame('500.00', $this->sumaReparto($id));
    }

    public function test_json_roto_reintenta_una_vez_y_luego_error_en_espanol(): void
    {
        $id = $this->crearCampanaApi();
        $this->respuestasIa[] = 'Claro, aquí va tu estrategia: {"resumen": "rota"';
        $this->respuestasIa[] = '{"resumen": 5}';
        $this->respuestasIa[] = $this->fixtureCrudo('estrategia-valida.json'); // no debe usarse
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $c = DB::table('marketing_campaigns')->find($id);
        $this->assertCount(2, $this->llamadasIa, 'Un intento + UN reintento, nunca más (cuesta dinero)');
        $this->assertSame('error', $c->estrategia_estado);
        $this->assertNotEmpty($c->estrategia_error);
        $this->assertLessThanOrEqual(500, mb_strlen($c->estrategia_error));
        $this->assertDoesNotMatchRegularExpression('/Exception|Stack|\.php|SQLSTATE|json_decode/i', $c->estrategia_error, 'Mensaje para personas, no traza');
        $this->assertNull(json_decode((string) $c->estrategia, true), 'No se guarda una estrategia inválida');
    }

    public function test_json_roto_y_luego_valido_queda_lista(): void
    {
        $id = $this->crearCampanaApi();
        $this->respuestasIa[] = '{"resumen": "rota"';
        $this->respuestasIa[] = '```json' . "\n" . $this->fixtureCrudo('estrategia-valida.json') . "\n```";
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $this->assertSame('lista', DB::table('marketing_campaigns')->where('id', $id)->value('estrategia_estado'));
        $this->assertCount(2, $this->llamadasIa);
        $this->assertStringContainsString('no cumplió', $this->llamadaLegible(1), 'El reintento le dice a la IA qué no cumplió');
    }

    public function test_ia_caida_deja_error_y_no_reintenta(): void
    {
        $id = $this->crearCampanaApi();
        array_push($this->respuestasIa, ['__status' => 400, '__mensaje' => 'QA: la IA rechaza'], ['__status' => 400]);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $c = DB::table('marketing_campaigns')->find($id);
        $this->assertSame('error', $c->estrategia_estado);
        $this->assertNotEmpty($c->estrategia_error);
        $this->assertLessThanOrEqual(2, count($this->llamadasIa));
    }

    public function test_textos_enormes_de_la_ia_se_rechazan_o_recortan_nunca_se_guardan_de_mas(): void
    {
        $id = $this->crearCampanaApi();
        $e = $this->fixture('estrategia-valida.json');
        $e['resumen'] = str_repeat('Texto largo ', 2000);
        $e['riesgos'] = ['<script>alert(1)</script>Riesgo', '{{brand.phone}}'];
        $this->respuestasIa[] = json_encode($e);
        $this->respuestasIa[] = json_encode($e);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $c = DB::table('marketing_campaigns')->find($id);
        if ($c->estrategia_estado === 'lista') {
            $g = json_decode($c->estrategia, true);
            $this->assertLessThanOrEqual(600, mb_strlen($g['resumen']));
            $this->assertStringNotContainsString('<script', $c->estrategia);
            $this->assertStringNotContainsString('{{', $c->estrategia);
        } else {
            $this->assertSame('error', $c->estrategia_estado);
        }
    }

    public function test_sin_ia_configurada_422_sin_despachar_nada(): void
    {
        $id = $this->crearCampanaApi();
        $this->quitarIa(['marketing.campana_360']);
        $res = $this->generar($id, ['alcance' => 'todo', 'reemplazar' => false]);
        $res->assertStatus(422)->assertJson(['ok' => false, 'error' => 'ia_no_configurada']);
        $this->assertStringContainsString('Falta asignar una IA', (string) $res->json('mensaje'));
        $this->assertStringContainsString('Estrategia de campañas 360', (string) $res->json('mensaje'), 'Nombra la función de la IA');
        Queue::assertNothingPushed();
        $this->assertSame('sin_generar', DB::table('marketing_campaigns')->where('id', $id)->value('estrategia_estado'));
    }

    public function test_sin_ia_de_piezas_alcance_piezas_da_422(): void
    {
        $id = $this->crearCampanaApi();
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $this->quitarIa(['marketing.piezas_campana']);
        $res = $this->generar($id, ['alcance' => 'piezas', 'reemplazar' => false]);
        $res->assertStatus(422)->assertJsonPath('error', 'ia_no_configurada');
        $this->assertStringContainsString('Piezas de campañas 360', (string) $res->json('mensaje'));
    }

    public function test_doble_clic_en_generar_da_409_generando_y_un_solo_trabajo(): void
    {
        $id = $this->crearCampanaApi();
        $this->generar($id, ['alcance' => 'todo', 'reemplazar' => false])->assertStatus(202);
        $b = $this->generar($id, ['alcance' => 'todo', 'reemplazar' => false]);
        $b->assertStatus(409)->assertJson(['ok' => false, 'error' => 'generando', 'mensaje' => 'Ya se está generando esta campaña.']);
        Queue::assertPushed(self::JOB_ESTRATEGIA, 1);
    }

    /** [Correcciones de QA, H-5] sin `alcance` no se adivina «todo»: 422 y la IA no se llama. */
    public function test_generar_sin_alcance_da_422_sin_llamar_a_la_ia(): void
    {
        $id = $this->crearCampanaApi();
        foreach ([[], ['reemplazar' => true], ['alcance' => null]] as $cuerpo) {
            $res = $this->generar($id, $cuerpo);
            $res->assertStatus(422)->assertJsonValidationErrors('alcance');
        }
        $this->assertSame([], $this->llamadasIa);
        Queue::assertNothingPushed();
        $this->assertSame('sin_generar', DB::table('marketing_campaigns')->where('id', $id)->value('estrategia_estado'));
    }

    public function test_piezas_sin_estrategia_422_y_campana_cerrada_409(): void
    {
        $id = $this->crearCampanaApi();
        $this->generar($id, ['alcance' => 'piezas', 'reemplazar' => false])->assertStatus(422)->assertJsonPath('error', 'sin_estrategia');
        foreach (['finished', 'canceled'] as $s) {
            $c = $this->campanaDirecta(1, ['status' => $s]);
            $this->generar($c->id, ['alcance' => 'estrategia', 'reemplazar' => false])->assertStatus(409)->assertJsonPath('error', 'campana_cerrada');
        }
        Queue::assertNothingPushed();
    }

    public function test_alcance_y_reemplazar_invalidos_dan_422(): void
    {
        $id = $this->crearCampanaApi();
        foreach ([['alcance' => 'todo_y_mas'], ['alcance' => ['todo']], ['alcance' => 'todo', 'reemplazar' => 'quizas'], ['alcance' => '']] as $cuerpo) {
            $res = $this->generar($id, $cuerpo);
            $this->assertSame(422, $res->getStatusCode(), json_encode($cuerpo) . ' → ' . $this->etiqueta($res));
        }
        Queue::assertNothingPushed();
    }

    public function test_el_prompt_lleva_campana_y_brand_kit_y_ningun_dato_de_leads_ni_clientes(): void
    {
        $lead = DB::table('marketing_leads')->insertGetId([
            'company_id' => 1, 'full_name' => 'Lead QA2 Secreto Zxq', 'phone' => '5511112222', 'status' => 'new',
            'source_id' => DB::table('marketing_lead_sources')->insertGetId(['company_id' => 1, 'code' => 'qa2', 'name' => 'QA2', 'created_at' => now(), 'updated_at' => now()]),
            'captured_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = $this->crearCampanaApi(['oferta.descripcion' => 'Oferta QA2 única 77']);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $prompt = $this->promptsLegibles();
        $this->assertStringContainsString('Oferta QA2 única 77', $prompt);
        $this->assertStringContainsString('Meganet QA', $prompt, 'Brand Kit en el prompt');
        $this->assertStringNotContainsString('Secreto Zxq', $prompt, 'Nunca datos de leads');
        $this->assertStringNotContainsString('5511112222', $prompt);
        $this->assertNotNull($lead);
    }

    private function promptsLegibles(): string
    {
        // Los cuerpos son JSON: se decodifican para buscar el texto tal cual.
        $out = '';
        foreach ($this->llamadasIa as $b) {
            $out .= json_encode(json_decode($b, true), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }

        return $out;
    }

    // ── progreso y edición de la estrategia ───────────────────────────────────

    public function test_progreso_refleja_el_estado_de_la_estrategia(): void
    {
        $id = $this->crearCampanaApi();
        $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}/progreso")->assertStatus(200)
            ->assertJson(['ok' => true, 'estrategia_estado' => 'sin_generar'])->assertJsonPath('generacion.estado', 'sin_generar');
        $this->generar($id)->assertStatus(202);
        $p = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}/progreso")->assertStatus(200);
        $this->assertSame('generando', $p->json('generacion.estado'));
        $this->correrTrabajos();
        $p = $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}/progreso")->assertStatus(200);
        $this->assertSame('lista', $p->json('estrategia_estado'));
        $this->assertIsArray($p->json('piezas'));
        $this->assertIsArray($p->json('videos'));
    }

    public function test_editar_estrategia_valida_y_recalcula_reparto(): void
    {
        $id = $this->crearCampanaApi(['presupuesto_mxn' => '999.99']);
        $this->generar($id)->assertStatus(202);
        $this->correrTrabajos();
        $e = $this->fixture('estrategia-valida.json');
        $e['resumen'] = 'Resumen editado a mano';
        $res = $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$id}/estrategia", ['estrategia' => $e]);
        $res->assertStatus(200)->assertJsonPath('data.estrategia.resumen', 'Resumen editado a mano');
        $this->assertSame('999.99', $this->sumaReparto($id));

        $e['mensajes_clave'] = ['uno']; // 3 a 5
        $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$id}/estrategia", ['estrategia' => $e])->assertStatus(422);
        $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$id}/estrategia", ['estrategia' => 'texto'])->assertStatus(422);
    }

    public function test_editar_estrategia_mientras_genera_da_409(): void
    {
        $id = $this->crearCampanaApi();
        $this->generar($id)->assertStatus(202);
        $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$id}/estrategia", ['estrategia' => $this->fixture('estrategia-valida.json')])
            ->assertStatus(409);
    }
}
