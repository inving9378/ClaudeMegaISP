<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logs de terceros: grupos (nombre y descripción) y los archivos de log de cada grupo
 * (nombre y ruta absoluta). Solo guarda rutas; los archivos se leen del disco al verlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('logs_terceros_grupos')) {
            Schema::create('logs_terceros_grupos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 120);
                $table->string('descripcion', 500)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('logs_terceros_archivos')) {
            Schema::create('logs_terceros_archivos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('grupo_id')->constrained('logs_terceros_grupos')->cascadeOnDelete();
                $table->string('nombre', 120);
                $table->string('ruta', 1024);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_terceros_archivos');
        Schema::dropIfExists('logs_terceros_grupos');
    }
};
