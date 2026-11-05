<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** UNI-T M2d: la evaluación semanal anota qué meta/versión usó (aditivo, nullable; solo con metas_m2 encendido). */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['meta_id' => 'unsignedBigInteger', 'meta_version' => 'unsignedInteger'] as $col => $tipo) {
            if (! Schema::hasColumn('talento_weekly_rule_evaluations', $col)) {
                Schema::table('talento_weekly_rule_evaluations', fn (Blueprint $t) => $t->{$tipo}($col)->nullable());
            }
        }
    }

    public function down(): void
    {
        foreach (['meta_id', 'meta_version'] as $col) {
            if (Schema::hasColumn('talento_weekly_rule_evaluations', $col)) {
                Schema::table('talento_weekly_rule_evaluations', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
