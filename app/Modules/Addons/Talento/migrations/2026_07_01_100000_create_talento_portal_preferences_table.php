<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Portal Técnico Web (Bloque 1) — preferencias por usuario del portal.
 * Aditiva. Guarda el tema (light/dark) por usuario para que la elección
 * viaje entre dispositivos (no solo localStorage).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('talento_portal_preferences')) {
            return;
        }

        Schema::create('talento_portal_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('theme', 10)->default('light'); // 'light' | 'dark'
            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('talento_portal_preferences');
    }
};
