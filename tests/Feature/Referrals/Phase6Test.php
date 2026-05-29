<?php

namespace Tests\Feature\Referrals;

use App\Jobs\Referrals\ApplyReferralCommissions;
use App\Models\Balance;
use App\Models\Referrals\ClientReferralProfile;
use App\Models\Referrals\Referral;
use App\Models\Referrals\ReferralCommission;
use App\Models\Referrals\ReferralNotificationLog;
use App\Models\Referrals\ReferralNotificationTemplate;
use App\Models\Referrals\ReferralProspect;
use App\Modules\Core\Clientes\Models\Client;
use App\Modules\Core\Clientes\Models\ClientMainInformation;
use App\Observers\ClientObserver;
use App\Services\Referrals\ReferralWhatsAppNotifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Support\Facades\Http;
use Tests\CreatesApplication;

class Phase6Test extends LaravelTestCase
{
    use CreatesApplication, DatabaseTransactions;

    // ── 13. Matching por teléfono normalizado ────────────────────────────────

    public function test_matching_prospect_to_client_funciona_con_telefono_normalizado(): void
    {
        $embajador = Client::factory()->create();
        $profile   = ClientReferralProfile::factory()->forClient($embajador)->multilevel()->eligible()->create();

        // Prospecto con teléfono formateado con prefijo internacional
        $prospect = ReferralProspect::factory()->forEmbajador($embajador)->create([
            'phone'  => '+52 555 987-6543',
            'status' => 'new',
        ]);

        // Nuevo cliente con mismo teléfono normalizado (solo dígitos)
        $client = Client::factory()->create();
        ClientMainInformation::factory()->forClient($client)->create([
            'phone' => '5559876543',
        ]);
        $client->referred_by_code = $profile->referral_code;

        // Invocar el observer directamente (el factory usa la clase base, no el proxy)
        Http::fake(); // prevent any HTTP call
        (new ClientObserver)->created($client);

        $prospectFresh = $prospect->fresh();
        $this->assertEquals('converted', $prospectFresh->status);
        $this->assertEquals($client->id, $prospectFresh->converted_client_id);
        $this->assertNotNull($prospectFresh->converted_at);

        $referral = Referral::where('referred_client_id', $client->id)->first();
        $this->assertNotNull($referral);
        $this->assertEquals($prospect->id, $referral->prospect_id);
    }

    // ── 14. Matching no falla si no hay prospecto compatible ─────────────────

    public function test_matching_no_falla_si_no_hay_prospecto_compatible(): void
    {
        $embajador = Client::factory()->create();
        $profile   = ClientReferralProfile::factory()->forClient($embajador)->multilevel()->eligible()->create();

        // Ningún prospecto con ese teléfono
        $client = Client::factory()->create();
        ClientMainInformation::factory()->forClient($client)->create([
            'phone' => '5559999999',
        ]);
        $client->referred_by_code = $profile->referral_code;

        Http::fake();
        // No debe lanzar excepción
        (new ClientObserver)->created($client);

        $referral = Referral::where('referred_client_id', $client->id)->first();
        $this->assertNotNull($referral);
        $this->assertNull($referral->prospect_id);
    }

    // ── 15. Notificación no lanza si WhatsApp API está caída ─────────────────

    public function test_notification_no_throws_if_whatsapp_api_down(): void
    {
        // Template activo
        ReferralNotificationTemplate::updateOrCreate(
            ['event_type' => 'commissions_applied'],
            [
                'label'         => 'Test',
                'body_template' => '¡Hola {{embajador_name}}! Se acreditaron ${{total_amount}}.',
                'active'        => true,
            ]
        );

        $embajador = Client::factory()->create();
        ClientMainInformation::factory()->forClient($embajador)->create(['phone' => '5551234567']);
        $embajador->load('client_main_information');

        // API devuelve 500 → RuntimeException en EvolutionApiService
        Http::fake(['*' => Http::response('Server Error', 500)]);

        // No debe lanzar excepción
        app(ReferralWhatsAppNotifier::class)->notifyCommissionsApplied($embajador, 29.90, 2);

        // Log registrado con success=false
        $this->assertDatabaseHas('referral_notification_logs', [
            'client_id'  => $embajador->id,
            'event_type' => 'commissions_applied',
            'success'    => false,
        ]);
    }

    // ── 16. Template renderiza placeholders correctamente ────────────────────

    public function test_template_renders_placeholders_correctly(): void
    {
        $template = new ReferralNotificationTemplate([
            'body_template' => '¡Hola {{embajador_name}}! Ganaste ${{amount}} por {{referido_name}}.',
        ]);

        $rendered = $template->render([
            'embajador_name' => 'Juan',
            'amount'         => '14.95',
            'referido_name'  => 'Pedro',
        ]);

        $this->assertEquals('¡Hola Juan! Ganaste $14.95 por Pedro.', $rendered);
    }

    // ── 17. ApplyReferralCommissions dispara evento y registra notificación ──

    public function test_apply_commissions_dispara_evento_y_notificacion(): void
    {
        // Template para commissions_applied
        ReferralNotificationTemplate::updateOrCreate(
            ['event_type' => 'commissions_applied'],
            [
                'label'         => 'Comisiones aplicadas',
                'body_template' => '¡Hola {{embajador_name}}! Se acreditaron ${{total_amount}} ({{count}} pagos).',
                'active'        => true,
            ]
        );

        $embajador = Client::factory()->create();
        ClientMainInformation::factory()->forClient($embajador)->create(['phone' => '5557654321']);
        Balance::create([
            'balanceable_id'   => $embajador->id,
            'balanceable_type' => 'App\Models\Client',
            'amount'           => 0,
        ]);

        // Comisión vencida (apply_after_at en el pasado)
        ReferralCommission::factory()->forBeneficiary($embajador)->create([
            'commission_amount' => 14.95,
            'apply_after_at'    => now()->subDay(),
        ]);

        // API Evolution responde OK
        Http::fake(['*' => Http::response(['key' => ['id' => 'fake123']], 200)]);

        (new ApplyReferralCommissions)->handle();

        $this->assertDatabaseHas('referral_notification_logs', [
            'client_id'  => $embajador->id,
            'event_type' => 'commissions_applied',
            'success'    => true,
        ]);
    }
}
