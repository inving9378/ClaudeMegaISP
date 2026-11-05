<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Models\ClientFiscalData;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Services\ExpedienteFiscalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Tests\CreatesApplication;

class ExpedienteFiscalServiceTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_escribe_en_client_fiscal_data_con_campos_nuevos(): void
    {
        $cid = $this->clienteLibre();
        $e = new CrmExpediente([
            'tipo_persona' => 'moral', 'razon_social' => 'EMPRESA FICTICIA SA DE CV',
            'rfc' => 'xaxx010101000', 'regimen_fiscal' => '601', 'domicilio_fiscal_cp' => '54900',
            'domicilio_fiscal_calle' => 'Calle Falsa', 'domicilio_fiscal_num_ext' => '12',
        ]);
        $e->rfc = 'EFI010101AB1';
        $f = (new ExpedienteFiscalService())->aplicarAlConvertir($e, $cid, [
            'metodo_pago_cfdi' => 'PPD', 'dias_credito' => 30, 'contacto_facturacion_nombre' => 'Persona Ficticia',
        ]);
        $this->assertSame('EFI010101AB1', $f->rfc);
        $this->assertSame('PPD', $f->metodo_pago_cfdi);
        $this->assertSame('12', $f->num_exterior);
        $this->assertSame(1, ClientFiscalData::where('client_id', $cid)->count());
    }

    public function test_rfc_invalido_o_de_otro_tipo_se_rechaza(): void
    {
        $e = new CrmExpediente(['tipo_persona' => 'fisica', 'rfc' => 'EFI010101AB1']);
        $this->assertArrayHasKey('rfc', (new ExpedienteFiscalService())->validar($e));
        $e2 = new CrmExpediente(['rfc' => 'basura']);
        $this->assertArrayHasKey('rfc', (new ExpedienteFiscalService())->validar($e2));
    }

    public function test_no_pisa_con_vacios_lo_ya_capturado(): void
    {
        $cid = $this->clienteLibre();
        ClientFiscalData::create(['client_id' => $cid, 'facturacion_fiscal' => true, 'razon_social' => 'PREVIA']);
        $e = new CrmExpediente(['rfc' => 'EFI010101AB1']);
        $f = (new ExpedienteFiscalService())->aplicarAlConvertir($e, $cid);
        $this->assertSame('PREVIA', $f->razon_social);
        $this->assertSame('EFI010101AB1', $f->rfc);
    }

    private function clienteLibre(): int
    {
        return (int) \DB::table('clients')
            ->whereNotIn('id', \DB::table('client_fiscal_data')->select('client_id'))->value('id');
    }
}
