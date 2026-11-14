<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use App\Modules\Addons\Marketing\Models\Lead as LeadLegado;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * F1-3 — funciones legado retiradas (§2.5): validación y findOrFail con scope se conservan (422 / 404) y
 * después responden 410 con el cuerpo exacto. Ninguna llama a la IA ni hace peticiones HTTP.
 */
class RetiradasBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    private $admin;

    private int $propia;

    private int $ajena;

    private int $leadPropio;

    private int $leadAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();
        Http::preventStrayRequests();
        Bus::fake();

        $this->admin = $this->adminMeganet();
        $otra = (int) $this->crearEmpresa()->id;
        $this->propia = (int) $this->crearCampana(1, ['status' => 'draft'])->id;
        $this->ajena = (int) $this->crearCampana($otra, ['status' => 'draft'])->id;
        $this->leadPropio = (int) $this->crearLead(1, 'new', '2026-10-01 00:00:00')->id;
        $this->leadAjeno = (int) $this->crearLead($otra, 'new', '2026-10-01 00:00:00')->id;
    }

    private function retirada($respuesta): void
    {
        $respuesta->assertStatus(410)->assertExactJson($this->cuerpoRetirada());
    }

    public function test_stats_y_plantillas(): void
    {
        $this->retirada($this->actingAs($this->admin)->getJson('/marketing/stats'));
        $this->retirada($this->actingAs($this->admin)->getJson('/marketing/templates'));
        $this->retirada($this->actingAs($this->admin)->putJson('/marketing/templates/1', ['name' => 'x']));
        $this->retirada($this->actingAs($this->admin)->deleteJson('/marketing/templates/1'));

        $this->actingAs($this->admin)->postJson('/marketing/templates/store', [])->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'channel', 'template_type', 'base_copy']);
        $this->retirada($this->actingAs($this->admin)->postJson('/marketing/templates/store', [
            'name' => 'Plantilla', 'channel' => 'whatsapp', 'template_type' => 'campaign', 'base_copy' => 'Hola',
        ]));
    }

    public function test_campanas_legado(): void
    {
        $this->retirada($this->actingAs($this->admin)->postJson('/marketing/campaigns/table'));

        $this->actingAs($this->admin)->postJson('/marketing/campaigns/store', [])->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'channel', 'start_date', 'end_date']);
        $this->retirada($this->actingAs($this->admin)->postJson('/marketing/campaigns/store', [
            'title' => 'Legado', 'channel' => ['whatsapp'], 'start_date' => '2026-11-01', 'end_date' => '2026-11-30',
        ]));

        $acciones = [
            ['putJson', ''], ['deleteJson', ''], ['postJson', '/approve'], ['postJson', '/pause'], ['postJson', '/activate'],
        ];
        foreach ($acciones as [$metodo, $sufijo]) {
            $this->retirada($this->actingAs($this->admin)->{$metodo}("/marketing/campaigns/{$this->propia}{$sufijo}"));
            $this->actingAs($this->admin)->{$metodo}("/marketing/campaigns/{$this->ajena}{$sufijo}")->assertNotFound();
            $this->actingAs($this->admin)->{$metodo}("/marketing/campaigns/999999999{$sufijo}")->assertNotFound();
        }

        $this->assertDatabaseHas('marketing_campaigns', ['id' => $this->propia, 'status' => 'draft', 'deleted_at' => null]);
    }

    public function test_la_ficha_de_campana_legado_redirige_al_panel(): void
    {
        $this->actingAs($this->admin)->get("/marketing/campaigns/{$this->propia}")->assertRedirect('/marketing');
        $this->actingAs($this->admin)->get("/marketing/campaigns/{$this->ajena}")->assertNotFound();
    }

    public function test_generador_de_contenido_sin_ia_ni_http(): void
    {
        $this->retirada($this->actingAs($this->admin)->postJson('/marketing/content/generate-copy', ['campaign_id' => $this->propia, 'template_id' => 1]));
        $this->retirada($this->actingAs($this->admin)->postJson('/marketing/content/generate-image', ['campaign_id' => $this->propia]));

        $this->actingAs($this->admin)->postJson('/marketing/content/generate-copy', ['campaign_id' => $this->ajena, 'template_id' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('campaign_id');
        $this->actingAs($this->admin)->postJson('/marketing/content/generate-image', ['campaign_id' => $this->ajena])
            ->assertStatus(422)->assertJsonValidationErrors('campaign_id');
        $this->actingAs($this->admin)->postJson('/marketing/content/generate-copy', [])
            ->assertStatus(422)->assertJsonValidationErrors(['campaign_id', 'template_id']);

        $this->retirada($this->actingAs($this->admin)->postJson('/marketing/content/approve/1'));
        $this->retirada($this->actingAs($this->admin)->postJson('/marketing/content/reject/1'));
        $this->retirada($this->actingAs($this->admin)->getJson("/marketing/content/campaign/{$this->propia}"));
        $this->actingAs($this->admin)->getJson("/marketing/content/campaign/{$this->ajena}")->assertNotFound();

        Http::assertNothingSent();
        Bus::assertNothingDispatched();
    }

    public function test_leads_legado(): void
    {
        $this->retirada($this->actingAs($this->admin)->postJson('/marketing/leads-legacy/table'));

        $acciones = [['getJson', ''], ['postJson', '/qualify'], ['putJson', '/status'], ['postJson', '/assign']];
        foreach ($acciones as [$metodo, $sufijo]) {
            $this->retirada($this->actingAs($this->admin)->{$metodo}("/marketing/leads-legacy/{$this->leadPropio}{$sufijo}"));
            $this->actingAs($this->admin)->{$metodo}("/marketing/leads-legacy/{$this->leadAjeno}{$sufijo}")->assertNotFound();
        }

        $this->assertSame('new', LeadLegado::withoutGlobalScopes()->find($this->leadPropio)->status);
        Http::assertNothingSent();
    }

    public function test_el_panel_sigue_sirviendo_la_vista(): void
    {
        $this->usarLayoutMinimo();

        $this->actingAs($this->admin)->get('/marketing')->assertOk();
        $this->actingAs($this->admin)->get('/marketing/campaigns')->assertOk();
    }

    public function test_las_retiradas_siguen_negadas_a_quien_no_es_admin_de_meganet(): void
    {
        $marketing = $this->crearUsuario();
        $this->asignar($marketing, 1, 'marketing');

        $this->actingAs($marketing)->getJson('/marketing/stats')->assertForbidden();
        $this->actingAs($marketing)->postJson('/marketing/campaigns/table')->assertForbidden();
    }
}
