<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990806 (sub-item de #9990792). Decision q2 de Irving (opcion recomendada):
 * conservar historico inmutable de lo que se firmo sobre la version obsoleta ANTES de reabrir
 * el acuse para la version nueva. `talento_employee_documents` y `talento_employee_document_
 * signatures` son tablas de ESTADO VIGENTE (UNIQUE por colaborador+template; el sign() de
 * TalentoEmployeeDocumentController reusa la misma fila via firstOrNew al re-firmar) -- ninguna
 * de las dos es append-only, asi que sin esta tabla la evidencia de "firmo la version N" se
 * perderia en cuanto alguien vuelva a firmar. Append-only a proposito: nunca se actualiza ni se
 * borra una fila de aqui (ver AcuseReopeningService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('talento_employee_document_reaperturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_document_id');
            $table->unsignedBigInteger('template_version_id_anterior')->nullable();
            $table->longText('rendered_html_anterior')->nullable();
            $table->string('status_anterior', 20)->nullable();
            $table->timestamp('signed_at_anterior')->nullable();
            $table->unsignedBigInteger('signed_by_anterior')->nullable();
            $table->string('signature_method_anterior')->nullable();
            $table->string('signature_path_anterior')->nullable();
            // Snapshot de talento_employee_document_signatures (multi-firma por slot) al momento
            // de reabrir: [{slot_key,label,signed_at,signed_by,signature_method,signature_path}].
            $table->json('firmas_slots_anterior')->nullable();
            $table->string('motivo', 60)->default('nueva_version_mayor');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('employee_document_id')->references('id')->on('talento_employee_documents')->onDelete('cascade');
            $table->index('employee_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_employee_document_reaperturas');
    }
};
