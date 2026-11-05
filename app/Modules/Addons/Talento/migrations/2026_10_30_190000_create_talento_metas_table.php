<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UNI-T M2a: metas versionadas (una por concepto). Aditiva; informativa mientras afecta_pago=false. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_metas')) {
            return;
        }
        Schema::create('talento_metas', function (Blueprint $t) {
            $t->id();
            $t->string('concepto', 80);
            $t->string('alcance_tipo', 20)->default('global'); // persona|equipo|puesto|rol|global
            $t->string('alcance_clave', 80)->nullable();
            $t->string('periodo', 20)->default('semanal');     // semanal|mensual
            $t->decimal('valor', 14, 2);
            $t->string('unidad', 30)->nullable();
            $t->boolean('afecta_pago')->default(false);
            $t->unsignedInteger('version')->default(1);
            $t->unsignedBigInteger('reemplaza_id')->nullable();
            $t->date('vigente_desde');
            $t->date('vigente_hasta')->nullable();
            $t->string('origen', 30)->default('manual');       // regla_compensacion|regla_semanal|manual
            $t->unsignedBigInteger('origen_id')->nullable();
            $t->string('motivo', 255)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->unsignedBigInteger('updated_by')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->foreign('reemplaza_id')->references('id')->on('talento_metas')->nullOnDelete();
            $t->index(['concepto', 'alcance_tipo', 'alcance_clave', 'vigente_desde'], 'tl_metas_concepto_alcance_idx');
        });
    }

    public function down(): void
    {
        // Aditiva a propósito: conserva el historial de metas.
    }
};
