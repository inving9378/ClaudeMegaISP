<?php

namespace Tests\Feature\Marketing\Fase2\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase2\Qa\Concerns\EscenarioFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 2 — asistente de campaña: alta, edición y borrado (contrato §2.4 rutas 1–5, criterio 1).
 * Cada regla de §2.4.2: normal, vacío, borde, arreglos donde va texto, `<script>`, SQL, `{{ }}`, montos con
 * 3 decimales, fechas al revés, 367 días, nicho inexistente. Dinero siempre cadena MXN con 2 decimales.
 */
class AsistenteCampanaTest extends MarketingTestCase
{
    use EscenarioFase2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenarioFase2(false);
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function crear(array $cambios = []): \Illuminate\Testing\TestResponse
    {
        return $this->como($this->admin)->postJson('/api/marketing/campanas-360', $this->cuerpoCampana($cambios));
    }

    // ── normal ───────────────────────────────────────────────────────────────

    public function test_alta_valida_devuelve_201_con_la_forma_campana360_y_dinero_mxn(): void
    {
        $res = $this->crear(['presupuesto_mxn' => '15000', 'oferta.precio_mxn' => '599.5']);
        $res->assertStatus(201)->assertJsonPath('ok', true);
        $d = $res->json('data');
        foreach (['id', 'nombre', 'estado', 'estado_etiqueta', 'objetivo', 'objetivo_etiqueta', 'publico', 'oferta', 'canales',
            'presupuesto_mxn', 'moneda', 'fecha_inicio', 'fecha_fin', 'estrategia', 'estrategia_estado', 'estrategia_error',
            'estrategia_generada_at', 'generacion', 'videos_resumen', 'creada_at', 'actualizada_at'] as $k) {
            $this->assertArrayHasKey($k, $d, "Falta {$k} en Campana360");
        }
        $this->assertSame('draft', $d['estado']);
        $this->assertSame('Borrador', $d['estado_etiqueta']);
        $this->assertSame('Conseguir clientes nuevos', $d['objetivo_etiqueta']);
        $this->assertSame('15000.00', $d['presupuesto_mxn'], 'Dinero en JSON = cadena con 2 decimales');
        $this->assertSame('599.50', $d['oferta']['precio_mxn']);
        $this->assertSame('MXN', $d['moneda']);
        $this->assertSame('sin_generar', $d['estrategia_estado']);
        $this->assertSame(['estado' => 'sin_generar', 'piezas_total' => 0, 'piezas_listas' => 0, 'piezas_generando' => 0, 'piezas_error' => 0], $d['generacion']);
        $this->assertSame(['total' => 0, 'listos' => 0, 'en_proceso' => 0, 'error' => 0], $d['videos_resumen']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[56]:00$/', $d['creada_at'], 'ISO-8601 America/Mexico_City');

        $fila = DB::table('marketing_campaigns')->find($d['id']);
        $this->assertSame(1, (int) $fila->company_id);
        $this->assertSame('mixed', $fila->type);
        $this->assertSame('MXN', $fila->currency);
        $this->assertSame('15000.00', (string) $fila->budget);
        $this->assertSame($this->admin->id, (int) $fila->creado_por_user_id);
        $this->assertNotEmpty($fila->slug);
    }

    public function test_presupuesto_cero_es_valido_campana_organica(): void
    {
        $this->crear(['presupuesto_mxn' => '0'])->assertStatus(201)->assertJsonPath('data.presupuesto_mxn', '0.00');
    }

    public function test_dos_campanas_con_el_mismo_nombre_tienen_slug_distinto(): void
    {
        $a = $this->crear(['nombre' => 'QA2 Mismo nombre'])->assertStatus(201)->json('data.id');
        $b = $this->crear(['nombre' => 'QA2 Mismo nombre'])->assertStatus(201)->json('data.id');
        $this->assertNotSame(DB::table('marketing_campaigns')->where('id', $a)->value('slug'), DB::table('marketing_campaigns')->where('id', $b)->value('slug'));
    }

    public function test_company_id_del_cuerpo_se_ignora(): void
    {
        $res = $this->como($this->admin)->postJson('/api/marketing/campanas-360', $this->cuerpoCampana() + [
            'company_id' => $this->empresaB->id, 'status' => 'active', 'spent' => '9999', 'estrategia_estado' => 'lista', 'type' => 'meta_ads',
        ]);
        $res->assertStatus(201);
        $fila = DB::table('marketing_campaigns')->find($res->json('data.id'));
        $this->assertSame(1, (int) $fila->company_id);
        $this->assertSame('draft', $fila->status);
        $this->assertSame('0.00', (string) $fila->spent);
        $this->assertSame('mixed', $fila->type);
        $this->assertNotSame('lista', $fila->estrategia_estado);
    }

    public function test_acentos_y_enie_se_conservan(): void
    {
        $this->crear(['nombre' => 'QA2 Campaña Año Nuevo ñandú', 'publico.zonas' => ['Santa Inés', 'Peñón']])
            ->assertStatus(201)->assertJsonPath('data.nombre', 'QA2 Campaña Año Nuevo ñandú')->assertJsonPath('data.publico.zonas.1', 'Peñón');
    }

    // ── validación (422 formato Laravel) ─────────────────────────────────────

    /** @return array<string, array{0: array, 1: string}> */
    public static function invalidos(): array
    {
        return [
            'nombre vacío' => [['nombre' => ''], 'nombre'],
            'nombre 2 caracteres' => [['nombre' => 'ab'], 'nombre'],
            'nombre 151' => [['nombre' => str_repeat('a', 151)], 'nombre'],
            'nombre 10 000' => [['nombre' => str_repeat('ñ', 10000)], 'nombre'],
            'nombre arreglo' => [['nombre' => ['a', 'b']], 'nombre'],
            'objetivo inventado' => [['objetivo' => 'hackear'], 'objetivo'],
            'objetivo SQL' => [['objetivo' => "nuevos_clientes' OR '1'='1"], 'objetivo'],
            'canales texto' => [['canales' => 'reels'], 'canales'],
            'canal inexistente' => [['canales' => ['tiktok']], 'canales.0'],
            'canales repetidos' => [['canales' => ['reels', 'reels']], 'canales.1'],
            'canales 7' => [['canales' => ['facebook', 'instagram', 'reels', 'video', 'anuncios_meta', 'whatsapp', 'whatsapp']], 'canales'],
            'público descripción 501' => [['publico.descripcion' => str_repeat('a', 501)], 'publico.descripcion'],
            'zonas 21' => [['publico.zonas' => array_fill(0, 21, 'Zona')], 'publico.zonas'],
            'zona 81' => [['publico.zonas' => [str_repeat('z', 81)]], 'publico.zonas.0'],
            'edad 12' => [['publico.edad_min' => 12], 'publico.edad_min'],
            'edad 100' => [['publico.edad_max' => 100], 'publico.edad_max'],
            'edad decimal' => [['publico.edad_min' => 25.5], 'publico.edad_min'],
            'edad min > max' => [['publico.edad_min' => 50, 'publico.edad_max' => 30], 'publico.edad_max'],
            'nicho inexistente' => [['publico.nichos' => ['familia', 'gamer_fantasma']], 'publico.nichos.1'],
            'intereses 11' => [['publico.intereses' => array_fill(0, 11, 'x')], 'publico.intereses'],
            'interés 41' => [['publico.intereses' => [str_repeat('i', 41)]], 'publico.intereses.0'],
            'plan 101' => [['oferta.plan' => str_repeat('p', 101)], 'oferta.plan'],
            'precio 3 decimales' => [['oferta.precio_mxn' => '599.999'], 'oferta.precio_mxn'],
            'precio negativo' => [['oferta.precio_mxn' => '-1'], 'oferta.precio_mxn'],
            'precio 9 enteros' => [['oferta.precio_mxn' => '123456789'], 'oferta.precio_mxn'],
            'condiciones 501' => [['oferta.condiciones' => str_repeat('c', 501)], 'oferta.condiciones'],
            'vigencia no fecha' => [['oferta.vigencia_hasta' => '31/12/2026'], 'oferta.vigencia_hasta'],
            'presupuesto negativo' => [['presupuesto_mxn' => '-100'], 'presupuesto_mxn'],
            'presupuesto 3 decimales' => [['presupuesto_mxn' => '100.001'], 'presupuesto_mxn'],
            'presupuesto 0.001' => [['presupuesto_mxn' => '0.001'], 'presupuesto_mxn'],
            'presupuesto 11 enteros' => [['presupuesto_mxn' => '99999999999.99'], 'presupuesto_mxn'],
            'presupuesto texto' => [['presupuesto_mxn' => 'mil pesos'], 'presupuesto_mxn'],
            'presupuesto con coma' => [['presupuesto_mxn' => '15,000.00'], 'presupuesto_mxn'],
            'presupuesto arreglo' => [['presupuesto_mxn' => ['15000']], 'presupuesto_mxn'],
            'fecha inicio no fecha' => [['fecha_inicio' => 'mañana'], 'fecha_inicio'],
            'fecha inicio 30 de febrero' => [['fecha_inicio' => '2027-02-30'], 'fecha_inicio'],
        ];
    }

    /**
     * [Correcciones de QA, F-1] «Guardar borrador en cualquier paso»: estos 4 huecos ya NO son 422 al guardar; crean un
     * BORRADOR (201) que los lista en `faltantes`. A cambio, la campaña incompleta no puede generarse (422
     * `campana_incompleta`, sin llamar a la IA ni despachar nada) ni activarse (422). Los demás casos siguen en invalidos().
     *
     * @return array<string, array{0: array, 1: string}>
     */
    public static function borradoresIncompletos(): array
    {
        return [
            'canales vacío' => [['canales' => []], 'canales'],
            'público sin descripción' => [['publico.descripcion' => ''], 'publico.descripcion'],
            'oferta sin descripción' => [['oferta.descripcion' => ''], 'oferta.descripcion'],
            'presupuesto falta' => [['presupuesto_mxn' => null], 'presupuesto_mxn'],
        ];
    }

    /** @dataProvider borradoresIncompletos */
    public function test_borrador_incompleto_se_guarda_pero_no_genera_ni_activa(array $cambios, string $campo): void
    {
        $this->asignarIa(['marketing.campana_360', 'marketing.piezas_campana']); // con IA: el freno debe ser la campaña, no la IA
        $res = $this->crear($cambios);
        $res->assertStatus(201)->assertJsonPath('data.estado', 'draft');
        $this->assertContains($campo, (array) $res->json('data.faltantes'), "{$campo} debe figurar en faltantes");
        $id = (int) $res->json('data.id');
        $this->assertSame(1, (int) DB::table('marketing_campaigns')->where('id', $id)->value('company_id'));

        foreach (['todo', 'estrategia', 'piezas'] as $alcance) {
            $g = $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/generar", ['alcance' => $alcance, 'reemplazar' => false]);
            $g->assertStatus(422)->assertJson(['ok' => false, 'error' => 'campana_incompleta']);
            $this->assertContains($campo, (array) $g->json('faltantes'));
            $this->assertStringContainsString('Completa la campaña', (string) $g->json('mensaje'));
        }
        $this->assertSame([], $this->llamadasIa, 'Una campaña incompleta no llama a la IA');
        Queue::assertNothingPushed();
        $this->assertSame('sin_generar', DB::table('marketing_campaigns')->where('id', $id)->value('estrategia_estado'));

        // aunque alguien le ponga la estrategia en BD, activar sigue frenado por los faltantes
        DB::table('marketing_campaigns')->where('id', $id)->update(['estrategia_estado' => 'lista', 'estrategia' => json_encode($this->fixture('estrategia-valida.json'))]);
        $a = $this->como($this->admin)->postJson("/api/marketing/campanas-360/{$id}/estado", ['estado' => 'active']);
        $a->assertStatus(422)->assertJsonPath('error', 'campana_incompleta');
        $this->assertSame('draft', DB::table('marketing_campaigns')->where('id', $id)->value('status'));
    }

    public function test_borrador_solo_con_nombre_y_objetivo_y_luego_se_completa(): void
    {
        $res = $this->como($this->admin)->postJson('/api/marketing/campanas-360', ['nombre' => 'QA2 Solo paso 1', 'objetivo' => 'promocion']);
        $res->assertStatus(201);
        $id = (int) $res->json('data.id');
        $this->assertNull($res->json('data.presupuesto_mxn'), 'Presupuesto sin capturar = null (0.00 sería orgánica)');
        foreach (['publico.descripcion', 'oferta.descripcion', 'canales', 'presupuesto_mxn', 'fecha_inicio', 'fecha_fin'] as $f) {
            $this->assertContains($f, (array) $res->json('data.faltantes'));
        }
        $completa = $this->cuerpoCampana();
        unset($completa['nombre'], $completa['objetivo']);
        $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$id}", $completa)->assertStatus(200)->assertJsonPath('data.faltantes', []);
        // sin nombre u objetivo sigue siendo 422
        $this->como($this->admin)->postJson('/api/marketing/campanas-360', ['nombre' => 'QA2 sin objetivo'])->assertStatus(422)->assertJsonValidationErrors('objetivo');
        $this->como($this->admin)->postJson('/api/marketing/campanas-360', ['objetivo' => 'promocion'])->assertStatus(422)->assertJsonValidationErrors('nombre');
    }

    /** @dataProvider invalidos */
    public function test_alta_invalida_da_422_con_el_campo(array $cambios, string $campo): void
    {
        $antes = $this->contarFilas('marketing_campaigns');
        $res = $this->crear($cambios);
        $res->assertStatus(422);
        $this->assertArrayHasKey($campo, (array) $res->json('errors'), "Se esperaba error en {$campo}: " . $this->etiqueta($res));
        $this->assertIsString($res->json('message'));
        $this->assertSame($antes, $this->contarFilas('marketing_campaigns'));
    }

    public function test_mensajes_de_validacion_en_espanol(): void
    {
        $res = $this->crear(['nombre' => '', 'presupuesto_mxn' => '-1', 'canales' => ['tiktok']]);
        $res->assertStatus(422);
        foreach ((array) $res->json('errors') as $campo => $msgs) {
            foreach ($msgs as $m) {
                $this->assertDoesNotMatchRegularExpression('/\bThe\b|\bfield\b|\bmust\b|\bis required\b|validation\./i', $m, "Mensaje en inglés en {$campo}: {$m}");
            }
        }
    }

    public function test_fechas_al_reves_inicio_pasado_y_vigencia_de_367_dias(): void
    {
        $hoy = $this->hoy();
        $this->crear(['fecha_inicio' => $hoy->addDays(10)->toDateString(), 'fecha_fin' => $hoy->addDays(5)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('fecha_fin');
        $this->crear(['fecha_inicio' => $hoy->subDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
        $this->crear(['fecha_inicio' => $hoy->toDateString(), 'fecha_fin' => $hoy->addDays(367)->toDateString()])->assertStatus(422);
        // bordes válidos: hoy (México), fin = inicio, 366 días
        $this->crear(['fecha_inicio' => $hoy->toDateString(), 'fecha_fin' => $hoy->toDateString()])->assertStatus(201);
        $this->crear(['fecha_inicio' => $hoy->toDateString(), 'fecha_fin' => $hoy->addDays(366)->toDateString()])->assertStatus(201);
    }

    public function test_bordes_validos_de_texto_y_edad(): void
    {
        $this->crear([
            'nombre' => 'abc', 'publico.descripcion' => str_repeat('d', 500), 'publico.edad_min' => 13, 'publico.edad_max' => 99,
            'publico.zonas' => array_fill(0, 20, str_repeat('z', 80)), 'oferta.precio_mxn' => '99999999.99', 'presupuesto_mxn' => '9999999999.99',
            'canales' => ['whatsapp'],
        ])->assertStatus(201)->assertJsonPath('data.presupuesto_mxn', '9999999999.99')->assertJsonPath('data.oferta.precio_mxn', '99999999.99');
    }

    // ── ataques de texto ─────────────────────────────────────────────────────

    public function test_html_y_script_se_limpian(): void
    {
        $res = $this->crear([
            'nombre' => 'QA2 <script>alert(1)</script>Fibra<b>!</b>',
            'publico.descripcion' => '<img src=x onerror=alert(1)>Familias',
            'oferta.descripcion' => '<a href="javascript:alert(1)">500 Megas</a>',
        ]);
        $res->assertStatus(201);
        $json = json_encode($res->json('data'));
        $this->assertStringNotContainsString('<script', $json);
        $this->assertStringNotContainsString('onerror', $json);
        $this->assertStringNotContainsString('javascript:', $json);
        $this->assertStringNotContainsString('<b>', $json);
        $fila = DB::table('marketing_campaigns')->find($res->json('data.id'));
        $this->assertStringNotContainsString('<', $fila->name . $fila->publico . $fila->oferta);
    }

    public function test_llaves_de_plantilla_y_caracteres_de_control_no_entran(): void
    {
        $res = $this->crear(['nombre' => "QA2 Promo {{brand.phone}}", 'oferta.descripcion' => "500 Megas\x00\x07 {{_override_primary_color}}"]);
        if ($res->getStatusCode() === 201) {
            $json = json_encode($res->json('data'));
            $this->assertStringNotContainsString('{{', $json);
            $this->assertStringNotContainsString('}}', $json);
            $this->assertStringNotContainsString('\u0000', $json);
            $this->assertStringNotContainsString('\u0007', $json);
        } else {
            $res->assertStatus(422);
        }
    }

    public function test_sql_en_textos_se_guarda_como_texto_y_no_rompe_nada(): void
    {
        $sql = "QA2 x'); DROP TABLE marketing_campaigns; --";
        $antes = $this->contarFilas('marketing_campaigns');
        $res = $this->crear(['nombre' => $sql, 'publico.zonas' => ["' OR 1=1 --"]]);
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('marketing_campaigns'));
        if ($res->getStatusCode() === 403) {
            // El WAF global del sistema (SqlInjectionProtection) la frena antes del controlador: correcto, nada se guarda.
            $this->assertStringContainsString('bloqueada por seguridad', (string) $res->getContent());
            $this->assertSame($antes, $this->contarFilas('marketing_campaigns'));

            return;
        }
        $res->assertStatus(201);
        $this->assertSame($sql, DB::table('marketing_campaigns')->where('id', $res->json('data.id'))->value('name'));
    }

    public function test_emojis_en_nombre_no_truenan(): void
    {
        $res = $this->crear(['nombre' => 'QA2 Promo 🚀🔥 navidad']);
        $this->assertContains($res->getStatusCode(), [201, 422], 'Emojis: se aceptan o se rechazan con 422, nunca 500');
    }

    public function test_filtros_del_indice_raros_no_truenan(): void
    {
        $this->crear();
        foreach (["?estado=draft", "?estado=inexistente", "?estado=draft'%20OR%201=1", "?pagina=0", "?pagina=-5", "?pagina=abc", "?pagina=99999",
            "?estado[]=draft", "?orden=id;DROP"] as $q) {
            $res = $this->como($this->admin)->getJson('/api/marketing/campanas-360' . $q);
            $this->assertContains($res->getStatusCode(), [200, 403, 422], "{$q} → " . $this->etiqueta($res));
            if ($res->getStatusCode() === 403) {
                $this->assertStringContainsString('bloqueada por seguridad', (string) $res->getContent(), 'Solo el WAF global puede dar 403 al admin');
            }
        }
        $res = $this->como($this->admin)->getJson('/api/marketing/campanas-360?estado=draft&pagina=1')->assertStatus(200);
        $this->assertSame(1, $res->json('meta.pagina'));
        $this->assertSame(20, $res->json('meta.por_pagina'));
        foreach ($res->json('data') as $fila) {
            $this->assertSame('draft', $fila['estado']);
            foreach (['id', 'nombre', 'estado', 'estado_etiqueta', 'objetivo_etiqueta', 'canales', 'presupuesto_mxn', 'fecha_inicio', 'fecha_fin', 'generacion', 'videos_resumen'] as $k) {
                $this->assertArrayHasKey($k, $fila);
            }
        }
    }

    public function test_indice_ordenado_por_creacion_desc_y_paginado_de_20(): void
    {
        $ids = [];
        for ($i = 0; $i < 22; $i++) {
            $ids[] = $this->campanaDirecta(1, ['name' => "QA2 orden {$i}", 'created_at' => now()->addSeconds($i)])->id;
        }
        $p1 = $this->como($this->admin)->getJson('/api/marketing/campanas-360?pagina=1')->assertStatus(200);
        $this->assertCount(20, $p1->json('data'));
        $this->assertSame(end($ids), $p1->json('data.0.id'));
        $this->assertGreaterThanOrEqual(22, $p1->json('meta.total'));
    }

    // ── editar / borrar ──────────────────────────────────────────────────────

    public function test_editar_en_borrador_cambia_solo_lo_enviado(): void
    {
        $id = $this->crear()->json('data.id');
        $res = $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$id}", ['presupuesto_mxn' => '2000.5']);
        $res->assertStatus(200)->assertJsonPath('data.presupuesto_mxn', '2000.50')->assertJsonPath('data.objetivo', 'nuevos_clientes');
        $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$id}", ['canales' => ['tiktok']])->assertStatus(422);
    }

    public function test_editar_activa_da_409_campana_activa(): void
    {
        $c = $this->campanaDirecta(1, ['status' => 'active', 'estrategia_estado' => 'lista']);
        $res = $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['nombre' => 'QA2 cambio']);
        $res->assertStatus(409)->assertJson(['ok' => false, 'error' => 'campana_activa', 'mensaje' => 'Pausa la campaña para cambiar sus datos.']);
    }

    public function test_editar_pausada_con_fecha_inicio_pasada_sin_cambiarla_si_se_permite(): void
    {
        $c = $this->campanaDirecta(1, ['status' => 'paused', 'start_date' => $this->hoy()->subDays(5)->toDateString()]);
        $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['nombre' => 'QA2 pausada editada'])->assertStatus(200);
        $this->como($this->admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['fecha_inicio' => $this->hoy()->subDays(2)->toDateString()])->assertStatus(422);
    }

    public function test_borrar_solo_en_borrador_y_es_logico_conservando_videos(): void
    {
        $id = $this->crear()->json('data.id');
        $pieza = DB::table('marketing_generated_content')->insertGetId([
            'company_id' => 1, 'type' => 'copy', 'pieza' => 'copy_facebook', 'variante' => 'A', 'status' => 'ready', 'source_campaign_id' => $id,
            'generation_engine' => 'ia:qa', 'regeneraciones' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $video = DB::table('marketing_generated_content')->insertGetId([
            'company_id' => 1, 'type' => 'video', 'pieza' => 'reel', 'status' => 'ready', 'source_campaign_id' => $id,
            'generation_engine' => 'mvm_ffmpeg', 'regeneraciones' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->como($this->admin)->deleteJson("/api/marketing/campanas-360/{$id}")->assertStatus(200)->assertJson(['ok' => true]);
        $this->assertNotNull(DB::table('marketing_campaigns')->where('id', $id)->value('deleted_at'), 'Borrado lógico');
        $this->assertNotNull(DB::table('marketing_generated_content')->where('id', $pieza)->value('deleted_at'), 'Las piezas de texto se borran lógicamente');
        $this->assertNull(DB::table('marketing_generated_content')->where('id', $video)->value('deleted_at'), 'Los videos se conservan');
        $this->como($this->admin)->getJson("/api/marketing/campanas-360/{$id}")->assertStatus(404);

        $activa = $this->campanaDirecta(1, ['status' => 'active']);
        $this->como($this->admin)->deleteJson("/api/marketing/campanas-360/{$activa->id}")->assertStatus(409)->assertJsonPath('error', 'solo_borrador');
    }

    public function test_doble_envio_del_alta_crea_dos_borradores_distintos_sin_500(): void
    {
        // El candado contra doble clic es del frontend (botón deshabilitado); el backend no debe tronar.
        $cuerpo = $this->cuerpoCampana(['nombre' => 'QA2 doble clic']);
        $a = $this->como($this->admin)->postJson('/api/marketing/campanas-360', $cuerpo);
        $b = $this->como($this->admin)->postJson('/api/marketing/campanas-360', $cuerpo);
        $a->assertStatus(201);
        $this->assertContains($b->getStatusCode(), [201, 409, 422]);
    }
}
