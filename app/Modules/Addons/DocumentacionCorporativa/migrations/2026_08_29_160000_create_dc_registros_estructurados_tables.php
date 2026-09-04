<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 2c (item roadmap #736). Cinco tablas que el
 * catálogo (Fase 0, `CatalogoSeeder`) ya referencia por nombre en
 * `config.tabla` y que `InventarioResolver::TABLAS_PERMITIDAS` ya trae en su
 * allowlist desde antes: hasta ahora `InventarioResolver::disponible()` las
 * declaraba no-disponibles porque no existían (`Schema::hasTable()` en falso)
 * y el concepto caía a "sin fuente configurada". Esta migración no toca el
 * resolvedor ni el catálogo — solo crea las tablas con las columnas que sus
 * filtros ya esperan (`tipo` en `dc_actas`/`dc_contratos`).
 *
 * Apartado I (societario): `dc_accionistas`, `dc_capital_variaciones`,
 * `dc_actas` (asambleas y consejo, distinguidas por `tipo`), `dc_poderes`.
 * Apartado VI (contratos): `dc_contratos` (8 tipos de relación comercial).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_accionistas')) {
            Schema::create('dc_accionistas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->string('nombre_razon_social');
                $table->decimal('porcentaje', 5, 2);
                $table->unsignedInteger('num_acciones')->nullable();
                $table->string('tipo_serie')->nullable();
                $table->date('fecha_alta');
                $table->date('fecha_baja')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('empresa_id');
            });
        }

        if (! Schema::hasTable('dc_capital_variaciones')) {
            Schema::create('dc_capital_variaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->date('fecha');
                $table->enum('tipo', ['aumento', 'disminucion']);
                $table->decimal('monto', 14, 2);
                $table->decimal('capital_resultante', 14, 2);
                $table->text('nota')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index('empresa_id');
            });
        }

        if (! Schema::hasTable('dc_actas')) {
            Schema::create('dc_actas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->enum('tipo', ['asamblea_ordinaria', 'asamblea_extraordinaria', 'consejo']);
                $table->date('fecha');
                $table->string('folio')->nullable();
                $table->text('resumen')->nullable();
                $table->boolean('protocolizada')->default(false);
                $table->foreignId('documento_id')->nullable()->constrained('dc_documentos')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'tipo']);
            });
        }

        if (! Schema::hasTable('dc_poderes')) {
            Schema::create('dc_poderes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->string('apoderado');
                $table->string('tipo_poder');
                $table->text('alcance')->nullable();
                $table->date('fecha_otorgamiento');
                $table->date('vigencia_fin')->nullable();
                $table->boolean('revocado')->default(false);
                $table->foreignId('documento_id')->nullable()->constrained('dc_documentos')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index('empresa_id');
            });
        }

        if (! Schema::hasTable('dc_contratos')) {
            Schema::create('dc_contratos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->enum('tipo', [
                    'cliente', 'proveedor', 'convenio_comercial', 'arrendamiento',
                    'servicios', 'mantenimiento', 'suministro', 'interconexion',
                ]);
                $table->string('contraparte');
                $table->text('objeto')->nullable();
                $table->date('fecha_inicio');
                $table->date('fecha_fin')->nullable();
                $table->decimal('monto', 14, 2)->nullable();
                $table->foreignId('documento_id')->nullable()->constrained('dc_documentos')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_contratos');
        Schema::dropIfExists('dc_poderes');
        Schema::dropIfExists('dc_actas');
        Schema::dropIfExists('dc_capital_variaciones');
        Schema::dropIfExists('dc_accionistas');
    }
};
