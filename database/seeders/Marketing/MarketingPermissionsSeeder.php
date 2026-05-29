<?php

namespace Database\Seeders\Marketing;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MarketingPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $resources = [
            'leads', 'pipelines', 'campaigns', 'conversations',
            'content', 'publications', 'marketing-settings',
        ];

        $actions = ['view', 'create', 'update', 'delete', 'manage'];

        $created = collect();

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $perm = Permission::firstOrCreate(
                    ['name' => "{$action}-{$resource}", 'guard_name' => 'web']
                );
                $created->push($perm);
            }
        }

        $specials = [
            'assign-leads',
            'score-leads',
            'send-message-whatsapp',
            'send-message-facebook',
            'send-message-instagram',
            'send-message-email',
            'publish-content',
        ];

        foreach ($specials as $name) {
            $perm = Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web']
            );
            $created->push($perm);
        }

        $role = Role::findByName('DESARROLLADOR', 'web');
        $role->givePermissionTo($created);
    }
}
