<?php

use App\Http\Controllers\Utils\ComunConstantsController;
use App\Http\Repository\FieldTypeRepository;
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
            'name' => 'Plantillas',
            'is_main' => true,
            'description' => 'Plantillas de Contratos',
            'main' => null,
        ]);
        $fields = [
            [
                'name' => 'title',
                'label' => 'Título',
                'type' =>1,
                'placeholder' => 'Título',
                'position' => 1,
                'additional_field' => false,
            ],
            [
                'name' => 'description',
                'label' => 'Descripción',
                'type' => 5,
                'placeholder' => 'Descripción',
                'position' => 2,
                'additional_field' => false,
            ],
            [
                'name' => 'template',
                'label' => 'Selecciona Plantilla',
                'type' => 22,
                'placeholder' => 'Selecciona Plantilla',
                'position' => 3,
                'additional_field' => false,
                'value' => '',
                'options' => json_encode(ComunConstantsController::TEMPLATE_CONTRACTS),
            ],

        ];

        $module->fields()->createMany($fields);

        $module->packages()->attach($bootstrap_multiselect_toaster_datatables_packages);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Plantillas')->first();
        $module->fields()->delete();
        $module->delete();
    }
};
