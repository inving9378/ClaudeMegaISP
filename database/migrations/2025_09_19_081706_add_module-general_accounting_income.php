<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::create([
            'name' => 'GeneralAccountingIncome',
            'group' => 'Administration'
        ]);

        $columnsDatatable = [
            [
                'name' => 'id',
                'label' => 'ID',
                'order' => 1,
            ],
            [
                'name' => 'created_at',
                'label' => 'Fecha',
                'order' => 2,
            ],
            [
                'name' => 'reference_number',
                'label' => 'Referencia',
                'order' => 4,
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'order' => 6,
            ],
            [
                'name' => 'amount',
                'label' => 'Monto',
                'order' => 8,
            ],
            [
                'name' => 'category',
                'label' => 'Categoría',
                'order' => 10,
            ],
            [
                'name' => 'created_by',
                'label' => 'Creado por',
                'order' => 12,
            ],
            [
                'name' => 'client_id',
                'label' => 'Cliente',
                'order' => 14,
            ],
            [
                'name' => 'payment_id',
                'label' => 'Pago',
                'order' => 16,
            ],
             [
                'name' => 'transaction_id',
                'label' => 'Transacción',
                'order' => 18,
            ],
            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 999
            ],
        ];
        $module->columnsDatatable()->createMany($columnsDatatable);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $google = [26];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor, $google, $apechart]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'GeneralAccountingIncome')->first();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
