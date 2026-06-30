<?php

namespace Database\Seeders;

use App\Models\ColumnDatatableModule;
use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * #175 — Siembra las filas `modules` + `column_datatable_modules` de las 3
 * datatables de Finanzas (FinanceTransaction / FinancePayment / FinanceInvoice).
 *
 * Contexto (deriva de #173): estos 3 módulos NUNCA tuvieron su fila en `modules`
 * ni sus columnas sembradas. Tras el fix de #173 ya no crashean, pero cargaban
 * SIN columnas (columns() degradaba a []). Sembrarlas las deja funcionales:
 * la datatable las pide vía requestColumnsDatatableByModule -> ModuleRepository.
 *
 * Idempotente: usa firstOrCreate por (name) en modules y por (module_id, name)
 * en column_datatable_modules. Re-correrlo NO duplica.
 *
 * REUTILIZABLE EN PROD: las filas faltan en .198 igual que en dev. Este mismo
 * seeder debe correrse en producción al desplegar:
 *     php artisan db:seed --class=Database\\Seeders\\FinanceDatatableModulesSeeder
 *
 * Los `name` coinciden con columnas reales de cada tabla (verificado): el helper
 * hace $value->$name y filters($cols, ...), así que un name inexistente saldría
 * vacío. `action` (order 999) es la columna de acciones que el helper excluye.
 */
class FinanceDatatableModulesSeeder extends Seeder
{
    public function run(): void
    {
        // [name de columna real, label en español]; el orden del arreglo define `order` (1..N)
        $modules = [
            'FinanceTransaction' => [
                ['id', 'ID'],
                ['date', 'Fecha'],
                ['description', 'Descripción'],
                ['type', 'Tipo'],
                ['debit', 'Cargo'],
                ['credit', 'Abono'],
                ['total', 'Total'],
                ['account_balance', 'Saldo'],
            ],
            'FinancePayment' => [
                ['id', 'ID'],
                ['number', 'Número'],
                ['date', 'Fecha'],
                ['payment_method_id', 'Método de pago'],
                ['amount', 'Monto'],
                ['payment_period', 'Periodo'],
                ['comment', 'Comentario'],
            ],
            'FinanceInvoice' => [
                ['id', 'ID'],
                ['number', 'Número'],
                ['client_id', 'Cliente'],
                ['document_date', 'Fecha de emisión'],
                ['total', 'Total'],
                ['estado', 'Estado'],
                ['payment_date', 'Fecha de pago'],
                ['type', 'Tipo'],
            ],
        ];

        foreach ($modules as $name => $columns) {
            $module = Module::firstOrCreate(
                ['name' => $name],
                ['group' => 'finance', 'is_main' => 1, 'main' => null, 'description' => null]
            );

            $order = 1;
            foreach ($columns as [$colName, $label]) {
                ColumnDatatableModule::firstOrCreate(
                    ['module_id' => $module->id, 'name' => $colName],
                    ['filter_name' => '', 'label' => $label, 'active' => 1, 'class' => '', 'order' => $order]
                );
                $order++;
            }

            // Columna de acciones, siempre al final (el helper la excluye de $columns)
            ColumnDatatableModule::firstOrCreate(
                ['module_id' => $module->id, 'name' => 'action'],
                ['filter_name' => '', 'label' => 'Acciones', 'active' => 1, 'class' => '', 'order' => 999]
            );

            $this->command?->info("Sembrado módulo {$name} (id {$module->id}) con " . (count($columns) + 1) . " columnas.");
        }
    }
}
