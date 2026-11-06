<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * 12 permisos del CRUD de catálogos del motor de ventas (item #9990808, Fase 1
 * de #9990781): ver/crear/editar/eliminar por catálogo (modalidades, modos de
 * pago, parámetros de reglamento). Asignados a super-administrator + DESARROLLADOR
 * (patrón ya usado en Flotas/MapaRed).
 */
return new class extends Migration
{
    private const PERMISOS = [
        'ventas_talento_catalogo_modalidades_ver'               => 'Ver el catálogo de modalidades de venta',
        'ventas_talento_catalogo_modalidades_crear'             => 'Crear modalidades de venta',
        'ventas_talento_catalogo_modalidades_editar'            => 'Editar modalidades de venta',
        'ventas_talento_catalogo_modalidades_eliminar'          => 'Eliminar modalidades de venta',
        'ventas_talento_catalogo_modos_pago_ver'                => 'Ver el catálogo de modos de pago de ventas',
        'ventas_talento_catalogo_modos_pago_crear'              => 'Crear modos de pago de ventas',
        'ventas_talento_catalogo_modos_pago_editar'             => 'Editar modos de pago de ventas',
        'ventas_talento_catalogo_modos_pago_eliminar'           => 'Eliminar modos de pago de ventas',
        'ventas_talento_catalogo_parametros_reglamento_ver'     => 'Ver el catálogo de parámetros del reglamento de ventas',
        'ventas_talento_catalogo_parametros_reglamento_crear'   => 'Crear parámetros del reglamento de ventas',
        'ventas_talento_catalogo_parametros_reglamento_editar'  => 'Editar parámetros del reglamento de ventas',
        'ventas_talento_catalogo_parametros_reglamento_eliminar' => 'Eliminar parámetros del reglamento de ventas',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $name => $description) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['description' => $description]
            );

            app(PermissionSyncService::class)->syncPermissionToBaseRoles($name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', array_keys(self::PERMISOS))
            ->where('guard_name', 'web')
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
