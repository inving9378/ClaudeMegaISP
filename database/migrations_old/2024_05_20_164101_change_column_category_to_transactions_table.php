<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Agregar columna temporal con los nuevos valores enum
            $table->enum('category_temp', ['Servicio', 'Descuento', 'Pago', 'Reembolso', 'Correccion'])->nullable();
        });

        // Copiar datos de la columna original a la columna temporal
        DB::statement('UPDATE transactions SET category_temp = category');

        Schema::table('transactions', function (Blueprint $table) {
            // Eliminar la columna original
            $table->dropColumn('category');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Renombrar la columna temporal a la original
            $table->renameColumn('category_temp', 'category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        
    }
};
