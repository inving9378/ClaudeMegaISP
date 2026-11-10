<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1200, Fase A2 — plantillas de orden de Talento (reemplazan a template_tasks +
 * list_template_verifications de Scheduling).
 *
 * - `fields` (json): arreglo de campos. Cada campo: key, label, type, required, when
 *   (creacion|cierre), options, unit, help, sort_order, identificador; las tablas además
 *   columns, rows_count / row_label_prefix o dynamic / max_rows. Lo valida
 *   Support\EsquemaCamposOrden.
 * - `checklist` (json): arreglo de renglones {label, cantidad?, unidad?}.
 *
 * Nunca se borran (FK restrict desde el tipo; las órdenes guardan su propia copia): se
 * desactivan con active=0.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_work_order_templates')) {
            return;
        }

        Schema::create('talento_work_order_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('type_id');
            $table->string('name', 160);
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('priority', 10)->default('Media');
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->json('fields');
            $table->json('checklist')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type_id', 'name'], 'twotpl_type_name_unique');
            $table->foreign('type_id', 'twotpl_type_fk')
                ->references('id')->on('talento_work_order_types')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_work_order_templates');
    }
};
