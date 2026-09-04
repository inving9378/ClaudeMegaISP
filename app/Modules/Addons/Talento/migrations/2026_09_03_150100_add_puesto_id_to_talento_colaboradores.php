<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #923. `job_title` (texto libre) NO se borra ni se toca en este item —
 * se conserva para no romper a sus consumidores actuales (docs de RH, etc.). `puesto_id`
 * es el campo nuevo que apunta al catálogo `talento_puestos`; la ficha (Fase 3) lo llena
 * seleccionando del catálogo y sincroniza `job_title` como espejo legible. Aditiva/idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            if (!Schema::hasColumn('talento_colaboradores', 'puesto_id')) {
                $table->unsignedBigInteger('puesto_id')->nullable()->after('job_title');
                $table->foreign('puesto_id')->references('id')->on('talento_puestos')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_colaboradores', function (Blueprint $table) {
            if (Schema::hasColumn('talento_colaboradores', 'puesto_id')) {
                $table->dropForeign(['puesto_id']);
                $table->dropColumn('puesto_id');
            }
        });
    }
};
