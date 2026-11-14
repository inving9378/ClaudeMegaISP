<?php

namespace Tests\Feature\Marketing\Fase0\Backend;

use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Marketing\Fase0\Backend\Concerns\EscenarioBackend;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 0 · backend — reglas exists: sobre tablas estrictas acotadas a la empresa activa (§2.4):
 * ContentGeneratorController (campaign_id) y PublishingController (channel_ids.*).
 *
 * Ajuste Fase 1 (cambio de alcance B de Henry, 2026-11-13): solo entra el admin de la empresa 1, así que el
 * usuario que hace la petición pasa a ser admin de la 1 y «la otra empresa» es B (antes era al revés: un
 * admin de B ya recibe 403 `sin_acceso` antes de validar). La exigencia (exists acotado) no cambia.
 */
class ExistsAcotadoBackendTest extends MarketingTestCase
{
    use EscenarioBackend;

    private User $usuarioB;

    private Empresa $b;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        Bus::fake();
        $this->prepararPermisos();

        $this->b = $this->crearEmpresa();
        $this->usuarioB = $this->crearUsuario(['super-administrator']);
        $this->asignar($this->usuarioB, Empresa::PRINCIPAL_ID, 'admin');
    }

    private function campana(int $companyId): int
    {
        return DB::table('marketing_campaigns')->insertGetId([
            'company_id' => $companyId, 'name' => 'Campaña ' . $companyId, 'slug' => 'c-' . uniqid(), 'type' => 'organic',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function canal(int $companyId): int
    {
        return DB::table('marketing_publication_channels')->insertGetId([
            'company_id' => $companyId, 'platform' => 'facebook', 'channel_type' => 'feed', 'name' => 'Canal ' . $companyId,
            'slug' => 'canal-' . uniqid(), 'supported_aspect_ratios' => json_encode(['1:1']), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_campaign_id_de_otra_empresa_es_422(): void
    {
        $ajena = $this->campana($this->b->id);

        $this->actingAs($this->usuarioB)->postJson('/marketing/content/generate-image', ['campaign_id' => $ajena])
            ->assertStatus(422)->assertJsonValidationErrors('campaign_id');
    }

    public function test_campaign_id_de_la_empresa_activa_pasa_la_validacion(): void
    {
        $propia = $this->campana(Empresa::PRINCIPAL_ID);

        $r = $this->actingAs($this->usuarioB)->postJson('/marketing/content/generate-image', ['campaign_id' => $propia]);

        $this->assertNotSame(403, $r->status(), 'Debe llegar a la validación (regla B: admin de la 1)');
        $this->assertArrayNotHasKey('campaign_id', (array) $r->json('errors'));
    }

    public function test_channel_ids_de_otra_empresa_es_422(): void
    {
        $ajeno = $this->canal($this->b->id);
        $propio = $this->canal(Empresa::PRINCIPAL_ID);

        $this->actingAs($this->usuarioB)->postJson('/api/marketing/publishing/campaigns/999999/publish', ['channel_ids' => [$propio, $ajeno]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('channel_ids.1')
            ->assertJsonMissingValidationErrors('channel_ids.0');
    }

    public function test_channel_ids_propios_pasan_la_validacion(): void
    {
        $propio = $this->canal(Empresa::PRINCIPAL_ID);

        // Pasa la validación; la campaña 999999 no existe → 404 del controlador.
        $this->actingAs($this->usuarioB)->postJson('/api/marketing/publishing/campaigns/999999/publish', ['channel_ids' => [$propio]])
            ->assertStatus(404);
    }
}
