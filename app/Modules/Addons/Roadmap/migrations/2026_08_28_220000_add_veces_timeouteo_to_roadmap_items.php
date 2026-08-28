<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #194 — separar "cuántas veces se reanudó" de "cuántas veces timeouteó".
 *
 * `reanudaciones_timeout` solo se incrementa DENTRO del bloque `puedeReanudar` de
 * `ParquearTimeoutCommand` (exige avance = commits en la rama > 0). Un item que timeoutea SIN dejar
 * commits —el perfil exacto del item demasiado grande, girando en vacío— nunca pasa por ese bloque:
 * va directo a `requiere_irving` y el contador se queda en 0 para siempre.
 *
 * Eso rompe la señal empírica #1 de `JarvisService::caberEnVuelta()` (`reanudaciones_timeout >= 1`
 * = "ya timeouteó antes"): mide "reanudado", no "timeouteó". El item que gira en vacío repetidas
 * veces —justo el que `caberEnVuelta` debería aprender a descomponer— sigue evaluando "cabe" cada
 * vez, porque su reanudación nunca ocurrió y por lo tanto nunca se contó.
 *
 * `veces_timeouteo` es el contador correcto para esa señal: se incrementa en CUALQUIER timeout real
 * (avance o no), separado de `reanudaciones_timeout` que sigue midiendo solo las reanudaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'veces_timeouteo')) {
                $table->unsignedSmallInteger('veces_timeouteo')->default(0)->after('reanudaciones_timeout');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'veces_timeouteo')) {
                $table->dropColumn('veces_timeouteo');
            }
        });
    }
};
