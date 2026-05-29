<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private array $permissions = [
        'marketing_view',
        'marketing_campaigns_view',
        'marketing_campaigns_create',
        'marketing_campaigns_edit',
        'marketing_campaigns_approve',
        'marketing_content_generate',
        'marketing_leads_view',
        'marketing_leads_manage',
        'marketing_templates_manage',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
