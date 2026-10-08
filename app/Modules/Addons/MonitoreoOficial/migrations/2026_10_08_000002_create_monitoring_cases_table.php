<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('monitoring_cases')) {
            return; // idempotente
        }
        Schema::create('monitoring_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->nullable()
                ->constrained('monitoring_sources')->nullOnDelete();
            $table->string('name');
            $table->string('authority')->index();
            $table->string('case_number')->nullable();          // expediente
            $table->string('registration_number')->nullable();  // registro
            $table->string('reference_number')->nullable();
            $table->string('class_code')->nullable();
            $table->string('current_legal_status')->nullable();
            $table->string('monitoring_status')->default('activo'); // activo | pausado
            $table->json('metadata')->nullable();
            $table->boolean('is_demo')->default(false)->index();     // marca el expediente de ejemplo de MEGANET
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('next_check_at')->nullable();
            $table->timestamp('last_change_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Identidad del expediente por autoridad → idempotencia del seed y del alta.
            $table->unique(['authority', 'case_number'], 'monitoring_cases_authority_case_unique');
        });
    }

    public function down(): void
    {
        // Tabla PROPIA del módulo: seguro eliminarla en la desinstalación.
        Schema::dropIfExists('monitoring_cases');
    }
};
