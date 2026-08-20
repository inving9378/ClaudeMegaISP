<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * #854 — permiso para subir/reemplazar el avatar de una terminal (o del supervisor) en la
 * pestaña Terminales de la Torre de Control. Superficie de ataque (subida de archivos) → acotada
 * a super-administrator + DESARROLLADOR, igual que `torre.config.edit` (mismo patrón, sin `.view`
 * en el nombre => `syncPermissionToBaseRoles` NO lo reparte al resto de roles).
 *
 * ADITIVA: `firstOrCreate` + `syncPermissionToBaseRoles`. NUNCA `syncPermissions` (destructivo).
 */
return new class extends Migration
{
    private const PERMISO = 'torre.terminales.editar_avatar';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISO, 'guard_name' => 'web'],
            ['description' => 'Subir/reemplazar el avatar de una terminal o del supervisor en Terminales']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->where('guard_name', 'web')->delete();
    }
};
