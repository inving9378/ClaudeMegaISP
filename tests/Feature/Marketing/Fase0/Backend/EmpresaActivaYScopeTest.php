<?php

namespace Tests\Feature\Marketing\Fase0\Backend;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\Lead;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Models\Lead as LeadModulo;
use App\Modules\Addons\Marketing\Models\Scopes\EmpresaScope;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\Feature\Marketing\Fase0\Backend\Concerns\EscenarioBackend;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 0 · backend — contexto EmpresaActiva + EmpresaScope + trait PerteneceAEmpresa.
 */
class EmpresaActivaYScopeTest extends MarketingTestCase
{
    use EscenarioBackend;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Bus::fake();
        $this->contexto()->limpiar();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    public function test_el_contexto_es_scoped_y_arranca_vacio(): void
    {
        $ctx = app(EmpresaActiva::class);

        $this->assertSame($ctx, app(EmpresaActiva::class), 'misma instancia dentro de la petición');
        $this->assertFalse($ctx->hay());
        $this->assertNull($ctx->id());
        $this->assertNull($ctx->empresa());
        $this->assertNull($ctx->rol());
    }

    public function test_establecer_y_limpiar(): void
    {
        $ctx = $this->contexto();
        $ctx->establecer($this->empresaPrincipal(), 'ventas');

        $this->assertTrue($ctx->hay());
        $this->assertSame(1, $ctx->id());
        $this->assertSame('ventas', $ctx->rol());
        $this->assertSame('Meganet', $ctx->empresa()->nombre);

        $ctx->limpiar();
        $this->assertFalse($ctx->hay());
        $this->assertNull($ctx->rol());
    }

    public function test_establecer_rechaza_un_rol_fuera_de_la_lista(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->contexto()->establecer($this->empresaPrincipal(), 'superjefe');
    }

    public function test_sin_contexto_el_scope_no_filtra(): void
    {
        $b = $this->crearEmpresa();
        $leadA = $this->crearLead(1, 'Lead A');
        $leadB = $this->crearLead($b->id, 'Lead B');

        $ids = Lead::whereIn('id', [$leadA->id, $leadB->id])->pluck('id')->all();
        sort($ids);

        $this->assertSame([$leadA->id, $leadB->id], $ids);
        $this->assertNotNull(Lead::find($leadB->id));
    }

    public function test_con_contexto_solo_ve_su_empresa_y_un_id_ajeno_es_404(): void
    {
        $b = $this->crearEmpresa();
        $leadA = $this->crearLead(1, 'Lead A');
        $leadB = $this->crearLead($b->id, 'Lead B');

        $this->contexto()->establecer($b, 'admin');

        $this->assertSame([$leadB->id], Lead::whereIn('id', [$leadA->id, $leadB->id])->pluck('id')->all());
        $this->assertSame(1, Lead::whereIn('id', [$leadA->id, $leadB->id])->count());
        $this->assertNull(Lead::find($leadA->id));

        $this->expectException(ModelNotFoundException::class);
        Lead::findOrFail($leadA->id);
    }

    public function test_el_scope_califica_la_columna_en_joins_y_whereHas(): void
    {
        $b = $this->crearEmpresa();
        $leadA = $this->crearLead(1, 'Lead A');
        $leadB = $this->crearLead($b->id, 'Lead B');
        $canal = DB::table('marketing_channels')->insertGetId(['code' => 'bk_' . uniqid(), 'name' => 'Canal prueba', 'company_id' => 1]);

        // Conversación de B ligada a un lead de A (caso raro, pero comprueba que el whereHas filtra al lead).
        $convB = Conversation::create(['company_id' => $b->id, 'lead_id' => $leadA->id, 'channel_id' => $canal]);
        Conversation::create(['company_id' => $b->id, 'lead_id' => $leadB->id, 'channel_id' => $canal]);

        $this->contexto()->establecer($b, 'admin');

        // join explícito: ambas tablas tienen company_id; sin calificar sería ambiguo (SQL error).
        $conJoin = Lead::query()
            ->join('marketing_conversations as c', 'c.lead_id', '=', 'marketing_leads.id')
            ->whereIn('marketing_leads.id', [$leadA->id, $leadB->id])
            ->pluck('marketing_leads.id')->unique()->values()->all();
        $this->assertSame([$leadB->id], $conJoin);

        $conLeadVisible = Conversation::whereHas('lead')->whereIn('lead_id', [$leadA->id, $leadB->id])->pluck('lead_id')->all();
        $this->assertSame([$leadB->id], $conLeadVisible, 'whereHas aplica el scope al lead relacionado');
        $this->assertNotNull(Conversation::find($convB->id), 'la conversación es de B y sí se ve');
    }

    public function test_creating_con_contexto_fuerza_la_empresa_activa(): void
    {
        $b = $this->crearEmpresa();
        $this->contexto()->establecer($b, 'marketing');

        $lead = Lead::create(['company_id' => 1, 'full_name' => 'Intento de colar a Meganet', 'captured_at' => now()]);

        $this->assertSame($b->id, (int) DB::table('marketing_leads')->where('id', $lead->id)->value('company_id'));
    }

    public function test_creating_sin_company_id_con_contexto_toma_la_activa(): void
    {
        $b = $this->crearEmpresa();
        $this->contexto()->establecer($b, 'admin');

        $campana = Campaign::create(['name' => 'Campaña B', 'slug' => 'campana-b-' . uniqid(), 'type' => 'organic']);

        $this->assertSame($b->id, (int) DB::table('marketing_campaigns')->where('id', $campana->id)->value('company_id'));
    }

    public function test_creating_sin_contexto_respeta_el_company_id_o_el_default(): void
    {
        $b = $this->crearEmpresa();

        $explicito = Lead::create(['company_id' => $b->id, 'full_name' => 'B explícito', 'captured_at' => now()]);
        $porDefecto = Lead::create(['full_name' => 'Sin company_id', 'captured_at' => now()]);

        $this->assertSame($b->id, (int) DB::table('marketing_leads')->where('id', $explicito->id)->value('company_id'));
        $this->assertSame(1, (int) DB::table('marketing_leads')->where('id', $porDefecto->id)->value('company_id'));
    }

    public function test_updating_con_contexto_no_mueve_de_empresa(): void
    {
        $b = $this->crearEmpresa();
        $leadB = $this->crearLead($b->id, 'Lead B');

        $this->contexto()->establecer($b, 'admin');
        $lead = Lead::findOrFail($leadB->id);
        $lead->update(['company_id' => 1, 'full_name' => 'Lead B editado']);

        $fila = DB::table('marketing_leads')->where('id', $leadB->id)->first();
        $this->assertSame($b->id, (int) $fila->company_id);
        $this->assertSame('Lead B editado', $fila->full_name, 'el resto del cambio sí se guarda');
    }

    public function test_updating_sin_contexto_se_comporta_como_antes(): void
    {
        $b = $this->crearEmpresa();
        $lead = $this->crearLead(1, 'Lead A');

        $lead->update(['company_id' => $b->id]);

        $this->assertSame($b->id, (int) DB::table('marketing_leads')->where('id', $lead->id)->value('company_id'));
    }

    public function test_el_modelo_lead_del_modulo_tambien_esta_aislado(): void
    {
        $b = $this->crearEmpresa();
        $leadA = $this->crearLead(1, 'Lead A');

        $this->contexto()->establecer($b, 'admin');
        $this->assertNull(LeadModulo::find($leadA->id));

        $this->contexto()->limpiar();
        $this->assertNotNull(LeadModulo::find($leadA->id));
    }

    public function test_los_modelos_compartidos_no_llevan_scope(): void
    {
        $this->assertFalse((new Setting())->hasGlobalScope(EmpresaScope::class));
        $this->assertTrue((new Lead())->hasGlobalScope(EmpresaScope::class));

        $b = $this->crearEmpresa();
        $this->contexto()->establecer($b, 'admin');

        // Setting (Brand Kit + llaves) se sigue leyendo con company_id = 1 aunque la activa sea B.
        $this->assertStringNotContainsString('company_id', Setting::query()->toSql());
    }

    public function test_los_trece_modelos_estrictos_llevan_el_scope(): void
    {
        $estrictos = [
            \App\Models\Marketing\Lead::class, \App\Models\Marketing\Campaign::class,
            \App\Models\Marketing\Conversation::class, \App\Models\Marketing\GeneratedContent::class,
            \App\Models\Marketing\LeadForm::class, \App\Models\Marketing\MultivariantCampaign::class,
            \App\Models\Marketing\PilotCampaign::class, \App\Models\Marketing\Pipeline::class,
            \App\Models\Marketing\Publication::class, \App\Models\Marketing\PublicationChannel::class,
            \App\Models\Marketing\ContentTemplate::class,
            \App\Modules\Addons\Marketing\Models\Lead::class, \App\Modules\Addons\Marketing\Models\Campaign::class,
        ];

        $b = $this->crearEmpresa();
        $this->contexto()->establecer($b, 'admin');

        foreach ($estrictos as $clase) {
            $modelo = new $clase();
            $this->assertTrue($modelo->hasGlobalScope(EmpresaScope::class), "$clase sin scope");
            $sql = $clase::query()->toSql();
            $this->assertStringContainsString($modelo->getTable() . '`.`company_id', $sql, "$clase no califica company_id");
        }
    }
}
