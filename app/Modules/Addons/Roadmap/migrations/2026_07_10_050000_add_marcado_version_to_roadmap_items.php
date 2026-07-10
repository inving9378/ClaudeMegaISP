<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integración robusta (#325). Aditiva. marcado_version: la rama/item quedó marcada por
 * Irving para entrar al armador de versiones (#312) → deploy a prod. Default false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (! Schema::hasColumn('roadmap_items', 'marcado_version')) {
                $table->boolean('marcado_version')->default(false)->after('merge_commit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $table) {
            if (Schema::hasColumn('roadmap_items', 'marcado_version')) {
                $table->dropColumn('marcado_version');
            }
        });
    }
};
