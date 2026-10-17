<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Item #505 — columna calculada «Documento» (Proforma / Factura s/v fiscal / Factura CFDI) en la
 * tabla de facturas (`invoices`: ficha del cliente y /finanzas/invoices), justo después de
 * «Estado». La calcula Invoice::documentoVigente(); no es columna de la BD (ver
 * Invoice::COLUMNAS_CALCULADAS). Idempotente: si ya existe, no hace nada.
 */
return new class extends Migration
{
    private const NOMBRE = 'documento';

    public function up(): void
    {
        $moduleId = DB::table('modules')->where('name', 'Invoice')->value('id');
        if (! $moduleId || DB::table('column_datatable_modules')->where('module_id', $moduleId)->where('name', self::NOMBRE)->exists()) {
            return;
        }

        $ordenEstado = (int) DB::table('column_datatable_modules')
            ->where('module_id', $moduleId)->where('name', 'status')->value('order');
        $posicion = $ordenEstado > 0 ? $ordenEstado + 1 : 998;

        // Recorre una posición las que van después de «Estado» (sin tocar «Acciones», 999).
        // `order` es varchar: se compara y suma como entero.
        DB::table('column_datatable_modules')
            ->where('module_id', $moduleId)
            ->whereRaw('CAST(`order` AS UNSIGNED) >= ? AND CAST(`order` AS UNSIGNED) < 999', [$posicion])
            ->update(['order' => DB::raw('CAST(`order` AS UNSIGNED) + 1'), 'updated_at' => now()]);

        DB::table('column_datatable_modules')->insert([
            'module_id'   => $moduleId,
            'name'        => self::NOMBRE,
            'filter_name' => null,
            'label'       => 'Documento',
            'active'      => '1',
            'class'       => null,
            'order'       => (string) $posicion,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        Cache::forget('datatable_columns_allInvoice');
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('name', 'Invoice')->value('id');
        $fila = $moduleId
            ? DB::table('column_datatable_modules')->where('module_id', $moduleId)->where('name', self::NOMBRE)->first()
            : null;
        if (! $fila) {
            return;
        }

        DB::table('column_datatable_modules')->where('id', $fila->id)->delete();
        DB::table('column_datatable_modules')
            ->where('module_id', $moduleId)
            ->whereRaw('CAST(`order` AS UNSIGNED) > ? AND CAST(`order` AS UNSIGNED) < 999', [(int) $fila->order])
            ->update(['order' => DB::raw('CAST(`order` AS UNSIGNED) - 1'), 'updated_at' => now()]);

        Cache::forget('datatable_columns_allInvoice');
    }
};
