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
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'template_verification')->update([
            'search' => json_encode([
                'model' => 'App\Models\ListTemplateVerification',
                'id' => 'id',
                'text' => 'name',
            ]),
        ]);

        $module->fields()->create([
            'name' => 'checks',
            'label' => 'Nombre',
            'placeholder' => 'Nombre',
            'type' => 3,
            'position' => 999,
            'additional_field' => false,
        ]);


        $moduleRight = Module::where('name', 'TaskRight')->first();

        $moduleRight->fields()->create([
            'name' => 'template_verification',
            'label' => 'Nombre',
            'placeholder' => 'Nombre',
            'type' => 3,
            'position' => 999,
            'additional_field' => false,
        ]);

        $moduleRight->fields()->create([
            'name' => 'checks',
            'label' => 'Nombre',
            'placeholder' => 'Nombre',
            'type' => 3,
            'position' => 999,
            'additional_field' => false,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'template_verification')->update([
            'search' => json_encode([
                'model' => 'App\Models\TemplateVerification',
                'id' => 'id',
                'text' => 'name',
            ]),
        ]);
        $module->fields()->where('name', 'checks')->delete();

        $moduleRight = Module::where('name', 'TaskRight')->first();
        $moduleRight->fields()->where('name', 'template_verification')->delete();
        $moduleRight->fields()->where('name', 'checks')->delete();
    }
};
