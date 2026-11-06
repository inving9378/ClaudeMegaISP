<?php

namespace Tests\Unit;

use App\Modules\Core\Auth\Middleware\CheckRoutePermission;
use Illuminate\Foundation\Testing\TestCase as LaravelTestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tests\CreatesApplication;

/**
 * getPermissionForUserAuthenticated() devuelve una Collection. Exigir array en el log de #978 tumbaba
 * con 500 a todo usuario que no es administrador en cada ruta que sí tenía permitida.
 */
class CheckRoutePermissionUsuarioSinAdminTest extends LaravelTestCase
{
    use CreatesApplication;

    private function pedir(string $ruta, array $permisos, array $mapa)
    {
        $usuario = \Mockery::mock();
        $usuario->shouldReceive('isNotActive', 'isAdmin', 'isDevelopment', 'isSuperAdmin')->andReturn(false);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('user')->andReturn($usuario);
        Auth::shouldReceive('id')->andReturn(1);
        config(['route_permission' => $mapa]);

        $middleware = new class(collect($permisos)) extends CheckRoutePermission {
            public function __construct(private $coleccion) {}

            public function getPermissionForUserAuthenticated()
            {
                return $this->coleccion;
            }
        };

        return $middleware->handle(Request::create($ruta), fn () => response('paso'));
    }

    public function test_usuario_con_permiso_pasa_sin_error(): void
    {
        $respuesta = $this->pedir('/ruta-x', ['permiso_x' => true], ['permiso_x' => ['/ruta-x']]);

        $this->assertSame('paso', $respuesta->getContent());
    }

    public function test_el_acceso_por_el_permiso_legado_se_registra_y_pasa(): void
    {
        Log::spy();
        $ruta = '/administracion/document_template/table';

        $respuesta = $this->pedir($ruta, ['config_view_system' => true], ['config_view_system' => [$ruta]]);

        $this->assertSame('paso', $respuesta->getContent());
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }
}
