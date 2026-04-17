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
        Schema::create('internet_consumptions', function (Blueprint $table) {
            $table->id();
            $table->string('client_name')->index();
            $table->string('session_id')->index(); // Para identificar reconexiones
            $table->bigInteger('bytes_in')->default(0);  // Subida
            $table->bigInteger('bytes_out')->default(0); // Bajada
            $table->integer('uptime')->default(0);
            $table->string('ip_address')->nullable();
            $table->string('mac_address')->nullable();
            $table->string('nas_ip')->nullable();
            $table->date('date_recorded')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('internet_consumptions');
    }
};
