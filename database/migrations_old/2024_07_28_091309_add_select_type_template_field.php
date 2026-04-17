<?php

use App\Models\FieldType;
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
        $fieldType = FieldType::create([
            'name' => 'select-2-type-template'
        ]);

        $module = Module::where('name', 'DocumentTemplateClient')->first();
        $module->fields()->delete();
        $fields = [
            [
                'name' => 'type',
                'label' => 'Tipo de Plantilla',
                'placeholder' => 'Tipo de Plantilla',
                'type' => $fieldType->id,
                'position' => 1,
                'additional_field' => false,
                'value' => '',
                'options' => null,
            ],
            [
                'name' => 'template',
                'label' => 'Plantilla',
                'placeholder' => 'Plantilla',
                'type' => 30,
                'position' => 999,
                'additional_field' => false,
                'value' => '',
                'options' => null,
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'DocumentTemplateClient')->first();
        $module->fields()->delete();
        $fields = [
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
                    'text' => 'name',
                    'scope' => 'typeClient'
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
    }
};
