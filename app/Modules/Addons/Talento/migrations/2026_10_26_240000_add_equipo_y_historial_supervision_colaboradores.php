<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNI-T C4c (item #957): equipo del colaborador + historial append-only de cambios de
 * supervisor/equipo. Aditiva: nada se llena solo, las columnas nacen en NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('talento_colaboradores', 'equipo')) {
            Schema::table('talento_colaboradores', function (Blueprint $table) {
                $table->string('equipo', 100)->nullable()->after('supervisor_id');
            });
        }

        if (! Schema::hasTable('talento_colaborador_supervision_historial')) {
            Schema::create('talento_colaborador_supervision_historial', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('colaborador_id');
                $table->string('campo', 20); // supervisor_id | equipo
                $table->string('valor_anterior', 150)->nullable();
                $table->string('valor_nuevo', 150)->nullable();
                $table->unsignedBigInteger('cambiado_por')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['colaborador_id', 'id'], 'tcsh_colab_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_colaborador_supervision_historial');
        if (Schema::hasColumn('talento_colaboradores', 'equipo')) {
            Schema::table('talento_colaboradores', fn (Blueprint $t) => $t->dropColumn('equipo'));
        }
    }
};
