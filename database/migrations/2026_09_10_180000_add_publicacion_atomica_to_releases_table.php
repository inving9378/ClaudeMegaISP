<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990674 (F3 de la épica #9990668) — columnas para el ReleasePublisher
 * atómico: `github_release_id`/`published_at` son el paso 4 del acto atómico (fila en
 * releases con el resultado real de GitHub); `estado_publicacion` distingue una versión
 * publicada por el motor nuevo de una fallida con compensación aplicada. Todas nullable:
 * las filas existentes (creadas antes del motor atómico, o mientras el feature flag
 * `releases.emision_atomica` siga apagado) quedan sin tocar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            if (!Schema::hasColumn('releases', 'github_release_id')) {
                $table->unsignedBigInteger('github_release_id')->nullable()->after('reversible_motivo');
            }
            if (!Schema::hasColumn('releases', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('github_release_id');
            }
            if (!Schema::hasColumn('releases', 'estado_publicacion')) {
                $table->string('estado_publicacion', 20)->nullable()->after('published_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            foreach (['estado_publicacion', 'published_at', 'github_release_id'] as $col) {
                if (Schema::hasColumn('releases', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
