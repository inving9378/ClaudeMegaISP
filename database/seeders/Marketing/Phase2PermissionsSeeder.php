<?php

namespace Database\Seeders\Marketing;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Phase2PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view-marketing-forms',
            'create-marketing-forms',
            'update-marketing-forms',
            'delete-marketing-forms',
            'manage-marketing-forms',
            'export-leads',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'DESARROLLADOR')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }

        $this->command->info('Phase 2 marketing permissions created and assigned to DESARROLLADOR.');
    }
}
