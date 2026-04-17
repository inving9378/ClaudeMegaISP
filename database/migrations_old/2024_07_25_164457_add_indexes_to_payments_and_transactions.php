<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Asegurarse de que 'id' esté indexado
            $table->index('id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Agregar índices para 'payment_id', 'client_id' y 'date'
            $table->index('payment_id');
            $table->index('client_id');
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Eliminar los índices en el rollback
            $table->dropIndex(['id']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Eliminar los índices en el rollback
            $table->dropIndex(['payment_id']);
            $table->dropIndex(['client_id']);
            $table->dropIndex(['date']);
        });
    }
};
