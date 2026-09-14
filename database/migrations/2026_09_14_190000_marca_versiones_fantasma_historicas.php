<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990677 (F6, épica #9990668) — "Reconciliar la historia: publicar retroactivo
 * o marcar histórica". Usa el reporte de F0 (`releases:reconciliar`, #9990669) para decidir,
 * versión por versión, el destino de cada fila `releases` sin publicación real.
 *
 * Columnas base (`github_release_id`/`published_at`/`estado_publicacion`): las agregan F3
 * (#9990674) y F4 (#9990675, épica #9990668) en ramas paralelas todavía sin mergear a main que
 * YA corrieron su migración contra esta BD compartida de dev. Este `up()` usa `hasColumn` (mismo
 * patrón que F4) para ser correcto sin importar en qué orden F3/F4/F6 lleguen a main.
 *
 * `estado_publicacion_motivo`: columna NUEVA, exclusiva de este item — ninguna de F3/F4 la
 * declara. El propio prompt exige que la marca `historica_no_publicada` venga "con su motivo
 * escrito"; sin una columna dedicada esa explicación no tiene dónde vivir (no se reutiliza
 * `description`: ya tiene contenido real de changelog en algunas filas, ver `summary`).
 *
 * DECISIÓN (autorizada por el propio item, "marca históricas por tu cuenta SIN preguntar"):
 * las 54 versiones de abajo son SOLO_TABLA en el cruce de F0 (fila en `releases` sin tag en
 * ningún lado: ni local, ni origin, ni GitHub Release) — no hay snapshot de código público que
 * publicar retroactivamente, así que se marcan `historica_no_publicada`. Se dividen en 2 grupos
 * por motivo real:
 *   - 47 de esquema legado `YYYY.MM.DD.N`, anteriores a que existiera el pipeline de tags de
 *     git + GitHub Releases (confirmado también por el comentario de F5 #9990676: "la tabla
 *     releases trae 47 filas legacy pre-tags que nunca tendrán tag/release").
 *   - 7 de esquema `V1.x` con intento de emisión que no completó el pipeline (la fila se creó
 *     pero nunca se generó el tag/push) — evidenciado por fechas que se repiten con OTRA versión
 *     del mismo número que sí publicó bien poco después (ej. V1.16-04.09.2026 SOLO_TABLA vs.
 *     V1.16-29.06.2026 PUBLICADA), consistente con reintentos tras los bugs de pipeline ya
 *     documentados en CLAUDE.md (tag lightweight, git_commit dependiente del idioma).
 *
 * EXCLUIDA a propósito: V1.2.1 (veredicto TAG_SIN_RELEASE, no SOLO_TABLA) — su tag SÍ existe en
 * git local y en origin (se subió a mano, ver CLAUDE.md), solo falta el objeto GitHub Release.
 * A diferencia de las 54 de abajo, aquí SÍ hay un snapshot público real — es la candidata a
 * "publicar retroactivamente" que el propio item ordena NO decidir en automático: se deja igual
 * (sin marcar) y se escala como item tipo=respuesta (canal de respuesta del propio #9990677)
 * para que Irving decida.
 *
 * Portable: usa `whereIn('version', ...)` sobre nombres de versión exactos — en un entorno donde
 * no existan estas filas (prod, otro dev) el UPDATE no afecta nada.
 */
return new class extends Migration
{
    private const LEGACY = [
        '2025.10.20.1', '2025.10.20.2', '2025.10.21.1', '2025.10.23.1', '2025.10.24.1',
        '2025.10.24.2', '2025.10.25.1', '2025.11.01.1', '2025.11.02.1', '2025.11.08.1',
        '2025.11.14.1', '2025.11.21.1', '2025.11.22.1', '2025.11.26.1', '2025.11.27.1',
        '2025.11.29.1', '2025.12.04.1', '2025.12.05.1', '2025.12.13.1', '2026.01.01.1',
        '2026.01.08.1', '2026.01.09.1', '2026.01.14.1', '2026.01.24.1', '2026.01.25.1',
        '2026.01.28.1', '2026.02.14.1', '2026.02.22.1', '2026.03.03.1', '2026.03.04.1',
        '2026.03.05.1', '2026.03.10.1', '2026.03.18.1', '2026.03.24.1', '2026.03.25.1',
        '2026.04.13.1', '2026.04.28.1', '2026.05.02.1', '2026.05.16.1', '2026.05.16.2',
        '2026.05.20.1', '2026.06.09.17', '2026.06.09.18', '2026.06.09.19', '2026.06.09.21',
        '2026.06.09.25', '2026.06.13.1',
    ];

    private const INTENTO_FALLIDO = [
        'V1.1', 'V1.7-26.06.2026', 'V1.8-26.06.2026', 'V1.16-04.09.2026',
        'V1.17-07.09.2026', 'V1.18-08.09.2026', 'V1.19-08.09.2026',
    ];

    private const MOTIVO_LEGACY = 'Esquema de versión legado (fecha numérica AAAA.MM.DD.N), '
        . 'previo a la existencia del pipeline de tags de git + GitHub Releases. Nunca tuvo tag '
        . 'ni push a origin — no hay snapshot de código público que publicar retroactivamente.';

    private const MOTIVO_INTENTO_FALLIDO = 'Intento de emisión de versión que no completó el '
        . 'pipeline: la fila de `releases` se creó pero nunca se generó el tag de git ni se subió '
        . 'a origin (veredicto SOLO_TABLA en `releases:reconciliar`). Sin tag no hay snapshot de '
        . 'código que publicar retroactivamente.';

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
                $table->string('estado_publicacion', 40)->nullable()->after('published_at');
            }
            if (!Schema::hasColumn('releases', 'estado_publicacion_motivo')) {
                $table->text('estado_publicacion_motivo')->nullable()->after('estado_publicacion');
            }
        });

        // F3 (#9990674) ya corrió su propia migración contra esta BD compartida de dev con
        // `varchar(20)` — insuficiente para el valor `historica_no_publicada` (23 caracteres)
        // que el propio prompt de este item exige escribir. Se ensancha aquí (requiere
        // doctrine/dbal, ya presente en composer.json) en vez de acortar el valor; siempre
        // corre, sea que la columna se acabe de crear arriba (ya en 40, no-op) o ya existiera
        // en 20 (la ensancha). No se revierte en down(): ensanchar es no-destructivo, y los
        // valores cortos de F3/F4 ('publicada'/'fallida') caben igual de bien en 40.
        Schema::table('releases', function (Blueprint $table) {
            $table->string('estado_publicacion', 40)->nullable()->change();
        });

        DB::table('releases')
            ->whereIn('version', self::LEGACY)
            ->whereNull('estado_publicacion')
            ->update([
                'estado_publicacion' => 'historica_no_publicada',
                'estado_publicacion_motivo' => self::MOTIVO_LEGACY,
                'updated_at' => now(),
            ]);

        DB::table('releases')
            ->whereIn('version', self::INTENTO_FALLIDO)
            ->whereNull('estado_publicacion')
            ->update([
                'estado_publicacion' => 'historica_no_publicada',
                'estado_publicacion_motivo' => self::MOTIVO_INTENTO_FALLIDO,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Reversible por diseño (el propio item: "las marcas son datos, se revierten con un
        // update"): solo limpia la marca que puso este `up()`, en las mismas 54 filas.
        DB::table('releases')
            ->whereIn('version', array_merge(self::LEGACY, self::INTENTO_FALLIDO))
            ->update([
                'estado_publicacion' => null,
                'estado_publicacion_motivo' => null,
            ]);

        // Las columnas base (github_release_id/published_at/estado_publicacion) las comparten F3
        // (#9990674) y F4 (#9990675) — este down() no las borra, no es su dueño exclusivo.
        if (Schema::hasColumn('releases', 'estado_publicacion_motivo')) {
            Schema::table('releases', function (Blueprint $table) {
                $table->dropColumn('estado_publicacion_motivo');
            });
        }
    }
};
