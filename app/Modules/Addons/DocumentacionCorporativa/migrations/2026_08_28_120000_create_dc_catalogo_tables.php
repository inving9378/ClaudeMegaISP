<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 0, catálogo.
 *
 * Aditiva. Tres tablas: la empresa emisora del expediente, sus 14 apartados y
 * los conceptos de cada apartado. Todo lleva `empresa_id` porque el módulo nace
 * multi-empresa (es pieza candidata para Medussa: el mismo problema lo tiene
 * cualquier ISP que arriende MegaISP).
 *
 * Por qué `dc_empresas` y no `company_information`: esa tabla es un singleton de
 * configuración (1 fila, sin `activo`, sin `regimen_fiscal`, sin
 * `fecha_constitucion`) y ningún consumidor la trata como multi-tenant. Se usa
 * como FUENTE DE SIEMBRA de razón social y RFC, no como tabla base.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_empresas')) {
            Schema::create('dc_empresas', function (Blueprint $table) {
                $table->id();
                $table->string('razon_social');
                $table->string('nombre_comercial')->nullable();
                $table->string('rfc', 13)->nullable();
                $table->string('regimen_fiscal')->nullable();
                $table->date('fecha_constitucion')->nullable();
                $table->text('domicilio_fiscal')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index('activo');
            });
        }

        if (! Schema::hasTable('dc_apartados')) {
            Schema::create('dc_apartados', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                // Numeral romano I..XIV tal como lo numera la solicitud.
                $table->string('clave', 8);
                $table->string('nombre');
                $table->text('descripcion')->nullable();
                $table->string('icono', 40)->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['empresa_id', 'clave']);
                $table->index(['empresa_id', 'orden']);
            });
        }

        if (! Schema::hasTable('dc_conceptos')) {
            Schema::create('dc_conceptos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->foreignId('apartado_id')->constrained('dc_apartados')->cascadeOnDelete();
                $table->string('nombre');
                $table->string('slug', 120);
                $table->enum('tipo_resolvedor', [
                    'sistema', 'documento', 'plantilla', 'grafica', 'inventario', 'pendiente',
                ])->default('pendiente');
                // Parámetros del resolvedor (fuente, filtros, resolvedores secundarios).
                $table->json('config')->nullable();
                // Plantilla de `document_templates` cuando el concepto se genera.
                $table->unsignedBigInteger('plantilla_id')->nullable();
                $table->boolean('obligatorio')->default(false);
                $table->string('rol_responsable', 80)->nullable();
                $table->enum('periodicidad_revision', [
                    'mensual', 'trimestral', 'semestral', 'anual', 'evento',
                ])->nullable();
                $table->string('base_legal')->nullable();
                $table->enum('confidencialidad', ['interna', 'restringida', 'critica'])->default('interna');
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['empresa_id', 'slug']);
                $table->index(['apartado_id', 'orden']);
                $table->index(['empresa_id', 'activo', 'obligatorio']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_conceptos');
        Schema::dropIfExists('dc_apartados');
        Schema::dropIfExists('dc_empresas');
    }
};
