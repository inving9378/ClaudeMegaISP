<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing IA 360 · Fase 0 — empresas (marcas) del grupo.
 *
 * Tabla nueva y aditiva. La fila 1 «Meganet» es la empresa principal: todas las filas
 * actuales de las tablas marketing_* tienen company_id = 1 (default de esas columnas).
 * No se agregan FKs desde tablas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_empresas')) {
            Schema::create('marketing_empresas', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 120);
                $table->string('slug', 60)->unique();
                $table->string('color_primario', 7)->nullable();
                $table->string('color_secundario', 7)->nullable();
                $table->boolean('activo')->default(true);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        // Semilla idempotente: la empresa principal con id explícito.
        DB::table('marketing_empresas')->insertOrIgnore([
            'id'               => 1,
            'nombre'           => 'Meganet',
            'slug'             => 'meganet',
            'color_primario'   => '#0066CC',
            'color_secundario' => '#FF6600',
            'activo'           => 1,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_empresas');
    }
};
