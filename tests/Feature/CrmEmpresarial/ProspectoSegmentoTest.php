<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Http\Requests\module\crm\CrmCreateRequest;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Models\CrmSegmento;
use App\Modules\Core\CRM\Models\Crm;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\CreatesApplication;

/** CRM-E 03 (#557): segmento al crear prospecto + persona moral. Datos ficticios. */
class ProspectoSegmentoTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    private function prospecto(array $extra = []): Crm
    {
        $crm = Crm::create([]);
        $crm->crm_main_information()->create($extra + ['name' => 'Prueba', 'father_last_name' => 'Uno', 'mother_last_name' => 'Dos']);

        return $crm;
    }

    private function seg(string $codigo): int
    {
        return CrmSegmento::where('codigo', $codigo)->value('id');
    }

    public function test_residencial_sin_segmento_no_crea_expediente(): void
    {
        $this->assertSame(0, CrmExpediente::where('crm_id', $this->prospecto()->id)->count());
        $this->assertSame(0, CrmExpediente::where('crm_id', $this->prospecto(['segmento_id' => $this->seg('RES')])->id)->count());
    }

    public function test_neg_pyme_y_ded_abren_expediente_en_borrador(): void
    {
        foreach (['NEG', 'PYME'] as $codigo) {
            $e = CrmExpediente::where('crm_id', $this->prospecto(['segmento_id' => $this->seg($codigo)])->id)->first();
            $this->assertNotNull($e, $codigo);
            $this->assertSame('borrador', $e->estado);
            $this->assertFalse($e->requiere_expediente_dedicado);
        }
        $e = CrmExpediente::where('crm_id', $this->prospecto(['segmento_id' => $this->seg('DED'), 'requiere_expediente_dedicado' => 1])->id)->first();
        $this->assertTrue($e->requiere_expediente_dedicado);
    }

    public function test_persona_moral_copia_razon_social_al_expediente(): void
    {
        $crm = $this->prospecto([
            'name' => 'Comercializadora Ficticia, S.A. de C.V.', 'father_last_name' => null, 'mother_last_name' => null,
            'segmento_id' => $this->seg('PYME'), 'tipo_persona' => 'moral', 'nombre_comercial' => 'Ficticia',
        ]);
        $e = CrmExpediente::where('crm_id', $crm->id)->first();
        $this->assertSame('Comercializadora Ficticia, S.A. de C.V.', $e->razon_social);
        $this->assertSame('Ficticia', $e->nombre_comercial);
    }

    private function validar(array $datos)
    {
        $req = Request::create('/crm', 'POST', $datos);
        $form = CrmCreateRequest::createFromBase($req);

        return Validator::make($datos, collect($form->rules($req))->only(['name', 'father_last_name', 'mother_last_name'])->all());
    }

    public function test_razon_social_con_puntos_y_comas_solo_para_moral(): void
    {
        $nombre = 'Redes & Datos Ficticios, S.A. de C.V.';
        $this->assertTrue($this->validar(['name' => $nombre, 'tipo_persona' => 'moral'])->passes());
        $this->assertTrue($this->validar(['name' => $nombre, 'tipo_persona' => 'fisica', 'father_last_name' => 'a', 'mother_last_name' => 'b'])->fails());
    }

    public function test_residencial_sigue_exigiendo_apellidos(): void
    {
        $this->assertTrue($this->validar(['name' => 'Juan'])->fails());
        $this->assertTrue($this->validar(['name' => 'Juan', 'father_last_name' => 'a', 'mother_last_name' => 'b'])->passes());
    }
}
