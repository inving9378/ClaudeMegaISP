<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * #652 pieza 2 — LAS PREGUNTAS DEL BRIEF, EN LLANO.
 *
 * ⚠️ POR QUÉ VA EN UNA COLUMNA APARTE Y NO DENTRO DE `preguntas`:
 * la IDENTIDAD de una opción ES SU TEXTO. `RoadmapItem::claveOpcion()` (línea 1070) devuelve
 * `substr(sha1(texto_normalizado), 0, 16)`, y la Torre responde mandando esa clave
 * (`TorreControl.vue:1176` → POST /circuito/elegir-opcion con `opcion: op.clave`).
 *
 * Reescribir el texto de una opción le CAMBIA la clave. Una respuesta ya guardada dejaría de
 * corresponder con ninguna opción, y peor: al re-generar el brief, lo que Irving autorizó
 * apuntaría a otra cosa. No es un riesgo de estilo, es corrupción de la decisión.
 *
 * Por eso `preguntas` es INTOCABLE y la traducción vive aquí, indexada por la clave ORIGINAL:
 *
 *   { "<pregunta_id>": { "pregunta": "…en llano…",
 *                        "opciones": { "<clave16 original>": "…en llano…" } } }
 *
 * La UI pinta el texto en llano pero sigue enviando la clave original. Lo que se autoriza no
 * cambia; sólo cambia cómo se lee.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roadmap_items', 'preguntas_natural')) {
            Schema::table('roadmap_items', function (Blueprint $t) {
                $t->json('preguntas_natural')->nullable()->after('resumen_natural_at');
            });
        }

        if (! Schema::hasColumn('roadmap_items', 'preguntas_natural_at')) {
            Schema::table('roadmap_items', function (Blueprint $t) {
                $t->timestamp('preguntas_natural_at')->nullable()->after('preguntas_natural');
            });
        }
    }

    public function down(): void
    {
        foreach (['preguntas_natural_at', 'preguntas_natural'] as $col) {
            if (Schema::hasColumn('roadmap_items', $col)) {
                Schema::table('roadmap_items', function (Blueprint $t) use ($col) {
                    $t->dropColumn($col);
                });
            }
        }
    }
};
