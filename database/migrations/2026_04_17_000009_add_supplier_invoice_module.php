<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (Module::where('name', 'SupplierInvoice')->exists()) {
            return;
        }

        try {
            $module = Module::create([
                'name'  => 'SupplierInvoice',
                'group' => 'Inventory',
            ]);

            $module->columnsDatatable()->createMany([
                ['name' => 'id',                 'label' => 'ID',            'order' => 1],
                ['name' => 'invoice_number',     'label' => 'No. Factura',   'order' => 2],
                ['name' => 'date',               'label' => 'Fecha',         'order' => 3],
                ['name' => 'supplier_id',        'label' => 'Proveedor',     'order' => 4],
                ['name' => 'supplier_vendor_id', 'label' => 'Vendedor',      'order' => 5],
                ['name' => 'total',              'label' => 'Total',         'order' => 6],
                ['name' => 'status',             'label' => 'Estado',        'order' => 7],
                ['name' => 'action',             'label' => 'Acciones',      'order' => 999],
            ]);
        } catch (\Exception $e) {
            \Log::error('SupplierInvoice module migration: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        $module = Module::where('name', 'SupplierInvoice')->first();
        if ($module) {
            $module->columnsDatatable()->delete();
            $module->delete();
        }
    }
};
