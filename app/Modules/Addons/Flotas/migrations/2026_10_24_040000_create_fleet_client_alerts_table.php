<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fleet_client_alerts')) {
            return;
        }

        Schema::create('fleet_client_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id')->index();
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->unsignedBigInteger('client_id')->index();
            $table->string('tipo', 30);
            $table->string('subject_key', 100)->default('');   // documento/mantenimiento/geocerca concreto
            $table->string('mensaje', 500);
            $table->json('entregas')->nullable();              // [{channel,status,destination,error}]
            $table->timestamp('visto_at')->nullable();
            $table->timestamp('created_at')->nullable();

            // Anti-spam: consulta por regla+vehículo+asunto dentro de la ventana de enfriamiento.
            $table->index(['rule_id', 'vehicle_id', 'subject_key', 'created_at'], 'fleet_client_alerts_dedupe');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_client_alerts');
    }
};
