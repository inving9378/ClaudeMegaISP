<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #923. Catálogo propio de puestos: hasta hoy `job_title` era texto libre en
 * `talento_colaboradores` (migración 2026_08_26_220000) y con 0/28 colaboradores llenos, el
 * selector de paquetes de documentos (#870) salía sin opciones. Aditiva/idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('talento_puestos')) {
            Schema::create('talento_puestos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100)->unique();
                $table->boolean('activo')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_puestos');
    }
};
