<?php

use App\Models\Release;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * Item roadmap #9991212 (pregunta sin resolver de #9991209): decisión de Irving sobre el tag
 * suelto `v0.3.0` (2026-09-11, sobre el merge de domiciliacion-suscripciones-fase1) — Opción 1
 * de la pregunta estructurada, la recomendada: "Dejarlo intacto y solo registrarlo en `releases`
 * con una marca ... para que el preflight lo ignore pero quede trazado".
 *
 * El tag NO se toca (sigue local y en origin, tal cual). El preflight/changelog YA lo ignora
 * como "versión previa" desde #9991209 — `ReleaseChangelogService::TAG_CONVENCION` filtra los
 * tags de git por nombre (`/^V\d+\.\d+-\d{2}\.\d{2}\.\d{4}$/`), así que `v0.3.0` (minúscula,
 * sin el guion de fecha) nunca entra en ese cálculo. Este `up()` solo cierra la parte de
 * trazabilidad: sin fila en `releases`, `v0.3.0` es invisible para cualquiera que audite la
 * tabla (incluido `releases:reconciliar`, que además solo cruza tags `V*` — jamás lo verá).
 *
 * `estado_publicacion='historica_fuera_de_convencion'` es un valor NUEVO, distinto de
 * `publicada`/`no_publicada` (usados por el pipeline normal) y de `historica_no_publicada`
 * (usado por #9990677 para filas SIN tag en ningún lado). Este caso es el opuesto: SÍ hay tag
 * real en local y origin, solo que nunca pasó por `github_release` (sin GitHub Release object,
 * confirmado 404 contra la API) ni sigue la convención de nombre. Ningún consumidor del repo
 * rama su comportamiento sobre el valor de `estado_publicacion` (solo se lee para mostrarlo) —
 * verificado por grep antes de escribir este valor nuevo.
 *
 * Idempotente (`firstOrCreate` por `version`) y reversible (`down()` borra solo esta fila).
 */
return new class extends Migration
{
    private const VERSION = 'v0.3.0';

    private const COMMIT_SHA = '1c55185093ec6c3eefef0261225f79d9c7bd43ee';

    private const MOTIVO = 'Tag suelto fuera de la convención `V1.x-dd.mm.yyyy` (creado 2026-09-11 '
        . 'sobre el merge de domiciliacion-suscripciones-fase1). Existe en local y en origin, pero '
        . 'nunca pasó por el paso `github_release` del pipeline (sin GitHub Release object, 404 '
        . 'confirmado). El tag se deja intacto (no se borra) por decisión de Irving (item #9991212, '
        . 'seguimiento de #9991209) — solo se registra aquí para trazabilidad. El preflight ya lo '
        . 'ignora como "versión previa" por nombre (`ReleaseChangelogService::TAG_CONVENCION`).';

    public function up(): void
    {
        if (Release::where('version', self::VERSION)->exists()) {
            return;
        }

        Release::create([
            'version'                   => self::VERSION,
            'release_date'              => '2026-09-11',
            'origin'                    => 'tag_fuera_de_convencion',
            'commit_sha'                => self::COMMIT_SHA,
            'estado_publicacion'        => 'historica_fuera_de_convencion',
            'estado_publicacion_motivo' => self::MOTIVO,
            'created_by'                => User::systemBot()?->id ?? 1,
        ]);
    }

    public function down(): void
    {
        Release::where('version', self::VERSION)
            ->where('origin', 'tag_fuera_de_convencion')
            ->delete();
    }
};
