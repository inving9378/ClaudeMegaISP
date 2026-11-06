<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #526 — mapeo ADITIVO Y REVERSIBLE del drift de `roadmap_items.modulo` (texto libre) contra
 * `module_sidebar_config` (opción B, elegida por Irving). `roadmap_items.modulo` NO se toca: la
 * Torre resuelve el alias solo al LEER/agrupar (RoadmapController::decisionesContadores/moduloUrl).
 *
 * `alias_normalizado` guarda el resultado de `normalizeModulo()` (ascii, minúsculas, solo
 * alfanumérico) del segmento base ("Roadmap / Torre de control" → "Roadmap" → "roadmap") para que
 * el lookup sea directo sin repetir la normalización en cada fila leída.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('module_aliases')) {
            Schema::create('module_aliases', function (Blueprint $table) {
                $table->id();
                $table->string('alias_normalizado', 100)->unique();
                $table->string('modulo_canonico', 150);
                $table->timestamps();
            });
        }

        // Duplicados detectados en la descripción del item #526.
        $seed = [
            ['alias' => 'Auth', 'canonico' => 'Autenticación'],
            ['alias' => 'Circuito', 'canonico' => 'Roadmap'],
        ];

        foreach ($seed as $row) {
            $normalizado = preg_replace('/[^a-z0-9]/', '', strtolower(\Illuminate\Support\Str::ascii($row['alias'])));
            DB::table('module_aliases')->updateOrInsert(
                ['alias_normalizado' => $normalizado],
                ['modulo_canonico' => $row['canonico'], 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('module_aliases');
    }
};
