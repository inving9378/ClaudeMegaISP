<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #9991208 — `release_descriptions.formato` (markdown | html): el render decide por columna, no
 * por heurística en cada lectura. Aditiva + backfill de una sola vez (idempotente: solo toca lo
 * que sigue en el default recién creado):
 *   - html     → filas del editor WYSIWYG (input-editor) con etiquetas de bloque reales y sin
 *                marcadores markdown.
 *   - markdown → todo lo demás: las notas del generador por IA (incluidas las 15 legacy guardadas
 *                con nl2br(e()), que `releases:migrar-descripciones-legacy` limpia) y el texto plano.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('release_descriptions', 'formato')) {
            Schema::table('release_descriptions', function (Blueprint $table) {
                $table->string('formato', 10)->default('markdown')->after('description');
            });
        }

        DB::table('release_descriptions')
            ->where('formato', 'markdown')
            ->where(function ($q) {
                $q->where('description', 'like', '%<p%')
                    ->orWhere('description', 'like', '%<ul%')
                    ->orWhere('description', 'like', '%<ol%')
                    ->orWhere('description', 'like', '%<strong%')
                    ->orWhere('description', 'like', '%<h1%')->orWhere('description', 'like', '%<h2%')->orWhere('description', 'like', '%<h3%')
                    ->orWhere('description', 'like', '%<table%')
                    ->orWhere('description', 'like', '%<div%');
            })
            ->where('description', 'not like', '%###%')
            ->where('description', 'not like', '%**%')
            ->update(['formato' => 'html']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('release_descriptions', 'formato')) {
            Schema::table('release_descriptions', fn (Blueprint $t) => $t->dropColumn('formato'));
        }
    }
};
