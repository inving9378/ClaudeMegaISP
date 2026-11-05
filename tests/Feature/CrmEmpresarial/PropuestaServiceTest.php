<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Models\CrmPropuestaHistorial;
use App\Modules\Addons\CrmEmpresarial\Services\PropuestaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Validation\ValidationException;
use Tests\CreatesApplication;

class PropuestaServiceTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private PropuestaService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new PropuestaService();
    }

    private function propuesta()
    {
        $e = CrmExpediente::create(['tipo_persona' => 'moral', 'razon_social' => 'EMPRESA FICTICIA SA DE CV']);
        return $this->svc->crear($e, null);
    }

    public function test_no_se_crea_con_expediente_incompleto(): void
    {
        $this->expectException(ValidationException::class);
        $this->svc->crear(CrmExpediente::create(['tipo_persona' => 'moral']), null);
    }

    public function test_nace_borrador_v1_con_historial(): void
    {
        $p = $this->propuesta();
        $this->assertSame('borrador', $p->estado);
        $this->assertSame(1, $p->version_actual);
        $this->assertSame(1, CrmPropuestaHistorial::where('propuesta_version_id', $p->vigente()->id)->count());
    }

    public function test_nueva_version_sustituye_y_conserva_la_anterior(): void
    {
        $p = $this->propuesta();
        $v1 = $this->svc->editarBorrador($p->vigente(), ['precio' => 1000]);
        $v2 = $this->svc->nuevaVersion($p, ['precio' => 1200], 'Ajuste de precio');
        $this->assertSame(2, $v2->version);
        $this->assertSame('borrador', $p->fresh()->estado);
        $this->assertSame('sustituida', $v1->fresh()->estado);
        $this->assertEquals(1000, $v1->fresh()->snapshot['precio']);
        $this->assertEquals(1200, $v2->snapshot['precio']);
    }

    public function test_flujo_completo_y_aprobacion_registrada(): void
    {
        $v = $this->propuesta()->vigente();
        foreach (['en_revision', 'aprobada', 'enviada', 'vista', 'aceptada'] as $a) {
            $v = $this->svc->transicionar($v, $a, null);
        }
        $this->assertSame('aceptada', $v->estado);
        $this->assertNotNull($v->aprobada_at);
        $this->assertSame('aceptada', $v->propuesta->fresh()->estado);
    }

    public function test_enviada_no_se_edita_ni_salta_estados(): void
    {
        $v = $this->propuesta()->vigente();
        try {
            $this->svc->transicionar($v, 'enviada');
            $this->fail('borrador→enviada no debe permitirse');
        } catch (ValidationException) {
        }
        foreach (['en_revision', 'aprobada', 'enviada'] as $a) {
            $v = $this->svc->transicionar($v, $a);
        }
        $this->expectException(ValidationException::class);
        $this->svc->editarBorrador($v, ['precio' => 1]);
    }

    public function test_aceptada_no_admite_version_nueva(): void
    {
        $p = $this->propuesta();
        $v = $p->vigente();
        foreach (['en_revision', 'aprobada', 'enviada', 'aceptada'] as $a) {
            $v = $this->svc->transicionar($v, $a);
        }
        $this->expectException(ValidationException::class);
        $this->svc->nuevaVersion($p, ['precio' => 1], 'x');
    }

    public function test_rechazo_exige_motivo(): void
    {
        $v = $this->propuesta()->vigente();
        foreach (['en_revision', 'aprobada', 'enviada'] as $a) {
            $v = $this->svc->transicionar($v, $a);
        }
        $this->expectException(ValidationException::class);
        $this->svc->transicionar($v, 'rechazada');
    }

    public function test_vencimiento_por_vigencia(): void
    {
        $p = $this->propuesta();
        $v = $p->vigente();
        foreach (['en_revision', 'aprobada', 'enviada'] as $a) {
            $v = $this->svc->transicionar($v, $a);
        }
        $v->update(['vigencia_hasta' => now()->subDay()->toDateString()]);
        $this->assertGreaterThanOrEqual(1, $this->svc->vencerVencidas());
        $this->assertSame('vencida', $v->fresh()->estado);
        $this->assertSame('vencida', $p->fresh()->estado);
    }

    public function test_historial_es_inmutable(): void
    {
        $h = CrmPropuestaHistorial::first() ?? CrmPropuestaHistorial::create(['propuesta_version_id' => 1, 'a' => 'borrador']);
        $this->expectException(\LogicException::class);
        $h->update(['a' => 'x']);
    }
}
