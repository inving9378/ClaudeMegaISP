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
        $module = Module::where('name', 'Ticket')->first();
        $module->fields()->where('name', 'priority')->update([
            'value' => null,
            'default_value' => 3,
        ]);
        $module->fields()->where('name', 'group')->update([
            'value' => null,
            'default_value' => 'Cualquier',
        ]);
        $module->fields()->where('name', 'type')->update([
            'value' => null,
            'default_value' => 'Pregunta',
        ]);

        $module->fields()->where('name', 'colony_id')->update([
            'type' => 43
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $module = Module::where('name', 'Ticket')->first();
        $module->fields()->where('name', 'priority')->update([
            'value' => 3,
            'default_value' => null,
        ]);
        $module->fields()->where('name', 'group')->update([
            'value' => 'Cualquier',
            'default_value' => null,
        ]);
        $module->fields()->where('name', 'type')->update([
            'value' => 'Pregunta',
            'default_value' => null,
        ]);
        $module->fields()->where('name', 'colony_id')->update([
            'type' => 23
        ]);
    }
};
