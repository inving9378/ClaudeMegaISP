<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE 2A.3 — QUIÉN puso el freno, y hasta cuándo.
 *
 * Hoy un bloqueo es un string dentro del título (`[BLOCKED-…]`/`[PARKED-…]`) y los 8 guards lo leen
 * con `LIKE '%[BLOCKED-%'`. Eso tiene tres problemas: no es consultable, no expira, y —el que
 * importa— NO distingue quién lo puso.
 *
 * Esa distinción es la decisión de Irving del 2026-08-18:
 *
 *   · `humano`       → FRENA. Es una decisión suya y se respeta.
 *   · `clasificador` → INFORMA. El triaje automático de riesgo (`circuito:priorizar-seguridad`,
 *                      Opus) opina, pero nunca detiene el despacho.
 *
 * Sin esta columna, "el clasificador solo aconseja" se implementaría dejando de honrar el rótulo
 * — y eso tiraría también los 32 frenos humanos, que sí se quieren conservar.
 *
 * Se REUSA `motivo_bloqueo` (ya existe) como texto del motivo. Aquí sólo se agrega el origen y la
 * caducidad.
 *
 * `bloqueo_expira_en` es el gancho de 2A.4 (`circuito:re-triage`): un bloqueo es una foto de un
 * momento, y sin fecha nadie vuelve a mirarlo. Nace NULL = "no caduca solo"; el re-triage lo puebla.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'origen_bloqueo')) {
                // Nullable a propósito: NULL = "sin bloqueo declarado". No se usa default 'humano',
                // que convertiría cualquier fila vieja en un freno duro por omisión.
                $table->enum('origen_bloqueo', ['humano', 'clasificador'])->nullable()->after('motivo_bloqueo');
                $table->index('origen_bloqueo');
            }

            if (! Schema::hasColumn('roadmap_items', 'bloqueo_expira_en')) {
                $table->timestamp('bloqueo_expira_en')->nullable()->after('origen_bloqueo');
                $table->index('bloqueo_expira_en');
            }

            if (! Schema::hasColumn('roadmap_items', 'bloqueo_renovaciones')) {
                // Cuántas veces el re-triage confirmó que el bloqueo seguía vigente. Un item renovado
                // muchas veces no está bloqueado: está mal planteado, y 2A.4 lo escala a Irving.
                $table->unsignedSmallInteger('bloqueo_renovaciones')->default(0)->after('bloqueo_expira_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            foreach (['origen_bloqueo', 'bloqueo_expira_en', 'bloqueo_renovaciones'] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
