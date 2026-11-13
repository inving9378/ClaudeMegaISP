<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadSource;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Jobs\ProcessIncomingMessageJob;
use App\Modules\Addons\Marketing\Jobs\ProcessMetaLeadJob;
use App\Modules\Addons\Marketing\Jobs\ScoreLeadJob;
use App\Modules\Addons\Marketing\Services\AiAgentService;
use App\Modules\Addons\Marketing\Services\MetaGraphApiService;
use App\Modules\Addons\WhatsAppAgent\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Tests\Feature\Marketing\Fase0\Qa\Concerns\EscenarioEmpresas;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — caminos SIN `marketing.empresa` (§2.3, riesgo R1, criterio 6).
 *
 * Webhook Evolution, webhook Meta Ads (GET verify / POST), formulario público (show/submit,
 * también con sesión de staff de la empresa B abierta), tracking del piloto y los jobs
 * ProcessIncomingMessageJob / ProcessMetaLeadJob sin contexto: mismo resultado que en la base.
 * IA, Meta y Evolution simulados; nunca llamadas reales.
 */
class CaminosSinSesionTest extends MarketingTestCase
{
    use EscenarioEmpresas;

    private string $tokenEvolution;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->montarEscenario();

        LeadSource::query()->firstOrCreate(['company_id' => 1, 'code' => 'whatsapp_direct'], ['name' => 'WhatsApp Directo']);
        LeadSource::query()->firstOrCreate(['company_id' => 1, 'code' => 'meta_ads'], ['name' => 'Meta Ads']);

        $this->tokenEvolution = Str::random(64);
        Setting::query()->updateOrCreate(['company_id' => 1, 'key' => 'evolution_webhook_token'], ['value' => $this->tokenEvolution, 'group' => 'channels', 'encrypted' => false]);
        Setting::query()->updateOrCreate(['company_id' => 1, 'key' => 'meta_verify_token'], ['value' => 'qa-verify', 'group' => 'channels', 'encrypted' => false]);
        Setting::query()->updateOrCreate(['company_id' => 1, 'key' => 'meta_app_secret'], ['value' => 'qa-secret', 'group' => 'channels', 'encrypted' => false]);
        Setting::query()->where('company_id', 1)->where('key', 'meta_lead_ads_form_ids')->delete();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    /** Petición nueva SIN sesión (como el bot/Meta/el navegador del cliente). */
    private function anonimo(): static
    {
        $this->contexto()->limpiar();
        $this->flushSession();
        $this->app["auth"]->forgetGuards();

        return $this;
    }

    private function payloadEvolution(string $telefono): array
    {
        return [
            'event'    => 'messages.upsert',
            'instance' => 'qa-instance',
            'data'     => [
                'key'              => ['id' => 'QA' . Str::random(10), 'fromMe' => false, 'remoteJid' => '521' . $telefono . '@s.whatsapp.net'],
                'pushName'         => 'Cliente QA',
                'messageTimestamp' => time(),
                'message'          => ['conversation' => 'Hola, quiero información'],
            ],
        ];
    }

    // ── Webhook Evolution ────────────────────────────────────────────────────

    public function test_webhook_evolution_sin_sesion_responde_igual_y_encola(): void
    {
        Queue::fake();
        $this->anonimo()
            ->postJson('/webhooks/marketing/evolution?token=' . $this->tokenEvolution, $this->payloadEvolution('5512345678'))
            ->assertOk()->assertJson(['ok' => true]);

        Queue::assertPushed(ProcessIncomingMessageJob::class);
    }

    public function test_webhook_evolution_con_sesion_de_staff_de_b_no_cambia_nada(): void
    {
        Queue::fake();
        $this->como($this->adminB, $this->empresaB->id)
            ->postJson('/webhooks/marketing/evolution?token=' . $this->tokenEvolution, $this->payloadEvolution('5512345679'))
            ->assertOk();

        Queue::assertPushed(ProcessIncomingMessageJob::class);
        $this->assertFalse($this->contexto()->hay(), 'el webhook no debe poner contexto de empresa');
    }

    public function test_webhook_evolution_token_invalido_sigue_en_401(): void
    {
        Queue::fake();
        $this->anonimo()->postJson('/webhooks/marketing/evolution?token=malo', $this->payloadEvolution('5512345670'))->assertStatus(401);
        Queue::assertNotPushed(ProcessIncomingMessageJob::class);
    }

    // ── Job ProcessIncomingMessageJob ────────────────────────────────────────

    public function test_job_mensaje_entrante_sin_contexto_crea_lead_y_conversacion_en_empresa_1(): void
    {
        Queue::fake();
        $this->mock(AiAgentService::class, fn ($m) => $m->shouldReceive('respondTo')->andReturnNull());
        $this->mock(WhatsAppProviderInterface::class, fn ($m) => $m->shouldIgnoreMissing());

        $telefono = '55' . random_int(10000000, 99999999);
        $this->contexto()->limpiar();
        $this->assertFalse($this->contexto()->hay());

        $job = new ProcessIncomingMessageJob($this->payloadEvolution($telefono));
        $this->app->call([$job, 'handle']);

        $lead = DB::table('marketing_leads')->where('whatsapp', $telefono)->first();
        $this->assertNotNull($lead, 'el job debe crear el lead como hoy');
        $this->assertSame(1, (int) $lead->company_id);

        $conv = DB::table('marketing_conversations')->where('lead_id', $lead->id)->first();
        $this->assertNotNull($conv);
        $this->assertSame(1, (int) $conv->company_id);
        $this->assertSame(1, DB::table('marketing_messages')->where('conversation_id', $conv->id)->count());
    }

    public function test_job_mensaje_entrante_reutiliza_el_lead_existente_de_empresa_1(): void
    {
        Queue::fake();
        $this->mock(AiAgentService::class, fn ($m) => $m->shouldReceive('respondTo')->andReturnNull());
        $this->mock(WhatsAppProviderInterface::class, fn ($m) => $m->shouldIgnoreMissing());

        $telefono = '55' . random_int(10000000, 99999999);
        DB::table('marketing_leads')->where('id', $this->a['lead']->id)->update(['whatsapp' => $telefono, 'phone' => $telefono]);
        $antes = DB::table('marketing_leads')->count();

        $this->contexto()->limpiar();
        $this->app->call([new ProcessIncomingMessageJob($this->payloadEvolution($telefono)), 'handle']);

        $this->assertSame($antes, DB::table('marketing_leads')->count(), 'no debe duplicar el lead');
    }

    // ── Webhook Meta Ads + job ───────────────────────────────────────────────

    public function test_webhook_meta_verify_get_sin_sesion(): void
    {
        $this->anonimo()
            ->get('/webhooks/marketing/meta-ads?hub_mode=subscribe&hub_verify_token=qa-verify&hub_challenge=12345')
            ->assertOk()->assertSee('12345');

        $this->anonimo()
            ->get('/webhooks/marketing/meta-ads?hub_mode=subscribe&hub_verify_token=otro&hub_challenge=12345')
            ->assertStatus(403);
    }

    public function test_webhook_meta_verify_con_sesion_de_staff_de_b(): void
    {
        $this->como($this->adminB, $this->empresaB->id)
            ->get('/webhooks/marketing/meta-ads?hub_mode=subscribe&hub_verify_token=qa-verify&hub_challenge=777')
            ->assertOk()->assertSee('777');
    }

    public function test_webhook_meta_post_con_firma_valida_encola_y_con_firma_mala_401(): void
    {
        Queue::fake();
        $cuerpo = json_encode(['object' => 'page', 'entry' => [['changes' => [[
            'field' => 'leadgen', 'value' => ['leadgen_id' => 'LG1', 'form_id' => 'F1', 'page_id' => 'P1', 'created_time' => time()],
        ]]]]]);
        $firma = 'sha256=' . hash_hmac('sha256', $cuerpo, 'qa-secret');

        $this->anonimo()->call('POST', '/webhooks/marketing/meta-ads', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $firma,
        ], $cuerpo)->assertOk()->assertJson(['status' => 'ok']);
        Queue::assertPushed(ProcessMetaLeadJob::class, 1);

        $this->anonimo()->call('POST', '/webhooks/marketing/meta-ads', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=falsa',
        ], $cuerpo)->assertStatus(401);
        Queue::assertPushed(ProcessMetaLeadJob::class, 1);
    }

    public function test_job_meta_lead_sin_contexto_crea_lead_en_la_empresa_del_job(): void
    {
        Queue::fake();
        $nombre = 'Meta QA ' . Str::random(6);
        $graph = Mockery::mock(MetaGraphApiService::class);
        $graph->shouldReceive('fetchLeadDetails')->andReturn([
            'field_data' => [['name' => 'full_name', 'values' => [$nombre]], ['name' => 'phone_number', 'values' => ['5511112222']]],
        ]);
        $this->app->instance(MetaGraphApiService::class, $graph);

        $this->contexto()->limpiar();
        $this->app->call([new ProcessMetaLeadJob('LG-' . Str::random(6), 'F1', 'P1', null, null, time()), 'handle']);

        $lead = DB::table('marketing_leads')->where('full_name', $nombre)->first();
        $this->assertNotNull($lead);
        $this->assertSame(1, (int) $lead->company_id, 'el job de Meta sigue creando en la empresa 1 por defecto');
        Queue::assertPushed(ScoreLeadJob::class);
    }

    // ── Formulario público ───────────────────────────────────────────────────

    public function test_formulario_publico_show_y_submit_sin_sesion(): void
    {
        Queue::fake();
        $form = $this->a['formulario'];

        $this->anonimo()->get('/public/marketing/lead-form/' . $form->slug)->assertOk();

        $nombre = 'Público QA ' . Str::random(6);
        $r = $this->anonimo()->postJson('/public/marketing/lead-form/' . $form->slug . '/submit', ['full_name' => $nombre]);
        $r->assertStatus(201);

        $lead = DB::table('marketing_leads')->where('id', $r->json('lead_id'))->first();
        $this->assertSame($nombre, $lead->full_name);
        $this->assertSame((int) $form->company_id, (int) $lead->company_id);
    }

    public function test_formulario_publico_de_a_con_sesion_de_staff_de_b_abierta_sigue_funcionando(): void
    {
        Queue::fake();
        $form = $this->a['formulario'];

        // El staff de B navega Marketing (fija su empresa en sesión)…
        $this->como($this->adminB, $this->empresaB->id)->getJson('/api/marketing/contexto')->assertOk();

        // …y en OTRA petición, con la misma sesión abierta, abre el formulario público de A.
        $this->contexto()->limpiar();
        $this->get('/public/marketing/lead-form/' . $form->slug)->assertOk();

        $this->contexto()->limpiar();
        $nombre = 'Con sesion B ' . Str::random(6);
        $r = $this->postJson('/public/marketing/lead-form/' . $form->slug . '/submit', ['full_name' => $nombre]);
        $r->assertStatus(201);

        $this->assertSame(1, (int) DB::table('marketing_leads')->where('id', $r->json('lead_id'))->value('company_id'),
            'el lead se crea con el company_id del formulario (A), no con el de la sesión (B)');
    }

    public function test_formulario_publico_de_b_crea_lead_en_b(): void
    {
        Queue::fake();
        $form = $this->b['formulario'];

        $r = $this->anonimo()->postJson('/public/marketing/lead-form/' . $form->slug . '/submit', ['full_name' => 'Lead para B']);
        $r->assertStatus(201);

        $this->assertSame($this->empresaB->id, (int) DB::table('marketing_leads')->where('id', $r->json('lead_id'))->value('company_id'));
    }

    public function test_formulario_publico_ignora_company_id_y_lead_form_id_del_request(): void
    {
        Queue::fake();
        $form = $this->a['formulario'];

        $r = $this->anonimo()->postJson('/public/marketing/lead-form/' . $form->slug . '/submit', [
            'full_name' => 'Intruso', 'company_id' => $this->empresaB->id,
        ]);
        $r->assertStatus(201);

        $this->assertSame(1, (int) DB::table('marketing_leads')->where('id', $r->json('lead_id'))->value('company_id'));
    }

    public function test_formulario_publico_html_y_script_no_rompen_ni_se_ejecutan(): void
    {
        Queue::fake();
        $form = $this->a['formulario'];
        $xss = '<script>alert(1)</script>Ñandú 🚀';

        $r = $this->anonimo()->postJson('/public/marketing/lead-form/' . $form->slug . '/submit', ['full_name' => $xss]);
        $r->assertStatus(201);
        $this->assertSame($xss, DB::table('marketing_leads')->where('id', $r->json('lead_id'))->value('full_name'));
    }

    public function test_embed_js_sin_sesion(): void
    {
        $this->anonimo()->get('/public/marketing/embed.js')->assertOk()->assertHeader('Content-Type', 'application/javascript');
    }

    // ── Tracking del piloto ──────────────────────────────────────────────────

    public function test_tracking_piloto_open_y_click_sin_sesion_y_con_sesion_de_otra_empresa(): void
    {
        $envioB = $this->b['envio'];
        $envioA = $this->a['envio'];

        $this->anonimo()->get('/marketing/pilot/track/open/' . $envioB->token . '.gif')
            ->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->assertNotNull(DB::table('marketing_pilot_campaign_sends')->where('id', $envioB->id)->value('opened_at'));

        // Con la sesión de staff de B abierta, el clic de un correo de A se sigue registrando.
        $this->como($this->adminB, $this->empresaB->id);
        $this->get('/marketing/pilot/track/click/' . $envioA->token)->assertStatus(302);
        $this->assertNotNull(DB::table('marketing_pilot_campaign_sends')->where('id', $envioA->id)->value('clicked_at'));

        $this->anonimo()->get('/marketing/pilot/track/click/noexiste123')->assertStatus(404);
    }

    public function test_ninguna_ruta_publica_fija_contexto_de_empresa(): void
    {
        Queue::fake();
        $this->como($this->adminA, 1);
        $this->get('/public/marketing/embed.js')->assertOk();
        $this->assertFalse($this->contexto()->hay(), 'una ruta pública no debe pasar por marketing.empresa');
    }
}
