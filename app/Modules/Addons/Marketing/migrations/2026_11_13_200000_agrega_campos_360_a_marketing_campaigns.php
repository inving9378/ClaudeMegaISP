<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing IA 360 · Fase 2 (§2.3 de docs/marketing/fase-2-paquetes.md) — campos del constructor de campañas 360.
 *
 * ADITIVA: solo agrega columnas nulas, cada una con guarda `hasColumn` (correr up() dos veces no hace nada).
 * No toca filas existentes. `down()` borra solo estas columnas (son nuevas de esta fase, sin uso previo).
 */
return new class extends Migration
{
    private const TABLA = 'marketing_campaigns';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        $columnas = [
            'objetivo'               => fn (Blueprint $t) => $t->string('objetivo', 40)->nullable(),
            'publico'                => fn (Blueprint $t) => $t->json('publico')->nullable(),
            'oferta'                 => fn (Blueprint $t) => $t->json('oferta')->nullable(),
            'canales'                => fn (Blueprint $t) => $t->json('canales')->nullable(),
            'estrategia'             => fn (Blueprint $t) => $t->json('estrategia')->nullable(),
            'estrategia_estado'      => fn (Blueprint $t) => $t->string('estrategia_estado', 20)->nullable(),
            'estrategia_error'       => fn (Blueprint $t) => $t->string('estrategia_error', 500)->nullable(),
            'estrategia_generada_at' => fn (Blueprint $t) => $t->timestamp('estrategia_generada_at')->nullable(),
            'creado_por_user_id'     => fn (Blueprint $t) => $t->unsignedBigInteger('creado_por_user_id')->nullable(),
        ];

        foreach ($columnas as $nombre => $definir) {
            if (! Schema::hasColumn(self::TABLA, $nombre)) {
                Schema::table(self::TABLA, fn (Blueprint $t) => $definir($t));
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        foreach (['objetivo', 'publico', 'oferta', 'canales', 'estrategia', 'estrategia_estado',
                  'estrategia_error', 'estrategia_generada_at', 'creado_por_user_id'] as $nombre) {
            if (Schema::hasColumn(self::TABLA, $nombre)) {
                Schema::table(self::TABLA, fn (Blueprint $t) => $t->dropColumn($nombre));
            }
        }
    }
};
