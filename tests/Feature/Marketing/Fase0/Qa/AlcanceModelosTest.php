<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use App\Models\Marketing\Attribution;
use App\Models\Marketing\Campaign;
use App\Models\Marketing\Channel;
use App\Models\Marketing\ContentTemplate;
use App\Models\Marketing\Conversation;
use App\Models\Marketing\GeneratedContent;
use App\Models\Marketing\Lead;
use App\Models\Marketing\LeadActivity;
use App\Models\Marketing\LeadForm;
use App\Models\Marketing\LeadPipeline;
use App\Models\Marketing\LeadSource;
use App\Models\Marketing\Message;
use App\Models\Marketing\MultivariantCampaign;
use App\Models\Marketing\PilotCampaign;
use App\Models\Marketing\PilotCampaignSend;
use App\Models\Marketing\Pipeline;
use App\Models\Marketing\PipelineStage;
use App\Models\Marketing\Publication;
use App\Models\Marketing\PublicationChannel;
use App\Models\Marketing\PublicationLog;
use App\Models\Marketing\Setting;
use App\Modules\Addons\Marketing\Models\Campaign as CampaignModulo;
use App\Modules\Addons\Marketing\Models\Concerns\PerteneceAEmpresa;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Models\EmpresaUsuario;
use App\Modules\Addons\Marketing\Models\Lead as LeadModulo;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\Fase0\Qa\Concerns\EscenarioEmpresas;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — alcance por empresa en los modelos (§2.3 / §2.4).
 *
 * Por cada modelo estricto: con contexto A no ve B (count/find/findOrFail/whereKey/whereHas/join/
 * relación desde un modelo compartido); `creating` fuerza A aunque llegue company_id de B;
 * `updating` no mueve de empresa; sin contexto ve ambas (igual que hoy).
 * Compartidos e hijos: sin filtro.
 */
class AlcanceModelosTest extends MarketingTestCase
{
    use EscenarioEmpresas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->montarEscenario();
    }

    protected function tearDown(): void
    {
        app(EmpresaActiva::class)->limpiar();
        parent::tearDown();
    }

    private function entrarA(): void
    {
        $this->contexto()->establecer($this->empresaA, 'admin');
    }

    private function entrarB(): void
    {
        $this->contexto()->establecer($this->empresaB, 'admin');
    }

    /** clave en $this->a/$this->b => clase del modelo estricto */
    public static function estrictos(): array
    {
        return [
            'Lead'                 => ['lead', Lead::class],
            'Lead (módulo)'        => ['lead', LeadModulo::class],
            'Campaign'             => ['campana', Campaign::class],
            'Campaign (módulo)'    => ['campana', CampaignModulo::class],
            'Conversation'         => ['conversacion', Conversation::class],
            'GeneratedContent'     => ['contenido', GeneratedContent::class],
            'LeadForm'             => ['formulario', LeadForm::class],
            'MultivariantCampaign' => ['multivariante', MultivariantCampaign::class],
            'PilotCampaign'        => ['piloto', PilotCampaign::class],
            'Pipeline'             => ['pipeline', Pipeline::class],
            'Publication'          => ['publicacion', Publication::class],
            'PublicationChannel'   => ['canalPublicacion', PublicationChannel::class],
            'ContentTemplate'      => ['plantilla', ContentTemplate::class],
        ];
    }

    // ── EmpresaActiva ────────────────────────────────────────────────────────

    public function test_empresa_activa_api_basica(): void
    {
        $ctx = $this->contexto();
        $this->assertFalse($ctx->hay());
        $this->assertNull($ctx->id());
        $this->assertNull($ctx->rol());
        $this->assertNull($ctx->empresa());

        $ctx->establecer($this->empresaB, 'ventas');
        $this->assertTrue($ctx->hay());
        $this->assertSame($this->empresaB->id, $ctx->id());
        $this->assertSame('ventas', $ctx->rol());
        $this->assertSame($this->empresaB->id, $ctx->empresa()->id);

        $ctx->limpiar();
        $this->assertFalse($ctx->hay());
        $this->assertNull($ctx->id());
    }

    public function test_empresa_activa_es_scoped_y_se_limpia_entre_jobs(): void
    {
        $this->contexto()->establecer($this->empresaA, 'admin');
        $this->assertTrue(app(EmpresaActiva::class)->hay());

        // Lo que hace el worker de cola antes de cada job (QueueServiceProvider) y Octane entre peticiones.
        $this->app->forgetScopedInstances();

        $this->assertFalse(app(EmpresaActiva::class)->hay(), 'EmpresaActiva debe registrarse con scoped(), no singleton().');
    }

    public function test_empresa_y_empresa_usuario_no_llevan_el_scope(): void
    {
        $this->assertNotContains(PerteneceAEmpresa::class, class_uses_recursive(Empresa::class));
        $this->assertNotContains(PerteneceAEmpresa::class, class_uses_recursive(EmpresaUsuario::class));

        $this->entrarB();
        $this->assertNotNull(Empresa::query()->find(1), 'Empresa no debe filtrarse por contexto');
        $this->assertGreaterThan(0, EmpresaUsuario::query()->where('company_id', 1)->count());
    }

    // ── estrictos ────────────────────────────────────────────────────────────

    /** @dataProvider estrictos */
    public function test_estricto_usa_el_trait(string $clave, string $clase): void
    {
        $this->assertContains(PerteneceAEmpresa::class, class_uses_recursive($clase), "{$clase} debe usar PerteneceAEmpresa");
    }

    /** @dataProvider estrictos */
    public function test_estricto_con_contexto_a_no_ve_b(string $clave, string $clase): void
    {
        $idA = $this->a[$clave]->id;
        $idB = $this->b[$clave]->id;
        $this->entrarA();

        $this->assertNotNull($clase::query()->find($idA), 'con A debe ver su fila');
        $this->assertNull($clase::query()->find($idB), 'con A NO debe ver la fila de B (find)');
        $this->assertSame(1, $clase::query()->whereKey([$idA, $idB])->count(), 'count con ambos ids debe ser 1');
        $this->assertFalse($clase::query()->where('id', $idB)->exists());

        $tabla = (new $clase())->getTable();
        $esperado = DB::table($tabla)->where('company_id', $this->empresaA->id);
        if (\Illuminate\Support\Facades\Schema::hasColumn($tabla, 'deleted_at')) {
            $esperado->whereNull('deleted_at');
        }
        $this->assertSame(
            $esperado->count(),
            $clase::query()->count(),
            'count() debe igualar las filas de A'
        );

        try {
            $clase::query()->findOrFail($idB);
            $this->fail('findOrFail sobre un id de B debió lanzar ModelNotFoundException (→ 404)');
        } catch (ModelNotFoundException $e) {
            $this->assertTrue(true);
        }
    }

    /** @dataProvider estrictos */
    public function test_estricto_con_contexto_b_no_ve_a(string $clave, string $clase): void
    {
        $this->entrarB();
        $this->assertNull($clase::query()->find($this->a[$clave]->id));
        $this->assertNotNull($clase::query()->find($this->b[$clave]->id));
        // Aunque el código diga where company_id = 1 (hoy hay controladores así), el scope manda.
        $this->assertSame(0, $clase::query()->where('company_id', 1)->count());
    }

    /** @dataProvider estrictos */
    public function test_estricto_sin_contexto_ve_ambas_igual_que_hoy(string $clave, string $clase): void
    {
        $this->contexto()->limpiar();
        $this->assertNotNull($clase::query()->find($this->a[$clave]->id));
        $this->assertNotNull($clase::query()->find($this->b[$clave]->id));
        $this->assertSame(2, $clase::query()->whereKey([$this->a[$clave]->id, $this->b[$clave]->id])->count());
    }

    /** @dataProvider estrictos */
    public function test_estricto_creating_fuerza_la_empresa_activa(string $clave, string $clase): void
    {
        $original = $this->a[$clave];
        $attrs = collect($original->getAttributes())->except(['id', 'created_at', 'updated_at', 'deleted_at'])->all();
        $attrs['company_id'] = $this->empresaB->id; // intento de colarse en B
        foreach (['slug', 'token', 'external_thread_id'] as $unico) {
            if (array_key_exists($unico, $attrs)) {
                $attrs[$unico] = Str::lower('qa-dup-' . Str::random(10));
            }
        }

        $this->entrarA();
        $nuevo = new $clase();
        $nuevo->forceFill($attrs);
        $nuevo->save();

        $this->assertSame($this->empresaA->id, (int) DB::table($nuevo->getTable())->where('id', $nuevo->id)->value('company_id'));
    }

    /** @dataProvider estrictos */
    public function test_estricto_updating_no_mueve_de_empresa(string $clave, string $clase): void
    {
        $id = $this->a[$clave]->id;
        $tabla = $this->a[$clave]->getTable();
        $this->entrarA();

        $m = $clase::query()->findOrFail($id);
        $m->company_id = $this->empresaB->id;
        $m->updated_at = now()->addMinute();
        $m->save();

        $this->assertSame($this->empresaA->id, (int) DB::table($tabla)->where('id', $id)->value('company_id'));
    }

    public function test_update_masivo_por_query_no_alcanza_filas_de_otra_empresa(): void
    {
        $this->entrarA();
        Lead::query()->update(['notes' => 'masivo-A']);

        $this->assertNotSame('masivo-A', DB::table('marketing_leads')->where('id', $this->b['lead']->id)->value('notes'));
        $this->assertSame('masivo-A', DB::table('marketing_leads')->where('id', $this->a['lead']->id)->value('notes'));
    }

    public function test_delete_masivo_por_query_no_alcanza_filas_de_otra_empresa(): void
    {
        $this->entrarA();
        Lead::query()->whereKey([$this->a['lead']->id, $this->b['lead']->id])->delete();

        $this->assertNull(DB::table('marketing_leads')->where('id', $this->b['lead']->id)->value('deleted_at'));
    }

    public function test_where_has_y_join_usan_columna_calificada(): void
    {
        $this->entrarA();

        // whereHas sobre otro modelo estricto: la conversación de B tiene lead de B → no aparece.
        $ids = Conversation::query()->whereHas('lead')->pluck('id')->all();
        $this->assertContains($this->a['conversacion']->id, $ids);
        $this->assertNotContains($this->b['conversacion']->id, $ids);

        // join con otra tabla que también tiene company_id: si el scope no califica la columna → SQL ambiguo.
        $filas = Conversation::query()
            ->join('marketing_leads', 'marketing_leads.id', '=', 'marketing_conversations.lead_id')
            ->select('marketing_conversations.id')
            ->pluck('id')->all();
        $this->assertContains($this->a['conversacion']->id, $filas);
        $this->assertNotContains($this->b['conversacion']->id, $filas);
    }

    public function test_relacion_desde_modelo_compartido_filtra_por_empresa(): void
    {
        $this->entrarA();

        // LeadSource y Channel son compartidos; sus hijos estrictos sí se filtran.
        $leads = $this->fuente->leads()->pluck('id')->all();
        $this->assertContains($this->a['lead']->id, $leads);
        $this->assertNotContains($this->b['lead']->id, $leads);

        $convs = $this->canal->conversations()->pluck('id')->all();
        $this->assertContains($this->a['conversacion']->id, $convs);
        $this->assertNotContains($this->b['conversacion']->id, $convs);

        // eager load desde el padre compartido
        $fuente = LeadSource::query()->with('leads')->find($this->fuente->id);
        $this->assertNotContains($this->b['lead']->id, $fuente->leads->pluck('id')->all());
    }

    public function test_relacion_belongs_to_hacia_estricto_de_otra_empresa_regresa_null(): void
    {
        // Mensaje (hijo, sin scope) de B: su conversación es de B → con A la relación no la carga.
        $this->entrarA();
        $msg = Message::query()->find($this->b['mensaje']->id);
        $this->assertNotNull($msg, 'Message es hijo sin scope: se ve (se aísla por su padre en los controladores)');
        $this->assertNull($msg->conversation, 'la conversación de B no debe cargarse con A activa');
    }

    // ── compartidos e hijos ──────────────────────────────────────────────────

    public static function sinScope(): array
    {
        return [
            'Setting'          => [Setting::class],
            'Channel'          => [Channel::class],
            'LeadSource'       => [LeadSource::class],
            'VideoTemplate'    => [\App\Models\Marketing\VideoTemplate::class],
            'MarketingNiche'   => [\App\Models\Marketing\MarketingNiche::class],
            'Asset'            => [\App\Models\Marketing\Asset::class],
            'AiAgentConfig'    => [\App\Models\Marketing\AiAgentConfig::class],
            'Message'          => [Message::class],
            'LeadActivity'     => [LeadActivity::class],
            'Attribution'      => [Attribution::class],
            'LeadPipeline'     => [LeadPipeline::class],
            'PipelineStage'    => [PipelineStage::class],
            'PublicationLog'   => [PublicationLog::class],
            'PilotCampaignSend'=> [PilotCampaignSend::class],
        ];
    }

    /** @dataProvider sinScope */
    public function test_compartidos_e_hijos_no_usan_el_trait(string $clase): void
    {
        if (! class_exists($clase)) {
            $this->fail("No existe {$clase} (el contrato §2.4 lo da por existente)");
        }
        $this->assertNotContains(PerteneceAEmpresa::class, class_uses_recursive($clase), "{$clase} NO debe llevar el scope en Fase 0 (D3)");
    }

    public function test_compartidos_se_ven_con_cualquier_empresa_activa(): void
    {
        $clave = 'qa_fase0_' . Str::lower(Str::random(8));
        Setting::query()->forceCreate(['company_id' => 1, 'key' => $clave, 'value' => 'x', 'group' => 'qa', 'encrypted' => false]);
        $fuenteB = LeadSource::query()->forceCreate(['company_id' => $this->empresaB->id, 'code' => 'qa_b_' . Str::random(5), 'name' => 'Fuente B']);

        $this->entrarB();
        $this->assertSame('x', Setting::get($clave), 'Setting::get con company 1 explícito debe seguir funcionando con B activa (Payments/Hub/Domiciliación)');
        $this->assertNotNull(LeadSource::query()->find($this->fuente->id));
        $this->assertNotNull(Channel::query()->find($this->canal->id));

        $this->entrarA();
        $this->assertNotNull(LeadSource::query()->find($fuenteB->id));
    }

    public function test_hijos_se_ven_sin_filtro_con_contexto(): void
    {
        $this->entrarA();
        $this->assertNotNull(Message::query()->find($this->b['mensaje']->id));
        $this->assertNotNull(LeadActivity::query()->find($this->b['actividad']->id));
        $this->assertNotNull(PilotCampaignSend::query()->find($this->b['envio']->id));
    }

    public function test_crear_un_estricto_sin_contexto_respeta_el_company_id_que_trae(): void
    {
        $this->contexto()->limpiar();
        $lead = Lead::query()->forceCreate([
            'company_id' => $this->empresaB->id, 'source_id' => $this->fuente->id, 'full_name' => 'Sin contexto',
            'status' => 'new', 'captured_at' => now(),
        ]);
        $this->assertSame($this->empresaB->id, (int) DB::table('marketing_leads')->where('id', $lead->id)->value('company_id'));

        $lead2 = Lead::query()->forceCreate([
            'source_id' => $this->fuente->id, 'full_name' => 'Sin contexto ni company', 'status' => 'new', 'captured_at' => now(),
        ]);
        $this->assertSame(1, (int) DB::table('marketing_leads')->where('id', $lead2->id)->value('company_id'), 'default 1 como hoy');
    }
}
