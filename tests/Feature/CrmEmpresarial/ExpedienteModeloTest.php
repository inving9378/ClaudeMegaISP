<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Models\CrmSegmento;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

class ExpedienteModeloTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_catalogo_de_segmentos_sembrado(): void
    {
        $this->assertEqualsCanonicalizing(
            ['RES', 'NEG', 'PYME', 'DED'],
            CrmSegmento::pluck('codigo')->all()
        );
        $this->assertFalse(CrmSegmento::where('codigo', 'RES')->first()->requiere_expediente);
    }

    public function test_columnas_aditivas_existen_y_son_nullables(): void
    {
        $this->assertTrue(Schema::hasColumns('crm_main_information', ['segmento_id', 'tipo_persona', 'nombre_comercial']));
        $this->assertTrue(Schema::hasColumn('client_additional_information', 'segmento_id'));
        $this->assertTrue(Schema::hasColumn('client_internet_services', 'sitio_id'));
        $this->assertTrue(Schema::hasColumn('client_custom_services', 'sitio_id'));
    }

    public function test_relaciones_del_expediente(): void
    {
        $seg = CrmSegmento::where('codigo', 'PYME')->first();
        $e = CrmExpediente::create(['segmento_id' => $seg->id, 'tipo_persona' => 'moral', 'razon_social' => 'Empresa Ficticia SA de CV']);
        $c = $e->contactos()->create(['nombre' => 'Contacto Ficticio', 'tipo' => 'tecnico']);
        $e->representantes()->create(['nombre' => 'Rep Ficticio', 'tipo_representacion' => 'apoderado']);
        $s = $e->sitios()->create(['nombre' => 'Nave 1', 'contacto_id' => $c->id]);
        $d = $e->documentos()->create(['uuid' => (string) \Illuminate\Support\Str::uuid(), 'tipo' => 'acta', 'titulo' => 'Acta']);
        $d->versiones()->create(['version' => 1, 'archivo_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'archivo_nombre_original' => 'a.pdf', 'mime' => 'application/pdf', 'hash' => str_repeat('a', 64)]);

        $e = $e->fresh();
        $this->assertSame('borrador', $e->estado);
        $this->assertSame('PYME', $e->segmento->codigo);
        $this->assertCount(1, $e->representantes);
        $this->assertSame($c->id, $s->fresh()->contacto->id);
        $this->assertCount(1, $e->documentos->first()->versiones);
    }
}
