<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item roadmap #1020 (sub-item 4/5 de #1012) — umbral de filas nuevas tolerado antes de que
 * una versión deje de considerarse "regreso limpio", por criticidad de tabla (decisión de
 * Irving, pregunta q1 del item: tablas de dinero/permisos toleran menos que un catálogo).
 * Config editable en tabla (no en config/*.php) porque Irving puede querer ajustar el número
 * sin tocar código; semilla idempotente con los 3 valores que él aprobó.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('release_reversibility_thresholds', function (Blueprint $table) {
            $table->id();
            $table->string('criticidad', 20)->unique();
            $table->unsignedInteger('umbral_filas');
            $table->timestamps();
        });

        foreach (['critica' => 50, 'media' => 500, 'baja' => 5000] as $criticidad => $umbral) {
            DB::table('release_reversibility_thresholds')->insertOrIgnore([
                'criticidad'  => $criticidad,
                'umbral_filas' => $umbral,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('release_reversibility_thresholds');
    }
};
