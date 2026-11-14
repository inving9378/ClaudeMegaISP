<?php

namespace Tests\Feature\Marketing\Fase2\Backend;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\GeneratedContent;
use App\Modules\Addons\Marketing\Models\Empresa;
use Illuminate\Support\Carbon;
use Tests\Feature\Marketing\Fase2\Backend\Concerns\EscenarioBackendFase2;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 2 · backend — CRUD y estado de Campañas 360 (§2.4.1–§2.4.6): validación del asistente, formas JSON,
 * aislamiento por empresa y rol, transiciones.
 */
class Campana360CrudBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sembrarNichos();
    }

    public function test_crea_borrador_con_forma_campana360(): void
    {
        $admin = $this->admin();

        $r = $this->actingAs($admin)->postJson('/api/marketing/campanas-360', $this->payloadCampana());

        $r->assertStatus(201)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.estado', 'draft')
            ->assertJsonPath('data.estado_etiqueta', 'Borrador')
            ->assertJsonPath('data.objetivo_etiqueta', 'Conseguir clientes nuevos')
            ->assertJsonPath('data.presupuesto_mxn', '15000.00')
            ->assertJsonPath('data.moneda', 'MXN')
            ->assertJsonPath('data.oferta.precio_mxn', '599.00')
            ->assertJsonPath('data.estrategia_estado', 'sin_generar')
            ->assertJsonPath('data.generacion.estado', 'sin_generar')
            ->assertJsonPath('data.videos_resumen.total', 0);

        $c = Campaign::query()->withoutGlobalScopes()->find($r->json('data.id'));
        $this->assertSame('mixed', $c->type);
        $this->assertSame(Empresa::PRINCIPAL_ID, (int) $c->company_id);
        $this->assertSame($admin->id, (int) $c->creado_por_user_id);
        $this->assertSame('MXN', $c->currency);
        $this->assertStringStartsWith('fibra-500-prueba-', $c->slug);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}-0[56]:00$/', $r->json('data.creada_at'));
    }

    public function test_presupuesto_cero_se_permite(): void
    {
        $r = $this->actingAs($this->admin())->postJson('/api/marketing/campanas-360', $this->payloadCampana(['presupuesto_mxn' => '0']));
        $r->assertStatus(201)->assertJsonPath('data.presupuesto_mxn', '0.00');
    }

    /** @dataProvider casosInvalidos */
    public function test_validacion_del_asistente(array $cambios, string $campo): void
    {
        $payload = $this->payloadCampana();
        foreach ($cambios as $ruta => $valor) {
            data_set($payload, $ruta, $valor);
        }

        $this->actingAs($this->admin())->postJson('/api/marketing/campanas-360', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campo]);
    }

    public static function casosInvalidos(): array
    {
        return [
            'nombre corto'           => [['nombre' => 'ab'], 'nombre'],
            'nombre largo'           => [['nombre' => str_repeat('a', 151)], 'nombre'],
            'nombre con llaves'      => [['nombre' => 'Hola {{brand.phone}}'], 'nombre'],
            'nombre arreglo'         => [['nombre' => ['x']], 'nombre'],
            'nombre solo etiquetas'  => [['nombre' => '<b></b>'], 'nombre'],
            'objetivo fuera'         => [['objetivo' => 'hackear'], 'objetivo'],
            'canal fuera'            => [['canales' => ['tiktok']], 'canales.0'],
            'canales de más'         => [['canales' => ['facebook', 'instagram', 'reels', 'video', 'anuncios_meta', 'whatsapp', 'facebook']], 'canales'],
            'publico descripcion 2'  => [['publico.descripcion' => 'ab'], 'publico.descripcion'],
            'publico texto'          => [['publico' => 'familias'], 'publico'],
            'oferta descripcion 2'   => [['oferta.descripcion' => 'ab'], 'oferta.descripcion'],
            'sin nombre'             => [['nombre' => null], 'nombre'],
            'sin objetivo'           => [['objetivo' => null], 'objetivo'],
            'canales repetidos'      => [['canales' => ['facebook', 'facebook']], 'canales.0'],
            'canales objeto'         => [['canales' => ['a' => 'facebook']], 'canales'],
            'edad fuera'             => [['publico.edad_min' => 10], 'publico.edad_min'],
            'edad al revés'          => [['publico.edad_min' => 60, 'publico.edad_max' => 30], 'publico.edad_max'],
            'nicho inexistente'      => [['publico.nichos' => ['marcianos']], 'publico.nichos.0'],
            'demasiadas zonas'       => [['publico.zonas' => array_fill(0, 21, 'Z')], 'publico.zonas'],
            'precio 3 decimales'     => [['oferta.precio_mxn' => '599.999'], 'oferta.precio_mxn'],
            'presupuesto 3 decimales'=> [['presupuesto_mxn' => '100.123'], 'presupuesto_mxn'],
            'presupuesto negativo'   => [['presupuesto_mxn' => '-5'], 'presupuesto_mxn'],
            'presupuesto texto'      => [['presupuesto_mxn' => 'mil'], 'presupuesto_mxn'],
            'presupuesto arreglo'    => [['presupuesto_mxn' => ['1']], 'presupuesto_mxn'],
            'inicio en el pasado'    => [['fecha_inicio' => '2020-01-01'], 'fecha_inicio'],
            'fin antes de inicio'    => [['fecha_fin' => '2020-01-01'], 'fecha_fin'],
            'fecha mal formada'      => [['fecha_fin' => '31/12/2026'], 'fecha_fin'],
        ];
    }

    public function test_borrador_parcial_con_solo_el_paso_1_f1(): void
    {
        $admin = $this->admin();

        $r = $this->actingAs($admin)->postJson('/api/marketing/campanas-360', ['nombre' => 'Solo nombre', 'objetivo' => 'promocion', 'canales' => []])
            ->assertStatus(201)
            ->assertJsonPath('data.presupuesto_mxn', null)
            ->assertJsonPath('data.publico', null)
            ->assertJsonPath('data.canales', []);
        $this->assertSame(['publico.descripcion', 'oferta.descripcion', 'canales', 'presupuesto_mxn', 'fecha_inicio', 'fecha_fin'], $r->json('data.faltantes'));
        $id = $r->json('data.id');

        // Lo que sí viene se valida igual de estricto.
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$id}", ['presupuesto_mxn' => 1500.5])->assertStatus(422);
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$id}", ['nombre' => ''])->assertStatus(422)->assertJsonValidationErrors(['nombre']);

        // Paso a paso hasta completarla.
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$id}", ['publico' => ['descripcion' => 'Familias'], 'canales' => ['facebook']])->assertOk();
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$id}", ['presupuesto_mxn' => '0'])->assertOk()->assertJsonPath('data.presupuesto_mxn', '0.00');
        $p = $this->payloadCampana();
        $fin = $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$id}", ['oferta' => $p['oferta'], 'fecha_inicio' => $p['fecha_inicio'], 'fecha_fin' => $p['fecha_fin']])
            ->assertOk();
        $this->assertSame([], $fin->json('data.faltantes'));
        $this->assertSame('Solo nombre', $fin->json('data.nombre'));
    }

    public function test_activar_exige_campana_completa_y_cancelar_no(): void
    {
        $admin = $this->admin();
        $id = $this->actingAs($admin)->postJson('/api/marketing/campanas-360', ['nombre' => 'Incompleta', 'objetivo' => 'promocion'])->json('data.id');
        Campaign::query()->whereKey($id)->update(['estrategia_estado' => 'lista']);

        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$id}/estado", ['estado' => 'active'])
            ->assertStatus(422)->assertJsonPath('error', 'campana_incompleta');
        $this->actingAs($admin)->postJson("/api/marketing/campanas-360/{$id}/estado", ['estado' => 'canceled'])->assertOk();
    }

    public function test_fecha_fin_anterior_a_inicio_422_claro_en_post_y_put(): void
    {
        $admin = $this->admin();
        $inicio = Carbon::now('America/Mexico_City')->addDays(10);

        // Aunque haya otros errores, la fecha de fin se reporta.
        $this->actingAs($admin)->postJson('/api/marketing/campanas-360', $this->payloadCampana([
            'nombre' => 'ab', 'fecha_inicio' => $inicio->toDateString(), 'fecha_fin' => $inicio->copy()->subDay()->toDateString(),
        ]))->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'fecha_fin'])
            ->assertJsonPath('errors.fecha_fin.0', 'La fecha de fin debe ser igual o posterior a la de inicio.');

        $c = $this->crearCampana360($admin, ['fecha_inicio' => $inicio->toDateString(), 'fecha_fin' => $inicio->copy()->addDays(5)->toDateString()]);
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['fecha_fin' => $inicio->copy()->subDays(2)->toDateString()])
            ->assertStatus(422)->assertJsonPath('errors.fecha_fin.0', 'La fecha de fin debe ser igual o posterior a la de inicio.');
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['fecha_inicio' => $inicio->copy()->addDays(9)->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_fin']);
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['fecha_fin' => $inicio->toDateString()])->assertOk();
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['fecha_fin' => '2026-02-31'])
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_fin']);
    }

    public function test_publico_solo_con_tipo_basta_y_la_zona_se_guarda_si_llega(): void
    {
        $admin = $this->admin();
        $r = $this->actingAs($admin)->postJson('/api/marketing/campanas-360', $this->payloadCampana([
            'publico' => ['descripcion' => 'Nuevos clientes', 'zonas' => null, 'edad_min' => null, 'edad_max' => null, 'nichos' => null, 'intereses' => null],
        ]))->assertStatus(201);
        $this->assertSame([], $r->json('data.faltantes'));
        $this->assertEquals(['descripcion' => 'Nuevos clientes', 'zonas' => [], 'edad_min' => null, 'edad_max' => null, 'nichos' => [], 'intereses' => []],
            $r->json('data.publico'));

        // Diagnóstico «Nextlalpan»: el backend guarda la zona cuando llega en la lista.
        $id = $r->json('data.id');
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$id}", ['publico' => ['descripcion' => 'Nuevos clientes', 'zonas' => ['Nextlalpan']]])
            ->assertOk()->assertJsonPath('data.publico.zonas', ['Nextlalpan']);
        $this->actingAs($admin)->getJson("/api/marketing/campanas-360/{$id}")->assertJsonPath('data.publico.zonas', ['Nextlalpan']);
        // Como texto suelto (no lista) se rechaza con 422, nunca se descarta en silencio.
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$id}", ['publico' => ['descripcion' => 'Nuevos clientes', 'zonas' => 'Nextlalpan']])
            ->assertStatus(422)->assertJsonValidationErrors(['publico.zonas']);
    }

    public function test_vigencia_de_367_dias_se_rechaza(): void
    {
        $inicio = Carbon::now('America/Mexico_City')->addDay();
        $this->actingAs($this->admin())->postJson('/api/marketing/campanas-360', $this->payloadCampana([
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin'    => $inicio->copy()->addDays(367)->toDateString(),
        ]))->assertStatus(422)->assertJsonValidationErrors(['fecha_fin']);
    }

    public function test_etiquetas_html_se_quitan_y_control_tambien(): void
    {
        $r = $this->actingAs($this->admin())->postJson('/api/marketing/campanas-360', $this->payloadCampana([
            'nombre' => "<script>alert(1)</script>Fibra\x07 rápida",
        ]));

        $r->assertStatus(201);
        $this->assertSame('alert(1)Fibra rápida', $r->json('data.nombre'));
    }

    public function test_indice_pagina_filtra_y_solo_trae_campanas_360(): void
    {
        $admin = $this->admin();
        $a = $this->crearCampana360($admin, ['nombre' => 'Campaña A']);
        $this->crearCampana360($admin, ['nombre' => 'Campaña B']);
        $this->crearCampana(Empresa::PRINCIPAL_ID, ['name' => 'Legado sin objetivo']);
        $a->update(['status' => 'canceled']);

        $todas = $this->actingAs($admin)->getJson('/api/marketing/campanas-360')->assertOk();
        $nombres = collect($todas->json('data'))->pluck('nombre');
        $this->assertContains('Campaña A', $nombres);
        $this->assertNotContains('Legado sin objetivo', $nombres);
        $this->assertSame(20, $todas->json('meta.por_pagina'));
        $this->assertSame(['id', 'nombre', 'estado', 'estado_etiqueta', 'objetivo_etiqueta', 'canales', 'presupuesto_mxn',
            'fecha_inicio', 'fecha_fin', 'generacion', 'videos_resumen'], array_keys($todas->json('data.0')));

        $canceladas = $this->actingAs($admin)->getJson('/api/marketing/campanas-360?estado=canceled')->assertOk();
        $this->assertSame(['Campaña A'], collect($canceladas->json('data'))->pluck('nombre')->all());

        $this->actingAs($admin)->getJson('/api/marketing/campanas-360?estado=raro')->assertStatus(422);
        $this->actingAs($admin)->getJson('/api/marketing/campanas-360?pagina=0')->assertStatus(422);
    }

    public function test_ficha_trae_todas_las_claves_de_piezas(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $this->crearPieza($c, 'copy_facebook', 'B', ['texto' => 'b', 'hashtags' => [], 'cta' => '']);
        $this->crearPieza($c, 'copy_facebook', 'A', ['texto' => 'a', 'hashtags' => [], 'cta' => '']);

        $r = $this->actingAs($admin)->getJson("/api/marketing/campanas-360/{$c->id}")->assertOk();

        $this->assertSame(['reel_guion', 'video_guion', 'copy_facebook', 'copy_instagram', 'mensaje_whatsapp', 'variante_anuncio'],
            array_keys($r->json('data.piezas')));
        $this->assertSame(['A', 'B'], collect($r->json('data.piezas.copy_facebook'))->pluck('variante')->all());
        $this->assertSame('lista', $r->json('data.piezas.copy_facebook.0.estado'));
        $this->assertSame([], $r->json('data.videos'));
    }

    public function test_ids_inexistentes_o_de_otra_empresa_dan_404(): void
    {
        $admin = $this->admin();
        $otra = $this->crearEmpresa();
        $ajena = $this->crearCampana($otra->id, ['name' => 'Ajena', 'objetivo' => 'promocion', 'status' => 'draft']);

        foreach (["/api/marketing/campanas-360/{$ajena->id}", '/api/marketing/campanas-360/99999999'] as $url) {
            $this->actingAs($admin)->getJson($url)
                ->assertStatus(404)
                ->assertJson(['ok' => false, 'error' => 'no_encontrado', 'mensaje' => 'No encontramos esa campaña.']);
        }
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$ajena->id}", ['nombre' => 'Robada'])->assertStatus(404);
        $this->actingAs($admin)->deleteJson("/api/marketing/campanas-360/{$ajena->id}")->assertStatus(404);
        $this->actingAs($admin)->getJson('/api/marketing/campanas-360/abc')->assertStatus(404);
    }

    public function test_acceso_negado_sin_sesion_sin_equipo_y_rol_no_admin(): void
    {
        $this->getJson('/api/marketing/campanas-360')->assertStatus(401);

        $sinEquipo = $this->crearUsuario();
        $this->actingAs($sinEquipo)->getJson('/api/marketing/campanas-360')->assertStatus(403);

        $marketing = $this->crearUsuario();
        $this->asignar($marketing, Empresa::PRINCIPAL_ID, 'marketing');
        $this->actingAs($marketing)->postJson('/api/marketing/campanas-360', $this->payloadCampana())->assertStatus(403);

        $superSinFila = $this->crearUsuario(['super-administrator']);
        $this->actingAs($superSinFila)->getJson('/api/marketing/campanas-360')->assertStatus(403);
    }

    public function test_editar_solo_en_borrador_o_pausada(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);

        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['nombre' => 'Nuevo nombre', 'presupuesto_mxn' => '20000.5'])
            ->assertOk()
            ->assertJsonPath('data.nombre', 'Nuevo nombre')
            ->assertJsonPath('data.presupuesto_mxn', '20000.50')
            ->assertJsonPath('data.objetivo', 'nuevos_clientes');

        $c->update(['status' => 'active']);
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['nombre' => 'Otro'])
            ->assertStatus(409)->assertJsonPath('error', 'campana_activa');

        $c->update(['status' => 'paused']);
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['nombre' => 'Pausada editada'])->assertOk();
    }

    public function test_editar_sin_cambiar_inicio_pasado_se_permite_y_cambiarlo_no(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $c->update(['start_date' => '2020-01-01', 'end_date' => '2020-02-01']);

        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['nombre' => 'Con inicio viejo', 'fecha_inicio' => '2020-01-01'])
            ->assertOk();
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['fecha_inicio' => '2020-01-02'])
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_inicio']);
        $this->actingAs($admin)->putJson("/api/marketing/campanas-360/{$c->id}", ['fecha_fin' => '2019-12-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['fecha_fin']);
    }

    public function test_borrar_solo_borrador_y_conserva_videos(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $pieza = $this->crearPieza($c, 'copy_facebook', 'A', ['texto' => 'a', 'hashtags' => [], 'cta' => '']);
        $video = $this->crearPieza($c, 'reel', 'A', [], 'ready');
        $video->forceFill(['type' => 'video'])->save();

        $this->actingAs($admin)->deleteJson("/api/marketing/campanas-360/{$c->id}")->assertOk()->assertExactJson(['ok' => true]);

        $this->assertSoftDeleted('marketing_campaigns', ['id' => $c->id]);
        $this->assertSoftDeleted('marketing_generated_content', ['id' => $pieza->id]);
        $this->assertNotNull(GeneratedContent::query()->withoutGlobalScopes()->find($video->id));

        $d = $this->crearCampana360($admin);
        $d->update(['status' => 'paused']);
        $this->actingAs($admin)->deleteJson("/api/marketing/campanas-360/{$d->id}")->assertStatus(409)->assertJsonPath('error', 'solo_borrador');
    }

    public function test_transiciones_de_estado(): void
    {
        $admin = $this->admin();
        $c = $this->crearCampana360($admin);
        $url = "/api/marketing/campanas-360/{$c->id}/estado";

        $this->actingAs($admin)->postJson($url, ['estado' => 'active'])->assertStatus(422)->assertJsonPath('error', 'sin_estrategia');
        $this->actingAs($admin)->postJson($url, ['estado' => 'finished'])
            ->assertStatus(422)->assertJsonPath('error', 'transicion_invalida')
            ->assertJsonPath('mensaje', 'No se puede pasar de Borrador a Terminada.');
        $this->actingAs($admin)->postJson($url, ['estado' => 'raro'])->assertStatus(422);

        $c->update(['estrategia_estado' => 'lista']);
        $this->actingAs($admin)->postJson($url, ['estado' => 'active'])->assertOk()->assertJsonPath('data.estado', 'active');
        $this->actingAs($admin)->postJson($url, ['estado' => 'paused'])->assertOk()->assertJsonPath('data.estado_etiqueta', 'Pausada');
        $this->actingAs($admin)->postJson($url, ['estado' => 'active'])->assertOk();
        $this->actingAs($admin)->postJson($url, ['estado' => 'finished'])->assertOk();
        $this->actingAs($admin)->postJson($url, ['estado' => 'active'])->assertStatus(422)->assertJsonPath('error', 'transicion_invalida');
    }

    public function test_vistas_blade_montan_el_tag_con_su_prop(): void
    {
        $this->usarLayoutMinimo();
        $admin = $this->admin();

        $this->actingAs($admin)->get('/marketing/campanas-360')->assertOk()->assertSee('<marketing-campanas-360-lista>', false);
        $this->actingAs($admin)->get('/marketing/campanas-360/nueva')->assertOk()->assertSee(':campana-id="null"', false);
        $this->actingAs($admin)->get('/marketing/campanas-360/12')->assertOk()->assertSee('<marketing-campana-360-ficha :campana-id="12">', false);
        $this->actingAs($admin)->get('/marketing/campanas-360/12/editar')->assertOk()->assertSee('<marketing-campana-360-asistente :campana-id="12">', false);
        $this->actingAs($this->crearUsuario())->get('/marketing/campanas-360')->assertStatus(403);

        // H-3: con fila marketing (sin_acceso) la pantalla es «No tienes acceso a Marketing», no el 403 genérico.
        $marketing = $this->crearUsuario();
        $this->asignar($marketing, Empresa::PRINCIPAL_ID, 'marketing');
        $this->actingAs($marketing)->get('/marketing/campanas-360/12')->assertStatus(403)->assertSee('No tienes acceso a Marketing');
        $this->actingAs($marketing)->getJson('/api/marketing/campanas-360')->assertStatus(403)->assertJsonPath('error', 'sin_acceso');
    }
}
