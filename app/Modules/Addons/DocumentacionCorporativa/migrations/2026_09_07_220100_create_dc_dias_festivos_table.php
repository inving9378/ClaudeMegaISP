<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DC plazo 180d hábiles — Fase 1 (item roadmap #9990573, decisión Irving #9990550 q2).
 *
 * Catálogo editable de días festivos oficiales de México, para excluir del
 * cómputo de días HÁBILES del plazo maestro de 180 días. Se descartó un
 * calendario IFT/SAT (sobre-ingeniería para Fase 1) y "sin festivos" (no
 * refleja el cálculo legal real). La lógica de cómputo vive en una fase
 * posterior; aquí solo se crea el catálogo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_dias_festivos')) {
            Schema::create('dc_dias_festivos', function (Blueprint $table) {
                $table->id();
                $table->date('fecha')->unique();
                $table->string('descripcion')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_dias_festivos');
    }
};
