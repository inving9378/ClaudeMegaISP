<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitoring_sources')) {
            return; // idempotente
        }
        Schema::create('monitoring_sources', function (Blueprint $table) {
            $table->id();
            $table->string('authority')->index();          // p.ej. IMPI
            $table->string('name');
            $table->string('source_type');                 // siga | vidoc | gaceta | manual_pdf | url
            $table->string('base_url')->nullable();
            $table->string('adapter_class')->nullable();    // FQN del adaptador
            $table->string('status')->default('disponible');
            $table->json('configuration')->nullable();      // no secreta
            $table->timestamp('last_check_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Tabla PROPIA del módulo: seguro eliminarla en la desinstalación.
        Schema::dropIfExists('monitoring_sources');
    }
};
