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
        Schema::create('client_payment_metadata', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_id');
            $table->foreignId('client_id')->constrained();

            // Estado anterior del cliente
            $table->decimal('previous_balance', 10, 2)->default(0);
            $table->date('previous_fecha_pago')->nullable();
            $table->date('previous_fecha_corte')->nullable();
            $table->string('previous_status')->nullable();

            // Información adicional
            $table->text('notes')->nullable();
            $table->json('additional_data')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices para mejor performance
            $table->index(['payment_id', 'client_id']);
        });
        Schema::table('payments', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_payment_metadata');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
