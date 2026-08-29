<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Item #744 (Fase 3 de #624) — mecanismo de reconstrucción de items P0 no-mergeados.
 *
 * Decisión de Irving (q3 del brief original de #624, opción 8e6b4a088d40e077): un item P0
 * perdido se reconstruye como un item NUEVO que referencia al original vía esta columna, en vez
 * de reabrir/reescribir el id original. `reabre_item_id` guarda ese id original.
 *
 * SIN FK: muchos de los ids candidatos (ver docs/roadmap-p0-inventario-crudo-item624.md) nunca
 * tuvieron fila persistida — el autoincrement se reinició tras el incidente y esos números hoy
 * pueden no existir, o existir reasignados a un item real sin relación (documentado en ese mismo
 * inventario). `reabre_item_id` es solo un entero de referencia histórica, nunca una relación
 * verificable en BD.
 *
 * ADITIVA e IDEMPOTENTE. `nullable`: todo item normal (no reconstruido) queda en null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (! Schema::hasColumn('roadmap_items', 'reabre_item_id')) {
                $t->unsignedInteger('reabre_item_id')->nullable()->after('origen_item_id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('roadmap_items', function (Blueprint $t) {
            if (Schema::hasColumn('roadmap_items', 'reabre_item_id')) {
                $t->dropColumn('reabre_item_id');
            }
        });
    }
};
