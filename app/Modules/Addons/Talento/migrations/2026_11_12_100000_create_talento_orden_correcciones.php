<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1286: el supervisor pide una corrección sobre una orden ya terminada (con motivo) y el técnico
 * sube desde la app las fotos que la muestran. La orden no cambia de estado ni de puntos: con la
 * validación separada apagada ya quedó validada al terminarse.
 *
 * Una corrección está «pedida» (espera al técnico), «enviada» (espera al supervisor) o cerrada
 * (aceptada, cancelada o reemplazada por otra). Las fotos de corrección se marcan con su id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('talento_orden_correcciones')) {
            Schema::create('talento_orden_correcciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tarea_id');
                $table->text('motivo');
                $table->unsignedBigInteger('pedida_por')->nullable();
                $table->timestamp('pedida_at')->nullable();
                $table->timestamp('enviada_at')->nullable();
                $table->unsignedBigInteger('enviada_por')->nullable();
                $table->text('nota_tecnico')->nullable();
                $table->timestamp('cerrada_at')->nullable();
                $table->unsignedBigInteger('cerrada_por')->nullable();
                $table->string('cierre', 20)->nullable();
                $table->timestamps();
                $table->index(['tarea_id', 'cerrada_at'], 'talento_orden_correcciones_tarea_cerrada_idx');
            });
        }

        Schema::table('talento_work_order_media', function (Blueprint $table) {
            if (! Schema::hasColumn('talento_work_order_media', 'correccion_id')) {
                $table->unsignedBigInteger('correccion_id')->nullable()->after('tarea_id');
                $table->index('correccion_id', 'talento_wo_media_correccion_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('talento_work_order_media', function (Blueprint $table) {
            if (Schema::hasColumn('talento_work_order_media', 'correccion_id')) {
                $table->dropIndex('talento_wo_media_correccion_idx');
                $table->dropColumn('correccion_id');
            }
        });

        Schema::dropIfExists('talento_orden_correcciones');
    }
};
