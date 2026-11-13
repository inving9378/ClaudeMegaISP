<?php

namespace Tests\Feature\Marketing\Fase0\Backend\Concerns;

use App\Models\Marketing\Lead;
use App\Models\User;
use App\Modules\Addons\Marketing\Models\Empresa;
use App\Modules\Addons\Marketing\Support\EmpresaActiva;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Datos de prueba del agente backend (Fase 0). Todo vive dentro de la transacción de
 * MarketingTestCase y se deshace al terminar cada prueba.
 */
trait EscenarioBackend
{
    protected function prepararPermisos(): void
    {
        Permission::firstOrCreate(['name' => 'marketing.empresas.gestionar', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function contexto(): EmpresaActiva
    {
        return app(EmpresaActiva::class);
    }

    /**
     * @param  array<int, string>  $roles  roles Spatie (por nombre)
     */
    protected function crearUsuario(array $roles = ['super-administrator'], array $atributos = []): User
    {
        $usuario = User::factory()->create(array_merge([
            'login_user' => 'mkt_b_' . Str::lower(Str::random(10)),
            'estado'     => 'activo',
            'is_system'  => 0,
        ], $atributos));

        foreach ($roles as $rol) {
            $usuario->assignRole($rol);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $usuario->fresh();
    }

    protected function crearEmpresa(array $atributos = []): Empresa
    {
        $sufijo = Str::lower(Str::random(6));

        return Empresa::create(array_merge([
            'nombre'         => 'Empresa B ' . $sufijo,
            'slug'           => 'empresa-b-' . $sufijo,
            'color_primario' => '#1F3A93',
            'activo'         => true,
        ], $atributos));
    }

    protected function asignar(User $usuario, int $empresaId, string $rol, bool $activo = true, ?int $creadoPor = 0): void
    {
        DB::table('marketing_empresa_usuario')->updateOrInsert(
            ['company_id' => $empresaId, 'user_id' => $usuario->id],
            [
                'rol'        => $rol,
                'activo'     => $activo ? 1 : 0,
                'created_by' => $creadoPor === 0 ? $usuario->id : $creadoPor,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    protected function crearLead(int $companyId, string $nombre = 'Lead de prueba'): Lead
    {
        // Sin contexto el trait respeta el company_id explícito.
        $this->contexto()->limpiar();

        return Lead::create([
            'company_id'  => $companyId,
            'full_name'   => $nombre,
            'status'      => 'new',
            'captured_at' => now(),
        ]);
    }

    protected function empresaPrincipal(): Empresa
    {
        return Empresa::findOrFail(Empresa::PRINCIPAL_ID);
    }

    /**
     * Sustituye el layout core-layout::master por uno mínimo para renderizar las vistas del módulo
     * sin depender del bundle compilado (public/js/app.js) del worktree.
     */
    protected function usarLayoutMinimo(): void
    {
        $dir = sys_get_temp_dir() . '/mkt-fase0-backend-layout';
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($dir . '/master.blade.php', "<html><body>@yield('content')</body></html>");

        app('view')->getFinder()->prependNamespace('core-layout', $dir);
    }
}
