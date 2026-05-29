<?php

namespace Database\Seeders\Marketing;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Phase4PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view-video-templates',
            'generate-video-content',
            'view-video-content',
            'delete-video-content',
            'manage-video-settings',
            'download-video',
            'manage-brand-assets',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'DESARROLLADOR')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }

        $this->command->info('Phase 4 permissions created and assigned to DESARROLLADOR.');
    }
}
