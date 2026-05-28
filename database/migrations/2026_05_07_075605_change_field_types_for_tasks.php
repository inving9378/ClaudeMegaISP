<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Module;
use App\Models\FieldType;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        FieldType::create([
            'name' => 'select-src-component',
        ]);
        $task = Module::where('name', 'Task')->firstOrFail();
        $task_left = Module::where('name', 'TaskLeft')->firstOrFail();

        if(!$task || !$task_left) return;

        $task_fields = $task->fields()
            ->where('type', 22)
            ->get();

        $select = FieldType::where('name', 'select-src-component')->firstOrFail();
        $task_fields->each(function($field) use ($select) {
            $field->update(['type' => $select->id]);
        });

        $task_left_fields = $task_left->fields()
            ->where('type', 22)
            ->get();

        $task_left_fields->each(function($field) use ($select) {
            $field->update(['type' => $select->id]);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $task = Module::where('name', 'Task')->firstOrFail();
        $task_left = Module::where('name', 'TaskLeft')->firstOrFail();

        if(!$task || !$task_left) return;

        $select = FieldType::where('name', 'select-src-component')->firstOrFail();

        $task_fields = $task->fields()
            ->where('type',$select->id)
            ->get();

        $task_fields->each(function($field){
            $field->update(['type' => 22]);
        });

        $task_left_fields = $task_left->fields()
            ->where('type',$select->id)
            ->get();

        $task_left_fields->each(function($field){
            $field->update(['type' => 22]);
        });

        FieldType::where('name', 'select-src-component')->delete();
    }
};
