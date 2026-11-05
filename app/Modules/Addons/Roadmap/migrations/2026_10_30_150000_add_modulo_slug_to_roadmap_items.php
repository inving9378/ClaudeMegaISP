<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #1050 (paso A de #1030) — módulo del registro al que pertenece cada item.
 *
 * `modulo` sigue siendo la etiqueta libre de siempre: el despachador y el auditor la usan tal cual
 * (tope por módulo, acoplamiento), así que no se toca. `modulo_slug` la traduce al slug de
 * `module_registry` (o a un área transversal `area-*`) con el mapa de `config/roadmap_modulos.php`.
 * NULL = etiqueta sin alias todavía. La llena `roadmap:mapear-modulos` y, para items nuevos, el
 * hook de guardado de RoadmapItem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'modulo_slug')) {
                $table->string('modulo_slug', 80)->nullable()->after('modulo')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'modulo_slug')) {
                $table->dropIndex(['modulo_slug']);
                $table->dropColumn('modulo_slug');
            }
        });
    }
};
