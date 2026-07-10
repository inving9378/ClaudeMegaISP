<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aislamiento por rama del Circuito (#311). Aditiva/incremental (NO migrate:fresh).
 * Enlaza item ↔ rama de git para que sea separado, revisable y reversible, y alimenta
 * el armador de versiones (#312).
 *  - branch: rama dedicada del item (circuito/item-<id>-<slug>).
 *  - merge_commit: SHA del merge --no-ff a main cuando la rama se integró.
 * Nullable → no rompe filas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'branch')) {
                $table->string('branch')->nullable()->after('target_version');
            }
            if (! Schema::hasColumn('roadmap_items', 'merge_commit')) {
                $table->string('merge_commit', 64)->nullable()->after('branch');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'merge_commit')) {
                $table->dropColumn('merge_commit');
            }
            if (Schema::hasColumn('roadmap_items', 'branch')) {
                $table->dropColumn('branch');
            }
        });
    }
};
