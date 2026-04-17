<?php

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
        $module = Module::create([
            'name' => 'DocumentTypeTemplate',
            'is_main' => true,
            'description' => 'Tipo de plantillas',
            'main' => null,
        ]);

        $fields = [
            [
                'name' => 'name',
                'label' => 'Tipo de Plantilla',
                'placeholder' => 'Tipo de Plantilla',
                'type' => 1,
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'options' => null,
            ],
        ];

        $module->fields()->createMany($fields);
        $columnsDatatableByModule = [
            [
                'name' => 'name',
                'filter_name' => null,
                'label' => "Tipo de Platilla",
                'order' => 1
            ],

            [
                'name' => 'action',
                'filter_name' => null,
                'label' => "Acciones",
                'order' => 999
            ],

        ];
        $module->columnsDatatable()->createMany($columnsDatatableByModule);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'DocumentTypeTemplate')->first();
        $module->fields()->delete();
        $module->columnsDatatable()->delete();
    }
};
