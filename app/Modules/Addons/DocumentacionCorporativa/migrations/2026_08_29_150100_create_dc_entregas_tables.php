<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 5. El paquete armado que sale del sistema y
 * su acta de entrega-recepción.
 *
 * `dc_entregas` = un ZIP generado (índice + exportaciones por concepto) más su
 * acta en PDF y el hash SHA-256 del ZIP (integridad: lo que se entregó es
 * exactamente lo que se selló). `dc_entrega_items` = el detalle línea por
 * línea de qué apartado/concepto entró al paquete y con qué nivel de detalle
 * (`agregado` = solo métricas, `detallado` = filas en CSV, `integro` = CSV+PDF).
 *
 * `solicitud_id` es NULLABLE: una entrega puede armarse sin una solicitud
 * formal registrada (ej. entrega interna de rutina), aunque el flujo normal
 * del apartado XIV es solicitud → entrega.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_entregas')) {
            Schema::create('dc_entregas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->foreignId('solicitud_id')->nullable()->constrained('dc_solicitudes')->nullOnDelete();
                $table->foreignId('generado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('fecha_entrega')->nullable();
                $table->string('ruta_zip')->nullable();
                $table->char('hash_sha256', 64)->nullable()->comment('SHA-256 del ZIP ya cerrado');
                $table->string('ruta_acta_pdf')->nullable();
                $table->json('indice')->nullable()->comment('resumen apartado/concepto/nivel/estado incluido en el paquete');
                $table->enum('estado', ['generando', 'generada', 'fallida'])->default('generando');
                $table->text('error')->nullable();
                $table->unsignedInteger('descargas_zip_count')->default(0);
                $table->unsignedInteger('descargas_acta_count')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'estado']);
                $table->index('solicitud_id');
            });
        }

        if (! Schema::hasTable('dc_entrega_items')) {
            Schema::create('dc_entrega_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('entrega_id')->constrained('dc_entregas')->cascadeOnDelete();
                $table->string('apartado_clave', 4);
                $table->foreignId('concepto_id')->nullable()->constrained('dc_conceptos')->nullOnDelete();
                $table->enum('nivel_detalle', ['agregado', 'detallado', 'integro'])->default('detallado');
                $table->string('archivo_incluido')->nullable()->comment('ruta relativa dentro del ZIP');
                $table->string('estado_resuelto', 20)->nullable()->comment('snapshot de ResultadoConcepto::estado al generar');
                $table->json('metricas')->nullable();
                $table->timestamps();

                $table->index(['entrega_id', 'apartado_clave']);
                $table->index('concepto_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_entrega_items');
        Schema::dropIfExists('dc_entregas');
    }
};
