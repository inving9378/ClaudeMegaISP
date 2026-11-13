<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase0\Qa\Concerns\EscenarioEmpresas;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — aislamiento en las rutas EXISTENTES (criterio 5, §2.4, §2.5).
 *
 * - Usuario de A contra recursos de B (y de B contra A) por id directo en CADA ruta existente con
 *   `{id}` de un modelo estricto → 404 y la fila destino queda intacta (sin colas, sin HTTP, sin correo).
 * - Listados, conteos y stats sin filas de la otra empresa.
 * - Las reglas `exists:` de §2.4 rechazan ids de la otra empresa (422).
 * - `company_id` mandado en el request se ignora.
 *
 * Fuera: vistas Blade con `{id}` (`marketing/leads/{id}`, `marketing/conversations/{id}`,
 * `marketing/lead-forms/{id}/edit`) — son cascarones que solo pasan el id a Vue y no leen la fila;
 * los datos llegan por la API, que sí se cubre aquí. Catálogos compartidos (`niches/{id}`,
 * `video-templates/{id}`) no se aíslan en Fase 0 (D3).
 */
class AislamientoEndpointsExistentesTest extends MarketingTestCase
{
    use EscenarioEmpresas;

    /** clave del modelo → tabla */
    private const TABLAS = [
        'lead' => 'marketing_leads', 'formulario' => 'marketing_lead_forms', 'conversacion' => 'marketing_conversations',
        'contenido' => 'marketing_generated_content', 'multivariante' => 'marketing_multivariant_campaigns',
        'piloto' => 'marketing_pilot_campaigns', 'canalPublicacion' => 'marketing_publication_channels',
        'publicacion' => 'marketing_publications', 'campana' => 'marketing_campaigns', 'envio' => 'marketing_pilot_campaign_sends',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Bus::fake();
        Mail::fake();
        Http::fake();
        $this->montarEscenario();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    /** [método, plantilla de URL, clave del modelo destino, cuerpo] */
    public static function rutasConId(): array
    {
        return [
            'lead ver'                 => ['GET', '/api/marketing/leads/{id}', 'lead', []],
            'lead editar'              => ['PUT', '/api/marketing/leads/{id}', 'lead', ['full_name' => 'HACK', 'status' => 'won', 'notes' => 'hack']],
            'lead borrar'              => ['DELETE', '/api/marketing/leads/{id}', 'lead', []],
            'lead reasignar'           => ['POST', '/api/marketing/leads/{id}/assign', 'lead', ['user_id' => '{actor}']],
            'lead puntaje IA'          => ['POST', '/api/marketing/leads/{id}/score', 'lead', []],
            'lead historial'           => ['GET', '/api/marketing/leads/{id}/activities', 'lead', []],
            'formulario ver'           => ['GET', '/api/marketing/lead-forms/{id}', 'formulario', []],
            'formulario editar'        => ['PUT', '/api/marketing/lead-forms/{id}', 'formulario', ['name' => 'HACK', 'active' => false]],
            'formulario borrar'        => ['DELETE', '/api/marketing/lead-forms/{id}', 'formulario', []],
            'formulario embed'         => ['GET', '/api/marketing/lead-forms/{id}/embed-code', 'formulario', []],
            'conversación ver'         => ['GET', '/api/marketing/conversations/{id}', 'conversacion', []],
            'conversación mensajes'    => ['GET', '/api/marketing/conversations/{id}/messages', 'conversacion', []],
            'conversación enviar'      => ['POST', '/api/marketing/conversations/{id}/send-message', 'conversacion', ['text' => 'hack']],
            'conversación IA on/off'   => ['POST', '/api/marketing/conversations/{id}/toggle-ai', 'conversacion', []],
            'conversación asignar'     => ['POST', '/api/marketing/conversations/{id}/assign', 'conversacion', ['user_id' => '{actor}']],
            'conversación cerrar'      => ['POST', '/api/marketing/conversations/{id}/close', 'conversacion', []],
            'conversación leído'       => ['POST', '/api/marketing/conversations/{id}/mark-as-read', 'conversacion', []],
            'video ver'                => ['GET', '/api/marketing/generated-content/{id}', 'contenido', []],
            'video progreso'           => ['GET', '/api/marketing/generated-content/{id}/progress', 'contenido', []],
            'video descargar'          => ['GET', '/api/marketing/generated-content/{id}/download', 'contenido', []],
            'video borrar'             => ['DELETE', '/api/marketing/generated-content/{id}', 'contenido', []],
            'multivariante ver'        => ['GET', '/api/marketing/multivariant-campaigns/{id}', 'multivariante', []],
            'multivariante progreso'   => ['GET', '/api/marketing/multivariant-campaigns/{id}/progress', 'multivariante', []],
            'multivariante borrar'     => ['DELETE', '/api/marketing/multivariant-campaigns/{id}', 'multivariante', []],
            'multivariante regenerar'  => ['POST', '/api/marketing/multivariant-campaigns/{id}/regenerate-variant/gamer', 'multivariante', []],
            'piloto ver'               => ['GET', '/api/marketing/pilot-campaigns/{id}', 'piloto', []],
            'piloto borrar'            => ['DELETE', '/api/marketing/pilot-campaigns/{id}', 'piloto', []],
            'piloto dry-run'           => ['POST', '/api/marketing/pilot-campaigns/{id}/dry-run', 'piloto', []],
            'piloto enviar'            => ['POST', '/api/marketing/pilot-campaigns/{id}/send', 'piloto', []],
            'piloto convertido'        => ['POST', '/api/marketing/pilot-campaigns/{id}/sends/{envio}/mark-converted', 'piloto', []],
            'publicar: ruteo'          => ['GET', '/api/marketing/publishing/campaigns/{id}/route', 'multivariante', []],
            'publicar: publicar'       => ['POST', '/api/marketing/publishing/campaigns/{id}/publish', 'multivariante', ['channel_ids' => ['{pc_propio}']]],
            'canal validar'            => ['POST', '/api/marketing/publishing/channels/{id}/validate', 'canalPublicacion', []],
            'canal configurar'         => ['PUT', '/api/marketing/publishing/channels/{id}/config', 'canalPublicacion', ['active' => false, 'platform_config' => ['page_id' => 'hack']]],
            'publicación ver'          => ['GET', '/api/marketing/publishing/publications/{id}', 'publicacion', []],
            'publicación reintentar'   => ['POST', '/api/marketing/publishing/publications/{id}/retry', 'publicacion', []],
            'publicación cancelar'     => ['POST', '/api/marketing/publishing/publications/{id}/cancel', 'publicacion', []],
            'publicación métricas'     => ['GET', '/api/marketing/publishing/publications/{id}/metrics', 'publicacion', []],
            'campaña legado editar'    => ['PUT', '/marketing/campaigns/{id}', 'campana', ['title' => 'HACK']],
            'campaña legado borrar'    => ['DELETE', '/marketing/campaigns/{id}', 'campana', []],
            'campaña legado aprobar'   => ['POST', '/marketing/campaigns/{id}/approve', 'campana', []],
            'campaña legado pausar'    => ['POST', '/marketing/campaigns/{id}/pause', 'campana', []],
            'campaña legado activar'   => ['POST', '/marketing/campaigns/{id}/activate', 'campana', []],
            'contenido por campaña'    => ['GET', '/marketing/content/campaign/{id}', 'campana', []],
            'lead legado ver'          => ['GET', '/marketing/leads-legacy/{id}', 'lead', []],
            'lead legado calificar'    => ['POST', '/marketing/leads-legacy/{id}/qualify', 'lead', []],
            'lead legado estatus'      => ['PUT', '/marketing/leads-legacy/{id}/status', 'lead', ['status' => 'won']],
            'lead legado asignar'      => ['POST', '/marketing/leads-legacy/{id}/assign', 'lead', ['user_id' => '{actor}']],
        ];
    }

    private function armar(string $plantilla, array $cuerpo, array $destino, array $propios, int $actorId): array
    {
        $url = str_replace(['{id}', '{envio}'], [$destino['__id'], $destino['envio']->id], $plantilla);
        array_walk_recursive($cuerpo, function (&$v) use ($propios, $actorId) {
            if ($v === '{actor}') {
                $v = $actorId;
            } elseif ($v === '{pc_propio}') {
                $v = $propios['canalPublicacion']->id;
            }
        });

        return [$url, $cuerpo];
    }

    private function fotografia(array $datos, string $clave): array
    {
        $foto = [$clave => $this->fila(self::TABLAS[$clave], $datos[$clave]->id)];
        if ($clave === 'conversacion') {
            $foto['mensajes'] = DB::table('marketing_messages')->where('conversation_id', $datos['conversacion']->id)->count();
        }
        if ($clave === 'piloto') {
            $foto['envio'] = $this->fila('marketing_pilot_campaign_sends', $datos['envio']->id);
        }
        if ($clave === 'lead') {
            $foto['actividades'] = DB::table('marketing_lead_activities')->where('lead_id', $datos['lead']->id)->count();
        }

        return $foto;
    }

    private function intentar(string $metodo, string $plantilla, string $clave, array $cuerpo, string $direccion): void
    {
        [$actor, $empresa, $destino, $propios] = $direccion === 'A→B'
            ? [$this->adminA, $this->empresaA->id, $this->b, $this->a]
            : [$this->adminB, $this->empresaB->id, $this->a, $this->b];

        $destino['__id'] = $destino[$clave]->id;
        [$url, $cuerpo] = $this->armar($plantilla, $cuerpo, $destino, $propios, $actor->id);
        $antes = $this->fotografia($destino, $clave);
        $publicacionesAntes = DB::table('marketing_publications')->count();

        $r = $this->como($actor, $empresa)->json($metodo, $url, $cuerpo);

        $this->assertSame(404, $r->status(), "{$direccion} {$metodo} {$url}: esperaba 404, llegó {$r->status()} " . substr((string) $r->getContent(), 0, 300));
        $this->assertFalse($this->contieneMarca($r, $destino['marca']), "{$direccion} {$metodo} {$url}: la respuesta trae datos de la otra empresa");
        $this->assertEquals($antes, $this->fotografia($destino, $clave), "{$direccion} {$metodo} {$url}: la fila de la otra empresa cambió");
        $this->assertSame($publicacionesAntes, DB::table('marketing_publications')->count(), 'no debe crear publicaciones');
        Queue::assertNothingPushed();
        Bus::assertNothingDispatched();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    /** @dataProvider rutasConId */
    public function test_usuario_de_a_contra_id_de_b_da_404_y_no_cambia_nada(string $metodo, string $plantilla, string $clave, array $cuerpo): void
    {
        $this->intentar($metodo, $plantilla, $clave, $cuerpo, 'A→B');
    }

    /** @dataProvider rutasConId */
    public function test_usuario_de_b_contra_id_de_a_da_404_y_no_cambia_nada(string $metodo, string $plantilla, string $clave, array $cuerpo): void
    {
        $this->intentar($metodo, $plantilla, $clave, $cuerpo, 'B→A');
    }

    public function test_campana_legado_ver_de_otra_empresa_no_responde_2xx_ni_cambia(): void
    {
        // Preexistente (§0.8): CampaignController@show hace withCount sobre tablas que no existen → 500 en la base.
        // Aquí solo se exige que NO entregue la campaña ajena.
        $antes = $this->fila('marketing_campaigns', $this->b['campana']->id);
        $r = $this->como($this->adminA, 1)->getJson("/marketing/campaigns/{$this->b['campana']->id}");

        $this->assertFalse($r->status() >= 200 && $r->status() < 300, "respondió {$r->status()}");
        $this->assertFalse($this->contieneMarca($r, $this->b['marca']));
        $this->assertEquals($antes, $this->fila('marketing_campaigns', $this->b['campana']->id));
    }

    // ── Listados, conteos y stats ────────────────────────────────────────────

    public static function listados(): array
    {
        return [
            'leads'               => ['/api/marketing/leads?per_page=100', true],
            'conversaciones'      => ['/api/marketing/conversations?per_page=100', true],
            'formularios'         => ['/api/marketing/lead-forms', true],
            'video generado'      => ['/api/marketing/generated-content', false],
            'multivariante'       => ['/api/marketing/multivariant-campaigns', true],
            'piloto'              => ['/api/marketing/pilot-campaigns', true],
            'publicaciones'       => ['/api/marketing/publishing/publications', true],
            'canales'             => ['/api/marketing/publishing/channels', true],
            'stats publicación'   => ['/api/marketing/publishing/dashboard/stats', false],
            'recientes'           => ['/api/marketing/publishing/dashboard/recent', false],
        ];
    }

    /** @dataProvider listados */
    public function test_listado_de_a_no_trae_filas_de_b(string $url, bool $debeVerLoPropio): void
    {
        $r = $this->como($this->adminA, 1)->getJson($url);
        $r->assertOk();
        $this->assertFalse($this->contieneMarca($r, $this->b['marca']), "{$url} trae datos de B");
        if ($debeVerLoPropio) {
            $this->assertTrue($this->contieneMarca($r, $this->a['marca']), "{$url}: con Meganet activa debe seguir viendo sus datos (criterio 4)");
        }
    }

    /** @dataProvider listados */
    public function test_listado_de_b_no_trae_filas_de_a(string $url, bool $noAplica = false): void
    {
        $r = $this->como($this->adminB, $this->empresaB->id)->getJson($url);
        $r->assertOk();
        $this->assertFalse($this->contieneMarca($r, $this->a['marca']), "{$url} trae datos de A con B activa");
    }

    public function test_stats_de_publicacion_cuentan_solo_la_empresa_activa(): void
    {
        $esperado = DB::table('marketing_publications')->where('company_id', 1)->whereNotNull('pub_channel_id')->whereNull('deleted_at')->count();
        $this->como($this->adminA, 1)->getJson('/api/marketing/publishing/dashboard/stats')->assertOk()->assertJsonPath('stats.total', $esperado);

        $this->como($this->adminB, $this->empresaB->id)->getJson('/api/marketing/publishing/dashboard/stats')
            ->assertOk()->assertJsonPath('stats.total', 0);
    }

    public function test_conteo_de_leads_paginado_cuadra_con_la_empresa(): void
    {
        $esperado = DB::table('marketing_leads')->where('company_id', 1)->whereNull('deleted_at')->count();
        $r = $this->como($this->adminA, 1)->getJson('/api/marketing/leads');
        $this->assertSame($esperado, $r->json('meta.total'));
    }

    public function test_filtros_con_inyeccion_sql_no_cruzan_empresas(): void
    {
        $ataques = [
            '?search=' . urlencode("%' OR 1=1 -- "),
            '?search=' . urlencode($this->b['marca']),
            '?sort=' . urlencode('company_id; DROP TABLE marketing_leads') . '&order=' . urlencode("asc, (select 1)"),
            '?status=' . urlencode("new' OR '1'='1"),
            '?tags[]=' . urlencode('"]\') OR 1=1 #'),
            '?score_min=' . urlencode('0 OR 1=1'),
        ];
        foreach ($ataques as $q) {
            $r = $this->como($this->adminA, 1)->getJson('/api/marketing/leads' . $q);
            $this->assertFalse($this->contieneMarca($r, $this->b['marca']), "leads{$q} trae datos de B");
        }
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('marketing_leads'));

        $r = $this->como($this->adminA, 1)->getJson('/api/marketing/conversations?search=' . urlencode($this->b['marca']));
        $this->assertFalse($this->contieneMarca($r, $this->b['marca']), 'conversations?search trae datos de B');
    }

    /**
     * Datos raros en filtros: no deben tronar (500). Si falla, comparar contra la base: lo más probable es
     * que sea PREEXISTENTE (MarketingLeadController@index no se toca en Fase 0).
     */
    public function test_filtros_raros_de_leads_no_truenan(): void
    {
        foreach ([
            '?sort=' . urlencode('company_id; DROP TABLE marketing_leads') . '&order=' . urlencode("asc, (select 1)"),
            '?tags[]=' . urlencode('"]\') OR 1=1 #'),
            '?score_min=' . urlencode('0 OR 1=1'),
            '?captured_from=' . urlencode('2099-13-45') . '&captured_to=' . urlencode('1900-01-01'),
            '?per_page=-1',
            '?per_page=0',
            '?per_page=99999999999',
            '?search=' . urlencode(str_repeat('ñ🚀', 5000)),
        ] as $q) {
            $r = $this->como($this->adminA, 1)->getJson('/api/marketing/leads' . $q);
            $this->assertLessThan(500, $r->status(), "leads{$q} tronó con {$r->status()}");
        }
    }

    // ── exists: acotados a la empresa activa (§2.4) ──────────────────────────

    public function test_generate_copy_rechaza_campana_de_otra_empresa(): void
    {
        $r = $this->como($this->adminA, 1)->postJson('/marketing/content/generate-copy', ['campaign_id' => $this->b['campana']->id]);
        $r->assertStatus(422)->assertJsonValidationErrors(['campaign_id']);

        // Control: con su propia campaña el error ya no es campaign_id.
        $r = $this->como($this->adminA, 1)->postJson('/marketing/content/generate-copy', ['campaign_id' => $this->a['campana']->id]);
        $r->assertStatus(422)->assertJsonMissingValidationErrors(['campaign_id']);
    }

    public function test_generate_image_rechaza_campana_de_otra_empresa(): void
    {
        $r = $this->como($this->adminB, $this->empresaB->id)->postJson('/marketing/content/generate-image', ['campaign_id' => $this->a['campana']->id]);
        $r->assertStatus(422)->assertJsonValidationErrors(['campaign_id']);
        Http::assertNothingSent();
    }

    public function test_publicar_rechaza_canal_de_otra_empresa(): void
    {
        $antes = DB::table('marketing_publications')->count();
        $r = $this->como($this->adminA, 1)->postJson(
            "/api/marketing/publishing/campaigns/{$this->a['multivariante']->id}/publish",
            ['channel_ids' => [$this->b['canalPublicacion']->id]]
        );
        $r->assertStatus(422)->assertJsonValidationErrors(['channel_ids.0']);
        $this->assertSame($antes, DB::table('marketing_publications')->count());
        Queue::assertNothingPushed();
    }

    // ── company_id del request se ignora ─────────────────────────────────────

    public function test_alta_de_lead_ignora_company_id_del_request(): void
    {
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/leads', [
            'source_id' => $this->fuente->id, 'full_name' => 'Colado a B', 'company_id' => $this->empresaB->id,
        ]);
        $r->assertStatus(201);
        $this->assertSame(1, (int) DB::table('marketing_leads')->where('full_name', 'Colado a B')->value('company_id'));
    }

    public function test_alta_de_lead_con_b_activa_queda_en_b_aunque_el_controlador_diga_1(): void
    {
        $r = $this->como($this->adminB, $this->empresaB->id)->postJson('/api/marketing/leads', [
            'source_id' => $this->fuente->id, 'full_name' => 'Alta desde B', 'company_id' => 1,
        ]);
        $r->assertStatus(201);
        $this->assertSame($this->empresaB->id, (int) DB::table('marketing_leads')->where('full_name', 'Alta desde B')->value('company_id'));
    }

    public function test_editar_lead_no_lo_mueve_de_empresa(): void
    {
        $this->como($this->adminA, 1)->putJson("/api/marketing/leads/{$this->a['lead']->id}", [
            'company_id' => $this->empresaB->id, 'notes' => 'intento de mover',
        ])->assertOk();

        $this->assertSame(1, (int) DB::table('marketing_leads')->where('id', $this->a['lead']->id)->value('company_id'));
    }

    public function test_alta_de_campana_multivariante_ignora_company_id(): void
    {
        $r = $this->como($this->adminA, 1)->postJson('/api/marketing/multivariant-campaigns', [
            'name' => 'Multi colada', 'campaign_type' => 'multivariant_plan_promo', 'plan_name' => 'Plan', 'plan_speed' => '100 MEGAS',
            'plan_price' => '599', 'phone_cta' => '55 0000 0000', 'highlight_feature' => 'Sin contratos', 'company_id' => $this->empresaB->id,
        ]);
        $r->assertStatus(201);
        $this->assertSame(1, (int) DB::table('marketing_multivariant_campaigns')->where('name', 'Multi colada')->value('company_id'));
    }

    public function test_conversacion_de_otra_empresa_por_filtro_lead_o_channel_no_aparece(): void
    {
        $r = $this->como($this->adminA, 1)->getJson('/api/marketing/conversations?assigned_user_id=&channel_id=' . $this->canal->id);
        $r->assertOk();
        $this->assertFalse($this->contieneMarca($r, $this->b['marca']));
    }
}
