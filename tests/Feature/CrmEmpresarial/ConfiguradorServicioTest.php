<?php

namespace Tests\Feature\CrmEmpresarial;

use App\Models\CompanyInformation;
use App\Models\User;
use App\Modules\Addons\CrmEmpresarial\Models\CrmExpediente;
use App\Modules\Addons\CrmEmpresarial\Services\ConfiguradorServicioService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\CreatesApplication;

class ConfiguradorServicioTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    public function test_iva_18900_da_3024_y_total_21924(): void
    {
        $r = (new ConfiguradorServicioService())->calcular(18900, 16);
        $this->assertSame(3024.0, $r['iva_importe']);
        $this->assertSame(21924.0, $r['total']);
    }

    public function test_precio_distinto_sin_permiso_no_se_guarda_y_con_permiso_si(): void
    {
        $exp = CrmExpediente::create(['tipo_persona' => 'moral', 'razon_social' => 'EMPRESA FICTICIA SA DE CV']);
        $tabla = DB::table('customs')->first();
        if (! $tabla || ! CompanyInformation::query()->exists()) {
            $this->markTestSkipped('Sin catálogo customs o company_information en esta BD.');
        }
        $svc = new ConfiguradorServicioService();
        $sin = User::factory()->make(['id' => 999999]);
        $datos = ['servicio_tipo' => 'custom', 'servicio_id' => $tabla->id, 'precio_aprobado' => (float) $tabla->price + 100,
            'justificacion_precio' => 'Prueba'];
        try {
            $svc->guardar($exp->id, $datos, $sin);
            $this->fail('Debió exigir el permiso.');
        } catch (AuthorizationException) {
            $this->assertSame(0, DB::table('crm_configuraciones_servicio')->where('expediente_id', $exp->id)->count());
        }

        // Precio igual al de lista: no requiere permiso y nunca queda "aprobado".
        $c = $svc->guardar($exp->id, ['servicio_tipo' => 'custom', 'servicio_id' => $tabla->id], $sin);
        $this->assertEquals((float) $tabla->price, (float) $c->precio_lista);
        $this->assertNull($c->aprobado_por);

        // Con permiso: queda aprobado, con versión nueva al cambiar el precio.
        $p = Permission::firstOrCreate(['name' => ConfiguradorServicioService::PERMISO_APROBAR, 'guard_name' => 'web']);
        $u = User::query()->first();
        $u->givePermissionTo($p);
        $c2 = $svc->guardar($exp->id, $datos, $u, $c);
        $this->assertSame($u->id, (int) $c2->aprobado_por);
        $this->assertSame(2, (int) $c2->version_oferta);
        // Regenerar documentos (guardar sin tocar precio) no cambia el aprobado.
        $c3 = $svc->guardar($exp->id, ['equipo_entrega' => 'Router ficticio'] + $datos, $u, $c2);
        $this->assertEquals((float) $c2->precio_aprobado, (float) $c3->precio_aprobado);
        $this->assertSame(2, (int) $c3->version_oferta);
    }

    public function test_redundancia_solo_si_verificada(): void
    {
        $c = new \App\Modules\Addons\CrmEmpresarial\Models\CrmConfiguracionServicio(['red_rutas_fisicas' => 2]);
        $svc = new ConfiguradorServicioService();
        $this->assertSame([], $svc->redundanciaVerificada($c));
        $c->red_verificada = true;
        $this->assertSame(['rutas_fisicas' => 2], $svc->redundanciaVerificada($c));
    }
}
