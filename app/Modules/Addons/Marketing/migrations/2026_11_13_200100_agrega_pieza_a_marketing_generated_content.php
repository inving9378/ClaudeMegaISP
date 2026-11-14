<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing IA 360 · Fase 2 (§2.3, decisión F2-2 de Henry) — piezas de campaña 360 dentro de
 * `marketing_generated_content` (guiones, copies, anuncios, mensajes de WhatsApp y los videos producidos).
 *
 * ADITIVA: columnas nulas (salvo `regeneraciones`, default 0) con guarda `hasColumn` e índices con guarda por nombre.
 * No toca filas existentes. `down()` borra solo estos índices y columnas.
 */
return new class extends Migration
{
    private const TABLA = 'marketing_generated_content';

    private const IDX_PIEZA = 'mkt_gc_campana_pieza_idx';

    private const IDX_ORIGEN = 'mkt_gc_pieza_origen_idx';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        $columnas = [
            'pieza'           => fn (Blueprint $t) => $t->string('pieza', 30)->nullable(),
            'variante'        => fn (Blueprint $t) => $t->string('variante', 2)->nullable(),
            'contenido'       => fn (Blueprint $t) => $t->json('contenido')->nullable(),
            'pieza_origen_id' => fn (Blueprint $t) => $t->unsignedBigInteger('pieza_origen_id')->nullable(),
            'editado_at'      => fn (Blueprint $t) => $t->timestamp('editado_at')->nullable(),
            'regeneraciones'  => fn (Blueprint $t) => $t->unsignedSmallInteger('regeneraciones')->default(0),
        ];

        foreach ($columnas as $nombre => $definir) {
            if (! Schema::hasColumn(self::TABLA, $nombre)) {
                Schema::table(self::TABLA, fn (Blueprint $t) => $definir($t));
            }
        }

        if (! $this->hayIndice(self::IDX_PIEZA)) {
            Schema::table(self::TABLA, fn (Blueprint $t) => $t->index(['source_campaign_id', 'pieza'], self::IDX_PIEZA));
        }

        if (! $this->hayIndice(self::IDX_ORIGEN)) {
            Schema::table(self::TABLA, fn (Blueprint $t) => $t->index(['pieza_origen_id'], self::IDX_ORIGEN));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        foreach ([self::IDX_PIEZA, self::IDX_ORIGEN] as $indice) {
            if ($this->hayIndice($indice)) {
                Schema::table(self::TABLA, fn (Blueprint $t) => $t->dropIndex($indice));
            }
        }

        foreach (['pieza', 'variante', 'contenido', 'pieza_origen_id', 'editado_at', 'regeneraciones'] as $nombre) {
            if (Schema::hasColumn(self::TABLA, $nombre)) {
                Schema::table(self::TABLA, fn (Blueprint $t) => $t->dropColumn($nombre));
            }
        }
    }

    private function hayIndice(string $nombre): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', self::TABLA)
            ->where('index_name', $nombre)
            ->exists();
    }
};
