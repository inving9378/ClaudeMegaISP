<?php

use App\Models\CommissionRule;
use App\Models\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $module = Module::firstWhere('name', 'CommissionRule');
        $columns = [
            [
                'name' => 'name',
                'label' => 'Nombre de la Regla',
                'new_name' => 'name',
                'new_label' => 'Nombre'
            ],
            [
                'name' => 'amount',
                'label' => 'Sueldo',
                'new_name' => 'fixed_salary',
                'new_label' => 'Sueldo'
            ],
            [
                'name' => 'number_of_prospects',
                'label' => 'Número de Prospectos Requeridos',
                'new_name' => 'number_of_prospects',
                'new_label' => 'Prospectos requeridos'
            ],
            [
                'name' => 'minimum_sales',
                'label' => 'Mínimo de ventas',
                'new_name' => 'minimum_sales',
                'new_label' => 'Ventas requeridas'
            ],
            [
                'name' => 'fixed_sales_commission',
                'label' => 'Comision por Venta (Fija)',
                'new_name' => 'sales_commission_format',
                'new_label' => 'Comision por venta'
            ],
            [
                'name' => 'fixed_sales_commission_additional',
                'label' => 'Comision por Venta Adicional (Fija)',
                'new_name' => 'additional_sales_commission_format',
                'new_label' => 'Comision adicional por venta'
            ],
            [
                'name' => 'installation_cost',
                'label' => 'Costo de instalacion',
                'new_name' => 'installation_cost',
                'new_label' => 'Costo de instalación'
            ],
            [
                'name' => 'total_bonus',
                'label' => 'Bono Mensual',
                'new_name' => 'monthly_bonus',
                'new_label' => 'Bono Mensual'
            ],
        ];
        foreach ($columns as $c) {
            $module->columnsDatatable()->where('name', $c['name'])->first()
                ->update([
                    'name' => $c['new_name'],
                    'label' => $c['new_label']
                ]);
        }
        $module->columnsDatatable()->create(
            [
                'name' => 'distributors_commission',
                'label' => 'Comisión distribuidores',
                'order' => 16,
            ]
        );
        $columns = [
            [
                'name' => 'commission_percentage',
                'label' => 'Comision por Venta (Porcentaje)',
                'order' => 8,
            ],
            [
                'name' => 'commission_percentage_additional',
                'label' => 'Comision por Venta Adicional (Porcentaje)',
                'order' => 10,
            ],
            [
                'name' => 'number_sales_required',
                'label' => 'Número de ventas para bono mensual',
                'order' => 13,
            ],
        ];
        foreach ($columns as $c) {
            $module->columnsDatatable()->where('name', $c['name'])->delete();
        }

        $rules = CommissionRule::all();
        foreach ($rules as $r) {
            $selected_fields = isset($r->selected_fields) ? $r->selected_fields : [];
            $r->fixed_salary = $r->amount;
            if ($r->fixed_sales_commission > 0) {
                if (!in_array('sales_commission', $selected_fields)) {
                    $selected_fields[] = 'sales_commission';
                }
                $r->sales_commission = $r->fixed_sales_commission;
                $r->sales_commission_type = '$';
            } else if ($r->commission_percentage > 0) {
                if (!in_array('sales_commission', $selected_fields)) {
                    $selected_fields[] = 'sales_commission';
                }
                $r->sales_commission = $r->commission_percentage;
                $r->sales_commission_type = '%';
            }
            if ($r->fixed_sales_commission_additional > 0) {
                if (!in_array('additional_sales_commission', $selected_fields)) {
                    $selected_fields[] = 'additional_sales_commission';
                }
                $r->additional_sales_commission = $r->fixed_sales_commission_additional;
                $r->additional_sales_commission_type = '$';
            } else if ($r->commission_percentage_additional > 0) {
                if (!in_array('additional_sales_commission', $selected_fields)) {
                    $selected_fields[] = 'additional_sales_commission';
                }
                $r->additional_sales_commission = $r->commission_percentage_additional;
                $r->additional_sales_commission_type = '%';
            }
            if ($r->total_bonus > 0) {
                if (!in_array('monthly_bonus', $selected_fields)) {
                    $selected_fields[] = 'monthly_bonus';
                }
                $r->monthly_bonus = [
                    [
                        'bonus' => $r->total_bonus,
                        'sales' => $r->number_sales_required
                    ]
                ];
            }
            if ($r->installation_cost > 0) {
                if (!in_array('installation_cost', $selected_fields)) {
                    $selected_fields[] = 'installation_cost';
                }
            }
            if ($r->iva > 0) {
                if (!in_array('iva', $selected_fields)) {
                    $selected_fields[] = 'iva';
                }
            }
            if (isset($r->zone)) {
                if (!in_array('zone', $selected_fields)) {
                    $selected_fields[] = 'zone';
                }
            }
            if (count($selected_fields) > 0) {
                $r->selected_fields = $selected_fields;
            }
            $r->save();
        }
        // Schema::table('commissions_rules', function (Blueprint $table) {
        //     $table->dropColumn('amount');
        // });      
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::table('commissions_rules', function (Blueprint $table) {
        //     $table->decimal('amount', $precision = 8, $scale = 2)->nullable()->default(0);
        // });
        $module = Module::firstWhere('name', 'CommissionRule');
        $columns = [
            [
                'name' => 'name',
                'label' => 'Nombre de la Regla',
                'new_name' => 'name',
                'new_label' => 'Nombre'
            ],
            [
                'name' => 'amount',
                'label' => 'Sueldo',
                'new_name' => 'fixed_salary',
                'new_label' => 'Sueldo'
            ],
            [
                'name' => 'number_of_prospects',
                'label' => 'Número de Prospectos Requeridos',
                'new_name' => 'number_of_prospects',
                'new_label' => 'Prospectos requeridos'
            ],
            [
                'name' => 'minimum_sales',
                'label' => 'Mínimo de ventas',
                'new_name' => 'minimum_sales',
                'new_label' => 'Ventas requeridas'
            ],
            [
                'name' => 'fixed_sales_commission',
                'label' => 'Comision por Venta (Fija)',
                'new_name' => 'sales_commission_format',
                'new_label' => 'Comision por venta'
            ],
            [
                'name' => 'fixed_sales_commission_additional',
                'label' => 'Comision por Venta Adicional (Fija)',
                'new_name' => 'additional_sales_commission_format',
                'new_label' => 'Comision adicional por venta'
            ],
            [
                'name' => 'installation_cost',
                'label' => 'Costo de instalacion',
                'new_name' => 'installation_cost',
                'new_label' => 'Costo de instalación'
            ],
            [
                'name' => 'total_bonus',
                'label' => 'Bono Mensual',
                'new_name' => 'monthly_bonus',
                'new_label' => 'Bono Mensual'
            ],
        ];
        foreach ($columns as $c) {
            $module->columnsDatatable()->where('name', $c['new_name'])->first()
                ->update([
                    'name' => $c['name'],
                    'label' => $c['label']
                ]);
        }
        $columns = [
            [
                'name' => 'commission_percentage',
                'label' => 'Comision por Venta (Porcentaje)',
                'order' => 8,
            ],
            [
                'name' => 'commission_percentage_additional',
                'label' => 'Comision por Venta Adicional (Porcentaje)',
                'order' => 10,
            ],
            [
                'name' => 'number_sales_required',
                'label' => 'Número de ventas para bono mensual',
                'order' => 13,
            ]
        ];
        $module->columnsDatatable()->createMany($columns);
        $module->columnsDatatable()->where('name', 'distributors_commission')->delete();
    }
};
