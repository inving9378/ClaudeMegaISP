<?php

namespace Tests\Feature\Core\Security;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use App\Modules\Core\Auth\Middleware\CheckRoutePermission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;
use Tests\CreatesApplication;

/**
 * Item #847 (Fase 1 del prompt de #843): motor de resolución de permisos.
 *
 * La corrección (PermissionTrait::getPermissionForUserAuthenticated usa
 * $user->getAllPermissions() = directos ∪ rol) ya está aplicada desde Fase 3a
 * (commit 708bcba0, ver CLAUDE.md "Rectificación de permisos/roles — Fase 3a").
 * Este archivo solo cubre esa resolución con pruebas, contra los 3 consumidores
 * reales del trait: el middleware CheckRoutePermission y los dos endpoints de
 * PermissionController (hasPermissionToView / allViewHasPermission).
 *
 * Cada test cubre el mismo trío: permiso SOLO por rol / SOLO directo / SIN
 * ninguno de los dos.
 *
 * USA DatabaseTransactions (rollback al terminar cada test), NO migrate:fresh.
 * Ejecutar selectivo:  php artisan test --filter=PermisosResolucionTest
 */
class PermisosResolucionTest extends TestCase
{
    use CreatesApplication, DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permisoDePrueba(string $sufijo): Permission
    {
        return Permission::create([
            'name'       => 'prueba.permisos.' . $sufijo . '.' . uniqid(),
            'guard_name' => 'web',
        ]);
    }

    private function usuario(string $sufijo): User
    {
        return User::create([
            'name'       => 'Prueba Permisos ' . $sufijo,
            'login_user' => 'prueba_permisos_' . $sufijo . '_' . uniqid(),
        ])->fresh();
    }

    private function usuarioConPermisoPorRol(Permission $permiso, string $sufijo): User
    {
        $rol = Role::create([
            'name'       => 'rol_prueba_' . $sufijo . '_' . uniqid(),
            'guard_name' => 'web',
        ]);
        $rol->givePermissionTo($permiso);

        $user = $this->usuario($sufijo);
        $user->assignRole($rol);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function usuarioConPermisoDirecto(Permission $permiso, string $sufijo): User
    {
        $user = $this->usuario($sufijo);
        $user->givePermissionTo($permiso);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * Invoca el middleware directamente (sin pasar por el router HTTP completo):
     * así la prueba queda aislada de cualquier controlador/tabla de negocio real
     * — `megaisp_test` no tiene el esquema completo (deuda conocida, ver
     * docs/bitacora-sesiones.md "queda en 236 tablas de 502") y una ruta real
     * podría fallar por drift ajeno a la resolución de permisos que se prueba
     * aquí. `$next` solo confirma que el middleware SÍ dejó pasar la petición.
     */
    private function pasaPorMiddleware(User $user, string $uri, bool $json = true): Response
    {
        $this->actingAs($user);

        $request = Request::create($uri, 'GET');
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }

        return (new CheckRoutePermission())->handle($request, fn ($req) => response('OK', 200));
    }

    // ── 1er consumidor: middleware CheckRoutePermission ─────────────────────

    public function test_permiso_solo_por_rol_deja_pasar_el_middleware(): void
    {
        $permiso = $this->permisoDePrueba('rol_mw');
        config(['route_permission' => [$permiso->name => ['/ruta-de-prueba-mw']]]);

        $user = $this->usuarioConPermisoPorRol($permiso, 'rol_mw');

        $respuesta = $this->pasaPorMiddleware($user, '/ruta-de-prueba-mw');

        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertSame('OK', $respuesta->getContent());
    }

    public function test_permiso_directo_deja_pasar_el_middleware(): void
    {
        $permiso = $this->permisoDePrueba('directo_mw');
        config(['route_permission' => [$permiso->name => ['/ruta-de-prueba-mw']]]);

        $user = $this->usuarioConPermisoDirecto($permiso, 'directo_mw');

        $respuesta = $this->pasaPorMiddleware($user, '/ruta-de-prueba-mw');

        $this->assertSame(200, $respuesta->getStatusCode());
        $this->assertSame('OK', $respuesta->getContent());
    }

    public function test_sin_el_permiso_el_middleware_deniega(): void
    {
        $permiso = $this->permisoDePrueba('sin_mw');
        config(['route_permission' => [$permiso->name => ['/ruta-de-prueba-mw']]]);

        $user = $this->usuario('sin_mw');

        // AJAX/JSON conserva el 403 real (política de denegación silenciosa, item #537).
        $this->assertSame(403, $this->pasaPorMiddleware($user, '/ruta-de-prueba-mw', json: true)->getStatusCode());

        // Navegación de página completa: redirige en silencio, no deja pasar.
        $this->assertSame(302, $this->pasaPorMiddleware($user, '/ruta-de-prueba-mw', json: false)->getStatusCode());
    }

    // ── 2° consumidor: PermissionController::hasPermissionToView ────────────

    public function test_has_permission_to_view_resuelve_los_tres_casos(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $permiso = $this->permisoDePrueba('view');
        config(['view_permission' => [$permiso->name => ['vista-de-prueba']]]);

        $conRol = $this->usuarioConPermisoPorRol($permiso, 'view_rol');
        $this->actingAs($conRol)
            ->postJson('/has-permission-to-view/vista-de-prueba')
            ->assertOk()
            ->assertJson(['data' => true]);

        $conDirecto = $this->usuarioConPermisoDirecto($permiso, 'view_directo');
        $this->actingAs($conDirecto)
            ->postJson('/has-permission-to-view/vista-de-prueba')
            ->assertOk()
            ->assertJson(['data' => true]);

        $sinPermiso = $this->usuario('view_sin');
        $this->actingAs($sinPermiso)
            ->postJson('/has-permission-to-view/vista-de-prueba')
            ->assertOk()
            ->assertJson(['data' => false]);
    }

    // ── 3er consumidor: PermissionController::allViewHasPermission ──────────

    public function test_all_view_has_permission_resuelve_los_tres_casos(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $permiso = $this->permisoDePrueba('allview');
        config(['route_permission' => [$permiso->name => ['/ruta-de-prueba-allview']]]);

        $conRol = $this->usuarioConPermisoPorRol($permiso, 'allview_rol');
        $respuesta = $this->actingAs($conRol)->postJson('/all-view-has-permission');
        $respuesta->assertOk();
        $this->assertContains($permiso->name, $respuesta->json());

        $conDirecto = $this->usuarioConPermisoDirecto($permiso, 'allview_directo');
        $respuesta = $this->actingAs($conDirecto)->postJson('/all-view-has-permission');
        $respuesta->assertOk();
        $this->assertContains($permiso->name, $respuesta->json());

        $sinPermiso = $this->usuario('allview_sin');
        $respuesta = $this->actingAs($sinPermiso)->postJson('/all-view-has-permission');
        $respuesta->assertOk();
        $this->assertNotContains($permiso->name, $respuesta->json());
    }
}
