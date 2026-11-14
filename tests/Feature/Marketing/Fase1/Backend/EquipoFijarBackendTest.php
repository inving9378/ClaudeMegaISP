<?php

namespace Tests\Feature\Marketing\Fase1\Backend;

use App\Modules\Addons\Marketing\Console\EquipoFijarCommand;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Marketing\Fase1\Backend\Concerns\EscenarioBackendFase1;
use Tests\Feature\Marketing\MarketingTestCase;

/**
 * `php artisan marketing:equipo-fijar <login_user>... [--aplicar]` (cambio de alcance de Henry, punto C).
 * Los logins son aleatorios de prueba: ningún login real aparece aquí.
 */
class EquipoFijarBackendTest extends MarketingTestCase
{
    use EscenarioBackendFase1;

    private function filasMeganet(): array
    {
        return DB::table('marketing_empresa_usuario')->where('company_id', 1)
            ->orderBy('user_id')->get(['user_id', 'rol', 'activo'])
            ->map(fn ($f) => [(int) $f->user_id, $f->rol, (int) $f->activo])->all();
    }

    /** Lo que debe repartir: `marketing.%` + `module.json → permissions[]`, sin `manage-integrations` (Hub global). */
    private function permisosMarketing(): array
    {
        return Permission::where('guard_name', 'web')
            ->where(fn ($q) => $q->where('name', 'like', 'marketing.%')->orWhereIn('name', EquipoFijarCommand::permisosDelModulo()))
            ->where('name', '!=', 'manage-integrations')
            ->pluck('name')->sort()->values()->all();
    }

    /** `megaisp_test` no corre la instalación del módulo: se crean aquí los permisos que module.json ya declara. */
    private function instalarPermisosDelModulo(): void
    {
        foreach (EquipoFijarCommand::permisosDelModulo() as $nombre) {
            if (! in_array($nombre, self::PERMISOS_FASE1, true)) {
                Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
            }
        }
        Permission::firstOrCreate(['name' => 'manage-integrations', 'guard_name' => 'web']);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_sin_aplicar_solo_muestra_y_no_cambia_nada(): void
    {
        $a = $this->crearUsuario();
        $b = $this->crearUsuario();
        $otro = $this->crearUsuario();
        $this->asignar($otro, 1, 'marketing');
        $antes = $this->filasMeganet();
        $logs = Activity::count();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$a->login_user, $b->login_user]])
            ->expectsOutputToContain('Vista previa: no se cambió nada')
            ->assertExitCode(0);

        $this->assertSame($antes, $this->filasMeganet());
        $this->assertSame($logs, Activity::count());
        $this->assertFalse($a->fresh()->hasPermissionTo('marketing.leads.view'));
    }

    public function test_un_login_que_no_existe_aborta_sin_cambiar_nada(): void
    {
        $a = $this->crearUsuario();
        $antes = $this->filasMeganet();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$a->login_user, 'no_existe_' . uniqid()], '--aplicar' => true])
            ->assertExitCode(1);

        $this->assertSame($antes, $this->filasMeganet());
        $this->assertFalse($a->fresh()->hasPermissionTo('marketing.leads.view'));
    }

    public function test_aplicar_deja_solo_a_esas_personas_como_admin_y_les_da_los_permisos(): void
    {
        $this->instalarPermisosDelModulo();
        $a = $this->crearUsuario(['super-administrator']);
        $b = $this->crearUsuario();
        $sale = $this->crearUsuario();
        $saleTambien = $this->crearUsuario();
        $this->asignar($b, 1, 'ventas', false);
        $this->asignar($sale, 1, 'admin');
        $this->asignar($saleTambien, 1, 'marketing');
        $otra = (int) $this->crearEmpresa()->id;
        $this->asignar($sale, $otra, 'admin');

        $this->artisan('marketing:equipo-fijar', ['logins' => [$a->login_user, $b->login_user], '--aplicar' => true])
            ->assertExitCode(0);

        $esperado = [[(int) $a->id, 'admin', 1], [(int) $b->id, 'admin', 1]];
        usort($esperado, fn ($x, $y) => $x[0] <=> $y[0]);
        $this->assertSame($esperado, $this->filasMeganet());

        // Las filas de otras empresas no se tocan.
        $this->assertDatabaseHas('marketing_empresa_usuario', ['company_id' => $otra, 'user_id' => $sale->id, 'rol' => 'admin']);

        // Permisos marketing.* dados de forma aditiva: los roles que ya tenía se conservan.
        $this->assertNotEmpty($this->permisosMarketing());
        foreach ([$a->fresh(), $b->fresh()] as $u) {
            foreach ($this->permisosMarketing() as $p) {
                $this->assertTrue($u->hasPermissionTo($p), "{$u->login_user} debe tener {$p}");
            }
        }
        $this->assertTrue($a->fresh()->hasRole('super-administrator'));
        $this->assertFalse($sale->fresh()->hasPermissionTo('marketing.leads.view'));

        // Bitácora.
        $log = Activity::where('log_name', 'marketing')->where('event', 'equipo_fijado')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame([$a->login_user, $b->login_user], $log->properties['admins']);
        // `megaisp_test` es compartida (puede haber usuarios de vista previa en Meganet): se exige que estén los nuestros.
        $quitados = array_column($log->properties['quitados'], 'login_user');
        $this->assertContains($sale->login_user, $quitados);
        $this->assertContains($saleTambien->login_user, $quitados);
        $this->assertNotContains($a->login_user, $quitados);
        $this->assertNotContains($b->login_user, $quitados);

        // Y ya entran (regla B), mientras los que salieron quedan fuera.
        $this->actingAs($b->fresh())->getJson('/api/marketing/leads')->assertOk();
        $this->actingAs($sale->fresh())->getJson('/api/marketing/leads')->assertForbidden()->assertJsonPath('error', 'sin_acceso');
    }

    public function test_aplicar_dos_veces_da_el_mismo_resultado(): void
    {
        $a = $this->crearUsuario();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$a->login_user], '--aplicar' => true])->assertExitCode(0);
        $primera = $this->filasMeganet();
        $permisos = $a->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$a->login_user, $a->login_user], '--aplicar' => true])->assertExitCode(0);

        $this->assertSame($primera, $this->filasMeganet());
        $this->assertSame([[(int) $a->id, 'admin', 1]], $this->filasMeganet());
        $this->assertSame($permisos, $a->fresh()->getDirectPermissions()->pluck('name')->sort()->values()->all());
    }

    public function test_da_todas_las_facultades_de_marketing_y_nada_de_fuera(): void
    {
        $this->instalarPermisosDelModulo();
        $a = $this->crearUsuario();

        $this->artisan('marketing:equipo-fijar', ['logins' => [$a->login_user], '--aplicar' => true])->assertExitCode(0);
        $a = $a->fresh();

        foreach ([
            'view-marketing-campaigns', 'create-marketing-campaigns', 'delete-marketing-campaigns',
            'regenerate-marketing-variants', 'manage-marketing-niches', 'test-voices', 'view-video-templates',
            'generate-video-content', 'view-video-content', 'delete-video-content', 'download-video',
            'publish-content', 'manage-publishing-channels', 'manage-publication-queue', 'manage-brand-assets',
            'connect-meta-account', 'manage-video-settings', 'marketing.leads.view', 'marketing.forms.create',
            'marketing.brand_kit.configure', 'marketing.empresas.gestionar',
        ] as $permiso) {
            $this->assertTrue($a->hasPermissionTo($permiso), "debe tener {$permiso}");
        }

        // `manage-integrations` es el Hub de Integraciones de todo el sistema: no se reparte.
        $this->assertFalse($a->hasPermissionTo('manage-integrations'));
        // Nada que no sea de Marketing.
        $fuera = $a->getDirectPermissions()->pluck('name')
            ->reject(fn ($n) => str_starts_with($n, 'marketing.') || in_array($n, EquipoFijarCommand::permisosDelModulo(), true));
        $this->assertSame([], $fuera->values()->all());
    }
}
