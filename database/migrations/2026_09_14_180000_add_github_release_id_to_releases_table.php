<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990675 (F4, épica #9990668): columnas base para el candado de "una versión
 * existe si y sólo si está publicada". Migración ADITIVA — solo agrega columnas nullable
 * + índice único (MySQL permite múltiples NULL bajo un índice único, así que no rompe nada
 * con la tabla vacía de estos valores).
 *
 * `github_release_id`: id numérico de la Release en GitHub (lo llenará F3, #9990674).
 * `estado_publicacion`: publicada|fallida|historica_no_publicada (la marcará F6, #9990677,
 * para las filas ya existentes; F3 para las nuevas).
 *
 * A propósito NO se agrega aquí el guard "impide guardar sin github_release_id" que pide el
 * item: F3 (el motor que llena esa columna al emitir) y F6 (el que marca las filas históricas
 * como historica_no_publicada) todavía no están mergeados — sin ellos, el guard bloquearía
 * HOY la creación de cualquier versión nueva (`ReleaseController::store`, usado a diario) y el
 * propio `RemoteDeployCommand::runSaveRelease` del pipeline de deploy, que tampoco setean estas
 * columnas. El propio item ya trae esta advertencia en su historial ("Devolver a la cola sólo
 * cuando F3 esté cerrado y verificado") y en su `descomposicion.depende_de` (posición 6 = F3).
 * El guard queda como sub-item de seguimiento, condicionado a que F3 y F6 mergeen primero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->unsignedBigInteger('github_release_id')->nullable()->after('origin');
            $table->string('estado_publicacion', 40)->nullable()->after('github_release_id');
            $table->unique('github_release_id');
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropUnique(['github_release_id']);
            $table->dropColumn(['github_release_id', 'estado_publicacion']);
        });
    }
};
