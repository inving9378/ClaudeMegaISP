<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vendedor es capacidad transversal de comisión, no rol de puesto.
        // El department solo se deriva de roles de puesto (TECNICO_*, Mostrador).
        DB::table('talento_role_departments')->where('role_name', 'Vendedor')->delete();
    }

    public function down(): void
    {
        DB::table('talento_role_departments')->upsert(
            ['role_name' => 'Vendedor', 'department' => 'Ventas'],
            ['role_name'],
            ['department']
        );
    }
};
