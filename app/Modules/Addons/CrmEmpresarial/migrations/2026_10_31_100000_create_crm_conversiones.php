<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM Empresarial — conversión de prospecto empresarial a cliente (item #563, épica #554).
 *
 * crm_conversiones = traza append-only prospecto → cliente (snapshot del expediente al convertir).
 * crm_expedientes.estado_comercial = ciclo comercial empresarial, independiente de
 * client_main_information.estado. Aditiva e idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_conversiones')) {
            Schema::create('crm_conversiones', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('crm_id')->nullable()->index();
                $t->unsignedBigInteger('client_id')->index();
                $t->unsignedBigInteger('expediente_id')->index();
                $t->unsignedBigInteger('propuesta_id')->nullable()->index();
                $t->json('snapshot');
                $t->unsignedBigInteger('convertido_por')->nullable();
                $t->timestamp('convertido_at')->useCurrent();
            });
        }

        if (Schema::hasTable('crm_expedientes') && ! Schema::hasColumn('crm_expedientes', 'estado_comercial')) {
            Schema::table('crm_expedientes', function (Blueprint $t) {
                $t->string('estado_comercial', 30)->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // Forward-only: la conversión es evidencia.
    }
};
