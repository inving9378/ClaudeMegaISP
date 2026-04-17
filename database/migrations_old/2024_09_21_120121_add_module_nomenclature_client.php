<?php

use App\Models\FieldType;
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
            'name' => 'ClientNomenclature',
            'is_main' => false,
            'main' => 'App\\Models\\Nomenclature'
        ]);

        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $google = [26];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor, $google, $apechart]);


        $module->fields()->create(
            [
                'name' => 'nomenclature_id',
                'label' => 'Nomenclatura',
                'type' => 23,
                'placeholder' => 'Selecciona Nomenclatura',
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\Nomenclature',
                    'id' => 'id',
                    'text' => 'name',
                    'scope' => 'notUsed'
                ]),
            ]
        );

        $module->fields()->create([
            'name' => 'client_id',
            'label' => 'Cliente',
            'placeholder' => '',
            'type' => 3,
            'position' => 999,
            'additional_field' => false,
        ]);
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ClientNomenclature')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
