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
        $this->asignar($this->usuarioB, $this->b->id, 'admin');
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
        $deA = $this->campana(1);

        $this->actingAs($this->usuarioB)->postJson('/marketing/content/generate-image', ['campaign_id' => $deA])
            ->assertStatus(422)->assertJsonValidationErrors('campaign_id');
    }

    public function test_campaign_id_de_la_empresa_activa_pasa_la_validacion(): void
    {
        $deB = $this->campana($this->b->id);

        $r = $this->actingAs($this->usuarioB)->postJson('/marketing/content/generate-image', ['campaign_id' => $deB]);

        $this->assertArrayNotHasKey('campaign_id', (array) $r->json('errors'));
    }

    public function test_channel_ids_de_otra_empresa_es_422(): void
    {
        $deA = $this->canal(1);
        $deB = $this->canal($this->b->id);

        $this->actingAs($this->usuarioB)->postJson('/api/marketing/publishing/campaigns/999999/publish', ['channel_ids' => [$deB, $deA]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('channel_ids.1')
            ->assertJsonMissingValidationErrors('channel_ids.0');
    }

    public function test_channel_ids_propios_pasan_la_validacion(): void
    {
        $deB = $this->canal($this->b->id);

        // Pasa la validación; la campaña 999999 no existe → 404 del controlador.
        $this->actingAs($this->usuarioB)->postJson('/api/marketing/publishing/campaigns/999999/publish', ['channel_ids' => [$deB]])
            ->assertStatus(404);
    }
}
