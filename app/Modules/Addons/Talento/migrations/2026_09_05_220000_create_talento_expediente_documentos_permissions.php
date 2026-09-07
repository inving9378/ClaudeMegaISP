<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Item roadmap #9990358 (Expediente RH — Hijo E1 de #203). Permisos dedicados para el
 * componente reusable de "Documentos" del expediente (Ver/Imprimir hoy; Regenerar/Subir
 * firmado en el Hijo E2). Nombre exacto aprobado por Irving en el brief del padre (q1/q2).
 * Aditiva: crea los permisos y los asigna a los roles base (super-administrator +
 * DESARROLLADOR), mismo patrón que 2026_08_26_220200_create_talento_expediente_view_permission.php.
 * No reemplaza ni toca 'talento.expediente.view' (sigue gateando los endpoints existentes).
 */
return new class extends Migration
{
    private const PERMISOS = [
        'talento.expediente.documentos.ver',
        'talento.expediente.documentos.gestionar',
    ];

    public function up(): void
    {
        $syncService = app(PermissionSyncService::class);

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
            $syncService->syncPermissionToBaseRoles($permiso);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', self::PERMISOS)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
