<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE PAGOS 3 (pieza 1) — Marcador de usuario de sistema/bot.
 * Aditiva: is_system=false para todos los usuarios existentes (humanos).
 * El usuario "Asistente IA" lo pondrá en true (ver migración siguiente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('estado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_system')) {
                $table->dropColumn('is_system');
            }
        });
    }
};
