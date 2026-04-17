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
        $module = Module::where('name', 'Task')->first();
        $newFieldType = FieldType::create([
            'name' => 'select-group-team'
        ]);

        $module->fields()->where('name', 'assigned_to')->update([
            'type' => $newFieldType->id
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Task')->first();
        $module->fields()->where('name', 'assigned_to')->update([
            'type' => 35
        ]);
    }
};
