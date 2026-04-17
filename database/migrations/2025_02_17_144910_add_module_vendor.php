<?php

use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;

return new class extends Migration
{
    public function up(): void
    {
        $module = Module::create([
            'name' => 'Seller',
            'group' => 'Configuration'
        ]);

        $columnsDatatable = [
            [
                'name' => 'id',
                'label' => 'ID',
                'order' => 1,
            ],
            [
                'name' => 'type',
                'label' => 'Tipo',
                'order' => 2,
            ],
            [
                'name' => 'name',
                'label' => 'Nombre',
                'order' => 3,
            ],
            [
                'name' => 'father_last_name',
                'label' => 'Apellido Paterno',
                'order' => 4,
            ],
            [
                'name' => 'mother_last_name',
                'label' => 'Apellido Materno',
                'order' => 5,
            ],
            [
                'name' => 'address',
                'label' => 'Dirección',
                'order' => 6,
            ],
            [
                'name' => 'municipality',
                'label' => 'Municipio',
                'order' => 7,
            ],
            [
                'name' => 'state',
                'label' => 'Estado',
                'order' => 8,
            ],
            [
                'name' => 'phone',
                'label' => 'Teléfono',
                'order' => 9,
            ],
            [
                'name' => 'balance',
                'label' => 'Saldo del vendedor',
                'order' => 10,
            ],
            [
                'name' => 'rfc',
                'label' => 'RFC',
                'order' => 11,
            ],
            [
                'name' => 'status',
                'label' => 'Status',
                'order' => 12,
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
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Seller')->first();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
