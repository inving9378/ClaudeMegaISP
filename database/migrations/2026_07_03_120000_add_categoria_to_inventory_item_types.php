<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clasificación de negocio Herramienta / Material para el Portal de Colaborador ("Mi material").
 *
 * herramienta = durable, se USA y se DEVUELVE (casco, guante, arnés, taladro…).
 * material    = consumible, se GASTA (cinta, grapas, fibra, cincho…).
 * null        = SIN CLASIFICAR (los tipos dudosos y los nuevos → default null; NO se adivina).
 *
 * Columna NUEVA propia del portal — NO se reusa `inventory_item_types.type` (tool/material), que
 * está mal poblado y lo consume la lógica de inventario. Editable a futuro por el admin.
 */
return new class extends Migration
{
    // OBVIOS (propuesta de Irving). Los DUDOSOS quedan null a propósito.
    private array $herramienta = [
        'UNIFORME', 'UNIFORMES', 'CASCO', 'GUANTE', 'GUANTES', 'ARNES', 'DESARMADOR',
        'MARTILLO', 'PINZA', 'TALADRO', 'CUCHILLO', 'LLAVE', 'CORTADORA', 'LAMPARA',
        'PORTA HERRAMIENTA', 'ESCALERA',
    ];

    private array $material = [
        'CINTA', 'GRAPAS', 'CINCHO', 'FIBRA', 'JUMPPER', 'NEOPRENO', 'HERRAJE', 'REMATES',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('inventory_item_types', 'categoria')) {
            Schema::table('inventory_item_types', function (Blueprint $t) {
                $t->string('categoria', 20)->nullable()->after('type'); // herramienta | material | null
            });
        }

        // Backfill idempotente por nombre (system-wide). Los dudosos NO se tocan → quedan null.
        DB::table('inventory_item_types')->whereIn('name', $this->herramienta)->update(['categoria' => 'herramienta']);
        DB::table('inventory_item_types')->whereIn('name', $this->material)->update(['categoria' => 'material']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventory_item_types', 'categoria')) {
            Schema::table('inventory_item_types', fn (Blueprint $t) => $t->dropColumn('categoria'));
        }
    }
};
