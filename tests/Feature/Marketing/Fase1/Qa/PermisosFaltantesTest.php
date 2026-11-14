<?php

namespace Tests\Feature\Marketing\Fase1\Qa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Marketing\Fase1\Qa\Concerns\EscenarioFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * QA · Marketing Fase 1 — [F1-2] permisos que faltaban (contrato §2.7, criterios 6 y 12).
 *
 * Henry: SOLO los 13 permisos y SOLO a super-administrator y DESARROLLADOR. Se prueba:
 *  - la migración crea exactamente los 13 (ni uno más: `marketing.templates.manage` NO);
 *  - partiendo de cero (se borran los 13 dentro de la transacción y se corre up()), el ÚNICO reparto es a
 *    esos dos roles: ningún otro rol, ningún usuario directo — también con PERMISSION_SYNC_AUTO_GRANT_VIEW;
 *  - up() dos veces no duplica; no usa syncRoles/syncPermissions; down() no borra nada;
 *  - un super-administrador SIN sembrar nada abre Leads, Formularios y Brand Kit (antes 403 a todos);
 *  - el estado actual de megaisp_test cumple lo mismo (salvo los usuarios de vista previa mkt_prev_*).
 */
class PermisosFaltantesTest extends MarketingTestCase
{
    use EscenarioFase1;

    private const MIGRACION = 'app/Modules/Addons/Marketing/migrations/2026_11_13_100000_crea_permisos_faltantes_de_marketing.php';
    private const ROLES_BASE = ['DESARROLLADOR', 'super-administrator'];

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        $this->contexto()->limpiar();
        parent::tearDown();
    }

    private function migracion(): object
    {
        $ruta = base_path(self::MIGRACION);
        $this->assertFileExists($ruta);

        return require $ruta;
    }

    private function idsDeLos13(): array
    {
        return Permission::query()->where('guard_name', 'web')->whereIn('name', self::$permisosDeLaMigracion)->pluck('id')->all();
    }

    /** @return array<string, array<int,string>> permiso => roles que lo tienen */
    private function rolesPorPermiso(): array
    {
        $out = [];
        $filas = DB::table('role_has_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
            ->whereIn('p.name', self::$permisosDeLaMigracion)->where('p.guard_name', 'web')
            ->orderBy('r.name')->get(['p.name as permiso', 'r.name as rol']);
        foreach ($filas as $f) {
            $out[$f->permiso][] = $f->rol;
        }
        ksort($out);

        return $out;
    }

    private function borrarLos13(): void
    {
        $ids = $this->idsDeLos13();
        DB::table('role_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ── catálogo ─────────────────────────────────────────────────────────────

    public function test_la_migracion_esta_registrada_en_megaisp_test_y_existen_los_13(): void
    {
        $this->assertTrue(
            DB::table('migrations')->where('migration', '2026_11_13_100000_crea_permisos_faltantes_de_marketing')->exists(),
            'La migración de F1-2 no está aplicada en megaisp_test'
        );
        $existentes = Permission::query()->where('guard_name', 'web')->whereIn('name', self::$permisosDeLaMigracion)->pluck('name')->all();
        sort($existentes);
        $esperados = self::$permisosDeLaMigracion;
        sort($esperados);
        $this->assertSame($esperados, $existentes);
    }

    public function test_desde_cero_crea_exactamente_los_13_y_nada_mas(): void
    {
        $this->borrarLos13();
        $antes = Permission::query()->pluck('name')->all();

        $this->migracion()->up();

        $nuevos = array_values(array_diff(Permission::query()->pluck('name')->all(), $antes));
        sort($nuevos);
        $esperados = self::$permisosDeLaMigracion;
        sort($esperados);
        $this->assertSame($esperados, $nuevos, 'La migración debe crear exactamente los 13 de §2.7');
        $this->assertNotContains('marketing.templates.manage', $nuevos, '§2.7: marketing.templates.manage NO se crea');
    }

    public function test_desde_cero_solo_super_administrador_y_desarrollador_los_reciben(): void
    {
        $this->borrarLos13();
        // Con el auto-reparto de *.view encendido: si la migración usara PermissionSyncService, aquí lo
        // repartiría a todos los roles. Henry autorizó SOLO los dos roles base.
        config(['permission_sync.auto_grant_view_base_roles' => true]);

        $roles = Role::query()->where('guard_name', 'web')->pluck('name')->all();
        $this->assertGreaterThan(2, count($roles), 'Debe haber otros roles para que la prueba signifique algo');

        $this->migracion()->up();

        $porPermiso = $this->rolesPorPermiso();
        $this->assertCount(13, $porPermiso, 'Cada uno de los 13 debe quedar en algún rol');
        foreach ($porPermiso as $permiso => $tienen) {
            sort($tienen);
            $this->assertSame(self::ROLES_BASE, $tienen, "{$permiso}: solo super-administrator y DESARROLLADOR");
        }

        $directos = DB::table('model_has_permissions')->whereIn('permission_id', $this->idsDeLos13())->count();
        $this->assertSame(0, $directos, 'La migración no da ninguno de los 13 directo a un usuario');
    }

    public function test_up_dos_veces_no_duplica(): void
    {
        $m = $this->migracion();
        $m->up();
        $permisos = Permission::query()->whereIn('name', self::$permisosDeLaMigracion)->count();
        $repartos = DB::table('role_has_permissions')->whereIn('permission_id', $this->idsDeLos13())->count();

        $m->up();

        $this->assertSame(13, $permisos);
        $this->assertSame($permisos, Permission::query()->whereIn('name', self::$permisosDeLaMigracion)->count());
        $this->assertSame($repartos, DB::table('role_has_permissions')->whereIn('permission_id', $this->idsDeLos13())->count());
    }

    public function test_up_no_quita_nada_a_nadie_es_aditiva(): void
    {
        $antesRoles = DB::table('role_has_permissions')->count();
        $antesModelos = DB::table('model_has_roles')->count();
        $antesDirectos = DB::table('model_has_permissions')->count();

        $this->migracion()->up();

        $this->assertGreaterThanOrEqual($antesRoles, DB::table('role_has_permissions')->count());
        $this->assertSame($antesModelos, DB::table('model_has_roles')->count(), 'No toca roles de usuarios');
        $this->assertSame($antesDirectos, DB::table('model_has_permissions')->count(), 'No toca permisos directos');
    }

    public function test_el_codigo_de_la_migracion_es_aditivo_y_down_no_borra(): void
    {
        $src = (string) file_get_contents(base_path(self::MIGRACION));
        $codigo = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $src);
        foreach (['syncRoles', 'syncPermissions', 'revokePermissionTo', 'removeRole', '->delete(', 'marketing_empresas', 'syncPermissionToBaseRoles'] as $prohibido) {
            $this->assertStringNotContainsString($prohibido, $codigo, "La migración no debe usar {$prohibido}");
        }

        $antes = Permission::query()->whereIn('name', self::$permisosDeLaMigracion)->count();
        $this->migracion()->down();
        $this->assertSame($antes, Permission::query()->whereIn('name', self::$permisosDeLaMigracion)->count(), 'down() no borra el catálogo');
    }

    public function test_estado_actual_de_megaisp_test_nadie_mas_los_tiene(): void
    {
        foreach ($this->rolesPorPermiso() as $permiso => $tienen) {
            sort($tienen);
            $this->assertSame(self::ROLES_BASE, $tienen, "{$permiso} en megaisp_test");
        }
        $directos = DB::table('model_has_permissions as mp')
            ->join('users as u', 'u.id', '=', 'mp.model_id')
            ->whereIn('mp.permission_id', $this->idsDeLos13())
            ->where('u.login_user', 'not like', 'mkt\_prev\_%') // usuarios de vista previa §2.10 (se borran al cierre)
            ->pluck('u.login_user')->all();
        $this->assertSame([], $directos, 'Usuarios con alguno de los 13 directo (fuera de la vista previa)');
    }

    // ── efecto: super-administrador sin sembrar nada ─────────────────────────

    public function test_super_administrador_sin_sembrar_nada_abre_leads_formularios_y_brand_kit(): void
    {
        $this->montarEscenarioFase1();
        // adminA = super-administrator + admin de la empresa A; los 13 le llegan SOLO por el rol (migración).
        $this->assertSame([], array_values(array_intersect(
            $this->adminA->getDirectPermissions()->pluck('name')->all(),
            self::$permisosDeLaMigracion
        )), 'La prueba no debe dárselos directo');

        $this->como($this->adminA, $this->empresaA->id)->getJson('/api/marketing/leads')->assertOk();
        $this->como($this->adminA, $this->empresaA->id)->getJson('/api/marketing/lead-forms')->assertOk();
        $this->como($this->adminA, $this->empresaA->id)->getJson('/api/marketing/brand-kit')->assertOk();
    }

    public function test_desarrollador_sin_sembrar_nada_abre_leads_formularios_y_brand_kit(): void
    {
        $this->montarEscenarioFase1();
        $dev = $this->crearUsuario('qa1_dev', [$this->empresaA->id => 'admin'], ['DESARROLLADOR'], []);

        $this->como($dev, $this->empresaA->id)->getJson('/api/marketing/leads')->assertOk();
        $this->como($dev, $this->empresaA->id)->getJson('/api/marketing/lead-forms')->assertOk();
        $this->como($dev, $this->empresaA->id)->getJson('/api/marketing/brand-kit')->assertOk();
    }

    public function test_otro_rol_de_sistema_sin_permiso_directo_sigue_con_403_de_spatie(): void
    {
        $this->montarEscenarioFase1();
        // Rol «Administrador» (no es base de F1-2): con empresa y rol admin de Marketing, pero sin el permiso.
        $otro = $this->crearUsuario('qa1_administrador', [$this->empresaA->id => 'admin'], ['Administrador'], []);
        if ($otro->can('marketing.leads.view')) {
            $this->fail('El rol Administrador recibió marketing.leads.view: Henry autorizó SOLO super-administrator y DESARROLLADOR');
        }
        $this->como($otro, $this->empresaA->id)->getJson('/api/marketing/leads')->assertForbidden();
    }

    public function test_los_permisos_que_siembra_qa_nunca_son_de_los_13(): void
    {
        $this->assertSame([], array_values(array_intersect(self::$permisosDeSeeder, self::$permisosDeLaMigracion)));
    }

    public function test_module_json_declara_los_13_sin_subir_version(): void
    {
        $json = json_decode((string) file_get_contents(base_path('app/Modules/Addons/Marketing/module.json')), true);
        $declarados = array_map(fn ($p) => is_array($p) ? ($p['name'] ?? $p['nombre'] ?? null) : $p, $json['permissions'] ?? []);
        foreach (self::$permisosDeLaMigracion as $p) {
            $this->assertContains($p, $declarados, "module.json → permissions[] debe declarar {$p} (§2.8)");
        }
        $base = (string) shell_exec('cd ' . escapeshellarg(base_path()) . ' && git show HEAD:app/Modules/Addons/Marketing/module.json 2>/dev/null');
        $versionBase = json_decode($base, true)['version'] ?? null;
        $this->assertNotNull($versionBase);
        $this->assertSame($versionBase, $json['version'] ?? null, 'No se sube version a mitad del plan');
    }
}
