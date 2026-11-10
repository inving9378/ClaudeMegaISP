<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #1225: al eliminar una orden pendiente queda por qué y quién (la fecha es `deleted_at`, que
 * `tasks` ya tenía con SoftDeletes). Aditiva y nula: las filas existentes no cambian. down() solo
 * quita estas dos columnas, que nacen aquí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tasks', 'motivo_eliminacion')) {
            Schema::table('tasks', fn (Blueprint $t) => $t->string('motivo_eliminacion', 500)->nullable());
        }
        if (! Schema::hasColumn('tasks', 'eliminada_por')) {
            Schema::table('tasks', fn (Blueprint $t) => $t->unsignedBigInteger('eliminada_por')->nullable());
        }
    }

    public function down(): void
    {
        foreach (['motivo_eliminacion', 'eliminada_por'] as $col) {
            if (Schema::hasColumn('tasks', $col)) {
                Schema::table('tasks', fn (Blueprint $t) => $t->dropColumn($col));
            }
        }
    }
};
