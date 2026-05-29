<?php

namespace Database\Seeders\Marketing;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class Phase3PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view-conversations',
            'manage-conversations',
            'send-message-whatsapp',
            'handoff-to-human',
            'override-ai-response',
            'configure-ai-agent',
            'view-ai-conversations',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $role = Role::where('name', 'DESARROLLADOR')->first();
        if ($role) {
            $role->givePermissionTo($permissions);
        }

        $this->command->info('Phase 3 permissions created and assigned to DESARROLLADOR.');
    }
}
