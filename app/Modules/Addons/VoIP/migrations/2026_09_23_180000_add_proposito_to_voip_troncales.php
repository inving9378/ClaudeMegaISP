<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MegaVoz Fase 1 — troncales con propósito (item roadmap plan 22-sep-2026).
 *
 * Nullable: una troncal sin propósito específico (la mayoría de las que ya
 * existen) sigue funcionando igual, sin clasificar. Solo las troncales
 * dedicadas a un uso concreto del conmutador MegaVoz declaran una.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voip_troncales', function (Blueprint $table) {
            $table->enum('proposito', [
                'registro_ucm',
                'saliente_cobranza',
                'saliente_avisos',
                'saliente_corte',
            ])->nullable()->after('direccion');
        });
    }

    public function down(): void
    {
        Schema::table('voip_troncales', function (Blueprint $table) {
            $table->dropColumn('proposito');
        });
    }
};
