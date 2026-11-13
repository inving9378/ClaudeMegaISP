<?php

namespace Tests\Feature\Marketing\Fase0\Backend;

use Illuminate\Support\Facades\DB;
use Tests\Feature\Marketing\Fase0\Backend\Concerns\EscenarioBackend;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * Fase 0 · backend — migración de asignación inicial (2026_11_12_100300) y semillas de las otras.
 * up()/down() se ejecutan dentro de la transacción de la prueba (se deshace al terminar).
 */
class MigracionAsignacionBackendTest extends MarketingTestCase
{
    use EscenarioBackend;

    private function migracion(): object
    {
        return require base_path('app/Modules/Addons/Marketing/migrations/2026_11_12_100300_asigna_equipo_actual_a_empresa_meganet.php');
    }

    private function filaDe(int $userId): ?object
    {
        return DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $userId)->first();
    }

    public function test_la_semilla_de_meganet_y_el_permiso_existen(): void
    {
        $this->assertDatabaseHas('marketing_empresas', [
            'id' => 1, 'nombre' => 'Meganet', 'slug' => 'meganet', 'color_primario' => '#0066CC', 'color_secundario' => '#FF6600', 'activo' => 1,
        ]);

        $roles = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('permissions.name', 'marketing.empresas.gestionar')
            ->pluck('roles.name')->sort()->values()->all();

        $this->assertSame(['DESARROLLADOR', 'super-administrator'], $roles);
    }

    public function test_selector_por_rol_con_exclusiones(): void
    {
        $superAdmin = $this->crearUsuario(['super-administrator']);
        $dev = $this->crearUsuario(['DESARROLLADOR']);
        $devYAdmin = $this->crearUsuario(['DESARROLLADOR', 'Administrador']);
        $superAdminEs = $this->crearUsuario(['Super Administrador']);
        $administrador = $this->crearUsuario(['Administrador']);
        $espejo = $this->crearUsuario(['super-administrator', 'client']);
        $inactivo = $this->crearUsuario(['super-administrator'], ['estado' => 'inactivo']);
        $bloqueado = $this->crearUsuario(['Administrador'], ['estado' => 'bloqueado']);
        $bot = $this->crearUsuario(['DESARROLLADOR'], ['is_system' => 1]);
        $vendedor = $this->crearUsuario(['Vendedor']);
        $sinRoles = $this->crearUsuario([]);

        $this->migracion()->up();

        foreach ([$superAdmin, $dev, $devYAdmin] as $u) {
            $fila = $this->filaDe($u->id);
            $this->assertNotNull($fila, "{$u->login_user} debió quedar asignado");
            $this->assertSame('admin', $fila->rol);
            $this->assertSame(1, (int) $fila->activo);
            $this->assertNull($fila->created_by);
        }
        foreach ([$superAdminEs, $administrador] as $u) {
            $this->assertSame('marketing', $this->filaDe($u->id)?->rol);
        }
        foreach ([$espejo, $inactivo, $bloqueado, $bot, $vendedor, $sinRoles] as $u) {
            $this->assertNull($this->filaDe($u->id), "{$u->login_user} no debió asignarse");
        }
    }

    public function test_es_idempotente_y_no_pisa_asignaciones_hechas_a_mano(): void
    {
        $superAdmin = $this->crearUsuario(['super-administrator']);
        $quitadoAMano = $this->crearUsuario(['super-administrator']);
        $quien = $this->crearUsuario(['super-administrator']);
        $this->asignar($quitadoAMano, 1, 'ventas', false, $quien->id);

        $m = $this->migracion();
        $m->up();
        $total = DB::table('marketing_empresa_usuario')->count();
        $m->up();

        $this->assertSame($total, DB::table('marketing_empresa_usuario')->count(), 'segunda corrida no duplica');
        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $superAdmin->id)->count());

        $fila = $this->filaDe($quitadoAMano->id);
        $this->assertSame('ventas', $fila->rol, 'la decisión humana se respeta');
        $this->assertSame(0, (int) $fila->activo);
    }

    public function test_down_solo_borra_lo_que_asigno_la_migracion(): void
    {
        $superAdmin = $this->crearUsuario(['super-administrator']);
        $aMano = $this->crearUsuario(['Mostrador']);
        $enOtraEmpresa = $this->crearUsuario(['Mostrador']);
        $b = $this->crearEmpresa();
        $this->asignar($aMano, 1, 'ventas', true, $superAdmin->id);
        $this->asignar($enOtraEmpresa, $b->id, 'admin', true, null);

        $m = $this->migracion();
        $m->up();
        $this->assertNotNull($this->filaDe($superAdmin->id));

        $m->down();

        $this->assertNull($this->filaDe($superAdmin->id));
        $this->assertSame(0, DB::table('marketing_empresa_usuario')->where('company_id', 1)->whereNull('created_by')->count());
        $this->assertNotNull($this->filaDe($aMano->id));
        $this->assertDatabaseHas('marketing_empresa_usuario', ['company_id' => $b->id, 'user_id' => $enOtraEmpresa->id]);
    }
}
