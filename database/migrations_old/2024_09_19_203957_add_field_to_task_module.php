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

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('estimated_time')->nullable()->after('duration');
            $table->string('dedicated_time')->nullable()->after('estimated_time');
        });
        $module = Module::where('name', 'Task')->first();
        if ($module) {
            $field = $module->fields()->where('name', 'task_color')->first();
            if ($field) {
                $field->delete();
            }
        }

        $module = Module::where('name', 'TaskLeft')->first();
        if ($module) {
            $field = $module->fields()->where('name', 'task_color')->first();
            if ($field) {
                $field->delete();
            }
        }

        $module = Module::where('name', 'Task')->first();
        $module->fields()->create([
            'name' => 'estimated_time',
            'label' => 'Tiempo estimado',
            'placeholder' => 'Tiempo estimado en hrs',
            'type' => 1,
            'position' => 25,
            'additional_field' => false,
            'class_col' => 'partial',
            'class_label' => "col-12",
            'class_field' => "col-12"
        ]);
        $module->fields()->create([
            'name' => 'dedicated_time',
            'label' => 'Tiempo dedicado',
            'placeholder' => 'Tiempo Dedicado',
            'type' => 1,
            'position' => 26,
            'additional_field' => false,
            'class_col' => 'partial',
            'class_label' => "col-12",
            'class_field' => "col-12"
        ]);

        $module = Module::where('name', 'TaskLeft')->first();
        $module->fields()->create([
            'name' => 'dedicated_time',
            'label' => 'Tiempo dedicado',
            'placeholder' => 'Tiempo Dedicado',
            'type' => 1,
            'position' => 13,
            'additional_field' => false,
            'class_label' => "col-12",
            'class_field' => "col-12"
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('estimated_time');
            $table->dropColumn('dedicated_time');
        });

        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'estimated_time')->delete();
        $module->fields()->where('name', 'dedicated_time')->delete();

        $module = Module::where('name', 'TaskLeft')->first();
        $module->fields()->where('name', 'estimated_time')->delete();
        $module->fields()->where('name', 'dedicated_time')->delete();
    }
};
