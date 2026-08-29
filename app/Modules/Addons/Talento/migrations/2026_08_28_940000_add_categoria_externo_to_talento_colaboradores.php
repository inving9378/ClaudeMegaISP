<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #730 (Fase 1.3, Apartado VII). `talento_colaboradores` no tiene
 * sub-clasificación para personal externo (servicios profesionales,
 * programadores, contadores, consultores, capacitadores). Columna nullable,
 * default NULL = sin clasificar (nunca se adivina). Aditiva/idempotente
 * (guard hasColumn), nunca migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_colaboradores', 'categoria_externo')) {
                $table->string('categoria_externo', 50)->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            if (Schema::hasColumn('talento_colaboradores', 'categoria_externo')) {
                $table->dropColumn('categoria_externo');
            }
        });
    }
};
