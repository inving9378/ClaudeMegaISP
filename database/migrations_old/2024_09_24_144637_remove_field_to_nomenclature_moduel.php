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

        $module = Module::where('name', 'Nomenclature')->first();
        $field = $module->fields()->where('name', 'multiple')->first();
        if ($field) {
            $field->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Nomenclature')->first();
        $fields = [
            [
                'name' => 'multiple',
                'label' => 'Agregar en Masa',
                'placeholder' => 'Separado por , Ej, D1Z1C1:1, D1Z1C1:2',
                'type' => 1,
                'position' => 5,
                'additional_field' => false,
            ]
        ];
        $module->fields()->createMany($fields);
    }
};
