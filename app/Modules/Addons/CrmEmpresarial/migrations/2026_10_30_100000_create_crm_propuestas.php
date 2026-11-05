<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Empresarial — propuestas comerciales con versiones y estados (item #562, épica #554).
 *
 * crm_propuestas = cabecera por expediente (espeja el estado de su versión vigente).
 * crm_propuesta_versiones = una fila por versión; el snapshot nunca se sobrescribe.
 * crm_propuesta_historial = bitácora append-only de transiciones.
 * Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_propuestas')) {
            Schema::create('crm_propuestas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('expediente_id')->index();
                $t->string('folio', 40)->unique();
                $t->string('estado', 30)->default('borrador')->index();
                $t->unsignedInteger('version_actual')->default(1);
                $t->unsignedBigInteger('creada_por')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('crm_propuesta_versiones')) {
            Schema::create('crm_propuesta_versiones', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('propuesta_id')->index();
                $t->unsignedInteger('version');
                $t->string('estado', 30)->default('borrador')->index();
                $t->json('snapshot');
                $t->date('vigencia_hasta')->nullable()->index();
                $t->unsignedBigInteger('aprobada_por')->nullable();
                $t->timestamp('aprobada_at')->nullable();
                $t->unsignedBigInteger('emision_id')->nullable()->index();
                $t->text('motivo')->nullable();
                $t->unsignedBigInteger('creada_por')->nullable();
                $t->timestamps();
                $t->unique(['propuesta_id', 'version']);
            });
        }

        if (! Schema::hasTable('crm_propuesta_historial')) {
            Schema::create('crm_propuesta_historial', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('propuesta_version_id')->index();
                $t->string('de', 30)->nullable();
                $t->string('a', 30);
                $t->text('motivo')->nullable();
                $t->unsignedBigInteger('por')->nullable();
                $t->timestamp('at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // Forward-only: las propuestas son evidencia comercial.
    }
};
