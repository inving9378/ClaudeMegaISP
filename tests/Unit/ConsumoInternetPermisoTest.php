<?php

namespace Tests\Unit;

use App\Http\Controllers\InternetConsumptionRadiusController;
use App\Modules\Core\Auth\Middleware\CheckRoutePermission;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\CreatesApplication;

/** /admin/consumo muestra datos de todos los clientes: solo entra quien tiene `consumo_internet.ver` (#1186). */
class ConsumoInternetPermisoTest extends LaravelTestCase
{
    use CreatesApplication;

    private function pedir(string $ruta, array $permisos)
    {
        $usuario = \Mockery::mock();
        $usuario->shouldReceive('isNotActive', 'isAdmin', 'isDevelopment', 'isSuperAdmin')->andReturn(false);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($usuario);
        Auth::shouldReceive('id')->andReturn(1);

        $middleware = new class(collect($permisos)) extends CheckRoutePermission {
            public function __construct(private $coleccion) {}

            public function getPermissionForUserAuthenticated()
            {
                return $this->coleccion;
            }
        };

        return $middleware->handle(Request::create($ruta), fn () => response('paso'));
    }

    public function test_las_rutas_pasan_por_el_middleware_de_permisos(): void
    {
        foreach (['consumption.index', 'consumption.show'] as $nombre) {
            $this->assertContains('check_route_permission', Route::getRoutes()->getByName($nombre)->gatherMiddleware());
        }
    }

    public function test_con_el_permiso_entra_a_listado_y_detalle(): void
    {
        foreach (['/admin/consumo', '/admin/consumo/usuario-x'] as $ruta) {
            $this->assertSame('paso', $this->pedir($ruta, ['consumo_internet.ver' => true])->getContent());
        }
    }

    public function test_sin_el_permiso_no_entra(): void
    {
        foreach (['/admin/consumo', '/admin/consumo/usuario-x'] as $ruta) {
            $this->assertNotSame('paso', $this->pedir($ruta, ['otro_permiso' => true])->getContent());
        }
    }

    public function test_sin_radius_muestra_aviso_en_vez_de_error(): void
    {
        config(['database.connections.radius.host' => '127.0.0.1', 'database.connections.radius.port' => 1]);
        DB::purge('radius');
        $controlador = app(InternetConsumptionRadiusController::class);

        $listado = $controlador->index(Request::create('/admin/consumo'));
        $this->assertTrue($listado->getData()['radiusNoDisponible']);
        $this->assertCount(0, $listado->getData()['data']);
    }
}
