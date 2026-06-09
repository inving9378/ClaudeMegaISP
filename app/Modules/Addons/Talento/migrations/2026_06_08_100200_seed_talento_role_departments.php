<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const MAPPINGS = [
        'TECNICO_INSTALADOR' => 'Planta externa',
        'TECNICO'            => 'Planta externa',
        'TECNICO_PLANTA'     => 'Planta interna',
        'Vendedor'           => 'Ventas',
    ];

    public function up(): void
    {
        foreach (self::MAPPINGS as $roleName => $department) {
            DB::table('talento_role_departments')->upsert(
                ['role_name' => $roleName, 'department' => $department],
                ['role_name'],
                ['department']
            );
        }
    }

    public function down(): void
    {
        DB::table('talento_role_departments')
            ->whereIn('role_name', array_keys(self::MAPPINGS))
            ->delete();
    }
};
