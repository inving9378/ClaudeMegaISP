<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $permissions = [
            'talento.dashboard.view',
            'talento.escalafon.view',
            'talento.escalafon.manage',
            'talento.embajadores.view',
        ];

        $guardName = 'web';
        $now       = now();

        foreach ($permissions as $name) {
            DB::table('permissions')->insertOrIgnore([
                'name'       => $name,
                'guard_name' => $guardName,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Assign to super-administrator and DESARROLLADOR (non-destructive)
        $roles = DB::table('roles')->whereIn('name', ['super-administrator', 'DESARROLLADOR'])->get(['id']);
        $permIds = DB::table('permissions')->whereIn('name', $permissions)->pluck('id');

        foreach ($roles as $role) {
            foreach ($permIds as $permId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permId,
                    'role_id'       => $role->id,
                ]);
            }
        }
    }

    public function down(): void {}
};
