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
        $bootstrap_multiselect = [1, 2];
        $select2 = [4, 5];
        $chosen_select = [21, 22];
        $toaster = [3];
        $datatables_packages = [7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19];
        $ckeditor = [23];
        $apechart = [20];
        $bootstrap_multiselect_toaster_datatables_packages = Arr::collapse([$bootstrap_multiselect, $toaster, $datatables_packages, $select2, $chosen_select, $ckeditor]);


        $module = Module::create([
            'name' => 'DocumentTemplate',
            'is_main' => true,
            'description' => 'Plantillas',
            'main' => null,
        ]);
        $fields = [
            [
                'name' => 'name',
                'label' => 'Nombre de Plantilla',
                'placeholder' => 'Nombre de Plantilla',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'options' => null,
            ],

            [
                'name' => 'template',
                'label' => 'Selecciona Plantilla',
                'type' => 22,
                'placeholder' => 'Selecciona Plantilla',
                'position' => 3,
                'additional_field' => false,
                'value' => '',
                'options' => null,
                'search' => json_encode([
                    'model' => 'App\Models\DocumentTemplate',
                    'id' => 'id',
                    'text' => 'name'
                ])
            ],
            [
                'name' => 'html',
                'include' => true,
                'type' => 3, //hidden
                'position' => 999,
                'additional_field' => false
            ]
        ];

        $module->fields()->createMany($fields);

        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'DocumentTemplatephp')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
