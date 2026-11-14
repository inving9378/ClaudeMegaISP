<?php

namespace Tests\Feature\Marketing\Fase1\Backend\Concerns;

use App\Models\Marketing\Campaign;
use App\Models\Marketing\Lead;
use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Datos de prueba del agente backend (Fase 1). Todo vive dentro de la transacción de MarketingTestCase.
 *
 * Regla §2.1: NO se siembran los 13 permisos de la migración 2026_11_13_100000 (si faltan, la prueba debe
 * fallar). `darPermisosDeModulo()` solo crea permisos que el módulo YA declara en module.json desde antes
 * de la Fase 1 (los instala ModuleLifecycleService en dev/prod, pero `megaisp_test` no corre esa instalación).
 */
trait EscenarioBackendFase1
{
    /** Los 13 de la migración de la Fase 1: nunca se crean aquí. */
    protected const PERMISOS_FASE1 = [
        'marketing.leads.view', 'marketing.leads.create', 'marketing.leads.update', 'marketing.leads.delete',
        'marketing.leads.assign', 'marketing.leads.score', 'marketing.forms.view', 'marketing.forms.create',
        'marketing.forms.update', 'marketing.forms.delete', 'marketing.brand_kit.configure',
        'marketing.conversations.view', 'marketing.campaigns.view',
    ];

    protected function contexto(): EmpresaActiva
    {
        return app(EmpresaActiva::class);
    }

    protected function crearUsuario(array $roles = [], array $atributos = []): User
    {
        $usuario = User::factory()->create(array_merge([
            'login_user' => 'mkt_f1b_' . Str::lower(Str::random(10)),
            'estado'     => 'activo',
            'is_system'  => 0,
        ], $atributos));

        foreach ($roles as $rol) {
            $usuario->assignRole($rol);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $usuario->fresh();
    }

    protected function crearEmpresa(): Empresa
    {
        $sufijo = Str::lower(Str::random(6));

        return Empresa::create([
            'nombre'         => 'Empresa F1B ' . $sufijo,
            'slug'           => 'empresa-f1b-' . $sufijo,
            'color_primario' => '#1F3A93',
            'activo'         => true,
        ]);
    }

    protected function asignar(User $usuario, int $empresaId, string $rol, bool $activo = true): void
    {
        DB::table('marketing_empresa_usuario')->updateOrInsert(
            ['company_id' => $empresaId, 'user_id' => $usuario->id],
            ['rol' => $rol, 'activo' => $activo ? 1 : 0, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** Persona que SÍ entra (regla B de Henry): usuario activo, sin roles de sistema, admin de Meganet. */
    protected function adminMeganet(array $permisos = []): User
    {
        $usuario = $this->crearUsuario();
        $this->asignar($usuario, Empresa::PRINCIPAL_ID, 'admin');
        if ($permisos !== []) {
            $usuario->givePermissionTo($permisos);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $usuario->fresh();
    }

    /** Permisos que el módulo declara desde antes de la Fase 1 y que `megaisp_test` no tiene instalados. */
    protected function darPermisosDeModulo(User $usuario, array $nombres): void
    {
        foreach ($nombres as $nombre) {
            $this->assertNotContains($nombre, self::PERMISOS_FASE1, "{$nombre} debe venir de la migración, no de la prueba.");
            Permission::firstOrCreate(['name' => $nombre, 'guard_name' => 'web']);
        }
        $usuario->givePermissionTo($nombres);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function crearLead(int $empresaId, string $estado, string $capturado, array $extra = []): Lead
    {
        $this->contexto()->limpiar();

        return Lead::create(array_merge([
            'company_id'  => $empresaId,
            'full_name'   => 'Lead F1B ' . Str::random(5),
            'status'      => $estado,
            'captured_at' => $capturado,
        ], $extra));
    }

    protected function crearCampana(int $empresaId, array $atributos): Campaign
    {
        $this->contexto()->limpiar();

        return Campaign::create(array_merge([
            'company_id' => $empresaId,
            'name'       => 'Campaña F1B ' . Str::random(5),
            'slug'       => 'campana-f1b-' . Str::lower(Str::random(10)),
            'type'       => 'meta_ads',
            'budget'     => '0.00',
            'spent'      => '0.00',
            'currency'   => 'MXN',
            'status'     => 'active',
            'start_date' => '2026-10-01',
            'end_date'   => null,
        ], $atributos));
    }

    protected function atribuir(int $leadId, int $campanaId): void
    {
        DB::table('marketing_attributions')->insert([
            'lead_id'     => $leadId,
            'campaign_id' => $campanaId,
            'touchpoint'  => 'first_touch',
            'weight'      => 1,
            'occurred_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    /** Layout mínimo para renderizar vistas del módulo sin el bundle compilado del worktree. */
    protected function usarLayoutMinimo(): void
    {
        $dir = sys_get_temp_dir() . '/mkt-fase1-backend-layout';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/master.blade.php', "<html><body>@yield('content')</body></html>");

        app('view')->getFinder()->prependNamespace('core-layout', $dir);
    }

    protected function cuerpoRetirada(): array
    {
        return [
            'ok'      => false,
            'error'   => 'retirada',
            'mensaje' => 'Esta función se retiró porque dependía de tablas que ya no existen. Las campañas y plantillas se rehacen en la Fase 2.',
        ];
    }
}
