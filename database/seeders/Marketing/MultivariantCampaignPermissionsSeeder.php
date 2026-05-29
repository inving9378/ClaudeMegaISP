<?php

namespace Database\Seeders\Marketing;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MultivariantCampaignPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view-marketing-campaigns',
            'create-marketing-campaigns',
            'regenerate-marketing-variants',
            'delete-marketing-campaigns',
            'manage-marketing-niches',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'DESARROLLADOR')->where('guard_name', 'web')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }
    }
}
