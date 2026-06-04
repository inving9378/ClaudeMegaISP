<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sub-paso 1: Catálogo de estándares de construcción de caja
        Schema::create('talento_construction_standards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->enum('type', ['fusion_loss', 'power', 'raqueta', 'organization', 'other'])
                  ->default('other');
            $table->string('ideal_value', 80)->nullable();   // e.g. "≤ 0.3 dB", "-18 a -24 dBm"
            $table->string('reference_image_path', 512)->nullable(); // public disk
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        // Sub-paso 2: Inspecciones de caja
        Schema::create('talento_caja_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('caja_ref', 60)->index();         // olt_onus.odb_name o libre
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('inspected_by');      // talento_colaboradores.id
            $table->string('photo_path', 512)->nullable();   // disk:local, antifraude idéntico a 4a
            $table->decimal('captured_lat', 10, 7)->nullable();
            $table->decimal('captured_lng', 10, 7)->nullable();
            $table->boolean('captured_in_app')->default(false);
            $table->decimal('fusion_loss_measured', 5, 2)->nullable(); // dB
            $table->decimal('power_measured', 6, 2)->nullable();       // dBm
            $table->unsignedTinyInteger('aesthetic_score')->nullable(); // 1-10
            $table->json('ia_flags')->nullable();            // flags del análisis IA (asesora)
            $table->enum('overall_result', ['pass', 'fail', 'needs_rework'])->nullable();
            $table->boolean('supervisor_validated')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('project_id')
                  ->references('id')->on('talento_projects')->onDelete('set null');
            $table->foreign('inspected_by')
                  ->references('id')->on('talento_colaboradores')->onDelete('restrict');
            $table->index(['caja_ref', 'created_at'], 'tci_caja_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_caja_inspections');
        Schema::dropIfExists('talento_construction_standards');
    }
};
