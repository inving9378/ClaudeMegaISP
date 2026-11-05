<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Services\ExpedienteValidador;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

class ExpedienteValidadorTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_guardar_incompleto_siempre_permitido_pero_informa(): void
    {
        $r = (new ExpedienteValidador())->validar(new CrmExpediente(), 'guardar');
        $this->assertTrue($r['permitido']);
        $this->assertArrayHasKey('razon_social', $r['faltantes']);
    }

    public function test_emitir_propuesta_exige_minimos(): void
    {
        $v = new ExpedienteValidador();
        $this->assertFalse($v->validar(new CrmExpediente(), 'emitir_propuesta')['permitido']);
        $e = new CrmExpediente(['tipo_persona' => 'moral', 'razon_social' => 'EMPRESA FICTICIA SA DE CV']);
        $this->assertTrue($v->validar($e, 'emitir_propuesta')['permitido']);
    }

    public function test_enviar_firma_acumula_requisitos_y_valida_formato(): void
    {
        $v = new ExpedienteValidador();
        $e = new CrmExpediente(['tipo_persona' => 'moral', 'razon_social' => 'X']);
        $r = $v->validar($e, 'enviar_firma');
        $this->assertFalse($r['permitido']);
        foreach (['rfc', 'regimen_fiscal', 'domicilio_fiscal_cp', 'email', 'representante'] as $k) {
            $this->assertArrayHasKey($k, $r['faltantes']);
        }
        $e->rfc = 'basura';
        $this->assertArrayHasKey('rfc', $v->validar($e, 'enviar_firma')['errores']);
    }

    public function test_activar_exige_mas_que_enviar_firma(): void
    {
        $r = (new ExpedienteValidador())->validar(new CrmExpediente(), 'activar');
        foreach (['uso_cfdi', 'telefono', 'contacto', 'sitio', 'representante'] as $k) {
            $this->assertArrayHasKey($k, $r['faltantes']);
        }
    }

    public function test_accion_desconocida_falla(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new ExpedienteValidador())->validar(new CrmExpediente(), 'otra');
    }
}
