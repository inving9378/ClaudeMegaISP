<?php

namespace Tests\Feature\Marketing;

use App\Models\User;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use App\Modules\Addons\Marketing\Support\EquipoMarketing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Marketing — acceso por ROL (decisión de Irving, 2026-11-14): super-administrator, Administrador, DESARROLLADOR y
 * Mostrador entran con todo, sin estar en la lista MARKETING_EQUIPO_LOGINS ni tener fila en el equipo.
 */
class AccesoPorRolesTest extends MarketingTestCase
{
    private const ROLES = ['super-administrator', 'Administrador', 'DESARROLLADOR', 'Mostrador'];

    private const MIGRACION = 'app/Modules/Addons/Marketing/migrations/2026_11_14_120000_da_permisos_de_marketing_a_roles_con_acceso.php';

    protected function setUp(): void
    {
        parent::setUp();
        foreach (array_merge(self::ROLES, ['Vendedor']) as $r) {
            Role::findOrCreate($r, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app(EmpresaActiva::class)->limpiar();
    }

    protected function tearDown(): void
    {
        app(EmpresaActiva::class)->limpiar();
        parent::tearDown();
    }

    private function usuario(array $roles, array $extra = []): User
    {
        $u = User::factory()->create(array_merge([
            'name' => 'Rol ' . Str::random(5), 'father_last_name' => 'PRUEBA',
            'login_user' => 'mkt_rol_' . Str::lower(Str::random(8)), 'estado' => 'activo', 'is_system' => 0,
        ], $extra));
        foreach ($roles as $r) {
            $u->assignRole($r);
        }

        return $u->fresh();
    }

    public function test_la_config_trae_los_cuatro_roles_que_pidio_irving(): void
    {
        $this->assertSame(self::ROLES, EquipoMarketing::rolesConAcceso());
    }

    public function test_cada_rol_con_acceso_entra_sin_fila_en_el_equipo(): void
    {
        foreach (self::ROLES as $rol) {
            $u = $this->usuario([$rol]);
            $this->assertFalse(
                DB::table('marketing_empresa_usuario')->where('user_id', $u->id)->exists(),
                "{$rol}: no debe necesitar fila en el equipo"
            );
            $this->assertTrue(EquipoMarketing::puedeEntrar($u), "{$rol}: el menú debe mostrarse");

            $res = $this->actingAs($u)->getJson(route('api.marketing.navegacion'));
            $this->assertNotContains($res->status(), [401, 403], "{$rol}: no debe ser rechazado ({$res->status()})");
        }
    }

    public function test_entra_aunque_no_exista_la_marca_del_equipo(): void
    {
        EquipoMarketing::quitarMarca();
        $u = $this->usuario(['Mostrador']);

        $res = $this->actingAs($u)->getJson(route('api.marketing.navegacion'));

        $this->assertNotContains($res->status(), [401, 403]);
    }

    public function test_un_rol_sin_acceso_y_sin_fila_es_rechazado(): void
    {
        $u = $this->usuario(['Vendedor']);

        $this->assertFalse(EquipoMarketing::puedeEntrar($u));
        $this->actingAs($u)->getJson(route('api.marketing.navegacion'))
            ->assertStatus(403)
            ->assertJsonPath('ok', false);
    }

    public function test_usuario_inactivo_con_rol_no_entra(): void
    {
        $u = $this->usuario(['super-administrator'], ['estado' => 'inactivo']);

        $this->assertFalse(EquipoMarketing::puedeEntrar($u));
        $this->actingAs($u)->getJson(route('api.marketing.navegacion'))
            ->assertStatus(401)
            ->assertJsonPath('error', 'usuario_inactivo');
    }

    public function test_la_migracion_da_todos_los_permisos_de_marketing_a_los_cuatro_roles_y_se_deshace_sola(): void
    {
        $permisos = EquipoMarketing::permisos();
        $this->assertNotEmpty($permisos, 'debe haber permisos de Marketing en la base de pruebas');

        $mostrador = Role::findByName('Mostrador', 'web');
        $vendedor = Role::findByName('Vendedor', 'web');
        $mostrador->permissions()->detach(); // solo en la transacción de esta prueba: parte de cero
        $previoVendedor = $vendedor->permissions()->pluck('name')->sort()->values()->all();
        $uno = $permisos->first();
        $administrador = Role::findByName('Administrador', 'web');
        $administrador->permissions()->detach();
        $administrador->givePermissionTo($uno); // ya tenía uno antes de la migración
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $migracion = require base_path(self::MIGRACION);
        $migracion->up();
        $migracion->up(); // idempotente

        foreach (self::ROLES as $nombre) {
            $rol = Role::findByName($nombre, 'web');
            foreach ($permisos as $p) {
                $this->assertTrue($rol->hasPermissionTo($p->name), "{$nombre} debe tener {$p->name}");
            }
        }
        $this->assertFalse(Role::findByName('Mostrador', 'web')->permissions()->where('name', 'manage-integrations')->exists());
        $this->assertSame($previoVendedor, $vendedor->fresh()->permissions()->pluck('name')->sort()->values()->all(), 'Vendedor no cambia');

        $migracion->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame(0, Role::findByName('Mostrador', 'web')->permissions()->count(), 'down() quita lo que dio a Mostrador');
        $this->assertSame([$uno->name], Role::findByName('Administrador', 'web')->permissions()->pluck('name')->all(),
            'down() no quita el permiso que Administrador ya tenía');
    }
}
