<?php

namespace Tests\Feature\Marketing\Fase0\Qa;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 0 — migración 100300 (asignación inicial, D1, criterio 2, riesgo R11).
 *
 * Ejecuta up()/down() DENTRO de la transacción de la prueba (es solo DML, se deshace al terminar):
 * selector por NOMBRE de rol, excluye client / is_system / no activos, idempotente, y down() solo
 * borra lo que ella misma escribió (company_id = 1 y created_by IS NULL).
 */
class MigracionAsignacionTest extends MarketingTestCase
{
    private const ARCHIVO = 'app/Modules/Addons/Marketing/migrations/2026_11_12_100300_asigna_equipo_actual_a_empresa_meganet.php';

    private function migracion(): object
    {
        $ruta = base_path(self::ARCHIVO);
        $this->assertFileExists($ruta);

        return require $ruta;
    }

    private function correr(string $metodo): void
    {
        ob_start();
        try {
            $this->migracion()->{$metodo}();
        } finally {
            ob_end_clean();
        }
    }

    private function usuario(array $roles, array $extra = []): User
    {
        $u = User::factory()->create(array_merge([
            'name' => 'QA Mig', 'login_user' => 'qa_mig_' . Str::lower(Str::random(10)), 'estado' => 'activo', 'is_system' => 0,
        ], $extra));
        foreach ($roles as $rol) {
            $u->assignRole($rol);
        }

        return $u;
    }

    private function asignacion(User $u): ?object
    {
        return DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $u->id)->first();
    }

    public function test_selector_por_rol_y_exclusiones(): void
    {
        $superAdmin  = $this->usuario(['super-administrator']);
        $dev         = $this->usuario(['DESARROLLADOR']);
        $administr   = $this->usuario(['Administrador']);
        $superAdmES  = $this->usuario(['Super Administrador']);
        $ambos       = $this->usuario(['Super Administrador', 'super-administrator']);
        $espejo      = $this->usuario(['super-administrator', 'client']);
        $inactivo    = $this->usuario(['super-administrator'], ['estado' => 'inactivo']);
        $bloqueado   = $this->usuario(['Administrador'], ['estado' => 'bloqueado']);
        $sistema     = $this->usuario(['DESARROLLADOR'], ['is_system' => 1]);
        $vendedor    = $this->usuario(['Vendedor']);
        $sinRol      = $this->usuario([]);

        $this->correr('up');

        $this->assertSame('admin', $this->asignacion($superAdmin)?->rol);
        $this->assertSame('admin', $this->asignacion($dev)?->rol);
        $this->assertSame('marketing', $this->asignacion($administr)?->rol);
        $this->assertSame('marketing', $this->asignacion($superAdmES)?->rol);
        $this->assertSame('admin', $this->asignacion($ambos)?->rol, 'si califica a admin, no queda como marketing');
        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('user_id', $ambos->id)->count());

        foreach (['cuenta espejo client' => $espejo, 'inactivo' => $inactivo, 'bloqueado' => $bloqueado, 'sistema' => $sistema, 'Vendedor' => $vendedor, 'sin rol' => $sinRol] as $que => $u) {
            $this->assertNull($this->asignacion($u), "{$que} no debe asignarse");
        }

        $fila = $this->asignacion($superAdmin);
        $this->assertSame(1, (int) $fila->activo);
        $this->assertNull($fila->created_by, 'created_by = null marca lo que escribió la migración');
    }

    public function test_es_idempotente_y_no_pisa_asignaciones_hechas_a_mano(): void
    {
        $a = $this->usuario(['super-administrator']);
        $manual = $this->usuario(['super-administrator']);
        DB::table('marketing_empresa_usuario')->insert([
            'company_id' => 1, 'user_id' => $manual->id, 'rol' => 'ventas', 'activo' => 0,
            'created_by' => $a->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->correr('up');
        $total = DB::table('marketing_empresa_usuario')->count();
        $this->correr('up');

        $this->assertSame($total, DB::table('marketing_empresa_usuario')->count(), 'correr dos veces no duplica');
        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('company_id', 1)->where('user_id', $a->id)->count());

        $filaManual = $this->asignacion($manual);
        $this->assertSame('ventas', $filaManual->rol, 'no pisa el rol puesto a mano');
        $this->assertSame(0, (int) $filaManual->activo, 'no reactiva a quien quitaron a mano');
    }

    public function test_down_solo_borra_lo_de_la_migracion(): void
    {
        $a = $this->usuario(['super-administrator']);
        $manual = $this->usuario(['Vendedor']);
        DB::table('marketing_empresa_usuario')->insert([
            'company_id' => 1, 'user_id' => $manual->id, 'rol' => 'ventas', 'activo' => 1,
            'created_by' => $a->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otraEmpresa = DB::table('marketing_empresas')->insertGetId([
            'nombre' => 'QA Mig B', 'slug' => 'qa-mig-' . Str::lower(Str::random(8)), 'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('marketing_empresa_usuario')->insert([
            'company_id' => $otraEmpresa, 'user_id' => $a->id, 'rol' => 'admin', 'activo' => 1,
            'created_by' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->correr('up');
        $this->assertNotNull($this->asignacion($a));

        $this->correr('down');

        $this->assertNull($this->asignacion($a), 'down borra lo que puso up');
        $this->assertSame(0, DB::table('marketing_empresa_usuario')->where('company_id', 1)->whereNull('created_by')->count());
        $this->assertNotNull($this->asignacion($manual), 'down no borra asignaciones hechas desde la pantalla');
        $this->assertSame(1, DB::table('marketing_empresa_usuario')->where('company_id', $otraEmpresa)->count(), 'down no toca otras empresas');
    }

    public function test_no_toca_roles_ni_permisos_spatie(): void
    {
        $this->usuario(['super-administrator']);
        $rolesAntes = DB::table('model_has_roles')->count();
        $permsAntes = DB::table('model_has_permissions')->count();
        $rhpAntes = DB::table('role_has_permissions')->count();

        $this->correr('up');

        $this->assertSame($rolesAntes, DB::table('model_has_roles')->count());
        $this->assertSame($permsAntes, DB::table('model_has_permissions')->count());
        $this->assertSame($rhpAntes, DB::table('role_has_permissions')->count());
    }

    public function test_el_selector_no_depende_de_ids_fijos(): void
    {
        $codigo = file_get_contents(base_path(self::ARCHIVO));
        $this->assertDoesNotMatchRegularExpression('/user_id[\'"]?\s*(=>|,|=)\s*\d+/', $codigo, 'no debe fijar user_id numéricos');
        $this->assertDoesNotMatchRegularExpression('/role_id[\'"]?\s*(=>|,|=)\s*\d+/', $codigo, 'no debe fijar role_id numéricos (dev ≠ prod)');
        $this->assertStringContainsString('super-administrator', $codigo);
        $this->assertStringContainsString('DESARROLLADOR', $codigo);
    }
}
