<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #171 (#9 Fase 2 — Chat IA ejecuta acciones) — Fase 2a, pieza de auditoría.
 *
 * Tabla de auditoría para cada tool-call que el Chat IA proponga (aunque el
 * usuario la rechace): quién, con qué prompt, qué tool, con qué argumentos y
 * qué resultó. Decisión de Irving (item #171, pregunta q4): tabla dedicada +
 * vista admin (la vista queda para la fase de ejecución real).
 *
 * Aditiva, sin consumidores todavía — nadie escribe en esta tabla hasta que
 * exista el compilador de tools + el flujo de confirmación humana (Fase 2b,
 * sesión supervisada con Irving: el catálogo concreto de tools sensibles
 * toca dinero/permisos y no se diseña sin él presente).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ia_tool_calls')) {
            return;
        }

        Schema::create('ia_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->text('prompt')->nullable();
            $table->string('tool', 150);
            $table->json('arguments')->nullable();
            $table->string('status', 20)->default('proposed');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('tool');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_tool_calls');
    }
};
