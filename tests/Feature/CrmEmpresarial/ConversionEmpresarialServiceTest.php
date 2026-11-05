<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Http\Requests\module\crm\ConvertToClientRequest;
use App\Modules\Addons\CrmEmpresarial\Models\CrmConversion;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Services\ConversionEmpresarialService;
use App\Modules\Core\CRM\Models\Crm;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\CreatesApplication;

class ConversionEmpresarialServiceTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(\App\Models\User::query()->firstOrFail()); // BaseModel sella created_by
    }

    private function expediente(array $extra = []): CrmExpediente
    {
        $crm = Crm::create([]);
        return CrmExpediente::create($extra + [
            'crm_id' => $crm->id, 'tipo_persona' => 'moral', 'razon_social' => 'EMPRESA FICTICIA CONVERSION SA DE CV',
            'rfc' => 'ECF010101AB1', 'regimen_fiscal' => '601', 'domicilio_fiscal_cp' => '54900',
            'domicilio_fiscal_calle' => 'Calle Falsa', 'email' => 'conv-ficticio-563@example.test', 'telefono' => '5500000563',
        ]);
    }

    public function test_checklist_bloquea_sin_identidad_y_lista_pendientes_sin_dato(): void
    {
        $r = (new ConversionEmpresarialService())->checklist(new CrmExpediente());
        $this->assertFalse($r['puede_convertir']);
        $ok = (new ConversionEmpresarialService())->checklist($this->expediente());
        $this->assertTrue($ok['puede_convertir']);
        $claves = collect($ok['items'])->where('ok', false)->pluck('clave')->all();
        foreach (['contrato', 'factibilidad', 'autorizacion_instalacion', 'sitios'] as $k) {
            $this->assertContains($k, $claves);
        }
    }

    public function test_duplicado_por_rfc_normalizado_exige_confirmacion(): void
    {
        $this->expediente(['rfc' => 'ECF-010101 AB1']);
        $e2 = $this->expediente();
        $dup = (new ConversionEmpresarialService())->duplicados($e2);
        $this->assertSame('rfc', $dup[0]['motivo']);
        $this->expectException(ValidationException::class);
        (new ConversionEmpresarialService())->convertir($e2, ['connection_type' => 'olt'], null, false);
    }

    public function test_convierte_sin_activar_servicios_y_deja_traza(): void
    {
        $e = $this->expediente();
        $crmId = (int) $e->crm_id;
        $antes = DB::table('client_internet_services')->count();
        $res = (new ConversionEmpresarialService())->convertir($e, ['connection_type' => 'olt'], null, true);

        $e->refresh();
        $this->assertSame($res['client_id'], (int) $e->client_id);
        $this->assertSame('registrado', $e->estado_comercial);
        $this->assertSame('ECF010101AB1', DB::table('client_fiscal_data')->where('client_id', $res['client_id'])->value('rfc'));
        $this->assertSame($e->razon_social, DB::table('client_main_information')->where('client_id', $res['client_id'])->value('name'));
        $this->assertSame($antes, DB::table('client_internet_services')->count());
        $c = CrmConversion::findOrFail($res['conversion_id']);
        $this->assertSame($e->id, (int) $c->expediente_id);
        $this->assertSame($crmId, (int) $c->crm_id); // el expediente pierde crm_id (FK nullOnDelete); la traza lo conserva
        $this->assertNull(Crm::find($crmId));

        $this->expectException(ValidationException::class); // no se convierte dos veces
        (new ConversionEmpresarialService())->convertir($e, [], null, true);
    }

    public function test_regresion_email_unico_no_exime_al_cliente_con_el_id_del_crm(): void
    {
        $cid = DB::table('clients')->insertGetId(['created_by' => \App\Models\User::query()->value('id'), 'updated_by' => \App\Models\User::query()->value('id'), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('client_main_information')->insert(['client_id' => $cid, 'name' => 'FICTICIO', 'email' => 'regresion-563@example.test', 'created_at' => now(), 'updated_at' => now()]);
        $main = DB::table('client_main_information')->where('client_id', $cid)->first();
        $req = Request::create('/convert-to-client/' . $main->id, 'POST');
        $req->setRouteResolver(fn () => new class($main->id) {
            public function __construct(private $id) {}
            public function parameter($k, $d = null) { return $this->id; }
        });
        $rules = (new ConvertToClientRequest())->rules($req);
        $v = Validator::make(['email' => $main->email], ['email' => $rules['email']]);
        $this->assertTrue($v->fails());
    }
}
