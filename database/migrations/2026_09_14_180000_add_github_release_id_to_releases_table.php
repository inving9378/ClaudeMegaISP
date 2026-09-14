<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990675 (F4, épica #9990668): índice único sobre `github_release_id` para
 * el candado de "una versión existe si y sólo si está publicada".
 *
 * Las columnas `github_release_id`/`published_at`/`estado_publicacion` YA las agrega la
 * migración de F3 (#9990674, `2026_09_10_180000_add_publicacion_atomica_to_releases_table`,
 * rama `circuito/item-9990674-f3-releasepublisher-emision-atomica-c`) — esa rama ya corrió
 * su migración contra esta BD compartida de dev (F3 está codeado y verificado, solo espera
 * el merge de Irving por ser nivel C). Por eso esta migración usa `hasColumn` en vez de
 * asumir que las columnas no existen: es correcta sin importar en qué orden lleguen a
 * `main` esta rama y la de F3 (si F3 aún no mergeó, las crea aquí; si ya mergeó, las
 * encuentra y solo agrega el índice).
 *
 * A propósito NO se agrega aquí el guard "impide guardar sin github_release_id" que pide el
 * item: F3 (el motor que llena la columna al emitir) y F6 (#9990677, el que marca las filas
 * históricas como historica_no_publicada) todavía no están mergeados a main — sin ellos, el
 * guard bloquearía HOY la creación de cualquier versión nueva (`ReleaseController::store`,
 * usado a diario) y `RemoteDeployCommand::runSaveRelease`, que tampoco setean estas columnas
 * mientras el flag `releases.emision_atomica` siga OFF. El propio item ya trae esta
 * advertencia en su historial ("Devolver a la cola sólo cuando F3 esté cerrado y verificado")
 * y en su `descomposicion.depende_de` (posición 6 = F3). El guard queda como sub-item de
 * seguimiento, condicionado a que F3 y F6 mergeen primero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            if (!Schema::hasColumn('releases', 'github_release_id')) {
                $table->unsignedBigInteger('github_release_id')->nullable()->after('origin');
            }
            if (!Schema::hasColumn('releases', 'estado_publicacion')) {
                $table->string('estado_publicacion', 20)->nullable()->after('github_release_id');
            }
        });

        if (!$this->uniqueIndexExists()) {
            Schema::table('releases', function (Blueprint $table) {
                $table->unique('github_release_id');
            });
        }
    }

    public function down(): void
    {
        if ($this->uniqueIndexExists()) {
            Schema::table('releases', function (Blueprint $table) {
                $table->dropUnique(['github_release_id']);
            });
        }

        // Las columnas las creó (o las creará) la migración de F3 — su propio down() las
        // borra. Este down() solo deshace lo que esta migración agregó (el índice), para no
        // pelearse con el down() de F3 sin importar el orden de merge.
    }

    private function uniqueIndexExists(): bool
    {
        $rows = DB::select("SHOW INDEX FROM releases WHERE Column_name = 'github_release_id' AND Non_unique = 0");

        return count($rows) > 0;
    }
};
