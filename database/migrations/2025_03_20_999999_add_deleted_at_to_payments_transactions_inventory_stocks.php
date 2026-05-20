<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Forward-fix: los modelos Payment, Transaction e InventoryItemStock usan el
 * trait SoftDeletes pero ninguna migración previa añadió la columna
 * `deleted_at` a sus tablas. Eso provoca que toda data-fixup migration
 * posterior (la primera en fallar es 2025_03_21_125457_reset_distribute_
 * commission_to_sale) que toque estas tablas vía Eloquent falle con
 * "Unknown column 'payments.deleted_at' in where clause" — el global
 * scope SoftDeletingScope añade WHERE deleted_at IS NULL automáticamente.
 *
 * Esta migración se inserta cronológicamente justo antes de la primera
 * que tropieza (timestamp 2025_03_20_999999) para que el resto de la
 * cadena pueda correr limpiamente. Idempotente — usa Schema::hasColumn().
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['payments', 'transactions', 'inventory_item_stocks'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (['payments', 'transactions', 'inventory_item_stocks'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'deleted_at')) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
