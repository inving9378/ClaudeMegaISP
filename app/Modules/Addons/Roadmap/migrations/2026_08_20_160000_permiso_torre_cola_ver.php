<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * #890 (Torre fase 6) — permiso dedicado para la cola ejecutable visible: orden real +
 * excluidos con motivo. Es información interna del despacho del circuito (no del negocio), así
 * que NO se reparte con `.view` a todos los roles base: el nombre termina en `.ver` (español) a
 * propósito para que `PermissionSyncService::isViewPermission()` (que sólo reconoce el sufijo
 * inglés `.view`) NO lo auto-otorgue a todo el mundo — se queda sólo en super-administrator y
 * DESARROLLADOR, igual que `torre.config.edit`.
 *
 * ADITIVA: `firstOrCreate` + `syncPermissionToBaseRoles` (mismo camino que
 * `ModuleLifecycleService::registerPermissions`).
 */
return new class extends Migration
{
    private const NOMBRE = 'torre.cola.ver';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::NOMBRE, 'guard_name' => 'web'],
            ['description' => 'Ver la cola ejecutable de la Torre de control (orden real + excluidos)']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::NOMBRE);
    }

    public function down(): void
    {
        Permission::where('name', self::NOMBRE)->delete();
    }
};
