<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #520 (decisión de Irving 2026-10-16): folio corto por factura «MN-AAAA-NNNNNN», consecutivo por
 * año, el mismo en Prefactura y Factura pagada; el número interno (invoices.number) no cambia.
 * Solo facturas NUEVAS (sin backfill). Aditiva:
 *  - invoices.folio (nullable, único) — las existentes quedan en NULL;
 *  - folio_contadores (anio, ultimo) — el consecutivo de cada año;
 *  - columna «Folio» al inicio de la tabla de facturas (orden 5; las demás empiezan en 6).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'folio')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('folio', 20)->nullable()->after('number')->unique();
            });
        }

        if (! Schema::hasTable('folio_contadores')) {
            Schema::create('folio_contadores', function (Blueprint $table) {
                $table->unsignedSmallInteger('anio')->primary();
                $table->unsignedInteger('ultimo')->default(0);
                $table->timestamps();
            });
        }

        $moduleId = DB::table('modules')->where('name', 'Invoice')->value('id');
        if ($moduleId && ! DB::table('column_datatable_modules')->where('module_id', $moduleId)->where('name', 'folio')->exists()) {
            DB::table('column_datatable_modules')->insert([
                'module_id' => $moduleId, 'name' => 'folio', 'filter_name' => null, 'label' => 'Folio',
                'active' => '1', 'class' => null, 'order' => '5', 'created_at' => now(), 'updated_at' => now(),
            ]);
            Cache::forget('datatable_columns_allInvoice');
        }
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('name', 'Invoice')->value('id');
        if ($moduleId) {
            DB::table('column_datatable_modules')->where('module_id', $moduleId)->where('name', 'folio')->delete();
            Cache::forget('datatable_columns_allInvoice');
        }
        Schema::dropIfExists('folio_contadores');
        if (Schema::hasColumn('invoices', 'folio')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropUnique(['folio']);
                $table->dropColumn('folio');
            });
        }
    }
};
