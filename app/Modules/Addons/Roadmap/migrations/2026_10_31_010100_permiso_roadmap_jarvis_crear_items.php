<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * #1110 — Permiso propio para que Jarvis (chat de la Torre) cree items tras el clic de Irving.
 * Decisión de Irving 2026-10-30: solo super-administrator + DESARROLLADOR (roles base).
 */
return new class extends Migration
{
    private const PERMISO = 'roadmap.jarvis.crear_items';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::PERMISO, 'guard_name' => 'web'],
            ['description' => 'Crear items del roadmap desde la conversación con Jarvis (tras confirmar la propuesta)']
        );
        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::PERMISO);
    }

    public function down(): void
    {
        Permission::where('name', self::PERMISO)->delete();
    }
};
