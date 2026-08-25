<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #209 — las dos columnas de ETA de Thomas que el código escribía sin que existieran.
 *
 * `ThomasService::estimarMinutos()` sella `eta_minutos`/`eta_asignada_at` con `forceFill()->save()`,
 * y `RoadmapController::store()` las lee al responder. Ninguna de las dos existía en la tabla: el
 * `create()` del item pasaba, y el `save()` posterior reventaba con SQLSTATE 42S22. Resultado: el
 * botón «Agregar item» de la Torre devolvía error **después** de haber creado el item, así que cada
 * reintento dejaba un duplicado aprobado y despachable (#204–#207, los cuatro del 2026-08-25 16:06).
 *
 * ⚠️ Esta migración se APLICÓ EN DEV SIN ARCHIVO (fantasma): las columnas ya existen en la base de
 * dev pero no había nada en git, así que el arreglo no viajaba ni a prod ni a los worktrees —donde
 * corre el código commiteado— y el botón habría seguido roto ahí. El archivo se escribe con el
 * MISMO nombre que ya está sellado en `migrations` para que dev no la re-corra y el resto sí.
 * Es exactamente el mecanismo del item #534 (migraciones fantasma).
 *
 * ADITIVA e IDEMPOTENTE: `hasColumn` en las dos direcciones. No toca datos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (! Schema::hasColumn('roadmap_items', 'eta_minutos')) {
                // smallint unsigned: el tope de esfuerzo de Thomas es 240 min (`circuito.thomas
                // .esfuerzo.tope_minutos`); 65 535 sobra y ocupa la mitad que un int.
                $t->unsignedSmallInteger('eta_minutos')->nullable()->after('eta_metodo');
            }
            if (! Schema::hasColumn('roadmap_items', 'eta_asignada_at')) {
                $t->timestamp('eta_asignada_at')->nullable()->after('eta_minutos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            foreach (['eta_asignada_at', 'eta_minutos'] as $col) {
                if (Schema::hasColumn('roadmap_items', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
