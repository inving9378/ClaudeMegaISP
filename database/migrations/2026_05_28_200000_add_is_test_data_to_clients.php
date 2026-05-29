<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('is_test_data')->default(false)->after('migrated_from_splynt')
                  ->comment('Marca registros generados por simulación — nunca mostrar en producción real');
        });

        // Índice para limpieza rápida
        Schema::table('clients', function (Blueprint $table) {
            $table->index('is_test_data', 'idx_is_test_data');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('idx_is_test_data');
            $table->dropColumn('is_test_data');
        });
    }
};
