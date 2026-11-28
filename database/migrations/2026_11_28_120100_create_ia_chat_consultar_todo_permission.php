<?php

use App\Modules\Core\Security\Services\PermissionSyncService;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * JARVIS puede consultar cualquier información del sistema (todas las tablas, en solo lectura, sin
 * contraseñas ni llaves) y cruzarla con los Excel que le mandan. Es más de lo que ve cada pantalla,
 * así que va con permiso propio: sin sufijo `.view` para que PermissionSyncService solo lo dé a
 * super-administrator y DESARROLLADOR; a otros roles se les asigna a mano desde Roles.
 *
 * ADITIVA: firstOrCreate + syncPermissionToBaseRoles (mismo camino que usar-ia-chat, #636).
 */
return new class extends Migration
{
    private const NOMBRE = 'ia-chat-consultar-todo';

    public function up(): void
    {
        Permission::firstOrCreate(
            ['name' => self::NOMBRE, 'guard_name' => 'web'],
            ['description' => 'JARVIS: consultar cualquier información del sistema y cruzarla con archivos']
        );

        app(PermissionSyncService::class)->syncPermissionToBaseRoles(self::NOMBRE);
    }

    public function down(): void
    {
        Permission::where('name', self::NOMBRE)->delete();
    }
};
