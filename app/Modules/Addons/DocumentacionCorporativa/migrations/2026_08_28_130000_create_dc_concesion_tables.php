<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 4, apartado XIII (concesiones, permisos y
 * calendario regulatorio).
 *
 * `dc_concesiones`      → un título de concesión, permiso, autorización, derecho
 *                        de vía, convenio de infraestructura o arrendamiento de
 *                        sitio. `vigencia_fin` y `responsable_user_id` son
 *                        obligatorios a nivel de esquema: este es el único
 *                        apartado donde un descuido apaga la operación, así que
 *                        no existe la fila "sin vigencia" ni la fila "sin dueño".
 * `dc_concesion_pagos`  → las obligaciones de pago periódicas de una concesión
 *                        (derechos, refrendos, contraprestaciones). `empresa_id`
 *                        se repite aquí igual que en `dc_documento_versiones`
 *                        (Fase 0): duplicar la llave de tenant en la tabla hija
 *                        evita un JOIN sólo para filtrar por empresa.
 *
 * `estado_tramite` es un ENUM de primera clase (vigente/en_renovacion/en_tramite/
 * vencido), NO texto libre: la solicitud original lo pide explícito porque
 * "trámites en proceso" y "renovaciones pendientes" ya son conceptos sembrados
 * del catálogo (Fase 0) que filtran por este campo exacto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dc_concesiones')) {
            Schema::create('dc_concesiones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->enum('tipo', [
                    'titulo_concesion', 'permiso', 'autorizacion', 'derecho_via',
                    'convenio_infraestructura', 'arrendamiento_sitio',
                ]);
                $table->string('autoridad')->nullable();
                $table->string('folio')->nullable();
                $table->text('objeto')->nullable();
                $table->date('fecha_otorgamiento')->nullable();
                // Obligatoria: sin fecha de vencimiento no hay nada que vigilar.
                $table->date('vigencia_fin');
                $table->text('obligaciones')->nullable();
                // Obligatorio: sin dueño, la alerta no le llega a nadie.
                $table->foreignId('responsable_user_id')->constrained('users')->restrictOnDelete();
                $table->enum('estado_tramite', ['vigente', 'en_renovacion', 'en_tramite', 'vencido'])
                    ->default('vigente');
                $table->foreignId('documento_id')->nullable()->constrained('dc_documentos')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'estado_tramite']);
                $table->index(['empresa_id', 'vigencia_fin']);
                $table->index('tipo');
            });
        }

        if (! Schema::hasTable('dc_concesion_pagos')) {
            Schema::create('dc_concesion_pagos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
                $table->foreignId('concesion_id')->constrained('dc_concesiones')->cascadeOnDelete();
                $table->string('concepto');
                $table->string('periodo')->nullable();
                $table->decimal('monto', 12, 2)->nullable();
                $table->date('fecha_vencimiento')->nullable();
                $table->date('fecha_pago')->nullable();
                $table->foreignId('comprobante_documento_id')->nullable()
                    ->constrained('dc_documentos')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['empresa_id', 'concesion_id']);
                $table->index('fecha_vencimiento');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_concesion_pagos');
        Schema::dropIfExists('dc_concesiones');
    }
};
