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
        $module = Module::where('name', 'TaskLeft')->first();
        $module->fields()->create([
            'name' => 'estimated_time',
            'label' => 'Tiempo estimado',
            'placeholder' => 'Tiempo estimado',
            'type' => 22,
            'search' => json_encode([
                'model' => 'App\Models\FrequencyEstimatedDedicatedTime',
                'id' => 'id',
                'text' => 'value',
            ]),
            'default_value' => 3,
            'position' => 15,
            'class_label' => 'col-12',
            'class_field' => 'col-12',
        ]);
        $module->fields()->where('name', 'dedicated_time')->first()->update([
            'label' => 'Tiempo Dedicado',
            'placeholder' => 'Tiempo Dedicado',
            'type' => 22,
            'search' => json_encode([
                'model' => 'App\Models\FrequencyEstimatedDedicatedTime',
                'id' => 'id',
                'text' => 'value',
            ]),
            'default_value' => 1,
            'position' => 16,
            'class_label' => 'col-12',
            'class_field' => 'col-12',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'TaskLeft')->first();
        $module->fields()->where('name', 'estimated_time')->delete();
        $module->fields()->where('name', 'dedicated_time')->first()->update([
            'type' => 3,
            'position' => 15
        ]);
    }
};
