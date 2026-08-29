<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentación Corporativa — Fase 5, apartado XIV (reserva de derechos y
 * trazabilidad): registro de QUIÉN pidió QUÉ, cuándo y con qué plazo.
 *
 * `apartados` guarda las claves (I..XIV) que el solicitante pidió ver, no el
 * detalle de conceptos — el detalle vive en `dc_entrega_items` cuando se arma
 * la entrega real. `documento_*` son metadatos de un archivo suelto (la carta
 * de solicitud escaneada, si la hay); NO se fuerza contra `dc_documentos`
 * porque esa tabla exige `concepto_id` (todo documento ahí pertenece a un
 * concepto del catálogo) y una solicitud no es un concepto — forzar uno
 * artificial sería peor que no tener el archivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dc_solicitudes')) {
            return;
        }

        Schema::create('dc_solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('dc_empresas')->cascadeOnDelete();
            $table->string('solicitante');
            // Libre a propósito: quién califica como accionista/socio/autoridad
            // es un criterio de negocio que este módulo NO decide (ver #667).
            $table->string('caracter')->nullable()->comment('accionista, socio, autoridad, etc. — texto libre');
            $table->date('fecha_recepcion');
            $table->unsignedInteger('plazo_dias')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->json('apartados')->comment('claves I..XIV solicitadas');
            $table->enum('estado', ['recibida', 'en_preparacion', 'entregada', 'rechazada'])
                ->default('recibida');
            $table->uuid('documento_uuid')->nullable()->comment('carta de solicitud escaneada, si la hay');
            $table->string('documento_nombre_original')->nullable();
            $table->string('documento_mime', 120)->nullable();
            $table->unsignedBigInteger('documento_bytes')->nullable();
            $table->foreignId('creado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'estado']);
            $table->index('fecha_limite');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dc_solicitudes');
    }
};
