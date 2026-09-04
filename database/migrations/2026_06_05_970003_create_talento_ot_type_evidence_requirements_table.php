<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // talento_work_order_types vive en el módulo Talento (app/Modules/Addons/Talento/migrations),
        // no en database/migrations: en la reconstrucción aislada de schema:rebuild-dryrun esa
        // migración no corre, así que la tabla puede no existir todavía. En dev/prod reales sí existe.
        if (!Schema::hasTable('talento_work_order_types')) {
            return;
        }

        Schema::create('talento_ot_type_evidence_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_type_id')
                ->constrained('talento_work_order_types')
                ->onDelete('cascade');
            $table->foreignId('evidence_type_id')
                ->constrained('talento_evidence_types')
                ->onDelete('cascade');
            // null = siempre obligatoria; 'cambio_equipo' = solo si hubo cambio
            $table->string('condition', 60)->nullable();
            $table->unique(['ot_type_id', 'evidence_type_id'], 'tot_ev_req_unique');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_ot_type_evidence_requirements');
    }
};
