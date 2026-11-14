<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * F1-2 — los 13 permisos que Leads, Formularios, Brand Kit y el menú ya exigían existen por migración y
 * solo los tienen super-administrator y DESARROLLADOR. Nada aquí los siembra (§2.1).
 */
class PermisosFaltantesBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    private const MIGRACION = 'app/Modules/Addons/Marketing/migrations/2026_11_13_100000_crea_permisos_faltantes_de_marketing.php';

    private function ids(): array
    {
        return Permission::query()->where('guard_name', 'web')->whereIn('name', self::PERMISOS_FASE1)->pluck('id')->all();
    }

    public function test_existen_los_trece_permisos(): void
    {
        $this->assertCount(13, $this->ids());
        $this->assertCount(13, array_unique(self::PERMISOS_FASE1));
        $this->assertFalse(Permission::where('name', 'marketing.templates.manage')->where('guard_name', 'web')->exists(), 'marketing.templates.manage no se crea en la Fase 1');
    }

    public function test_solo_super_administrador_y_desarrollador_los_tienen(): void
    {
        $porRol = DB::table('role_has_permissions as rp')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')
            ->whereIn('rp.permission_id', $this->ids())
            ->selectRaw('r.name, COUNT(*) AS total')
            ->groupBy('r.name')
            ->pluck('total', 'name')
            ->map(fn ($n) => (int) $n)
            ->all();

        ksort($porRol);
        $this->assertSame(['DESARROLLADOR' => 13, 'super-administrator' => 13], $porRol);
        $this->assertSame(0, DB::table('model_has_permissions')->whereIn('permission_id', $this->ids())->count());
    }

    public function test_correr_up_dos_veces_no_duplica_nada(): void
    {
        $migracion = require base_path(self::MIGRACION);
        $migracion->up();
        $migracion->up();

        foreach (self::PERMISOS_FASE1 as $nombre) {
            $this->assertSame(1, Permission::where('name', $nombre)->where('guard_name', 'web')->count(), $nombre);
        }
        $this->assertSame(26, DB::table('role_has_permissions')->whereIn('permission_id', $this->ids())->count());
    }

    public function test_down_no_borra_el_catalogo(): void
    {
        $migracion = require base_path(self::MIGRACION);
        $migracion->down();

        $this->assertCount(13, $this->ids());
    }

    public function test_un_super_administrador_admin_de_meganet_abre_leads_formularios_y_brand_kit_sin_403(): void
    {
        $usuario = $this->crearUsuario(['super-administrator']);
        $this->asignar($usuario, 1, 'admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($usuario)->getJson('/api/marketing/leads')->assertOk();
        $this->actingAs($usuario)->getJson('/api/marketing/lead-forms')->assertOk();
        $this->actingAs($usuario)->getJson('/api/marketing/brand-kit')->assertOk();
    }

    public function test_un_admin_de_meganet_sin_esos_permisos_sigue_recibiendo_403_de_spatie(): void
    {
        $usuario = $this->adminMeganet();

        $this->actingAs($usuario)->getJson('/api/marketing/leads')->assertForbidden();
        $this->actingAs($usuario)->getJson('/api/marketing/brand-kit')->assertForbidden();
    }
}
