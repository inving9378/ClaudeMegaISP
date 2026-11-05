<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** #1113 — título corto de cada conversación libre con Jarvis, calculado una vez de su primera pregunta. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('jarvis_conversaciones', 'titulo')) {
            Schema::table('jarvis_conversaciones', function (Blueprint $table) {
                $table->string('titulo', 120)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('jarvis_conversaciones', 'titulo')) {
            Schema::table('jarvis_conversaciones', function (Blueprint $table) {
                $table->dropColumn('titulo');
            });
        }
    }
};
