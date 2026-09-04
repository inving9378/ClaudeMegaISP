<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MR-36 (item roadmap #9990332) — DEPENDENCIAS ENTRE ITEMS COMO DATO.
 *
 * El pool no conocía el orden lógico: `detectarColisionesEnVuelo()` compara el DIFF DE ARCHIVOS de
 * las ramas en vuelo, así que dos items que dependen uno del otro pero tocan archivos distintos no
 * colisionan y se despachan a la vez. Pasó el 2026-09-04 con MR-04/MR-05/MR-06: los tres reclamados
 * en la misma pasada, y MR-05 («copia de datos legacy → mapared_*») arrancó a copiar hacia un
 * esquema que MR-04 todavía no había creado. Hubo que cortar dos vueltas a mano.
 *
 * La única protección que existía era una frase en el `prompt`, y es desigual: MR-07 la tenía, MR-05
 * y MR-06 no. Además el despachador NUNCA lee el prompt — una dependencia en prosa es una sugerencia
 * para el modelo, no una regla para el sistema.
 *
 * Aditiva y nullable: un item sin dependencias se comporta EXACTAMENTE como hoy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roadmap_items', 'depende_de')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                // JSON con los ids de los items que deben estar CERRADOS (completado + merge en
                // main) antes de que éste sea reclamable. `null` = sin dependencias.
                $table->json('depende_de')->nullable()->after('origen_item_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roadmap_items', 'depende_de')) {
            Schema::table('roadmap_items', function (Blueprint $table) {
                $table->dropColumn('depende_de');
            });
        }
    }
};
