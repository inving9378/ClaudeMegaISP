<?php

namespace Tests\Feature\Marketing\Fase0\Frontend;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 0 · paquete frontend: la vista «Empresas y equipo» (GET /marketing/empresas) monta el componente
 * solo para quien es admin de la empresa activa Y tiene el permiso marketing.empresas.gestionar.
 * Todo corre dentro de la transacción de MarketingTestCase (megaisp_test).
 */
class PantallaEmpresasTest extends MarketingTestCase
{
    private const PERMISO = 'marketing.empresas.gestionar';

    protected function setUp(): void
    {
        parent::setUp();
        Permission::firstOrCreate(['name' => self::PERMISO, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function usuario(bool $conPermiso = true): User
    {
        $u = User::factory()->create([
            'login_user' => 'mkt_front_' . Str::lower(Str::random(10)),
            'estado'     => 'activo',
            'is_system'  => 0,
        ]);
        if ($conPermiso) {
            $u->givePermissionTo(self::PERMISO);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $u->fresh();
    }

    private function asignar(User $u, string $rol, int $empresaId = 1, bool $activo = true): void
    {
        DB::table('marketing_empresa_usuario')->updateOrInsert(
            ['company_id' => $empresaId, 'user_id' => $u->id],
            ['rol' => $rol, 'activo' => $activo, 'created_by' => $u->id, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    private function abrir(User $u)
    {
        return $this->actingAs($u)
            ->withSession(['marketing.empresa_activa_id' => 1])
            ->get('/marketing/empresas');
    }

    public function test_admin_con_permiso_ve_la_pantalla_con_el_componente(): void
    {
        $u = $this->usuario();
        $this->asignar($u, 'admin');

        $this->abrir($u)
            ->assertOk()
            ->assertSee('<marketing-empresas-equipo', false)
            ->assertSee(':auth-user-id="' . $u->id . '"', false);
    }

    public function test_rol_marketing_recibe_403(): void
    {
        $u = $this->usuario();
        $this->asignar($u, 'marketing');

        $this->abrir($u)->assertForbidden()->assertDontSee('<marketing-empresas-equipo', false);
    }

    public function test_rol_ventas_recibe_403(): void
    {
        $u = $this->usuario();
        $this->asignar($u, 'ventas');

        $this->abrir($u)->assertForbidden()->assertDontSee('<marketing-empresas-equipo', false);
    }

    public function test_sin_empresa_recibe_403_con_la_pantalla_explicativa(): void
    {
        $u = $this->usuario();

        $this->abrir($u)
            ->assertForbidden()
            ->assertSee('Aún no tienes empresa en Marketing')
            ->assertDontSee('<marketing-empresas-equipo', false);
    }

    public function test_asignacion_inactiva_cuenta_como_sin_empresa(): void
    {
        $u = $this->usuario();
        $this->asignar($u, 'admin', 1, false);

        $this->abrir($u)->assertForbidden()->assertDontSee('<marketing-empresas-equipo', false);
    }

    public function test_admin_sin_permiso_spatie_recibe_403(): void
    {
        $u = $this->usuario(false);
        $this->asignar($u, 'admin');

        $this->abrir($u)->assertForbidden()->assertDontSee('<marketing-empresas-equipo', false);
    }

    public function test_sin_sesion_no_entra(): void
    {
        $this->get('/marketing/empresas')->assertRedirect();
    }
}
