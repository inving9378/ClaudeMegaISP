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
        $module->fields()->where('name', 'estimated_time')->first()->update([
            'type' => 22,
            'search' => json_encode([
                'model' => 'App\Models\FrequencyEstimatedDedicatedTime',
                'id' => 'id',
                'text' => 'value',
            ]),
            'default_value' => 3

        ]);
        $module->fields()->where('name', 'dedicated_time')->first()->update([
            'type' => 22,
            'search' => json_encode([
                'model' => 'App\Models\FrequencyEstimatedDedicatedTime',
                'id' => 'id',
                'text' => 'value',
            ]),
            'default_value' => 1
        ]);

        $module->fields()->where('name', 'time_to_task_location')->delete();
        $module->fields()->where('name', 'time_from_task_location')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'estimated_time')->first()->update([
            'type' => 3,
            'search' => null,


        ]);
        $module->fields()->where('name', 'dedicated_time')->first()->update([
            'type' => 3,
            'search' => null,
        ]);
    }
};
