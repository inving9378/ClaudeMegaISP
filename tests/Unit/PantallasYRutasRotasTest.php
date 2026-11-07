<?php

namespace Tests\Unit;

use App\Modules\Addons\Payments\Controllers\PaymentProviderController;
use App\Modules\Addons\Payments\Models\PaymentProvider;
use App\Modules\Core\Clientes\Models\Client;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Tests\CreatesApplication;

/** Pantallas y rutas que daban 500 por vistas movidas, métodos inexistentes o datos ausentes (#1185). */
class PantallasYRutasRotasTest extends LaravelTestCase
{
    use CreatesApplication;

    public function test_las_vistas_movidas_existen(): void
    {
        foreach ([
            'core-layout::master', 'core-layout::master-without-nav',
            'core-configuracion::commission.index', 'core-configuracion::commission.add',
            'core-configuracion::commission.edit', 'core-configuracion::commission.vendors',
            'core-configuracion::rules.columns.name', 'core-configuracion::rules.columns.sellers',
        ] as $vista) {
            $this->assertTrue(View::exists($vista), "Falta la vista {$vista}");
        }
    }

    public function test_ninguna_ruta_apunta_a_un_metodo_inexistente(): void
    {
        $rotas = [];
        foreach (Route::getRoutes() as $ruta) {
            $accion = $ruta->getActionName();
            if (! str_contains($accion, '@')) {
                continue;
            }
            [$clase, $metodo] = explode('@', $accion);
            if (class_exists($clase) && ! (new \ReflectionClass($clase))->hasMethod($metodo)) {
                $rotas[] = "{$accion} (/{$ruta->uri()})";
            }
        }

        $this->assertSame([], $rotas);
    }

    public function test_cliente_sin_informacion_principal_no_rompe_el_nombre(): void
    {
        $cliente = new Client();
        $cliente->setRelation('client_main_information', null);

        $this->assertNull($cliente->client_name);
    }

    public function test_la_clave_de_ruta_coincide_con_el_permiso(): void
    {
        $mapa = config('route_permission');

        $this->assertArrayHasKey('sistema.test_script.manage', $mapa);
        $this->assertArrayNotHasKey('sistema_test_script_manage', $mapa);
    }

    public function test_proveedor_con_config_ilegible_no_rompe_el_resumen(): void
    {
        $proveedor = new PaymentProvider();
        $proveedor->setRawAttributes(['id' => 1, 'name' => 'x', 'provider' => 'openpay', 'config' => 'cifrado-con-otra-llave']);

        $resumen = new \ReflectionMethod(PaymentProviderController::class, 'summary');
        $resumen->setAccessible(true);
        $datos = $resumen->invoke(app(PaymentProviderController::class), $proveedor);

        $this->assertTrue($datos['config_ilegible']);
        $this->assertFalse($datos['config_meta']['has_api_key']);
    }
}
