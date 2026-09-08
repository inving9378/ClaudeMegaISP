<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #9990637 — marca de procedencia para filas reconstruidas por backfill.
 *
 * `releases` perdió los builds V1.16–V1.32 (nunca se insertaron en dev; ver
 * `releases:backfill-from-tags`). Las filas que ese comando reconstruye desde los tags de git
 * NO tienen autor/notas/commit real que inventar (decisión de Irving, item #9990637 q2): se
 * marcan con `origin='backfill_git_tags'` para distinguirlas de una release real creada por el
 * flujo normal (`origin=null`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->string('origin', 40)->nullable()->after('release_date');
        });
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
