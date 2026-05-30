<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Migración delta para v1.1.0 — prueba de upgrade()
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_items', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('demo_items', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
