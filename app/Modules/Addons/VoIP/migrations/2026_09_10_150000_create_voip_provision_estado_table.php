<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de provisión, paso a paso (#9990718 §3).
 *
 * Para poder **reintentar desde donde se quedó** en vez de repetir media hora de
 * compilación, y para que un fallo deje rastro de qué se alcanzó a hacer.
 *
 * ─── POR QUÉ CADA PASO GUARDA LA VERSIÓN DEL PROVISIONADOR ────────────────
 *
 * Un cliente puede quedarse a medias con una versión y actualizar MegaISP antes
 * de reintentar. Sin ese dato, el estado que se encuentra es **mixto** —unos pasos
 * hechos con la lógica vieja y otros por hacer con la nueva— y reintentar encima
 * es adivinar: no se sabe si lo ya hecho sigue siendo válido para la versión que
 * va a continuar.
 *
 * Guardándolo por registro, el provisionador puede decidir con evidencia: si los
 * pasos completados son de una versión anterior a la suya, sabe que tiene que
 * revisarlos o rehacerlos, en vez de darlos por buenos porque dicen `completado`.
 *
 * Es el mismo problema que este proyecto ya tuvo tres veces en dos días —estados
 * registrados que no corresponden a la realidad—, y aquí se previene guardando
 * con qué versión se escribió cada uno.
 *
 * ADITIVA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('voip_provision_estado')) {
            return;
        }

        Schema::create('voip_provision_estado', function (Blueprint $table) {
            $table->id();

            // Identifica la corrida completa: varios pasos comparten ejecución.
            $table->uuid('ejecucion_uuid')->index();

            $table->string('paso', 40)
                ->comment('verificar|descargar|dependencias|compilar|esquema|config|credenciales|siembra|arrancar|validar');

            $table->enum('estado', ['pendiente', 'en_progreso', 'completado', 'fallido', 'omitido'])
                ->default('pendiente');

            // ── El requisito: con qué versión se hizo ESTE paso ──
            $table->string('version_provisionador', 20)
                ->comment('Versión del provisionador que ejecutó este paso. Sin esto, reintentar sobre un estado mixto es adivinar.');

            // Contexto de la corrida, para saber sobre qué se trabajaba.
            $table->string('version_asterisk', 20)->nullable();
            $table->string('esquema_revision', 40)->nullable()
                ->comment('Revisión de Alembic aplicada, cuando el paso la conoce.');

            $table->unsignedSmallInteger('intentos')->default(0);

            // Detalle estructurado. NUNCA credenciales: solo su origen.
            $table->json('detalle')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('iniciado_at')->nullable();
            $table->timestamp('terminado_at')->nullable();
            $table->timestamps();

            // Un paso una vez por ejecución: reintentar actualiza, no acumula filas.
            $table->unique(['ejecucion_uuid', 'paso']);
            $table->index(['paso', 'estado']);
            $table->index('version_provisionador');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voip_provision_estado');
    }
};
