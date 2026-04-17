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
            'name' => 'ReleaseDescription',
            'group' => 'Administration'
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
        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);

        $fields = [
            [
                'name' => 'title',
                'label' => 'Titulo',
                'placeholder' => 'Ej. Optimización de carga',
                'type' => 1,
                'additional_field' => false,
                'position' => 1,
                'class_col' => 'full',
                'class_field'=> 'col-12',
                'class_label' => 'col-12'
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'placeholder' => 'Ej. Optimización de carga para mejorar el rendimiento de tu sitio web.',
                'type' => 42,
                'position' => 2,
                'additional_field' => false,
                'class_col' => 'full',
                'class_field'=> 'col-12',
                'class_label' => 'col-12'
            ],
            [
                'name' => 'release_id',
                'label' => '',
                'type' => 3,
                'position' => 3,
                'additional_field' => false,
            ],

        ];

        $module->fields()->createMany($fields);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'ReleaseDescription')->first();
        $module->fields()->delete();
        $module->packages()->detach();
        $module->delete();
    }
};
