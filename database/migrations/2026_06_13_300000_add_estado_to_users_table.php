<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('estado', ['activo', 'bloqueado', 'inactivo'])
                  ->default('activo')
                  ->after('active');
        });

        // Migrar datos: active=1 → activo, active=0 → inactivo
        DB::statement("UPDATE users SET estado = CASE WHEN active = 1 THEN 'activo' ELSE 'inactivo' END");
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
