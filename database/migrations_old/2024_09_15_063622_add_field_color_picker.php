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
        $typeColorPickerField = [
            'name'=>'color-picker',
        ];
        $typeField = FieldType::create($typeColorPickerField);

        $module = Module::where('name','Task')->first();
        $module->fields()->create([
            'name' => 'task_color',
            'label' => 'Color de La Tarea',
            'placeholder' => 'Nombre de la Tarea',
            'type' => $typeField->id,
            'position' => 25,
            'additional_field' => false,
        ]);

        $module = Module::where('name','TaskLeft')->first();
        $module->fields()->create([
            'name' => 'task_color',
            'label' => 'Color de La Tarea',
            'placeholder' => 'Nombre de la Tarea',
            'type' => $typeField->id,
            'position' => 13,
            'additional_field' => false,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        FieldType::where('name','color-picker')->delete();
        $module = Module::where('name','Task')->first();
        $module->fields()->where('name','task_color')->first()->delete();
        $module = Module::where('name','TaskLeft')->first();
        $module->fields()->where('name','task_color')->first()->delete();
    }
};
