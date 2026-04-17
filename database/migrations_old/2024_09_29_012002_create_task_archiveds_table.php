<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('archived')->after('task_color')->default(false);
            $table->string('archived_at')->after('archived')->nullable();
            $table->string('archived_by')->after('archived_at')->nullable();
        });

        $permissions = [
            'task_view_archived_task'
        ];
        foreach ($permissions as $permissionName) {
            // Verifica si el permiso existe con el nombre y el guard_name especificado
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();

            // Si no existe, lo crea
            if (!$permission) {
                try {
                    $permission = Permission::create([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);
                } catch (PermissionAlreadyExists $e) {
                    // Maneja la excepción si ocurre
                    echo "El permiso `{$permissionName}` ya existe para el guard `web`.";
                }
            }

            $permission->assignRole('super-administrator');
            $permission->assignRole('Super Administrador');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('archived');
            $table->dropColumn('archived_by');
            $table->dropColumn('archived_at');
        });

        $permissions = [
            'task_view_archived_task'
        ];
        foreach ($permissions as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();

            if ($permission) {
                $permission->removeRole('super-administrator');
                $permission->removeRole('Super Administrador');

                $permission->delete();
            }
        }
    }
};
