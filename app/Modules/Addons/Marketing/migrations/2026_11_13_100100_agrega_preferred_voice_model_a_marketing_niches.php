<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing IA 360 · Fase 1 (hallazgo de QA) — `marketing_niches.preferred_voice_model` la usa el comparador de
 * voces (VoiceComparatorController) pero no tenía migración en el repo: existía en dev (agregada a mano) y no en
 * una base creada desde cero (`megaisp_test`), donde el comparador daba 500.
 *
 * ADITIVA y con guarda: si la columna ya existe (dev y, se espera, prod) no hace nada. Misma definición que en dev
 * (leída con SHOW COLUMNS): varchar(30) NOT NULL DEFAULT 'tts-1', después de `preferred_voice_id`.
 *
 * `down()` NO borra la columna a propósito: no hay forma de saber si la creó esta migración o ya estaba antes
 * (en dev/prod ya estaba, con datos); revertirla borraría la voz elegida por nicho.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketing_niches') || Schema::hasColumn('marketing_niches', 'preferred_voice_model')) {
            return;
        }

        Schema::table('marketing_niches', function (Blueprint $table) {
            $columna = $table->string('preferred_voice_model', 30)->default('tts-1');

            if (Schema::hasColumn('marketing_niches', 'preferred_voice_id')) {
                $columna->after('preferred_voice_id');
            }
        });
    }

    public function down(): void
    {
        // Intencionalmente vacío: ver el comentario de la clase.
    }
};
