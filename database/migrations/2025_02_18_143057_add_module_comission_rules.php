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
            'name' => 'CommissionRule',
            'group' => 'Configuration'
        ]);

        $columnsDatatable = [
            [
                'name' => 'id',
                'label' => 'ID',
                'order' => 1,
            ],

            [
                'name' => 'name',
                'label' => 'Nombre de la Regla',
                'order' => 2,
            ],
            [
                'name' => 'zone',
                'label' => 'Zona',
                'order' => 3,
            ],
            [
                'name' => 'amount',
                'label' => 'Sueldo',
                'order' => 4,
            ],
            [
                'name' => 'number_of_prospects',
                'label' => 'Número de Prospectos Requeridos',
                'order' => 5,
            ],
            [
                'name' => 'minimum_sales',
                'label' => 'Minimo de Ventas',
                'order' => 6,
            ],
            [
                'name' => 'fixed_sales_commission',
                'label' => 'Comision por Venta (Fija)',
                'order' => 7,
            ],
            [
                'name' => 'commission_percentage',
                'label' => 'Comision por Venta (Porcentaje)',
                'order' => 8,
            ],
            [
                'name' => 'period',
                'label' => 'Período',
                'order' => 9,
            ],
            [
                'name' => 'commission_percentage_additional',
                'label' => 'Comision por Venta Adicional (Porcentaje)',
                'order' => 10,
            ],
            [
                'name' => 'fixed_sales_commission_additional',
                'label' => 'Comision por Venta Adicional (Fija)',
                'order' => 11,
            ],
            [
                'name' => 'total_bonus',
                'label' => 'Bono Mensual',
                'order' => 12,
            ],
            [
                'name' => 'number_sales_required',
                'label' => 'Número de ventas para bono mensual',
                'order' => 13,
            ],
            [
                'name' => 'installation_cost',
                'label' => 'Costo de instalacion',
                'order' => 14,
            ],
            [
                'name' => 'sellers_count',
                'label' => 'Vendedores',
                'order' => 15,
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
        $module = Module::where('name', 'CommissionRule')->first();
        $module->columnsDatatable()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
